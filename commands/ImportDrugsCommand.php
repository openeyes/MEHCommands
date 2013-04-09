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

class ImportDrugsCommand extends ImportGdataCommand {
	
	public function run($args) {
		$data = $this->loadData('Drugs', array('drug_type', 'drug_form', 'drug_route',
				'drug_route_option', 'drug_frequency', 'drug_duration', 'drug', 'drug_set',
				'drug_set_item', 'allergy', 'site_subspecialty_drug'));
		
		// Munge site_subspecialty_drug data
		$sites = Site::model()->findAll('institution_id = ?', array(1));
		$rows = $data['site_subspecialty_drug'];
		$columns = array_shift($rows);
		$site_rows = array($columns);
		$index = array_search('site_id', $columns);
		foreach($sites as $site) {
			foreach($rows as $row) {
				$row[$index] = $site->id;
				$site_rows[] = $row;
			}
		}
		$data['site_subspecialty_drug'] = $site_rows;
		
		// Generate drug_allergy_assignment
		$rows = $data['drug'];
		$columns = array_shift($rows);
		$daa_rows = array(array(
				1 => 'drug_id',
				2 => 'allergy_id'
		));
		$drug_id_index = array_search('id', $columns);
		$allergy_id_index = array_search('allergy_id', $columns);
		foreach($rows as $row) {
			if($row[$allergy_id_index] &&$row[$allergy_id_index] != '#N/A' && $row[$allergy_id_index] != 'NULL') {
				$daa_rows[] = array(1 => $row[$drug_id_index], 2 => $row[$allergy_id_index]);
			}
		}
		$data['drug_allergy_assignment'] = $daa_rows;
		
		$this->importData($data, array(
				'drug_type' => array(
						'table' => 'drug_type',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
						),
				),
				'drug_form' => array(
						'table' => 'drug_form',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
						),
				),
				'drug_route' => array(
						'table' => 'drug_route',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
								'display_order',
						),
				),
				'drug_route_option' => array(
						'table' => 'drug_route_option',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
								'drug_route_id',
						),
				),
				'drug_frequency' => array(
						'table' => 'drug_frequency',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
								'long_name',
								'display_order',
						),
				),
				'drug_duration' => array(
						'table' => 'drug_duration',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
								'display_order',
						),
				),
				'drug' => array(
						'table' => 'drug',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'tallman',
								'name',
								'id',
								'aliases',
								'discontinued',
								'type_id',
								'form_id',
								'dose_unit',
								'default_dose',
								'default_route_id',
								'default_frequency_id',
								'default_duration_id',
								'preservative_free',
						),
				),
				'drug_set' => array(
						'table' => 'drug_set',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
								'subspecialty_id',
						),
				),
				'drug_set_item' => array(
						'table' => 'drug_set_item',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'drug_id',
								'id',
								'drug_set_id',
								'default_frequency_id',
								'default_duration_id',
						),
				),
				'allergy' => array(
						'table' => 'allergy',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
						),
				),
				'site_subspecialty_drug' => array(
						'table' => 'site_subspecialty_drug',
						'match_fields' => array('site_id', 'subspecialty_id', 'drug_id'),
						'truncate' => true,
						'column_mappings' => array(
								'site_id',
								'subspecialty_id',
								'drug_id',
						),
				),
				'drug_allergy_assignment' => array(
						'table' => 'drug_allergy_assignment',
						'match_fields' => array('drug_id', 'allergy_id'),
						'truncate' => true,
						'column_mappings' => array(
								'drug_id',
								'allergy_id',
						),
				),
		));
	}
}
