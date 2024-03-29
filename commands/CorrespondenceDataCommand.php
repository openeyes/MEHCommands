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

class CorrespondenceDataCommand extends CConsoleCommand
{
	public function getName()
	{
		return '';
	}

	public function getHelp()
	{
		return "";
	}

	public function run($args)
	{
		Yii::import('application.modules.OphCoCorrespondence.models.*');

		foreach (LetterMacro::model()->findAll() as $lm) {
			$lm->delete();
		}

		foreach (FirmLetterMacro::model()->findAll() as $flm) {
			$flm->delete();
		}

		foreach (SubspecialtyLetterMacro::model()->findAll() as $slm) {
			$slm->delete();
		}

		echo "Creating Postop Discharge Summary macro ... ";

		foreach (Site::model()->findAll() as $site) {
			if (!$lm = LetterMacro::model()->find('name=? and site_id=?',array('Postop Discharge Summary',$site->id))) {
				$lm = new LetterMacro;
				$lm->name = 'Postop Discharge Summary';
			}

			$lm->site_id = $site->id;
			$lm->episode_status_id = 4;
			$lm->recipient_patient = 0;
			$lm->recipient_doctor = 1;
			$lm->use_nickname = 1;
			$lm->body = 'This [age] year old [sub] was discharged from hospital today.

Diagnosis: [obd]

Operation: [ops]
Prescription: [pre]

[Pro] has been given an appointment for post-operative review in 2 weeks time.';
			$lm->cc_patient = 1;
			$lm->display_order = 1;
			$lm->save();
		}

		echo "OK\n";

		echo "Creating alternative Postop Discharge Summary macro for cataract subspecialty ... ";

		$subspecialty = Subspecialty::model()->find('ref_spec=?',array('CA'));

		if (!$lm = SubspecialtyLetterMacro::model()->find('name=? and subspecialty_id=?',array('Postop Discharge Summary',$subspecialty->id))) {
			$lm = new SubspecialtyLetterMacro;
			$lm->name = 'Postop Discharge Summary';
		}

		$lm->subspecialty_id = $subspecialty->id;
		$lm->episode_status_id = 4;
		$lm->recipient_patient = 0;
		$lm->recipient_doctor = 1;
		$lm->use_nickname = 1;
		$lm->body = 'This [age] year old [sub] was discharged from hospital today.

Diagnosis: [obd]

Operation: [ops]
Prescription: [pre]

[Pro] has been given an appointment for post-operative review in 3 weeks time.';
		$lm->cc_patient = 1;
		$lm->display_order = 1;
		$lm->save();

		echo "OK\n";

		echo "Creating Drug change macro ... ";

		foreach (Site::model()->findAll() as $site) {
			if (!$dc = LetterMacro::model()->find('name=? and site_id=?',array('Drug change',$site->id))) {
				$dc = new LetterMacro;
				$dc->name = 'Drug change';
			}

			$dc->site_id = $site->id;
			$dc->episode_status_id = null;
			$dc->recipient_patient = 0;
			$dc->recipient_doctor = 1;
			$dc->use_nickname = 1;
			$dc->body = 'I reviewed this [age] year old [sub] in the clinic today. I have changed [pos] medication to the following: [pre].

I would be grateful if you could keep [obj] supplied with these for the future.';
			$dc->cc_patient = 1;
			$dc->display_order = 2;
			$dc->save();
		}

		echo "OK\n";

		echo "Creating Annual review macro ... ";

		foreach (Site::model()->findAll() as $site) {
			if (!$ar = LetterMacro::model()->find('name=? and site_id=?',array('Annual review',$site->id))) {
				$ar = new LetterMacro;
				$ar->name = 'Annual review';
			}

			$ar->site_id = $site->id;
			$ar->episode_status_id = null;
			$ar->recipient_patient = 0;
			$ar->recipient_doctor = 1;
			$ar->use_nickname = true;
			$ar->body = 'I saw this [age] year old [sub] for annual review today. There has been no change in [pos] condition and no change in management is required. [Pro] will be seen again in twelve months time.';
			$ar->cc_patient = 1;
			$ar->display_order = 3;
			$ar->save();
		}

		echo "OK\n";

		echo "Creating Episode Discharge macro ... ";

		foreach (Site::model()->findAll() as $site) {
			if (!$d = LetterMacro::model()->find('name=? and site_id=?',array('Episode Discharge',$site->id))) {
				$d = new LetterMacro;
				$d->name = 'Episode Discharge';
			}

			$d->site_id = $site->id;
			$d->episode_status_id = 6;
			$d->recipient_patient = 0;
			$d->recipient_doctor = 1;
			$d->use_nickname = true;
			$d->body = 'I saw this [age] year old [sub] today following [pos] recent treatment for a [eps] [epd]. Everything is stable and no further treatment is required.

No further follow up is required, and I have discharged [obj] from the clinic.';
			$d->cc_patient = 1;
			$d->display_order = 4;
			$d->save();
		}

		echo "OK\n";

		$letter_strings = array(
			'Introduction' => array(
				'Refer' => 'I would be very grateful if you would take over the management of this [age] year old [sub]',
				'Follow up visit' => 'This [age] year old [sub] attended the clinic for a routine follow up visit',
				'Referral' => 'Thank you very much for referring this [age] year old [sub] who I saw in the clinic today',
				'A&E Walk in' => 'This [age] year old [sub] attended the Accident and Emergency department, and was referred on to the clinic',
			),
			'Findings' => array(),
			'Diagnosis' => array(
				'Principal' => '[pos] principal diagnosis is [eps] [epd]',
			),
			'Management' => array(
				'Benefit' => 'I think [pro] would benefit from surgery and I explained the risks and benefits to [obj]',
				'Tail off topical medication' => 'We have tailed off [pos] topical medication and will see [obj] again in six weeks time',
				'Listed with date' => '[pro] has been listed for [opl] and an admission date will be arranged shortly',
				'Listed no date' => '[pro] has been listed for [opl] and will be admitted on [adm]',
				'Declined surgery' => 'The benefits and risks of surgery were fully discussed, but [pro] decided against',
				'No treatment' => '[pro] requires no treatment at the present time',
				'Thinking' => 'The benefits and risks of surgery were fully discussed, and [pro] is going to think about whether [pro] would like to proceed to surgery',
				'Observation' => '[pro] will be observed in the outpatient clinic for the time being',
			),
			'Drugs' => array(
				'Reducing' => '[pro] is on a reducing dose of topical medication',
				'Prescription' => '[pro] has been prescribed [pre]',
				'Stopped' => 'I have stopped all [pos] topical medication',
			),
			'Outcome' => array(
				'Take back' => 'I would be very grateful if you would take [obj] back for routine review',
				'Optician' => '[pro] should visit [pos] optician in the near future for a refraction',
				'Refer to Cataract' => '[pro] has been referred to the Cataract Service for further management',
				'VR follow up' => '[pro] will be seen again in the clinic in',
				'Discharge' => 'No further follow up is required, and I have discharged [obj]',
			),
		);

		foreach (LetterString::model()->findAll() as $ls) {
			$ls->delete();
		}

		foreach (SubspecialtyLetterString::model()->findAll() as $sls) {
			$sls->delete();
		}

		foreach (FirmLetterString::model()->findAll() as $fls) {
			$fls->delete();
		}

		echo "Creating letter strings:\n";

		foreach ($letter_strings as $group_name => $strings) {
			$group = LetterStringGroup::model()->find('name=?',array($group_name));

			echo " - $group_name:\n";

			$x = 1;
			foreach ($strings as $name => $body) {
				echo " - - $name ... ";

				foreach (Site::model()->findAll() as $site) {
					$ls = new LetterString;
					$ls->site_id = $site->id;
					$ls->letter_string_group_id = $group->id;
					$ls->name = $name;
					$ls->body = $body;
					$ls->display_order = $x++;
					$ls->save();
				}

				echo "OK\n";
			}
		}

		$findings = array(
			array(
				'name' => 'History',
				'body' => '[pro] gave a history of [hpc]',
				'display_order' => 5,
				'event_type' => 'OphCiExamination',
				'element_type' => 'Element_OphCiExamination_History',
			),
			array(
				'name' => 'Visual acuity',
				'body' => 'The best corrected visual acuity was [vbb]',
				'display_order' => 10,
				'event_type' => 'OphCiExamination',
				'element_type' => 'Element_OphCiExamination_VisualAcuity',
			),
			array(
				'name' => 'Adnexal comorbidity',
				'body' => 'Adnexal Comorbidity was [add] on the right and [adl] on the left',
				'display_order' => 15,
				'event_type' => 'OphCiExamination',
				'element_type' => 'Element_OphCiExamination_AdnexalComorbidity',
			),
			array(
				'name' => 'Anterior segment',
				'body' => 'Anterior segment examination showed [asr] on the right and [asl] on the left',
				'display_order' => 20,
				'event_type' => 'OphCiExamination',
				'element_type' => 'Element_OphCiExamination_AnteriorSegment',
			),
			array(
				'name' => 'Intraocular pressure',
				'body' => 'The intraocular pressure is [ipb]',
				'display_order' => 30,
				'event_type' => 'OphCiExamination',
				'element_type' => 'Element_OphCiExamination_IntraocularPressure',
			),
			array(
				'name' => 'Posterior segment',
				'body' => 'Posterior segment examination showed [psr] on the right and [psl] on the left',
				'display_order' => 40,
				'event_type' => 'OphCiExamination',
				'element_type' => 'Element_OphCiExamination_PosteriorSegment',
			),
		);

		$group = LetterStringGroup::model()->find('name=?',array('Findings'));

		foreach ($findings as $finding) {
			foreach (Site::model()->findAll('institution_id=?',array(1)) as $site) {
				if (!$ls = LetterString::model()->find('letter_string_group_id=? and name=? and site_id=?',array($group->id,$finding['name'],$site->id))) {
					$ls = new LetterString;
					$ls->letter_string_group_id = $group->id;
					$ls->name = $finding['name'];
					$ls->site_id = $site->id;
				}
				$ls->body = $finding['body'];
				$ls->display_order = $finding['display_order'];
				$ls->event_type = $finding['event_type'];
				$ls->element_type = $finding['element_type'];

				if (!$ls->save()) {
					throw new Exception("Unable to save letter string: ".print_r($ls->getErrors(),true));
				}
			}
		}
	}
}
