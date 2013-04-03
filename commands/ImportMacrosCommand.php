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


class ImportMacrosCommand extends CConsoleCommand {
	public function run($args) {
		Yii::import('application.vendors.*');
		require_once 'Zend/Loader.php';
		Zend_Loader::loadClass('Zend_Gdata');
		Zend_Loader::loadClass('Zend_Gdata_AuthSub');
		Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
		Zend_Loader::loadClass('Zend_Gdata_Spreadsheets');
		$service = Zend_Gdata_Spreadsheets::AUTH_SERVICE_NAME;
		$client = Zend_Gdata_ClientLogin::getHttpClient(Yii::app()->params['gdata_username'], Yii::app()->params['gdata_password'], $service);
		$ss = new Zend_Gdata_Spreadsheets($client);
		$spreadsheet_feed = $ss->getSpreadsheetFeed();
		
		// Load worksheets into array
		$data = array();
		foreach ($spreadsheet_feed->entries as $workbook) {
			if ($workbook->title == 'Correspondence Macros') {
				$spreadsheet_key = basename($workbook->id);
				$query = new Zend_Gdata_Spreadsheets_DocumentQuery();
				$query->setSpreadsheetKey($spreadsheet_key);
				$worksheet_feed = $ss->getWorksheetFeed($query);
				foreach($worksheet_feed->entries as $worksheet) {
					if(!in_array($worksheet->title, array('firm_letter_macro'))) {
						continue;
					}
					$worksheet_key = basename($worksheet->id);
					$query = new Zend_Gdata_Spreadsheets_CellQuery();
					$query->setSpreadsheetKey($spreadsheet_key);
					$query->setWorksheetId($worksheet_key);
					$cell_feed = $ss->getCellFeed($query);
					foreach ($cell_feed as $cell) {
						$data[(string)$worksheet->getTitle()][$cell->cell->getRow()][$cell->cell->getColumn()] = $cell->cell->getText();
					}
				}
			}
		}
		
		// Import worksheets
		$table_mappings = array(
				'firm_letter_macro' => array(
						'table' => 'et_ophcocorrespondence_firm_letter_macro',
						'pk' => 'name',
				),
		);
		$column_mappings = array(
				'firm_letter_macro' => array(
						'name',
						'firm_id',
						'display_order',
						'episode_status_id',
						'body',
						'recipient_patient',
						'recipient_doctor',
						'cc_patient',
						'cc_doctor',
						'use_nickname',
				),
		);
		foreach($data as $worksheet_name => $rows) {
			$table = $table_mappings[$worksheet_name]['table'];
			$pk = $table_mappings[$worksheet_name]['pk'];
			$columns = array_shift($rows);
			echo $worksheet_name."\n";
			foreach($rows as $row_index => $row) {
				$row_import = array();
				foreach($column_mappings[$worksheet_name] as $gcolumn_name => $oecolumn_name) {
					if(is_int($gcolumn_name)) {
						$gcolumn_name = $oecolumn_name;
					}
					$index = array_search($gcolumn_name, $columns);
					if($index) {
						$value = isset($row[$index]) ? $row[$index] : null;
						if($value == '#N/A' || $value == 'NULL') {
							$value = null;
						}
						$row_import[$oecolumn_name] = $value;
					}
				}
				$existing_id = Yii::app()->db->createCommand()
					->select('id')
					->from($table)
					->where($pk.' = :pk')
					->queryScalar(array(':pk' => $row_import[$pk]));
				if($existing_id) {
					$result = Yii::app()->db->createCommand()
						->update($table, $row_import, 'id = :pk', array(':pk' => $row_import[$pk]));
					echo "!";
				} else {
					$result = Yii::app()->db->createCommand()
						->insert($table, $row_import);
					echo "+";
				}
			}
			echo "\n";
		}
		
	}
}
