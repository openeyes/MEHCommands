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

class DrFosterImportCommand extends CConsoleCommand {
	public $source;
	public $curl;
	public $html;

	public function run($args) {
		if (!$this->source = ImportSource::model()->find('name=?',array('Dr Foster Health'))) {
			throw new Exception("Source not found: Dr Foster Health");
		}

		$this->curl = new Curl;

		$html = $this->curl->get('http://www.drfosterhealth.co.uk/hospital-guide/full-hospital-list/');

		if (preg_match_all('/<a href="(private\-hospitals\-[a-z]\.aspx)">/',$html,$m)) {
			foreach ($m[1] as $uri) {
				$this->process_uri($uri);
			}
		}

		echo "\n";
	}

	public function process_uri($uri) {
		$html = $this->curl->get('http://www.drfosterhealth.co.uk/hospital-guide/full-hospital-list/'.$uri);

		if (preg_match_all('/<a href="(\/hospital\-guide\/hospital\/private\/.*?\.aspx)">/',$html,$m)) {
			foreach ($m[1] as $i => $uri) {
				$this->process_hospital($uri);
			}
		}
	}

	public function process_hospital($uri) {
		preg_match('/-([0-9]+)\.aspx$/',$uri,$m);

		$remote_id = $m[1];

		$html = $this->curl->get('http://www.drfosterhealth.co.uk'.$uri);

		if (!preg_match('/<span class="fn org">(.*?)<\/span>,[\s\t\r\n]+<address class="adr">[\s\t\r\n]+<span class="street-address">(.*?)<\/span>,[\s\t\r\n]+<span class="region">(.*?)<\/span>,[\s\t\r\n]+<span class="country-name">(.*?)<\/span>,[\s\t\r\n]+<span class="postal-code">(.*?)<\/span>.*?<span class="value">(.*?)<\/span>/s',$html,$m)) {
			echo "Regex failed at $uri\n";
			return;
		}

		$name = str_replace('&#39;',"'",ucwords($m[1]));
		if (preg_match('/,/',$m[2])) {
			$ex = explode(',',$m[2]);
			$address1 = trim($ex[0]);
			$address2 = trim($ex[1]);
		} else {
			$address1 = $m[2];
		}
		$town = $m[3];
		$country = $m[4];
		$postcode = $m[5];
		$telephone = $m[6];

		if (!$institution = Institution::model()->with(array('contact'=>array('with'=>'address')))->find('source_id=? and remote_id=?',array($this->source->id,$remote_id))) {
			$contact = new Contact;
			$contact->primary_phone = $telephone;
			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}

			$institution = new Institution;
			$institution->source_id = $this->source->id;
			$institution->remote_id = $remote_id;
			$institution->name = $name;
			$institution->contact_id = $contact->id;

			if (!$institution->save()) {
				throw new Exception("Unable to save institution: ".print_r($institution->getErrors(),true));
			}

			echo "+";
		}

		$contact = $institution->contact;
		if ($contact->primary_phone != $telephone) {
			$contact->primary_phone = $telephone;
			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}
		}

		if (!$address = $institution->contact->address) {
			$address = new Address;
			$address->parent_class = 'Contact';
			$address->parent_id = $institution->contact_id;
		}

		if ($address->address1 != $address1 ||
			$address->address2 != @$address2 ||
			$address->city != $town ||
			$address->postcode != $postcode) {

			$address->address1 = $address1;
			$address->address2 = @$address2;
			$address->city = $town;
			$address->postcode = $postcode;
			$address->country_id = 1;

			if ($address->id) echo "u";

			if (!$address->save()) {
				throw new Exception("Unable to save address: ".print_r($address,true));
			}
		}
	}
}
