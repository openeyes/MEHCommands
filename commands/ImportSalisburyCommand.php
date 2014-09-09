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

class ImportSalisburyCommand extends ImportGdataCommand
{
	public function run($args)
	{
		Yii::app()->db->createCommand("delete from ophtrlaser_laserprocedure")->query();
		Yii::app()->db->createCommand("delete from proc where term = 'Laser dummy procedure'")->query();

		$data = $this->loadData('Salisbury', array('Subspecialty','SubspecialtySubsection','Procedure','Benefit','ProcedureBenefit','Complication','ProcedureComplication','OPCSCode','ProcedureSubspecialtyAssignment','ProcSubspecialtySubsectionAssignment','CommonOphthalmicDisorder','CommonSystemicDisorder','PostopDrug','PostOpSiteSubspecialtyDrug','PostOpInstructions'));

		$this->importData($data, array(
			'Subspecialty' => array(
				'table' => 'subspecialty',
				'match_fields' => array('id'),
				'column_mappings' => array(
					'id',
					'name',
					'ref_spec',
					'specialty_id',
				),
			),
			'SubspecialtySubsection' => array(
				'table' => 'subspecialty_subsection',
				'match_fields' => array('name','subspecialty_id'),
				'column_mappings' => array(
					'id',
					'subspecialty_id',
					'name',
					'active',
				),
			),
			'Procedure' => array(
				'table' => 'proc',
				'match_fields' => array('term'),
				'column_mappings' => array(
					'term',
					'short_format' => array(
						'method' => 'getShortFormat',
						'field' => 'short_format',
					),
					'id',
					'default_duration',
					'snomed_code',
					'snomed_term',
					'aliases',
					'unbooked',
					'active',
				),
			),
			'Benefit' => array(
				'table' => 'benefit',
				'match_fields' => array('id'),
				'column_mappings' => array(
					'id',
					'name',
					'active',
				),
			),
			'ProcedureBenefit' => array(
				'table' => 'procedure_benefit',
				'match_fields' => array('proc_id','benefit_id'),
				'column_mappings' => array(
					'id',
					'proc_id',
					'benefit_id',
				),
			),
			'Complication' => array(
				'table' => 'complication',
				'match_fields' => array('id'),
				'column_mappings' => array(
					'id',
					'name',
					'active',
				),
			),
			'ProcedureComplication' => array(
				'table' => 'procedure_complication',
				'match_fields' => array('proc_id','complication_id'),
				'column_mappings' => array(
					'id',
					'proc_id',
					'complication_id',
				),
			),
			'OPCSCode' => array(
				'table' => 'opcs_code',
				'match_fields' => array('name'),
				'column_mappings' => array(
					'id',
					'name',
					'description',
					'active',
				),
			),
			'ProcedureOPCSCode' => array(
				'table' => 'proc_opcs_assignment',
				'match_fields' => array('proc_id','opcs_code_id'),
				'column_mappings' => array(
					'id',
					'proc_id',
					'opcs_code_id',
				),
			),
			'ProcedureSubspecialtyAssignment' => array(
				'table' => 'proc_subspecialty_assignment',
				'match_fields' => array('proc_id','subspecialty_id'),
				'column_mappings' => array(
					'id',
					'proc_id',
					'subspecialty_id',
				),
			),
			'ProcSubspecialtySubsectionAssignment' => array(
				'table' => 'proc_subspecialty_subsection_assignment',
				'match_fields' => array('proc_id','subspecialty_subsection_id'),
				'column_mappings' => array(
					'id',
					'proc_id',
					'subspecialty_subsection_id',
				),
			),
			'CommonOphthalmicDisorder' => array(
				'table' => 'common_ophthalmic_disorder',
				'match_fields' => array('disorder_id','subspecialty_id'),
				'column_mappings' => array(
					'id',
					'disorder_id',
					'subspecialty_id',
				),
			),
			'CommonSystemicDisorder' => array(
				'table' => 'common_systemic_disorder',
				'match_fields' => array('disorder_id'),
				'column_mappings' => array(
					'id',
					'disorder_id',
				),
			),
			'PostopDrug' => array(
				'table' => 'ophtroperationnote_postop_drug',
				'match_fields' => array('id'),
				'column_mappings' => array(
					'id',
					'name',
					'display_order',
					'active',
				),
			),
			'PostOpSiteSubspecialtyDrug' => array(
				'table' => 'ophtroperationnote_postop_site_subspecialty_drug',
				'match_fields' => array('site_id','subspecialty_id','drug_id'),
				'column_mappings' => array(
					'id',
					'site_id',
					'subspecialty_id',
					'drug_id',
					'display_order',
					'default',
				),
			),
			'PostOpInstructions' => array(
				'table' => 'ophtroperationnote_site_subspecialty_postop_instructions',
				'match_fields' => array('site_id','subspecialty_id'),
				'column_mappings' => array(
					'id',
					'site_id',
					'subspecialty_id',
					'content',
					'display_order',
				),
			),
		));
	}

	public function mapgetShortFormat($sf)
	{
		if ($sf === null) {
			return '';
		}

		return $sf;
	}
}
