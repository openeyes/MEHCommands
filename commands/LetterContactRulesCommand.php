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

class LetterContactRulesCommand extends CConsoleCommand
{
	public function run($args)
	{
		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = null;
		$contact->rule_order = 1;
		$contact->firm_id = 19;
		$contact->theatre_id = 9;
		$contact->refuse_telephone = '020 7566 2205';
		$contact->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = null;
		$contact->rule_order = 2;
		$contact->firm_id = 233;
		$contact->site_id = 6;
		$contact->refuse_telephone = '020 7566 2020';
		$contact->save();

		$contact1 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact1->parent_rule_id = null;
		$contact1->rule_order = 10;
		$contact1->site_id = 1;
		$contact1->refuse_telephone = '020 7566 2206/2292';
		$contact1->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact1->id;
		$contact->subspecialty_id = 4;
		$contact->refuse_telephone = '020 7566 2006';
		$contact->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact1->id;
		$contact->subspecialty_id = 6;
		$contact->refuse_telephone = '020 7566 2006';
		$contact->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact1->id;
		$contact->subspecialty_id = 7;
		$contact->refuse_telephone = '020 7566 2056';
		$contact->save();

		$contact2 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact2->parent_rule_id = $contact1->id;
		$contact2->rule_order = 10;
		$contact2->subspecialty_id = 8;
		$contact2->refuse_telephone = '020 7566 2258';
		$contact2->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact2->id;
		$contact->theatre_id = 21;
		$contact->refuse_telephone = '020 7566 2311';
		$contact->refuse_title = 'the Admission Coordinator';
		$contact->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact1->id;
		$contact->subspecialty_id = 11;
		$contact->refuse_telephone = '020 7566 2258';
		$contact->refuse_title = 'the Paediatrics and Strabismus Admission Coordinator';
		$contact->health_telephone = '0207 566 2595 and ask to speak to a nurse';
		$contact->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact1->id;
		$contact->subspecialty_id = 13;
		$contact->refuse_title = 'Joyce Carmichael';
		$contact->refuse_telephone = '020 7566 2205';
		$contact->health_telephone = '020 7253 3411 X4336 and ask for a Laser Nurse';
		$contact->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact1->id;
		$contact->subspecialty_id = 14;
		$contact->refuse_telephone = '020 7566 2258';
		$contact->refuse_title = 'the Paediatrics and Strabismus Admission Coordinator';
		$contact->health_telephone = '0207 566 2595 and ask to speak to a nurse';
		$contact->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact1->id;
		$contact->subspecialty_id = 16;
		$contact->refuse_telephone = '020 7566 2004';
		$contact->save();

		$contact2 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact2->parent_rule_id = null;
		$contact2->rule_order = 10;
		$contact2->site_id = 3;
		$contact2->refuse_telephone = '020 8967 5766';
		$contact2->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact2->id;
		$contact->theatre_id = 22;
		$contact->refuse_telephone = '020 8967 5648';
		$contact->refuse_title = 'the Admission Coordinator';
		$contact->save();

		$contact3 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact3->parent_rule_id = null;
		$contact3->rule_order = 10;
		$contact3->site_id = 4;
		$contact3->refuse_telephone = '0203 182 4027';
		$contact3->save();

		$contact4 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact4->parent_rule_id = null;
		$contact4->rule_order = 10;
		$contact4->site_id = 5;
		$contact4->refuse_telephone = '020 8725 0060';
		$contact4->health_telephone = '020 8725 0060';
		$contact4->save();

		$contact5 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact5->parent_rule_id = null;
		$contact5->rule_order = 10;
		$contact5->site_id = 6;
		$contact5->refuse_telephone = '020 7566 2712';
		$contact5->save();

		$contact = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact->parent_rule_id = $contact5->id;
		$contact->subspecialty_id = 7;
		$contact->refuse_telephone = '020 7566 2020';
		$contact->save();

		$contact6 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact6->parent_rule_id = null;
		$contact6->rule_order = 10;
		$contact6->site_id = 7;
		$contact6->refuse_telephone = '01707 646422';
		$contact6->save();

		$contact7 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact7->parent_rule_id = null;
		$contact7->rule_order = 10;
		$contact7->site_id = 8;
		$contact7->refuse_telephone = '020 8725 0060';
		$contact7->save();

		$contact8 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact8->parent_rule_id = null;
		$contact8->rule_order = 10;
		$contact8->site_id = 9;
		$contact8->refuse_telephone = '020 8211 8323';
		$contact8->save();

		$contact9 = new OphTrOperationbooking_Letter_Contact_Rule;
		$contact9->rule_order = 1;
		$contact9->parent_rule_id = null;
		$contact9->firm_id = 19;
		$contact9->theatre_id = 25;
		$contact9->refuse_telephone = '020 7566 2205';
		$contact9->save();
	}
}
