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

class ImportMacrosCommand extends ImportGdataCommand {
	public function run($args) {
		$data = $this->loadData('Correspondence Macros', array('firm_letter_macro'));
		$this->importData($data, array(
				'firm_letter_macro' => array(
						'table' => 'et_ophcocorrespondence_firm_letter_macro',
						'match_fields' => array('name', 'firm_id'),
						'column_mappings' => array(
								'name',
								'firm_label' => array('field' => 'firm_id', 'method' => 'FindFirm'),
								'display_order',
								'episode_status_name' => array('field' => 'episode_status_id', 'method' => 'Find', 'args' => array('class' => 'EpisodeStatus', 'field' => 'name')),
								'body',
								'recipient_patient',
								'recipient_doctor',
								'cc_patient',
								'cc_doctor',
								'use_nickname',
						),
				),
		));
	}
	
	/**
	 * Lookup attribute in model and return it's id
	 * @param mixed $value
	 * @param array $args
	 * @return integer
	 */
	protected function mapFindFirm($value) {
		$tokens = explode('|', $value);
		$firm_name = trim($tokens[0]);
		$subspecialty_name = trim($tokens[1]);
		$criteria = new CDbCriteria;
		$criteria->join = '
				JOIN service_subspecialty_assignment ssa ON ssa.id = t.service_subspecialty_assignment_id
				JOIN subspecialty s ON s.id = ssa.subspecialty_id
				';
		$criteria->condition = 's.name = :subspecialty_name AND t.name = :firm_name';
		$criteria->params = array(':subspecialty_name' => $subspecialty_name, ':firm_name' => $firm_name);
		echo "($firm_name)\n";
		echo "($subspecialty_name)\n";
		if($firm = Firm::model()->find($criteria)) {
			return $firm->id;
		} else {
			return null;
		}
	}

}
