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

Yii::import('application.modules.OphTrOperationbooking.models.*');

class LetterContactRulesCommand extends CConsoleCommand {
	public function run($args) {
		Yii::app()->db->createCommand("set foreign_key_checks = 0")->query();
		Yii::app()->db->createCommand("delete from ophtroperationbooking_letter_contact_rule")->query();
		Yii::app()->db->createCommand("set foreign_key_checks = 1")->query();

		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 5,
			'is_child' => 1,
			'refuse_telephone' => '020 7566 2258',
			'refuse_title' => 'the Paediatrics and Strabismus Admission Coordinator',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 10,
			'firm_id' => 19,
			'theatre_id' => 9,
			'refuse_telephone' => '020 7566 2205',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 20,
			'firm_id' => 233,
			'site_id' => 6,
			'refuse_telephone' => '020 7566 2020',
		));
		$contact1 = $this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 100,
			'site_id' => 1,
			'refuse_telephone' => '020 7566 2206/2292',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact1->id,
			'subspecialty_id' => 4,
			'refuse_telephone' => '020 7566 2006',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact1->id,
			'subspecialty_id' => 6,
			'refuse_telephone' => '020 7566 2006',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact1->id,
			'subspecialty_id' => 7,
			'refuse_telephone' => '020 7566 2056',
		));
		$contact2 = $this->findOrCreateRule(array(
			'parent_rule_id' => $contact1->id,
			'rule_order' => 10,
			'subspecialty_id' => 8,
			'refuse_telephone' => '020 7566 2258',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact2->id,
			'theatre_id' => 21,
			'refuse_telephone' => '020 7566 2311',
			'refuse_title' => 'the Admission Coordinator',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact1->id,
			'subspecialty_id' => 11,
			'refuse_telephone' => '020 7566 2258',
			'refuse_title' => 'the Paediatrics and Strabismus Admission Coordinator',
			'health_telephone' => '0207 566 2595 and ask to speak to a nurse',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact1->id,
			'subspecialty_id' => 13,
			'refuse_title' => 'Joyce Carmichael',
			'refuse_telephone' => '020 7566 2205',
			'health_telephone' => '020 7253 3411 X4336 and ask for a Laser Nurse',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact1->id,
			'subspecialty_id' => 14,
			'refuse_telephone' => '020 7566 2258',
			'refuse_title' => 'the Paediatrics and Strabismus Admission Coordinator',
			'health_telephone' => '0207 566 2595 and ask to speak to a nurse',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact1->id,
			'subspecialty_id' => 16,
			'refuse_telephone' => '020 7566 2004',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 10,
			'site_id' => 3,
			'refuse_telephone' => '020 8967 5766',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact2->id,
			'theatre_id' => 22,
			'refuse_telephone' => '020 8967 5648',
			'refuse_title' => 'the Admission Coordinator',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 10,
			'site_id' => 4,
			'refuse_telephone' => '0203 182 4027',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 10,
			'site_id' => 5,
			'refuse_telephone' => '020 8725 0060',
			'health_telephone' => '020 8725 0060',
		));
		$contact5 = $this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 10,
			'site_id' => 6,
			'refuse_telephone' => '020 7566 2712',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => $contact5->id,
			'subspecialty_id' => 7,
			'refuse_telephone' => '020 7566 2020',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 10,
			'site_id' => 7,
			'refuse_telephone' => '01707 646422',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 10,
			'site_id' => 8,
			'refuse_telephone' => '020 8725 0060',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 10,
			'site_id' => 9,
			'refuse_telephone' => '020 8211 8323',
		));
		$this->findOrCreateRule(array(
			'parent_rule_id' => null,
			'rule_order' => 1,
			'firm_id' => 19,
			'theatre_id' => 25,
			'refuse_telephone' => '020 7566 2205',
		));
	}

	public function findOrCreateRule($params) {
		$criteria = new CDbCriteria;

		foreach ($params as $key => $value) {
			$criteria->addCondition("$key = :$key");
			$criteria->params[":$key"] = $value;
		}

		if ($rule = OphTrOperationbooking_Letter_Contact_Rule::model()->find($criteria)) {
			return $rule;
		}

		$rule = new OphTrOperationbooking_Letter_Contact_Rule;

		foreach ($params as $key => $value) {
			$rule->{$key} = $value;
		}

		if (!$rule->save()) {
			throw new Exception("Unable to save rule: ".print_r($rule->getErrors(),true));
		}

		return $rule;
	}
}
