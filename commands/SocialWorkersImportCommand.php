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

class SocialWorkersImportCommand extends CConsoleCommand
{
	public $source;
	public $sourceName = 'Social worker directory';
	public $lookup = array();
	public $consultants = array();
	public $sites = array();
	public $curl;
	public $label_socialworker;

	public function run($args)
	{
		if (!$this->source = ImportSource::model()->find('name=?',array($this->sourceName))) {
			throw new Exception("Source not found: $this->sourceName");
		}

		$this->curl = new Curl;

		$this->label_socialworker = $this->getLabel('Social worker');

		echo "Importing social workers: ";

		$this->refreshSocialWorkers();

		echo "\n";
	}

	public function getLabel($name)
	{
		if (!$cl = ContactLabel::model()->find('name=?',array($name))) {
			$cl = new ContactLabel;
			$cl->name = $name;
			if (!$cl->save()) {
				throw new Exception("Unable to save contact label: ".print_r($cl->getErrors(),true));
			}
		}

		return $cl;
	}

	public function refreshSocialWorkers()
	{
		$html = $this->curl->post('http://www.socialworkdirectory.co.uk/',array(
			'geoengne' => 1,
			'geoengnw' => 1,
			'geoengyh' => 1,
			'geoengem' => 1,
			'geoengwm' => 1,
			'geoenge' => 1,
			'geoenglon' => 1,
			'geoengse' => 1,
			'geoengsw' => 1,
			'geoscotstr' => 1,
			'geoscotdum' => 1,
			'geoscotbord' => 1,
			'geoscotloth' => 1,
			'geoscotcent' => 1,
			'geoscotfife' => 1,
			'geoscottays' => 1,
			'geoscotgram' => 1,
			'geoscothigh' => 1,
			'geoscotwest' => 1,
			'geoscotshet' => 1,
			'geoscotork' => 1,
			'geowalesnorth' => 1,
			'geowaleseast' => 1,
			'geowalessouth' => 1,
			'geowaleswest' => 1,
			'geowalesmid' => 1,
			'geoniantrim' => 1,
			'geoniarmagh' => 1,
			'geonidown' => 1,
			'geoniferma' => 1,
			'geonilond' => 1,
			'geonityrone' => 1,
			'adoption' => 1,
			'adult' => 1,
			'asylum' => 1,
			'bereave' => 1,
			'brain' => 1,
			'camhs' => 1,
			'care' => 1,
			'childpro' => 1,
			'childfam' => 1,
			'crimjust' => 1,
			'dementia' => 1,
			'directpay' => 1,
			'dislearn' => 1,
			'disphys' => 1,
			'earyr' => 1,
			'ethics' => 1,
			'hiv' => 1,
			'hurights' => 1,
			'learndev' => 1,
			'lookchild' => 1,
			'mentalcap' => 1,
			'mentalheal' => 1,
			'olderpeople' => 1,
			'palcare' => 1,
			'povjust' => 1,
			'renal' => 1,
			'sensory' => 1,
			'advocacy' => 1,
			'compliance' => 1,
			'courtassess' => 1,
			'directservice' => 1,
			'directfamily' => 1,
			'expertwit' => 1,
			'famassess' => 1,
			'pracassess' => 1,
			'theraputic' => 1,
			'postplacement' => 1,
			'practiceteach' => 1,
			'traintut' => 1,
			'comensoc' => 1,
			'comenpar' => 1,
			'supervision' => 1,
			'manconsul' => 1,
			'interim' => 1,
			'research' => 1,
			'strat' => 1,
			'complaints' => 1,
			'placements' => 1,
			'interagency' => 1,
			'quality' => 1,
			'revieweval' => 1,
			'inspection' => 1,
			'bynamefirst' => '',
			'bynamesurname' => '',
			'numcheck' => 10,
			'check' => 10,
			'indesearch' => 'Search',
		));

		preg_match_all('/<div class="result">(.*?)<\/div>/s',$html,$m);

		foreach ($m[1] as $blob) {
			$name = $this->regex('/<h2>(.*?)<\/h2>/',$blob);
			$address = $this->regex('/<p class="address"><strong>Address: <\/strong>(.*?)<\/p>/',$blob);
			$tel = $this->regex('/<p class="tel"><strong>Telephone: <\/strong>([0-9\s]+)<\/p>/',$blob);
			$mob = $this->regex('/<p class="mob"><strong>Mobile: <\/strong>([0-9\s]+)<\/p>/',$blob);
			$email = $this->regex('/<p class="email"><strong>Email: <\/strong><a href="(.*?)">/',$blob);
			$website = $this->regex('/<p class="website"><strong>Website: <\/strong><a href="(.*?)">/',$blob);
			$qualifications = $this->regex('/<p class="quali"><strong>Qualifications: <\/strong>(.*?)<\/p>/',$blob);
			$workwith = $this->regex('/<p class="workwith"><strong>I work with: <\/strong>(.*?)<\/p>/',$blob);
			$geo = $this->regex('/<p class="geo"><strong>Working locations: <\/strong>(.*?)<\/p>/',$blob);
			$expert = $this->regex('/<p class="expert"><strong>Expertise: <\/strong>(.*?)<\/p>/',$blob);
			$client = $this->regex('/<p class="client"><strong>Client group: <\/strong>(.*?)<\/p>/',$blob);
			$spskills = $this->regex('/<p class="spskills"><strong>Extra skills: <\/strong>(.*?)<\/p>/',$blob);
			$desc = $this->regex('/<p class="desc"><strong>Description: <\/strong>(.*?)<\/p>/s',$blob);

			if (!$address) {
				echo "x";
			} else {
				$remote_id = sha1($name.$address);

				$names = explode(' ',$name);
				$first_name = array_shift($names);
				$last_name = implode(' ',$names);

				$address = explode(', ',$address);

				$address1 = $address2 = $town = $county = $postcode = '';

				$address1 = array_shift($address);
				$postcode = array_pop($address);

				if (count($address) >= 3) {
					$address2 = array_shift($address);
					$town = array_shift($address);
					$county = array_shift($address);
				} elseif (count($address >= 2)) {
					$town = array_shift($address);
					$county = array_shift($address);
				} else {
					$town = array_shift($address);
				}

				if (!$person = Person::model()->with(array('contact'=>array('with'=>'address')))->find('source_id=? and remote_id=?',array($this->source->id,$remote_id))) {
					$contact = new Contact;
					$contact->first_name = $first_name;
					$contact->last_name = $last_name;
					$contact->primary_phone = $mob ? $mob : $tel;
					$contact->qualifications = $qualifications;
					$contact->contact_label_id = $this->label_socialworker->id;

					if (!$contact->save()) {
						throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
					}

					$person = new Person;
					$person->contact_id = $contact->id;
					$person->source_id = $this->source->id;
					$person->remote_id = $remote_id;

					if (!$person->save()) {
						throw new Exception("Unable to save person: ".print_r($person->getErrors(),true));
					}

					echo ".";
				}

				if (!$address = $person->contact->address) {
					$address = new Address;
					$address->parent_class = 'Contact';
					$address->parent_id = $person->contact_id;
					$address->country_id = 1;
				}

				if ($address->address1 != $address1 ||
					$address->address2 != $address2 ||
					$address->city != $town ||
					$address->county != $county ||
					$address->postcode != $postcode ||
					$address->email != $email) {

					$address->address1 = $address1;
					$address->address2 = $address2;
					$address->city = $town;
					$address->county = $county;
					$address->postcode = $postcode;
					$address->email = $email;

					if ($address->id) {
						echo "u";
					}

					if (!$address->save()) {
						throw new Exception("Unable to save address: ".print_r($address->getErrors(),true));
					}
				}
			}
		}
	}

	public function regex($regex,$data)
	{
		if (preg_match($regex,$data,$m)) {
			return trim($m[1]);
		}
		return false;
	}
}
