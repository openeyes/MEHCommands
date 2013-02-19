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

class LetterWarningRulesCommand extends CConsoleCommand {
	public function run($args) {
		Yii::import('application.modules.OphTrOperation.models.*');

		Yii::app()->db->createCommand("delete from ophtroperation_admission_letter_warning_rule")->query();
		Yii::app()->db->createCommand("delete from ophtroperation_admission_letter_warning_rule_type")->query();

		$type1 = new OphTrOperation_Admission_Letter_Warning_Rule_Type;
		$type1->name = 'Preop Assessment';
		$type1->save();

		$rule1 = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule1->rule_type_id = $type1->id;
		$rule1->show_warning = true;
		$rule1->warning_text = "All admissions require a Pre-Operative Assessment which you must attend. Non-attendance will cause a delay or possible <em>cancellation</em> to your surgery.";
		$rule1->emphasis = false;
		$rule1->strong = true;
		$rule1->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->theatre_id = 21;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->theatre_id = 22;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->theatre_id = 22;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->theatre_id = 9;
		$rule->subspecialty_id = 6;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->subspecialty_id = 13;
		$rule->show_warning = false;
		$rule->save();

		$type2 = new OphTrOperation_Admission_Letter_Warning_Rule_Type;
		$type2->name = 'Prescription';
		$type2->save();

		$rule2 = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule2->rule_type_id = $type2->id;
		$rule2->show_warning = true;
		$rule2->warning_text = "You may be given a prescription after your treatment. This can be collected from our pharmacy on the ward, however unless you have an exemption certificate the standard prescription charge will apply.	Please ensure you have the correct money or ask the relative/friend/carer who is collecting you to make sure they bring some money to cover the prescription.";
		$rule2->emphasis = true;
		$rule2->strong = false;
		$rule2->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type2->id;
		$rule->parent_rule_id = $rule2->id;
		$rule->subspecialty_id = 13;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type2->id;
		$rule->parent_rule_id = $rule2->id;
		$rule->theatre_id = 21;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type2->id;
		$rule->parent_rule_id = $rule2->id;
		$rule->theatre_id = 22;
		$rule->show_warning = false;
		$rule->save();

		$type3 = new OphTrOperation_Admission_Letter_Warning_Rule_Type;
		$type3->name = 'Admission Instruction';
		$type3->save();

		$rule3 = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule3->rule_type_id = $type3->id;
		$rule3->show_warning = false;
		$rule3->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type3->id;
		$rule->parent_rule_id = $rule3->id;
		$rule->is_child = true;
		$rule->site_id = 5;
		$rule->warning_text = "Please contact the Children's Ward as soon as possible on 0207 566 2595 to discuss pre-operative instructions";
		$rule->emphasis = false;
		$rule->strong = true;
		$rule->save();

		$type4 = new OphTrOperation_Admission_Letter_Warning_Rule_Type;
		$type4->name = 'Seating';
		$type4->save();

		$rule4 = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule4->rule_type_id = $type4->id;
		$rule4->show_warning = true;
		$rule4->warning_text = "We would like to request that only 1 person should accompany you in order to ensure that adequate seating is available for patients";
		$rule4->emphasis = false;
		$rule4->strong = false;
		$rule4->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type4->id;
		$rule->parent_rule_id = $rule4->id;
		$rule->theatre_id = 21;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperation_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type4->id;
		$rule->parent_rule_id = $rule4->id;
		$rule->theatre_id = 22;
		$rule->show_warning = false;
		$rule->save();
	}
}