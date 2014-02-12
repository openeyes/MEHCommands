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

require_once 'Zend/Loader.php';

class CommissioningBodyServicesCommand extends CConsoleCommand {
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
			if ($entry->title == 'Commissioning Body Services') {
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

		array_shift($data);

		$commissioning_bodies = array();
		foreach (CommissioningBody::model()->findAll() as $commissioning_body) {
			$commissioning_bodies[$commissioning_body->code] = $commissioning_body->id;
		}

		if (!$service_type = CommissioningBodyServiceType::model()->find('shortname=?',array('DRSS'))) {
			$service_type = new CommissioningBodyServiceType;
			$service_type->name = 'Diabetic Retinopathy Screening Service';
			$service_type->shortname = 'DRSS';

			if (!$service_type->save()) {
				throw new Exception("Unable to save service type: ".print_r($service_type->getErrors(),true));
			}
		}

		foreach ($data as $i => $row) {
			$criteria = new CDbCriteria;
			if (isset($row[1])) {
				$criteria->addCondition('code=:code');
				$criteria->params[':code'] = $row[1];
			} else {
				$criteria->addCondition('name=:name');
				$criteria->params[':name'] = $row[2];
			}
			$criteria->addCondition('commissioning_body_service_type_id=:cbst');
			$criteria->params[':cbst'] = $service_type->id;
			if (!$cbs = CommissioningBodyService::model()->find($criteria)) {
				$cbs = new CommissioningBodyService;
				$cbs->code = @$row[1];
				$cbs->name = $row[2];
				$cbs->commissioning_body_service_type_id = $service_type->id;
			}

			if (!$contact = $cbs->contact) {
				$contact = new Contact;
			}

			$contact->first_name = $row[3];
			$contact->last_name = $row[4];

			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}

			if (!$address = $contact->address) {
				$address = new Address;
				$address->parent_class = 'Contact';
				$address->parent_id = $contact->id;
			}

			$address->address1 = @$row[5].", ".$row[6];
			$address->address2 = @$row[7];
			$address->city = @$row[8];
			$address->county = @$row[9];
			$address->postcode = @$row[10];
			$address->country_id = 1;

			if (!$address->save()) {
				throw new Exception("Unable to save address: ".print_r($address->getErrors(),true));
			}

			$cbs->name = $row[2];
			$cbs->contact_id = $contact->id;
			$cbs->commissioning_body_id = isset($commissioning_bodies[$cbs->code]) ? $commissioning_bodies[$cbs->code] : null;

			if (!$cbs->save()) {
				throw new Exception("Unable to save commissioning body service: ".print_r($cbs->getErrors(),true));
			}

			echo ".";
		}

		echo "\n";
	}
}
