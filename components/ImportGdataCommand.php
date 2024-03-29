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

abstract class ImportGdataCommand extends CConsoleCommand
{
	/**
	 * Load a Gdata spreadsheet into an array
	 * @param string $spreadsheet_title
	 * @param array $worksheet_titles
	 * @return array
	 */
	protected function loadData($spreadsheet_title, $worksheet_titles = null)
	{
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
			if ($workbook->title == $spreadsheet_title) {
				$spreadsheet_key = basename($workbook->id);
				$query = new Zend_Gdata_Spreadsheets_DocumentQuery();
				$query->setSpreadsheetKey($spreadsheet_key);
				$worksheet_feed = $ss->getWorksheetFeed($query);
				foreach ($worksheet_titles as $worksheet_title) {
					$found = false;
					foreach ($worksheet_feed->entries as $worksheet) {
						if ($worksheet->title == $worksheet_title) {
							$found = true;
							break;
						}
					}
					if (!$found) {
						throw new CException('Worksheet not found: '.$worksheet_title);
					}
					$worksheet_key = basename($worksheet->id);
					$query = new Zend_Gdata_Spreadsheets_CellQuery();
					$query->setSpreadsheetKey($spreadsheet_key);
					$query->setWorksheetId($worksheet_key);
					$cell_feed = $ss->getCellFeed($query);
					foreach ($cell_feed as $cell) {
						$data[(string) $worksheet->getTitle()][$cell->cell->getRow()][$cell->cell->getColumn()] = $cell->cell->getText();
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Lookup attribute in model and return it's id
	 * @param mixed $value
	 * @param array $args
	 * @return integer
	 */
	protected function mapFind($value, $args)
	{
		$class = $args['class'];
		$field = $args['field'];

		$criteria = new CDbCriteria;
		$criteria->addCondition($field.' = :value');
		$criteria->params[':value'] = $value;

		if (is_array(@$args['where'])) {
			foreach ($args['where'] as $_key => $_value) {
				$criteria->addCondition($_key.' = :'.$_key);
				$criteria->params[':'.$_key] = $_value;
			}
		}

		$records = $class::model()->findAll($criteria);

		if (count($records) > 1) {
			throw new CException("More than one matching record in $class for $field = $value");
		} elseif (count($records) == 1) {
			return $records[0]->id;
		}
	}

	/**
	 * Import data into database
	 * @param array $data
	 * @param array $mappings
	 */
	protected function importData($data, $mappings)
	{
		foreach ($data as $worksheet_name => $rows) {
			$table = $mappings[$worksheet_name]['table'];
			$match_condition = array();
			foreach ($mappings[$worksheet_name]['match_fields'] as $match_field) {
				$match_condition[] = $match_field.' = :'.$match_field;
			}
			$match_condition = implode(' AND ', $match_condition);
			$columns = array_shift($rows);
			if (@$mappings[$worksheet_name]['truncate']) {
				echo 'Truncating '.$worksheet_name." ... ";
				Yii::app()->db->createCommand()->truncateTable($table);
				echo "done\n";
			}
			echo 'Importing '.$worksheet_name." ";
			foreach ($rows as $row_index => $row) {
				$row_import = array();
				foreach ($mappings[$worksheet_name]['column_mappings'] as $gcolumn_name => $oecolumn_name) {
					$method = null;
					if (is_int($gcolumn_name)) {
						// Straight mapping
						$gcolumn_name = $oecolumn_name;
					} elseif (is_array($oecolumn_name)) {
						// Method mapping
						$method = $oecolumn_name['method'];
						$args = @$oecolumn_name['args'];
						$oecolumn_name = $oecolumn_name['field'];
					}
					$index = array_search($gcolumn_name, $columns);
					if ($index) {
						$value = isset($row[$index]) ? $row[$index] : null;
						if ($value == '#N/A' || $value == 'NULL') {
							$value = null;
						}
						if ($method) {
							$value = $this->{'map'.$method}($value, $args);
						}
						$row_import[$oecolumn_name] = $value;
					}
				}
				$match_params = array();
				foreach ($mappings[$worksheet_name]['match_fields'] as $match_field) {
					$match_params[':'.$match_field] = $row_import[$match_field];
				}
				$existing = Yii::app()->db->createCommand()
				->select('*')
				->from($table)
				->where($match_condition)
				->queryAll(true, $match_params);
				if (count($existing) > 1) {
					throw new CException("More than one existing record found");
				}
				if ($existing) {

					// See if it needs updating
					$update = false;
					foreach ($row_import as $field => $value) {
						if ($existing[0][$field] != $value) {
							$update = true;
							break;
						}
					}

					if ($update) {
						try {
							$result = Yii::app()->db->createCommand()
							->update($table, $row_import, $match_condition, $match_params);
							echo "!";
						} catch (CDbException $e) {
							echo "\nError updating row in $table\n";
							var_dump($row);
							var_dump($row_import);
							throw $e;
						}
					} else {
						echo ".";
					}
				} else {
					try {
						$result = Yii::app()->db->createCommand()
						->insert($table, $row_import);
						echo "+";
					} catch (CDbException $e) {
						echo "\nError adding row to $table\n";
						var_dump($row);
						var_dump($row_import);
						throw $e;
					}
				}
			}
			echo " done.\n";
		}
	}
}
