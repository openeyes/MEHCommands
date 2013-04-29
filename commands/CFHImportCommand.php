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

class CFHImportCommand extends CConsoleCommand {
	public function run($args) {
		$c = curl_init();
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($c,CURLOPT_URL,"http://nww.connectingforhealth.nhs.uk/ods/downloads/zfiles/etrust.zip");
		$tmpfile = tempnam("/tmp","TEMP").'.zip';
		file_put_contents($tmpfile,curl_exec($c));
		chdir("/tmp");
		$esc = escapeshellarg($tmpfile);
		`unzip -o $esc 1>/dev/null 2>/dev/null`;
		@unlink($tmpfile);

		$fp = fopen('etrust.csv','r');

		while ($data = fgetcsv($fp)) {
			$this->process($this->sanitise($data));
		}

		fclose($fp);
		@unlink('etrust.csv');

		echo "\n";
	}

	public function sanitise($data) {
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

	public function process($data) {
		if (strlen($data[0]) == 3) {
			$this->processInstitution($data);
		} else if (strlen($data[0]) == 5) {
			$this->processSite($data);
		} else {
			throw new Exception("Invalid format:\n".print_r($data,true));
		}
	}

	public function processInstitution($data) {
		if (!$institution = Institution::model()->with(array('contact'=>array('with'=>'address')))->find('lower(name)=?',array(strtolower($data[1])))) {
			$contact = new Contact;
			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}

			$institution = new Institution;
			$institution->contact_id = $contact->id;
			$institution->name = $data[1];
			$institution->code = $data[0];
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

			echo ".";
		}
	}

	public function findSite($institution_id, $data) {
		if ($site = Site::model()->find('institution_id=? and code=?',array($institution_id,substr($data[0],3,2)))) {
			return $site;
		}

		$sites = array();

		foreach (Site::model()->with(array('contact'=>array('with'=>'address')))->findAll('institution_id=? and lower(name)=? and postcode=?',array($institution_id,$data[1],$data[9])) as $i => $site) {
			$sites[] = $site;
		}

		if (count($sites) >1) {
			echo "Multiple sites found:\n";
			echo "Institution: $institution_id\n";
			print_r($data);
			exit;
		} else if (count($sites) == 1) {
			return $sites[0];
		}

		return false;
	}

	public function processSite($data) {
		$institution_code = substr($data[0],0,3);

		if (!$institution = Institution::model()->find('code=?',array($institution_code))) {
			echo "Institution not found: $institution_code\n";
			exit;
		}

		if (!$site = $this->findSite($institution->id,$data)) {
			$contact = new Contact;
			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}

			$site = new Site;
			$site->code = substr($data[0],3,2);
			$site->institution_id = $institution->id;
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

			echo ".";
		}
	}
}
