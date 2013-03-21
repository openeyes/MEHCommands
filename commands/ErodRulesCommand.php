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

class ErodRulesCommand extends CConsoleCommand {
	
	public function getName() {
	}
	
	public function getHelp() {
	}

	public function run($args) {
		$uveitis = Subspecialty::model()->find('name=?',array('Uveitis'));
		$mr = Subspecialty::model()->find('name=?',array('Medical Retinal'));

		$mr_ssa = ServiceSubspecialtyAssignment::model()->find('subspecialty_id=?',array($mr->id));

		if (!$rule = ErodRule::model()->find('subspecialty_id=?',array($uveitis->id))) {
			$rule = new ErodRule;
			$rule->subspecialty_id = $uveitis->id;
			$rule->save();
		}

		$Okhravi = Firm::model()->find('name=? and service_subspecialty_assignment_id=?',array('Okhravi Narciss',$mr_ssa->id));

		if (!$item = ErodRuleItem::model()->find('erod_rule_id=? and item_type=? and item_id=?',array($rule->id,'firm',$Okhravi->id))) {
			$item = new ErodRuleItem;
			$item->erod_rule_id = $rule->id;
			$item->item_type = 'firm';
			$item->item_id = $Okhravi->id;
			$item->save();
		}

		$Pavesio = Firm::model()->find('name=? and service_subspecialty_assignment_id=?',array('Pavesio Carlos',$mr_ssa->id));

		if (!$item = ErodRuleItem::model()->find('erod_rule_id=? and item_type=? and item_id=?',array($rule->id,'firm',$Pavesio->id))) {
			$item = new ErodRuleItem;
			$item->erod_rule_id = $rule->id;
			$item->item_type = 'firm';
			$item->item_id = $Pavesio->id;
			$item->save();
		}
	}
}
