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

class ImportSalisburyAttributesCommand extends ImportGdataCommand
{
	public function run($args)
	{
		$data = $this->loadData('Attributes-toby', array('Attribute', 'Element', 'Option'));
		$this->importData($data, array(
				'Attribute' => array(
						'table' => 'ophciexamination_attribute',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'name',
								'label',
								'element_type_name' => array('field' => 'element_type_id', 'method' => 'Find', 'args' => array('class' => 'ElementType', 'field' => 'name')),
								'id',
						),
				),
				'Element' => array(
						'table' => 'ophciexamination_attribute_element',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'attribute_id',
								'element_type_name' => array('field' => 'element_type_id', 'method' => 'Find', 'args' => array('class' => 'ElementType', 'field' => 'name')),
								'id',
						),
				),
				'Option' => array(
						'table' => 'ophciexamination_attribute_option',
						'match_fields' => array('id'),
						'column_mappings' => array(
								'attribute_element_id',
								'subspecialty_name' => array('field' => 'subspecialty_id', 'method' => 'Find', 'args' => array('class' => 'Subspecialty', 'field' => 'name')),
								'value',
								'delimiter',
								'id',
						),
				),
		));
	}
}
