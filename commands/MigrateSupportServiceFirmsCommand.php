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

class MigrateSupportServiceFirmsCommand extends CConsoleCommand {
	public function run($args) {
		$ss_firms = array();
		foreach (array('EDD','Optometry','Orthoptics','Ultrasound') as $name) {
			if (!$firm_id = Yii::app()->db->createCommand()->select("id")->from("firm")->where("name=:name",array(':name'=>$name))->queryScalar()) {
				Yii::app()->db->createCommand()->insert('firm',array('name'=>$name));
				$firm_id = Yii::app()->db->createCommand()->select("max(id)")->from("firm")->queryScalar();
			}
			$ss_firms[$name] = $firm_id;
		}

		$support_services = Yii::app()->db->createCommand()->select("*")->from("specialty")->where("name = :name",array(":name"=>"Support Services"))->queryRow();

		$subspecialties = array();
		$subspecialty_ids = array();

		foreach (Yii::app()->db->createCommand()->select("*")->from("subspecialty")->where("specialty_id=:specialty_id",array(':specialty_id'=>$support_services['id']))->queryAll() as $subspecialty) {
			$subspecialties[] = $subspecialty;
			$subspecialty_ids[] = $subspecialty['id'];
		}

		$ss_firm_ids = array();
		foreach (Yii::app()->db->createCommand()
			->select("f.id")
			->from("firm f")
			->join("service_subspecialty_assignment ssa","f.service_subspecialty_assignment_id = ssa.id")
			->join("subspecialty s","s.id = ssa.subspecialty_id")
			->where("ssa.subspecialty_id in (".implode(',',$subspecialty_ids).")")
			->queryAll() as $firm) {
			$ss_firm_ids[] = $firm['id'];
		}

		foreach (Yii::app()->db->createCommand()
			->select("f.*, s.name as subspecialty")
			->from("firm f")
			->join("service_subspecialty_assignment ssa","f.service_subspecialty_assignment_id = ssa.id")
			->join("subspecialty s","s.id = ssa.subspecialty_id")
			->where("ssa.subspecialty_id in (".implode(',',$subspecialty_ids).")")
			->queryAll() as $firm) {

			foreach (Yii::app()->db->createCommand()
				->select("*")
				->from("episode ep")
				->where("firm_id = :firmId",array(':firmId'=>$firm['id']))
				->order('start_date asc')
				->queryAll() as $episode) {

				$ss_episode = $this->getSupportServicesEpisode($episode['patient_id'],$ss_firm_ids,$episode['deleted']);

				foreach (array('audit','event','referral_episode_assignment') as $table) {
					Yii::app()->db->createCommand()->update($table,array('episode_id'=>$ss_episode['id']),"episode_id = {$episode['id']}");
				}

				Yii::app()->db->createCommand()->delete("episode","id={$episode['id']}");

				foreach (array('audit','firm_user_assignment','ophtroperationbooking_letter_contact_rule','ophtroperationbooking_operation_erod','ophtroperationbooking_operation_sequence','ophtroperationbooking_operation_session','ophtroperationbooking_waiting_list_contact_rule','phrase_by_firm','referral','setting_firm','user_firm_preference','user_firm_rights','et_ophcocorrespondence_firm_letter_macro') as $table) {
					Yii::app()->db->createCommand()->update($table,array('firm_id'=>$ss_firms[$firm['subspecialty']]),"firm_id=".$firm['id']);
				}

				Yii::app()->db->createCommand()->update('user',array('last_firm_id'=>$ss_firms[$firm['subspecialty']]),"last_firm_id={$firm['id']}");
			}

			Yii::app()->db->createCommand()->delete('firm',"id={$firm['id']}");
		}

		/* Migrate subspecialty macros */

		foreach ($subspecialties as $subspecialty) {
			foreach (Yii::app()->db->createCommand()->select("*")->from("et_ophcocorrespondence_subspecialty_letter_macro")->where("subspecialty_id=:subspecialty_id",array(':subspecialty_id'=>$subspecialty['id']))->queryAll() as $slm) {
				Yii::app()->db->createCommand()->insert('et_ophcocorrespondence_firm_letter_macro',array(
					'firm_id' => $ss_firms[$subspecialty['name']],
					'name' => $slm['name'],
					'recipient_patient' => $slm['recipient_patient'],
					'recipient_doctor' => $slm['recipient_doctor'],
					'use_nickname' => $slm['use_nickname'],
					'body' => $slm['body'],
					'cc_patient' => $slm['cc_patient'],
					'display_order' => $slm['display_order'],
					'last_modified_user_id' => $slm['last_modified_user_id'],
					'last_modified_date' => $slm['last_modified_date'],
					'created_user_id' => $slm['created_user_id'],
					'created_date' => $slm['created_date'],
					'episode_status_id' => $slm['episode_status_id'],
					'cc_doctor' => $slm['cc_doctor'],
				));

				Yii::app()->db->createCommand()->delete('et_ophcocorrespondence_subspecialty_letter_macro',"id={$slm['id']}");
			}
		}

		Yii::app()->db->createCommand()->delete('service_subspecialty_assignment','subspecialty_id in ('.implode(',',$subspecialty_ids).')');
		Yii::app()->db->createCommand()->delete('subspecialty','id in ('.implode(',',$subspecialty_ids).')');
		Yii::app()->db->createCommand()->delete('specialty','id='.$support_services['id']);
	}

	public function getSupportServicesEpisode($patient_id, $ss_firm_ids, $deleted) {
		if ($episode = Yii::app()->db->createCommand()->select("*")->from("episode")->where("patient_id = :patient_id and support_services = :one and firm_id is null and deleted = :deleted",array(':patient_id'=>$patient_id,':one'=>1,'deleted'=>$deleted))->queryRow()) {
			return $episode;
		}

		if (!$first_episode = Yii::app()->db->createCommand()->select("*")->from("episode")->where("patient_id = $patient_id and firm_id in (".implode(',',$ss_firm_ids).") and deleted = $deleted")->order("start_date asc")->queryRow()) {
			echo "Unable to find support services episode for patient: $patient_id\n";
			exit;
		}

		unset($first_episode['id']);
		unset($first_episode['firm_id']);
		$first_episode['support_services'] = 1;

		Yii::app()->db->createCommand()->insert('episode',$first_episode);

		return $this->getSupportServicesEpisode($patient_id, $ss_firm_ids, $deleted);
	}
}
