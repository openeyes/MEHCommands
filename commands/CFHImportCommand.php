<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

class CFHImportCommand extends CConsoleCommand
{
	public $source;
	public $lookup = array();
	public $consultants = array();
	public $sites = array();

	public function run($args)
	{
		if (!$this->source = ImportSource::model()->find('name=?',array('Connecting for Health'))) {
			throw new Exception("Source not found: Connecting for Health");
		}

		echo "Updating sites/institutions ";

		$this->refreshSites();

		echo "Updating consultants ";

		$this->refreshConsultants();
	}

	public function getCFH($name)
	{
		$c = curl_init();
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($c,CURLOPT_URL,"http://nww.connectingforhealth.nhs.uk/ods/downloads/zfiles/$name.zip");
		$tmpfile = tempnam("/tmp","TEMP");
		@unlink($tmpfile);
		$tmpfile .= '.zip';
		file_put_contents($tmpfile,curl_exec($c));
		chdir("/tmp");
		$esc = escapeshellarg($tmpfile);
		`unzip -o $esc 1>/dev/null 2>/dev/null`;
		@unlink($tmpfile);
		$data = array();
		foreach (explode(chr(10),trim(file_get_contents($name.'.csv'))) as $item) {
			if ($name == 'etrust') {
				$data[] = $this->sanitiseEtrust(str_getcsv($item));
			} else {
				$data[] = str_getcsv($item);
			}
		}
		@unlink($name.'.csv');

		return $data;
	}

	public function refreshSites()
	{
		foreach ($this->getCFH('etrust') as $i => $site) {
			$this->processSiteOrInstitution($site);
			$this->lookup[] = $site[0];

			if ($i %100 == 0) {
				echo ".";
			}
		}

		echo "\n";
	}

	public function refreshConsultants()
	{
		// this serves as a lookup table which massively speeds up the import as we can skip already imported records without having to query the database
		foreach (Yii::app()->db->createCommand()
			->select("p.remote_id, s.remote_id as site_code, i.remote_id as institution_code")
			->from("person p")
			->join("contact c","p.contact_id = c.id")
			->leftJoin("contact_location cl","cl.contact_id = c.id")
			->leftJoin("site s","cl.site_id = s.id")
			->leftJoin("institution i","cl.institution_id = i.id")
			->where("p.source_id = ?",array($this->source->id))
			->queryAll() as $row) {

			$code = $row['site_code'] ? $row['site_code'] : $row['institution_code'];

			$this->consultants[$code][] = $row['remote_id'];
		}

		foreach (Yii::app()->db->createCommand()->select("*")->from("institution")->queryAll() as $row) {
			$this->sites[$row['remote_id']] = $row;
		}

		foreach (Yii::app()->db->createCommand()->select("*")->from("site")->queryAll() as $row) {
			$this->sites[$row['remote_id']] = $row;
		}

		foreach ($this->getCFH('econcur') as $i => $consultant) {
			$this->processConsultant($consultant);

			if ($i %100 == 0) {
				echo ".";
			}
		}

		echo "\n";
	}

	public function sanitiseEtrust($data)
	{
		$data[1] = ucwords(strtolower($data[1]));
		$data[1] = preg_replace('/ nhs/i',' NHS',$data[1]);
		$data[1] = preg_replace('/ and/i',' and',$data[1]);
		$data[1] = preg_replace('/ of/i',' of',$data[1]);

		for ($i=4;$i<=8;$i++) {
			$data[$i] = ucwords(strtolower(trim($data[$i])));
			$data[$i] = preg_replace('/ nhs /i',' NHS ',$data[$i]);
			$data[$i] = preg_replace('/ of /i',' of ',$data[$i]);
			$data[$i] = preg_replace('/ and /i',' and ',$data[$i]);
		}

		return $data;
	}

	public function processSiteOrInstitution($data)
	{
		if (strlen($data[0]) == 3) {
			$this->processInstitution($data);
		} elseif (strlen($data[0]) == 5) {
			$this->processSite($data);
		} else {
			throw new Exception("Invalid format:\n".print_r($data,true));
		}
	}

	public function processInstitution($data)
	{
		if (!$institution = Institution::model()->with(array('contact'=>array('with'=>'address')))->find('source_id=? and remote_id=?',array($this->source->id,$data[0]))) {
			$contact = new Contact;
			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}

			$institution = new Institution;
			$institution->contact_id = $contact->id;
			$institution->source_id = $this->source->id;
			$institution->remote_id = $data[0];
			$institution->name = $data[1];
			if (!$institution->save()) {
				throw new Exception("Unable to save institution: ".print_r($institution->getErrors(),true));
			}
		} else {
			$contact = $institution->contact;
		}

		if (!$address = $contact->address) {
			$address = new Address;
			$address->country_id = 1;
			$address->parent_class = 'Contact';
			$address->parent_id = $contact->id;
		}

		if ($data[6]) {
			$data[5] .= ', '.$data[6];
		}

		if ($address->address1 != $data[4] ||
			$address->address2 != $data[5] ||
			$address->city != $data[7] ||
			$address->county != $data[8] ||
			$address->postcode != $data[9]) {

			$address->address1 = $data[4];
			$address->address2 = $data[5];
			$address->city = $data[7];
			$address->county = $data[8];
			$address->postcode = $data[9];

			if (!$address->save()) {
				throw new Exception("Unable to save address: ".print_r($address->getErrors(),true));
			}

			echo "+";
		}

		$this->lookup[$data[0]] = $institution->id;
	}

	public function processSite($data)
	{
		$institution_code = substr($data[0],0,3);

		if (!$institution = Institution::model()->find('source_id=? and remote_id=?',array($this->source->id,$institution_code))) {
			echo "Institution not found: $institution_code\n";
			exit;
		}

		if ($institution_code == 'RP6') return;

		if (!$site = Site::model()->with(array('contact'=>array('with'=>'address')))->find('institution_id=? and source_id=? and remote_id=?',array($institution->id,$this->source->id,$data[0]))) {
			$contact = new Contact;
			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}

			$site = new Site;
			$site->institution_id = $institution->id;
			$site->source_id = $this->source->id;
			$site->remote_id = $data[0];
			$site->name = $data[1];
			$site->contact_id = $contact->id;

			if (!$site->save()) {
				throw new Exception("Unable to save site: ".print_r($site->getErrors(),true));
			}
		}

		if (!$address = $site->contact->address) {
			$address = new Address;
			$address->country_id = 1;
			$address->parent_class = 'Contact';
			$address->parent_id = $site->contact_id;
		}

		if ($data[6]) {
			$data[5] .= ', '.$data[6];
		}

		if ($address->address1 != $data[4] ||
			$address->address2 != $data[5] ||
			$address->city != $data[7] ||
			$address->county != $data[8] ||
			$address->postcode != $data[9]) {

			$address->address1 = $data[4];
			$address->address2 = $data[5];
			$address->city = $data[7];
			$address->county = $data[8];
			$address->postcode = $data[9];

			if (!$address->save()) {
				throw new Exception("Unable to save address: ".print_r($address->getErrors(),true));
			}

			echo "+";
		}

		$this->lookup[$data[0]] = $site->id;
	}

	public function processConsultant($data)
	{
		if (isset($this->consultants[$data[7]]) && in_array($data[0],$this->consultants[$data[7]])) {
			return;
		}

		if (!$specialty = Specialty::model()->find('code=?',array($data[5]))) {
			throw new Exception("Unknown specialty function code: {$data[5]}");
		}

		if (!$person = Person::model()->with('contact')->find('source_id=? and remote_id=?',array($this->source->id,$data[0]))) {
			$contact = new Contact;

			if ($specialty->default_is_surgeon) {
				$contact->title = $data[4] == 'M' ? 'Mr' : 'Miss';
			} else {
				$contact->title = 'Dr';
			}

			$contact->first_name = $data[3];
			$contact->last_name = $data[2];
			if ($specialty->default_title) {
				$contact->contact_label_id = $this->getContactLabel($specialty->default_title)->id;
			}

			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}

			$person = new Person;
			$person->source_id = $this->source->id;
			$person->remote_id = $data[0];
			$person->contact_id = $contact->id;

			if (!$person->save()) {
				throw new Exception("Unable to save person: ".print_r($person->getErrors(),true));
			}

			$person->setMetadata('gmc_number',$data[0]);
			$person->setMetadata('practitioner_code',$data[1]);
			$person->setMetadata('gender',$data[4]);
		}

		$contact = $person->contact;

		if (strlen($data[7]) == 3) {
			if (isset($this->sites[$data[7]])) {
				$institution = $this->sites[$data[7]];

				if (!$cl = ContactLocation::model()->find('contact_id=? and institution_id=?',array($contact->id,$institution['id']))) {
					$cl = new ContactLocation;
					$cl->contact_id = $contact->id;
					$cl->institution_id = $institution['id'];

					if (!$cl->save()) {
						throw new Exception("Unable to save contact location: ".print_r($cl->getErrors(),true));
					}
				}
			} else {
				echo "Unknown institution: {$data[7]}\n";
			}
		} else {
			if (isset($this->sites[$data[7]])) {
				$site = $this->sites[$data[7]];

				if (!$cl = ContactLocation::model()->find('contact_id=? and site_id=?',array($contact->id,$site['id']))) {
					$cl = new ContactLocation;
					$cl->contact_id = $contact->id;
					$cl->site_id = $site['id'];

					if (!$cl->save()) {
						throw new Exception("Unable to save contact location: ".print_r($cl->getErrors(),true));
					}
				}
			} else {
				echo "Unknown site: {$data[7]}\n";
			}
		}

		echo "+";
	}

	public function getContactLabel($name)
	{
		if ($cl = ContactLabel::model()->find('name=?',array($name))) {
			return $cl;
		}
		$cl = new ContactLabel;
		$cl->name = $name;
		if (!$cl->save()) {
			throw new Exception("Unable to save contact label: ".print_r($cl->getErrors(),true));
		}

		return $cl;
	}
}
