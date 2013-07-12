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

class ImportConsultantsCommand extends CConsoleCommand
{
	public function getName()
	{
		return '';
	}

	public function getHelp()
	{
		return "";
	}

	public function run($args)
	{
		if (!file_exists("/tmp/Econcur.csv")) {
			die("File not found: /tmp/Econcur.csv\n");
		}

		if (!file_exists("/tmp/specialists.csv")) {
			die("File not found: /tmp/specialists.csv\n");
		}

		$specialists = array();
		$fp = fopen("/tmp/specialists.csv","r");

		while ($data = fgetcsv($fp)) {
			$specialists[$data[1]] = array(
				'name' => $data[3],
				'surgeon' => $data[4],
			);
		}

		fclose($fp);

		Yii::app()->db->createCommand("delete from contact where parent_class = 'Specialist'")->query();
		Yii::app()->db->createCommand("delete from site_specialist_assignment")->query();
		Yii::app()->db->createCommand("delete from specialist")->query();
		Yii::app()->db->createCommand("delete from specialist_type")->query();

		$fp = fopen("/tmp/Econcur.csv","r");

		$people = array();

		$missing_institutions = array();

		while ($data = fgetcsv($fp)) {
			// Ignore consultants not in the same specialty category as Bill (opthalmologists)
			if ($data[5] == 130) {
				if ($data[7] != 'RP6') {
					if (!$consultant = Consultant::model()->find('practitioner_code=?',array($data[1]))) {
						$consultant = new Consultant;
						$consultant->gmc_number = $data[0];
						$consultant->practitioner_code = $data[1];
						$consultant->gender = $data[4];
						$consultant->save();
					}

					if (!$contact = $consultant->contact) {
						$contact = new Contact;
						$contact->parent_class = 'Consultant';
						$contact->parent_id = $consultant->id;
					}

					$contact->first_name = $data[3];
					$contact->last_name = $data[2];
					$contact->save();

					if (!$institution = Institution::model()->find('code=?',array($data[7]))) {
						if (!in_array($data[7],$missing_institutions)) {
							$missing_institutions[] = $data[7];
						}
						continue;
					}

					foreach (Site::model()->findAll('institution_id=?',array($institution->id)) as $site) {
						if (!SiteConsultantAssignment::model()->find('site_id=? and consultant_id=?',array($site->id,$consultant->id))) {
							$ica = new SiteConsultantAssignment;
							$ica->site_id = $site->id;
							$ica->consultant_id = $consultant->id;
							$ica->save();
						}
					}

					echo "Added consultant: $contact->first_name $contact->last_name\n";
				}
			} else {
				// not ophthalmology
				if ($data[7] != 'RP6') {

					$specialty_func_code = $data[5];

					if (!$specialist_type = SpecialistType::model()->find('name=?',array($specialists[$specialty_func_code]['name']))) {
						$specialist_type = new SpecialistType;
						$specialist_type->name = $specialists[$specialty_func_code]['name'];
						$specialist_type->save();
					}

					if (!$specialist = Specialist::model()->find('practitioner_code=?',array($data[1]))) {
						$specialist = new Specialist;
						$specialist->gmc_number = $data[0];
						$specialist->practitioner_code = $data[1];
						$specialist->gender = $data[4];
						$specialist->specialist_type_id = $specialist_type->id;
						$specialist->surgeon = $specialists[$specialty_func_code]['surgeon'];
						$specialist->save();
					}

					if (!$contact = $specialist->contact) {
						$contact = new Contact;
						$contact->parent_class = 'Specialist';
						$contact->parent_id = $specialist->id;
					}

					if ($specialist->surgeon) {
						$contact->title = ($specialist->gender == 'M' ? 'Mr' : 'Miss');
					} else {
						$contact->title = 'Dr';
					}

					$contact->first_name = $data[3];
					$contact->last_name = $data[2];
					$contact->save();

					if (!$institution = Institution::model()->find('code=?',array($data[7]))) {
						echo "Missing institution: {$data[7]}\n";

						if (!in_array($data[7],$missing_institutions)) {
							$missing_institutions[] = $data[7];
						}
						continue;
					}

					foreach (Site::model()->findAll('institution_id=?',array($institution->id)) as $site) {
						if (!SiteSpecialistAssignment::model()->find('site_id=? and specialist_id=?',array($site->id,$specialist->id))) {
							$ica = new SiteSpecialistAssignment;
							$ica->site_id = $site->id;
							$ica->specialist_id = $specialist->id;
							$ica->save();
						}
					}

					echo "Added specialist: $contact->first_name $contact->last_name\n";
				}
			}
		}

		echo "Missing institutions: ".implode(", ",$missing_institutions)."\n\n";
	}
}
?>
