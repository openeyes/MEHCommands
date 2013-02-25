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

Yii::import('application.modules.OphTrOperationbooking.models.*');

class WaitingListContactRulesCommand extends CConsoleCommand {
	public function run($args) {
		Yii::app()->db->createCommand("delete from ophtroperationbooking_waiting_list_contact_rule")->query();

		$rule1 = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule1->parent_rule_id = null;
		$rule1->is_child = true;
		$rule1->name = 'Naeela Butt';
		$rule1->telephone = '020 8725 0060';
		$rule1->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule1->id;
		$rule->site_id = 1;
		$rule->name = 'a nurse';
		$rule->telephone = '020 7566 2595';
		$rule->save();

		$rule2 = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule2->parent_rule_id = null;
		$rule2->is_child = false;
		$rule2->name = 'Naeela Butt';
		$rule2->telephone = '020 8725 0060';
		$rule2->save();

		$rule3 = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule3->parent_rule_id = $rule2->id;
		$rule3->site_id = 1;
		$rule3->name = 'Sherry Ramos';
		$rule3->telephone = '0207 566 2258';
		$rule3->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule3->id;
		$rule->service_id = 2;
		$rule->name = 'Sarah Veerapatren';
		$rule->telephone = '020 7566 2206/2292';
		$rule->save();

		$rule4 = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule4->parent_rule_id = $rule3->id;
		$rule4->service_id = 4;
		$rule4->name = 'Ian Johnson';
		$rule4->telephone = '020 7566 2006';
		$rule4->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule4->id;
		$rule->firm_id = 69;
		$rule->name = 'Joyce Carmichael';
		$rule->telephone = '020 7566 2205/2704';
		$rule->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule4->id;
		$rule->firm_id = 70;
		$rule->name = 'Joyce Carmichael';
		$rule->telephone = '020 7566 2205/2704';
		$rule->save();

		$rule5 = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule5->parent_rule_id = $rule3->id;
		$rule5->service_id = 5;
		$rule5->name = 'Ian Johnson';
		$rule5->telephone = '020 7566 2006';
		$rule5->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule5->id;
		$rule->firm_id = 69;
		$rule->name = 'Joyce Carmichael';
		$rule->telephone = '020 7566 2205/2704';
		$rule->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule5->id;
		$rule->firm_id = 70;
		$rule->name = 'Joyce Carmichael';
		$rule->telephone = '020 7566 2205/2704';
		$rule->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule3->id;
		$rule->service_id = 6;
		$rule->name = "Karen O'Connor";
		$rule->telephone = '020 7566 2056';
		$rule->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule3->id;
		$rule->service_id = 11;
		$rule->name = 'Sherry Ramos';
		$rule->telephone = '0207 566 2258';
		$rule->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule2->id;
		$rule->site_id = 3;
		$rule->name = 'Valerie Giddings';
		$rule->telephone = '020 8967 5648';
		$rule->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule2->id;
		$rule->site_id = 4;
		$rule->name = 'Saroj Mistry';
		$rule->telephone = '020 8869 3161';
		$rule->save();

		$rule6 = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule6->parent_rule_id = $rule2->id;
		$rule6->site_id = 6;
		$rule6->name = 'Eileen Harper';
		$rule6->telephone = '020 7566 2020';
		$rule6->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule6->id;
		$rule->service_id = 4;
		$rule->name = 'Linda Haslin';
		$rule->telephone = '020 7566 2712';
		$rule->save();

		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule2->id;
		$rule->site_id = 7;
		$rule->name = 'Sue Harney';
		$rule->telephone = '020 7566 2339';
		$rule->save();
	
		$rule = new OphTrOperationbooking_Waiting_List_Contact_Rule;
		$rule->parent_rule_id = $rule6->id;
		$rule->site_id = 9;
		$rule->name = 'Veronica Brade';
		$rule->telephone = '020 7566 2843';
		$rule->save();
	}
}
