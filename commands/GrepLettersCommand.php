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

class GrepLettersCommand extends CConsoleCommand {
	public function run($args) {
		# $users = array('');
		$users = array('ALL');

		# $phrases = array('Accessory Caniliculus');
		# $phrases = array('canal','cannal');
		# $phrases = array('ccessory%canal','ccessory%cannal');
		# $phrases = array('accessory lacrimal canal');
		$phrases = array('accessory canaliculus','accessory cannaliculus','accessory cannlic','accessory cannilic','accessory lacrimal');

		$user_ids = Yii::app()->db->createCommand("select * from user where username in ('" . join('\',\'',$users)."')")->queryAll(); 
		if ($users[0] == 'ALL') {
			$user_ids[0]['first_name'] = 'ALL';
			$user_ids[0]['last_name'] = 'USERS';
		}
		foreach ($phrases as $phrase) {
			echo "Phrase: " . $phrase . "\n";
			foreach ($user_ids as $user_id) {
				echo "\t" . $user_id['first_name'] . " " . $user_id['last_name'] . "\n";

				if ($users[0] == 'ALL') {
					$query = "select id, event_id from et_ophcocorrespondence_letter where body like '%".$phrase."%';";
					$query_leg = "select id, event_id,epatient_hosnum,epatient_id from et_ophleepatientletter_epatientletter where letter_html like '%".$phrase."%';";
				} else {
					$query = "select id, event_id from et_ophcocorrespondence_letter where (created_user_id=" . $user_id['id'] . " or last_modified_user_id=" . $user_id['id'] . ") and body like '%".$phrase."%';";
					$query_leg = "select id, event_id,epatient_hosnum,epatient_id from et_ophleepatientletter_epatientletter where (created_user_id=" . $user_id['id'] . " or last_modified_user_id=" . $user_id['id'] . ") and letter_html like '%".$phrase."%';";
				}
				echo "\t\tcorrespondence: \n";
				foreach (Yii::app()->db->createCommand($query)->queryAll() as $row) {
					$event = Event::model()->findByPk($row['event_id']);
					if ($patient = @$event->episode->patient) {
						echo "\t\t\t" . $patient->hos_num . ", " . $patient->first_name . " " . $patient->last_name . ", " . "dob: " . $patient->dob . "   " . "http://openeyes.moorfields.nhs.uk/OphCoCorrespondence/default/view/" . $event->id . "\n";
					} else {
						echo "\t\t\tNo episode/patient for: " . $event->id;
					}
				}
				echo "\t\tlegacy: \n";
				foreach (Yii::app()->db->createCommand($query_leg)->queryAll() as $row) {
					$event = Event::model()->findByPk($row['event_id']);
					if ($patient = @$event->episode->patient) {
						echo "\t\t\t" . $patient->hos_num . ", " . $patient->first_name . " " . $patient->last_name . ", " . "dob: " . $patient->dob . "   " . "http://openeyes.moorfields.nhs.uk/OphLeEpatientletter/default/view/" . $event->id . "\n";
					} else {
						echo "\t\t\t" ."** patient not in openeyes.  epatient hos num: " . $row['epatient_hosnum'] . ", epatient letter row id: " . $row['epatient_id'] . "\n";
					}
				}
			}
		}
	}
}
