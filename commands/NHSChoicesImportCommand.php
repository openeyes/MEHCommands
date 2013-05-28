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

class NHSChoicesImportCommand extends CConsoleCommand {
	public $source;
	public $lookup = array();
	public $consultants = array();
	public $sites = array();
	public $curl;
	public $label_optometrist;

	function run($args) {
		if (!$this->source = ImportSource::model()->find('name=?',array('NHS Choices'))) {
			throw new Exception("Source not found: NHS Choices");
		}

		$this->curl = new Curl;

		$this->label_optometrist = $this->getLabel('Optometrist');

		echo "Importing optometrists: ";

		$this->refreshOptometrists();
	}

	function getLabel($name) {
		if (!$cl = ContactLabel::model()->find('name=?',array($name))) {
			$cl = new ContactLabel;
			$cl->name = $name;
			if (!$cl->save()) {
				throw new Exception("Unable to save contact label: ".print_r($cl->getErrors(),true));
			}
		}

		return $cl;
	}

	function refreshOptometrists($page=52) {
		$html = $this->curl->get('http://www.nhs.uk/Search/Pages/Results.aspx?q=optometrist&collection=all_results&page='.$page);

		preg_match_all('/<h2><a href="(http:\/\/www\.nhs\.uk\/[a-z]+\/[a-z]+\/[a-z]+\/([a-z]+\/)?([a-z]+\/)?defaultview\.aspx\?id=[0-9]+).*?'.'>(.*?)<\/a>/',$html,$m);

		foreach ($m[1] as $i => $url) {
			$name = trim($m[4][$i]);

			if (preg_match('/optometrist/i',$name)) {
				$name = preg_replace('/^.*? - /','',$name);
				$name = preg_replace('/ <.*$/','',$name);
				$name = preg_replace('/ \(.*$/','',$name);

				$this->processOptometrist($url,$name);
			}
		}

		if (preg_match('/">Next<\/a>/',$html)) {
			$this->refreshOptometrists($page+1);
		}

		if ($page == 1) {
			echo "\n";
		}
	}

	function processOptometrist($url,$name) {
		preg_match('/([0-9]+)$/',$url,$m);
		$remote_id = $m[1];

		$ex = explode(' ',$name);

		if (count($ex) == 1) {
			$first_name = '';
			$last_name = $name;
		} else {
			$first_name = array_shift($ex);
			$last_name = implode(' ',$ex);
		}

		$html = $this->curl->get($url);

		if (preg_match('/<p>Telephone: ([0-9\s]+)</',$html,$m)) {
			$telephone = $m[1];
		} else {
			$telephone = '';
		}

		preg_match('/>Address: (.*?)</',$html,$m);
		$address = explode(', ',$m[1]);

		$postcode = array_pop($address);
		$address1 = array_shift($address);

		if (count($address) >= 3) {
			$address2 = array_shift($address);
			$town = array_shift($address);
			$county = array_shift($address);
		} else if (count($address) == 2) {
			$address2 = array_shift($address);
			$town = array_shift($address);
		} else {
			$town = array_shift($address);
		}

		if (!$person = Person::model()->with(array('contact'=>array('with'=>'address')))->find('source_id=? and remote_id=?',array($this->source->id,$remote_id))) {
			$contact = new Contact;
			$contact->first_name = $first_name;
			$contact->last_name = $last_name;
			$contact->primary_phone = $telephone;
			$contact->contact_label_id = $this->label_optometrist->id;

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

			echo "+";
		}

		if (!$address = $person->contact->address) {
			$address = new Address;
			$address->parent_class = 'Contact';
			$address->parent_id = $person->contact_id;
		}

		if ($address->address1 != @$address1 ||
			$address->address2 != @$address2 ||
			$address->city != @$town ||
			$address->county != @$county ||
			$address->postcode != @$postcode) {

			$address->address1 = @$address1;
			$address->address2 = @$address2;
			$address->city = @$town;
			$address->county = @$county;
			$address->postcode = @$postcode;
			$address->country_id = 1;

			if ($address->id) echo "u";

			if (!$address->save()) {
				throw new Exception("Unable to save address: ".print_r($address->getErrors(),true));
			}
		}
	}
}
