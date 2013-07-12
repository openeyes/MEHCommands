<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

/**
 * Takes live data, and uses it to produce random sample data primarily for training purposes.
 * Currently it just randomises patient names, details and addresses.
 * The intention is too keep the user accounts, firms, and site info
 *
 * @todo Strip out events?
 * @todo Randomise other contacts and addresses
 * @todo Clean audit logs, user sessions etc.
 * @author jamie
 *
 */
class AnonymiseCommand extends CConsoleCommand
{
	public function run($args)
	{
		echo "This command is unfinished and will destroy your swiss cheese plant, are you really sure you want to run it? Type 'hell yeah' to continue: ";
		$handle = fopen ("php://stdin","r");
		$line = fgets($handle);
		if (trim($line) != 'hell yeah') {
			echo "Probably for the best!\n";
			exit;
		}

		//$this->anonymisePatients();
		Yii::app()->db->createCommand()->truncateTable('audit');
		$this->clearElements();
		foreach (Event::model()->findAll() as $event) {
			$event->delete();
		}
		foreach (Episode::model()->findAll() as $episode) {
			$episode->delete();
		}
	}

	protected function clearElements()
	{
		$element_types = ElementType::model()->findAll();
		foreach ($element_types as $element_type) {
			$model_name = $element_type->class_name;
			echo "Clearing $model_name\n";
			$elements = $model_name::model()->findAll();
			foreach ($elements as $element) {
				$element->delete();
			}
		}
	}

	protected function anonymisePatients()
	{
		echo "Collecting data...";
		$first_names = Yii::app()->db->createCommand()
		->selectDistinct('title, first_name')
		->from('contact')
		->where('LENGTH(first_name) > 2')
		->queryAll();
		echo ".";
		$last_names = Yii::app()->db->createCommand()
		->selectDistinct('last_name')
		->from('contact')
		->queryColumn();
		echo ".";
		$qualifications = Yii::app()->db->createCommand()
		->selectDistinct('qualifications')
		->from('contact')
		->queryColumn();
		echo ".";
		$phone_numbers = array();
		for ($i = 1; $i <= 500; $i++) {
			$phone_numbers[] = '0'.substr(number_format(time() * rand(),0,'',''),0,4)
			. ' ' . substr(number_format(time() * rand(),0,'',''),0,6);
		}
		echo ".";
		$road_addresses = Yii::app()->db->createCommand()
		->selectDistinct('address1')
		->from('address')
		->where('address1 REGEXP \'[0-9]\'')
		->queryColumn();
		foreach ($road_addresses as $index => $road_address) {
			$road_addresses[$index] = preg_replace('/\d+/', rand(1,300), $road_address);
		}
		echo ".";
		$location_addresses = Yii::app()->db->createCommand()
		->selectDistinct("city, county, SUBSTRING_INDEX(postcode, ' ', 1) as postcode_prefix")
		->from('address')
		->where('country_id = 1')
		->limit(10)
		->queryAll();
		$email_domains = array(
				'gmail.com',
				'outlook.com',
				'yahoo.com',
		);
		echo "done.\n";

		// Patient contact records
		echo "Anonymising contact records...\n";
		$contact_ids = Yii::app()->db->createCommand()
		->select('id')
		->from('contact')
		->where("parent_class = 'Patient'")
		->queryColumn();
		echo "processing ".count($contact_ids)." contact records\n";
		foreach ($contact_ids as $contact_id) {
			$first_name = $first_names[array_rand($first_names)];
			$title = trim($first_name['title']);
			$first_name = trim($first_name['first_name']);
			$last_name = trim($last_names[array_rand($last_names)]);
			$phone_number = $phone_numbers[array_rand($phone_numbers)];
			Yii::app()->db->createCommand()
			->update('contact', array(
			'first_name' => $first_name,
			'last_name' => $last_name,
			'title' => $title,
			'primary_phone' => $phone_number,
			'nick_name' => null,
			'qualifications' => null,
			), 'id = :id', array(':id' => $contact_id));
			echo '.';
		}
		echo "done\n";

		// Patient address records
		echo "Anonymising patient address records...\n";
		$address_ids = Yii::app()->db->createCommand()
		->select('id')
		->from('address')
		->where("parent_class = 'Patient'")
		->queryColumn();
		echo "processing ".count($address_ids)." address records\n";
		foreach ($address_ids as $address_id) {
			$address1 = trim($road_addresses[array_rand($road_addresses)]);
			$location_address = $location_addresses[array_rand($location_addresses)];
			$city = trim($location_address['city']);
			$postcode = trim($location_address['postcode_prefix']) . ' '
					. rand(1,9)
					. strtoupper($this->randomString(2,true));
			$county = trim($location_address['county']);
			$email = strtolower($this->randomString(8)).'@'.trim($email_domains[array_rand($email_domains)]);
			Yii::app()->db->createCommand()
			->update('address', array(
			'address1' => $address1,
			'address2' => '',
			'city' => $city,
			'postcode' => $postcode,
			'county' => $county,
			'country_id' => 1,
			'email' => $email
			), 'id = :id', array(':id' => $address_id));
			echo '.';
		}
		echo "done\n";

		// Patient records
		echo "Anonymising patient records...";
		$patient_ids = Yii::app()->db->createCommand()
		->select('id')
		->from('patient')
		->queryColumn();
		echo "processing ".count($patient_ids)." patient records\n";
		foreach ($patient_ids as $patient_id) {
			$hos_num = substr(number_format(time() * rand(),0,'',''),0,7);
			$nhs_num = substr(number_format(time() * rand(),0,'',''),0,10);

			// DOB
			$latest_dob = Yii::app()->db->createCommand()
			->select('datetime')
			->from('event')
			->join('episode', 'episode.id = event.episode_id')
			->where('episode.patient_id = :patient_id')
			->order('event.datetime ASC')
			->queryScalar(array(':patient_id' => $patient_id));
			$dob_to = ($latest_dob) ? strtotime($latest_dob) : strtotime('2010-01-01');
			$dob_from = strtotime('1913-01-01');
			$dob = date('Y-m-d', rand($dob_from, $dob_to));

			// DOD (1%)
			if (rand(1,100) == 1) {
				$earliest_dod = Yii::app()->db->createCommand()
				->select('datetime')
				->from('event')
				->join('episode', 'episode.id = event.episode_id')
				->where('episode.patient_id = :patient_id')
				->order('event.datetime DESC')
				->queryScalar(array(':patient_id' => $patient_id));
				$dod_from = ($earliest_dod) ? strtotime($earliest_dod) : strtotime($dob);
				if ($dod_from < strtotime('1980-01-01')) {
					$dod_from = strtotime('1980-01-01');
				}
				$dod_to = time();
				$dod = date('Y-m-d', rand($dod_from, $dod_to));
			} else {
				$dod = null;
			}

			// Gender
			$title = Yii::app()->db->createCommand()
			->select('title')
			->from('contact')
			->where("parent_class = 'Patient' AND parent_id = :patient_id")
			->queryScalar(array(':patient_id' => $patient_id));
			if (in_array(strtolower($title), array('ms', 'mrs', 'miss'))) {
				$gender = 'F';
			} else if (in_array(strtolower($title), array('mr'))) {
				$gender = 'M';
			} else {
				$gender = (rand(1,3) == 1) ? 'F' : 'M';
			}

			$practice_id = null;
			$gp_id = null;
			$ethnic_group_id = null;

			Yii::app()->db->createCommand()
			->update('patient', array(
			'pas_key' => $hos_num,
			'dob' => $dob,
			'hos_num' => $hos_num,
			'nhs_num' => $nhs_num,
			'ethnic_group_id' => $ethnic_group_id,
			'date_of_death' => $dod,
			'practice_id' => $practice_id,
			'gp_id' => $gp_id,
			'gender' => $gender,
			), 'id = :id', array(':id' => $patient_id));
			echo '.';
		}
		echo "done\n";
	}

	protected function randomString($length = 10, $no_numbers = false)
	{
		$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		if (!$no_numbers) {
			$characters .= '0123456789';
		}
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, strlen($characters) - 1)];
		}
		return $randomString;
	}

}
