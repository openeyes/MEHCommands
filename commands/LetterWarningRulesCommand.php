<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

class LetterWarningRulesCommand extends CConsoleCommand {
	public function run($args) {
		Yii::import('application.modules.OphTrOperationbooking.models.*');

		Yii::app()->db->createCommand("set foreign_key_checks = 0")->query();
		Yii::app()->db->createCommand("delete from ophtroperationbooking_admission_letter_warning_rule")->query();
		Yii::app()->db->createCommand("delete from ophtroperationbooking_admission_letter_warning_rule_type")->query();

		$type1 = new OphTrOperationbooking_Admission_Letter_Warning_Rule_Type;
		$type1->name = 'Preop Assessment';
		$type1->save();

		$rule1 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule1->rule_type_id = $type1->id;
		$rule1->show_warning = true;
		$rule1->warning_text = "All admissions require a Pre-Operative Assessment which you must attend. Non-attendance will cause a delay or possible <em>cancellation</em> to your surgery.";
		$rule1->emphasis = false;
		$rule1->strong = true;
		$rule1->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->theatre_id = 21;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->theatre_id = 22;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->theatre_id = 25;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->theatre_id = 9;
		$rule->subspecialty_id = 6;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type1->id;
		$rule->parent_rule_id = $rule1->id;
		$rule->subspecialty_id = 13;
		$rule->show_warning = false;
		$rule->save();

		$type2 = new OphTrOperationbooking_Admission_Letter_Warning_Rule_Type;
		$type2->name = 'Prescription';
		$type2->save();

		$rule2 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule2->rule_type_id = $type2->id;
		$rule2->show_warning = true;
		$rule2->warning_text = "You may be given a prescription after your treatment. This can be collected from our pharmacy on the ward, however unless you have an exemption certificate the standard prescription charge will apply.	Please ensure that you or your friend/relative/carer who is collecting you has a credit card/debit card available to cover the prescription charges.";
		$rule2->emphasis = true;
		$rule2->strong = false;
		$rule2->save();

		$rule2_2 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule2_2->rule_order = 10;
		$rule2_2->rule_type_id = $type2->id;
		$rule2_2->parent_rule_id = $rule2->id;
		$rule2_2->firm_id = 19;
		$rule2_2->theatre_id = 9;
		$rule2_2->show_warning = false;
		$rule2_2->save();

		$rule2_2 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule2_2->rule_order = 10;
		$rule2_2->rule_type_id = $type2->id;
		$rule2_2->parent_rule_id = $rule2->id;
		$rule2_2->firm_id = 19;
		$rule2_2->theatre_id = 25;
		$rule2_2->show_warning = false;
		$rule2_2->save();

		$rule2_2 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule2_2->rule_order = 10;
		$rule2_2->rule_type_id = $type2->id;
		$rule2_2->parent_rule_id = $rule2->id;
		$rule2_2->subspecialty_id = 6;
		$rule2_2->show_warning = false;
		$rule2_2->save();

		$rule2_2 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule2_2->rule_order = 20;
		$rule2_2->rule_type_id = $type2->id;
		$rule2_2->parent_rule_id = $rule2->id;
		$rule2_2->site_id = 1;
		$rule2_2->warning_text = "You may be given a prescription after your treatment. This can be collected from our pharmacy on the ward, however unless you have an exemption certificate the standard prescription charge will apply.	Please ensure you, or the person collecting you, have the correct money/card payment to cover the prescription cost.";
		$rule2_2->save();

		$type3 = new OphTrOperationbooking_Admission_Letter_Warning_Rule_Type;
		$type3->name = 'Admission Instruction';
		$type3->save();

		$rule3 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule3->rule_type_id = $type3->id;
		$rule3->show_warning = true;
		$rule3->is_child = true;
		$rule3->warning_text = "Please contact the Children's Ward as soon as possible on 0207 566 2595 to discuss pre-operative instructions";
		$rule3->emphasis = false;
		$rule3->strong = true;
		$rule3->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type3->id;
		$rule->parent_rule_id = $rule3->id;
		$rule->site_id = 5;
		$rule->show_warning = false;
		$rule->save();

		$type4 = new OphTrOperationbooking_Admission_Letter_Warning_Rule_Type;
		$type4->name = 'Seating';
		$type4->save();

		$refractive = Subspecialty::model()->find('name=?',array('Refractive'));

		$rule4 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule4->rule_type_id = $type4->id;
		$rule4->subspecialty_id = $refractive->id;
		$rule4->show_warning = true;
		$rule4->warning_text = "We would like to request that only 1 person should accompany you in order to ensure that adequate seating is available for patients";
		$rule4->emphasis = false;
		$rule4->strong = false;
		$rule4->save();

		$rule4 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule4->rule_type_id = $type4->id;
		$rule4->firm_id = 19;
		$rule4->theatre_id = 9;
		$rule4->show_warning = true;
		$rule4->warning_text = "We would like to request that only 1 person should accompany you in order to ensure that adequate seating is available for patients";
		$rule4->emphasis = false;
		$rule4->strong = false;
		$rule4->save();

		$rule4 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule4->rule_type_id = $type4->id;
		$rule4->firm_id = 19;
		$rule4->theatre_id = 25;
		$rule4->show_warning = true;
		$rule4->warning_text = "We would like to request that only 1 person should accompany you in order to ensure that adequate seating is available for patients";
		$rule4->emphasis = false;
		$rule4->strong = false;
		$rule4->save();

		$type5 = new OphTrOperationbooking_Admission_Letter_Warning_Rule_Type;
		$type5->name = 'Prescription charges';
		$type5->save();

		$rule5 = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule5->rule_type_id = $type5->id;
		$rule5->show_warning = true;
		$rule5->warning_text = "Check whether you have to pay or are exempt from prescription charges.	If you are exempt you will need to provide proof that you are exempt every time you collect a prescription.";
		$rule5->emphasis = true;
		$rule5->strong = false;
		$rule5->is_child = 0;
		$rule5->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->parent_rule_id = $rule5->id;
		$rule->rule_type_id = $type5->id;
		$rule->firm_id = 19;
		$rule->theatre_id = 9;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->parent_rule_id = $rule5->id;
		$rule->rule_type_id = $type5->id;
		$rule->firm_id = 19;
		$rule->theatre_id = 25;
		$rule->show_warning = false;
		$rule->save();

		$rule = new OphTrOperationbooking_Admission_Letter_Warning_Rule;
		$rule->rule_type_id = $type5->id;
		$rule->parent_rule_id = $rule5->id;
		$rule->subspecialty_id = 13;
		$rule->show_warning = false;
		$rule->save();
	}
}
