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

class OrthopticMacrosCommand extends CConsoleCommand {
	public function run($args) {
		$subspecialty = Subspecialty::model()->find('name=?',array('Orthoptics'));
		//$ssa = ServiceSubspecialtyAssignment::model()->find('subspecialty_id=?',array($subspecialty->id));
		//$firm = Firm::model()->find('name=? and service_subspecialty_assignment_id=?',array('Moorfields',$ssa->id));

		$macros = array(
			array(
				'name' => 'CVC Discharge letter',
				'body' => 'This patient has been discharged from the Eye clinic.
				
Date of last appointment: 
				
Diagnosis: [eps] [epd]
				
Visual Acuity: Right Eye:   		Left Eye: 		Test:  	With/Without Glasses
				
Their last prescription was issued on: 
Right Eye:				Left Eye:			
				
Other information: 
				
We have advised the patient to visit a local optician on a regular basis.  We are happy to review them again if there are future concerns and have advised they will need to seek a re-referral.',
			),
			array(
				'name' => 'CVC DNA',
				'body' => 'Your patient has failed to attend 2 appointments in the Orthoptic Department, they have therefore been discharged.

If you wish for them to be seen again, they will need to be re-referred.',
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
