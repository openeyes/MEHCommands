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

class ImportWorkflowsCommand extends ImportGdataCommand
{
	public function run($args)
	{
		$data = $this->loadData('Workflows', array('Workflow', 'Set', 'Item'));
		$this->importData($data, array(
				'Workflow' => array(
						'table' => 'ophciexamination_workflow',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'id',
						),
				),
				'Set' => array(
						'table' => 'ophciexamination_element_set',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'workflow_id',
								'position',
								'id',
						),
				),
				'Item' => array(
						'table' => 'ophciexamination_element_set_item',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'set_id',
								'element_type_classname' => array('field' => 'element_type_id', 'method' => 'Find', 'args' => array('class' => 'ElementType', 'field' => 'class_name')),
								'id',
						),
				),
		));
	}
}
