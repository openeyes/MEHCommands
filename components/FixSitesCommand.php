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

class FixSitesCommand extends CConsoleCommand {
	public $remap = array(
		30 => 36,
		194 => 193,
		563 => 561,
		935 => 940,
		1129 => 1121,
		1333 => 1332,
		1758 => 1755,
		2032 => 2031,
		2189 => 2185,
		2301 => 2304,
		2334 => 2316,
		2795 => 2792,
		2793 => 2773,
		2791 => 2773,
		2794 => 2773,
		2790 => 2773,
	);

	public function run($args) {
		foreach ($this->remap as $from_id => $to_id) {
			$result = true;

			foreach (array(
				'audit',
				'element_operation',
				'et_ophcocorrespondence_firm_site_secretary',
				'et_ophcocorrespondence_letter',
				'et_ophcocorrespondence_letter_macro',
				'et_ophcocorrespondence_letter_old',
				'et_ophcocorrespondence_letter_string',
				'et_ophtroperationnote_postop_site_subspecialty_drug',
				'et_ophtroperationnote_site_subspecialty_postop_instructions',
				'patient_contact_assignment',
				'setting_site',
				'site_consultant_assignment',
				'site_specialist_assignment',
				'site_subspecialty_anaesthetic_agent',
				'site_subspecialty_anaesthetic_agent_default',
				'site_subspecialty_drug',
				'site_subspecialty_operative_device',
				'theatre',
				'ward') as $table) {

				if (!$this->switch_table($table, $from_id, $to_id)) {
					echo "Switch failed for table: $table\n";
					$result = false;
				}
			}

			if ($result) {
				Yii::app()->db->createCommand("delete from site where id = $from_id")->query();
			}
		}
	}

	public function switch_table($table, $from_id, $to_id) {
		$bad = false;

		foreach (Yii::app()->db->createCommand("select * from $table where site_id = $from_id")->queryAll() as $row) {
			unset($row['created_date']);
			unset($row['created_user_id']);
			unset($row['last_modified_date']);
			unset($row['last_modified_user_id']);

			$row['site_id'] = $to_id;

			if (!$this->exists($table, $row)) {
			} else {
				$bad = true;
			}
		}

		if (!$bad) {
			Yii::app()->db->createCommand("update $table set site_id = $to_id where site_id = $from_id")->query();
			return true;
		} else {
			return false;
		}
	}

	public function exists($table, $row) {
		$select = implode(',',array_keys($row));

		$where = '';
		$whereParams = array();
		foreach ($row as $key => $value) {
			if ($where) $where .= ' and ';
			$where .= "$key = :$key";
			$whereParams[":".$key] = $value;
		}

		return (Yii::app()->db->createCommand()
			->select($select)
			->from($table)
			->where($where, $whereParams)
			->queryRow());
	}
}
