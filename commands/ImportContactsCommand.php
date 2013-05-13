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

require_once 'Zend/Loader.php';

class ImportContactsCommand extends CConsoleCommand {
	public function run($args) {
		Zend_Loader::loadClass('Zend_Gdata');
		Zend_Loader::loadClass('Zend_Gdata_AuthSub');
		Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
		Zend_Loader::loadClass('Zend_Gdata_Spreadsheets');

		$service = Zend_Gdata_Spreadsheets::AUTH_SERVICE_NAME;
		$client = Zend_Gdata_ClientLogin::getHttpClient(Yii::app()->params['gdata_username'], Yii::app()->params['gdata_password'], $service);

		$ss = new Zend_Gdata_Spreadsheets($client);

		$feed = $ss->getSpreadsheetFeed();

		$data = array();

		foreach ($feed->entries as $entry) {
			if ($entry->title == 'New contacts') {
				$spreadsheetKey = basename($entry->id);
				$query = new Zend_Gdata_Spreadsheets_DocumentQuery();
				$query->setSpreadsheetKey($spreadsheetKey);
				$feed = $ss->getWorksheetFeed($query);
				$worksheetKey = basename($feed->entries[0]->id);

				$query = new Zend_Gdata_Spreadsheets_CellQuery();
				$query->setSpreadsheetKey($spreadsheetKey);
				$query->setWorksheetId($worksheetKey);
				$cellFeed = $ss->getCellFeed($query);

				foreach ($cellFeed as $cellEntry) {
					$data[$cellEntry->cell->getRow()][$cellEntry->cell->getColumn()] = $cellEntry->cell->getText();
				}
			}
		}

		$mode = false;

		foreach ($data as $row) {
			if ($row[1] == 'Ophthalmic consultants') {
				$mode = "ophthalmic";
			} else if ($row[1] == 'Non-ophthalmic consultants') {
				$mode = "non-ophthalmic";
			} else {
				if ($row[1] == 'Prefix') {
					$fields = $row;
				} else {
					if ($mode) {
						foreach ($fields as $i => $field) {
							if (isset($row[$i])) {
								$row[$field] = preg_replace('/^[,]+/','',preg_replace('/[,]+$/','',trim($row[$i])));
								unset($row[$i]);
							}
						}

						echo "{$row['Prefix']} {$row['Firstname']} {$row['Lastname']}: ";

						$site = false;

						if (@$row['Site ID']) {
							if (!$site = Site::model()->findByPk($row['Site ID'])) {
								echo "Unable to find site id '{$row['Site ID']}'\n";
								continue;
							}
						} else {
							if (!$institution = Institution::model()->findByPk(@$row['Institution ID'])) {
								echo "Unable to find institution id '{$row['Institution ID']}'\n";
								continue;
							}
						}

						$type = ($mode == 'ophthalmic') ? 'consultant' : 'specialist';
						$related_table = $site ? 'site' : 'institution';
						$related_id = $site ? $site->id : $institution->id;

						if (Yii::app()->db->createCommand()
							->select("contact.id")
							->from("contact")
							->join($type,"contact.parent_id = $type.id and contact.parent_class = '".ucfirst($type)."'")
							->join("{$related_table}_{$type}_assignment","{$related_table}_{$type}_assignment.{$type}_id = $type.id and {$related_table}_{$type}_assignment.{$related_table}_id = $related_id")
							->where("contact.title = :title and contact.first_name = :first_name and contact.last_name = :last_name",array(
								':title' => $row['Prefix'],
								':first_name' => $row['Firstname'],
								':last_name' => $row['Lastname'],
							))->queryRow()) {
							echo "Already exists\n";
						} else {
							if ($type == 'consultant') {
								$consultant = new Consultant;
								if ($row['Prefix'] == 'Mr') {
									$consultant->gender = 'M';
								} else if (in_array($row['Prefix'],array('Mrs','Miss','Ms'))) {
									$consultant->gender = 'F';
								}
								if (!$consultant->save(false)) {
									echo "Unable to create consultant: ".print_r($consultant->getErrors(),true)."\n";
									continue;
								}
							} else {
								if (!$specialist_type = SpecialistType::model()->find('name=?',array($row['Description']))) {
									$specialist_type = new SpecialistType;
									$specialist_type->name = $row['Description'];
									$specialist_type->save();
								}

								$consultant = new Specialist;
								$consultant->specialist_type_id = $specialist_type->id;
								if (!$consultant->save(false)) {
									echo "Unable to create specialist: ".print_r($consultant->getErrors(),true)."\n";
									continue;
								}
							}

							$contact = new Contact;
							$contact->title = $row['Prefix'];
							$contact->first_name = $row['Firstname'];
							$contact->last_name = $row['Lastname'];
							$contact->parent_class = ucfirst($type);
							$contact->parent_id = $consultant->id;
							if (!$contact->save()) {
								echo "Unable to save contact: ".print_r($contact->getErrors(),true)."\n";
								continue;
							}

							$model = ucfirst($related_table).ucfirst($type)."Assignment";
							$sca = new $model;
							$typeid = $type.'_id';
							$sca->$typeid = $consultant->id;
							$relatedtableid = $related_table.'_id';
							$sca->$relatedtableid = $related_id;
							if (!$sca->save()) {
								echo "Unable to save $model: ".print_r($sca->getErrors(),true)."\n";
								continue;
							}

							echo "CREATED\n";
						}
					}
				}
			}
		}
	}
}
