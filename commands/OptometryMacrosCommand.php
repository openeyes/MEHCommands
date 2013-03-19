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

class OptometryMacrosCommand extends CConsoleCommand {
	public function run($args) {
		$subspecialty = Subspecialty::model()->find('name=?',array('Optometry'));
		//$ssa = ServiceSubspecialtyAssignment::model()->find('subspecialty_id=?',array($subspecialty->id));
		//$firm = Firm::model()->find('name=? and service_subspecialty_assignment_id=?',array('Moorfields',$ssa->id));

		$macros = array(
			array(
				'name' => 'RGP lens follow up',
				'body' => 'Diagnosis: [eps] [epd]

This [sub] was seen in the contact lens service today. [Pro] is currently wearing rigid gas permeable lenses in right/left/both eyes and achieves visual acuities RE 6/* and LE 6/*. 

Anterior eye examination reveals no changes to the condition and we shall review the patient again in ......... time for the contact lenses.',
			),
			array(
				'name' => 'Soft lens follow up',
				'body' => 'Diagnosis: [eps] [epd]

This [sub] was seen in the contact lens service today. [Pro] is currently wearing soft annual/monthly/daily lenses in right/left/both eyes and achieves visual acuities RE 6/* and LE 6/*. 

Anterior eye examination reveals no changes to the condition and we shall review the patient again in ......... time for the contact lenses.',
			),
			array(
				'name' => 'Bandage lens follow up',
				'body' => 'Diagnosis: [eps] [epd]

This [sub] was seen in the contact lens service today. [Pro] has been fitted with a therapeutic bandage lens to right/left/both eyes. The lens is to be worn on an extended/daily wear basis. Visual acuities today are RE 6/* and LE 6/*. 

We shall review the patient again in ......... time for replacement of the therapeutic contact lenses.',
			),
			array(
				'name' => 'Inappropriate referral letter',
				'body' => 'Diagnosis: [eps] [epd]

This [sub] was seen in the contact lens service today. Unfortunately the patient does not fall under the hospital eye service eligibility criteria for contact lenses. We have discussed the options for contact lens wear available and have advised the patient to consult their local optometrist for a contact lens assessment if they still wish to pursue this avenue.',
			),
			array(
				'name' => 'Discharge letter',
				'body' => 'Diagnosis: [eps] [epd]

This [sub] was seen in the contact lens service today. They have previously been wearing contact lenses, however they have stopped their contact lens wear. We have therefore discharged them from the contact lens service however should they wish to resume contact lens wear please re-refer as required.',
			),
			array(
				'name' => 'DNA Discharge Letter',
				'body' => 'Diagnosis: [eps] [epd]

This [sub] has previously been seen in the contact lens service. Unfortunately the patient has failed to attend two booked appointments and has now been discharged form the clinic. If they wish to be seen again under the contact lens service we will require a new referral.',
			),
			array(
				'name' => 'New',
				'body' => 'Diagnosis: [eps] [epd]

This [sub] was seen in the Low Vision Clinic today.  [Pos] corrected Visual Acuities are R logMAR (snellen) L LogMAR (snellen).  At near [pro] can read N* @*cm. 

We demonstrated various aids and have loaned
New spectacles were prescribed for DV/NV/no new spectacles were needed. 

The patient was advised 

We will see the patient again in *months/ we have not arranged follow up in the Low Vision Clinic but will be happy to see again if needed.',
			),
			array(
				'name' => 'QTVI',
				'body' => 'Diagnosis: [eps] [epd]

This [sub] was seen in the Low Vision Clinic today. [Pos] corrected Visual Acuities are R logMAR (snellen) L LogMAR (snellen). At near [pro] can read N* @*cm.

We demonstrated various aids and have loaned 
New spectacles were prescribed for DV/NV/no new spectacles were needed.

[Pro] is getting on well at school/ [Pro] is finding difficulties with 

We will see the patient again in *months',
			),
		);

		foreach ($macros as $i => $macro) {
			echo "Creating: '{$macro['name']}': ";

			if (!$lm = SubspecialtyLetterMacro::model()->find('subspecialty_id=? and name=?',array($subspecialty->id,$macro['name']))) {
				$lm = new SubspecialtyLetterMacro;
				$lm->subspecialty_id = $subspecialty->id;
				$lm->name = $macro['name'];
			}

			$lm->recipient_doctor = 1;
			$lm->body = $macro['body'];
			$lm->cc_patient = 1;
			$lm->display_order = $i+1;
			$lm->save();

			echo "done\n";
		}
	}
}
