<?php
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
