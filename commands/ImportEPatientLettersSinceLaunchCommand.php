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

class ImportEPatientLettersSinceLaunchCommand extends CConsoleCommand
{
	public $subspecialty_remap = array(
		'ST' => 'PE',
	);

	public $user_remap = array(
		'Mr David Bessant' => 11,
	);

	public function run($args)
	{
		$dbe = mssql_connect(Yii::app()->params['epatient_hostname'],Yii::app()->params['epatient_username'],Yii::app()->params['epatient_password']);
		mssql_select_db(Yii::app()->params['epatient_database'], $dbe);

		$count = 1;

		$batch = 1000;
		$id = 2035221;

		while (1) {
			$result = mssql_query("
				SELECT
					top $batch
					dbo.letters.id,
					dbo.letters.letterdate,
					dbo.letters.recipienttype,
					dbo.letters.createdby,
					dbo.letters.recipientdata,
					dbo.letters.contactdata,
					dbo.letters.datedata,
					dbo.letters.letterbody,
					dbo.letters.printed,
					dbo.letters.patientepisodeid,
					dbo.letters.locationid,
					dbo.letters.ccgp,
					dbo.letters.letterset,
					dbo.letters.pers_id,
					dbo.patients.pers_id,
					dbo.patients.hosnum
				FROM
					dbo.letters,
					dbo.patients
				WHERE
					dbo.letters.id >= $id
				ORDER BY
					dbo.letters.id
			");

			if (!$result) {
				echo mssql_get_last_message() . "\n";
				exit;
			}

			$event_type = EventType::model()->find('class_name=?',array('OphCoCorrespondence'));

			Yii::import('application.modules.OphCoCorrespondence.models.*');

			$i = 0;
			while ($row = mssql_fetch_object($result)) {
				$timestamp = strtotime($row->letterdate);

				if (!$created_by_user = $this->find_person($row->createdby)) {
					echo "Unable to find created by user: $row->createdby\n";
					$i++;
					continue;
				}

				if (!$row->hosnum = trim($this->get_hosnum_for_episode($row->pers_id))) {
					echo "Unable to find hosnum for episode $row->patientepisodeid\n";
					$i++;
					continue;
				}

				if (!$patient = Patient::model()->find('hos_num=?',array($row->hosnum))) {
					$patient = new Patient;
					$patient->hos_num = $row->hosnum;
					$data = $patient->search();
					$nr = $patient->search_nr();

					if ($nr != 1) {
						echo "Unable to find patient: $row->hosnum\n";
						$i++;
						continue;
					}

					foreach ($data as $patient) {}
				}

				if (!$firm = $this->getFirmForLetter($row)) {
					echo "x";
					/*echo "Failed: $row->id\n";
					exit;*/
				} else {
					$episode = $this->get_or_create_episode($patient->id, $firm, $timestamp, $created_by_user);

					$event = new Event;
					$event->episode_id = $episode->id;
					$event->created_user_id = $created_by_user->id;
					$event->created_date = date('Y-m-d',$timestamp);
					$event->event_type_id = $event_type->id;
					$event->created_date = date('Y-m-d H:i:s',$timestamp);
					$event->last_modified_user_id = $created_by_user->id;
					$event->last_modified_date = date('Y-m-d H:i:s',$timestamp);
					$event->save(true,null,true);

					$letter = new ElementLetter;
					$letter->event_id = $event->id;
					$letter->date = date('Y-m-d H:i:s',$timestamp);
					$letter->address = '';
					$letter->introduction = '';
					$letter->re = '';
					$letter->body = '';
					$letter->footer = '';
					$letter->cc = '';
					$letter->draft = !$row->printed;
					$letter->last_modified_user_id = $created_by_user->id;
					$letter->last_modified_date = date('Y-m-d H:i:s',$timestamp);
					$letter->created_user_id = $created_by_user->id;
					$letter->created_date = date('Y-m-d H:i:s',$timestamp);
					$letter->site_id = 1;
					$letter->save(true,null,true);

					echo ".";
				}

				$i++;
			}

			if ($i == 0) {
				break;
			}

			$id += $batch + 1;
		}

		echo "\n";
	}

	public function get_or_create_episode($patient_id, $firm, $timestamp, $created_by_user)
	{
		foreach (Episode::model()->findAll('patient_id=?',array($patient_id)) as $episode) {
			if ($episode->firm->serviceSubspecialtyAssignment->subspecialty_id == $firm->serviceSubspecialtyAssignment->subspecialty_id) {
				return $episode;
			}
		}

		$episode = new Episode;
		$episode->patient_id = $patient_id;
		$episode->firm_id = $firm->id;
		$episode->start_date = date('Y-m-d H:i:s',$timestamp);
		$episode->created_user_id = $created_by_user->id;
		$episode->last_modified_user_id = $created_by_user->id;
		$episode->created_date = date('Y-m-d H:i:s',$timestamp);
		$episode->last_modified_date = date('Y-m-d H:i:s',$timestamp);
		$episode->save(true,null,true);

		echo "e";

		return $episode;
	}

	public function get_hosnum_for_episode($pers_id)
	{
		$result = mssql_query("select * from patients where pers_id = $pers_id");

		if ($row = mssql_fetch_object($result)) {
			return $row->hosnum;
		}

		return false;
	}

	public function getFirmForLetter($letter)
	{
		$result = mssql_query("select * from patientepisode where patientepisodeid = $letter->patientepisodeid");

		if ($row = mssql_fetch_object($result)) {
			$result2 = mssql_query("select * from services where serv_id = $row->ServiceID");
			if ($row2 = mssql_fetch_object($result2)) {
				if (isset($this->subspecialty_remap[$row2->serv_code])) {
					$row2->serv_code = $this->subspecialty_remap[$row2->serv_code];
				}

				if (!$subspecialty = Subspecialty::model()->find('ref_spec=?',array($row2->serv_code))) {
					echo "Subspecialty not found: $row2->serv_code\n";
					return false;
				}

				if (!$user = $this->find_person($row->ConsultantId)) {
					return false;
				}

				$n = 0;
				foreach (FirmUserAssignment::model()->findAll('user_id=?',array($user->id)) as $fua) {
					if ($fua->firm->serviceSubspecialtyAssignment->subspecialty_id == $subspecialty->id) {
						$firm = $fua->firm;
						$n++;
					}
				}

				if ($n != 1) {
					echo "Firm not found for user $user->id [$n matches]\n";
					return false;
				}

				return true;
			}

			echo "Failed to find consultant: $row->ConsultantId\n";
			return false;
		}

		echo "Failed to find episode: $letter->patientepisodeid\n";
		return false;
	}

	public function find_person($pers_id)
	{
		if (!$result3 = mssql_query("select * from appusers where pers_id = $pers_id")) {
			echo mssql_get_last_message() . "\n";
			return false;
		}

		if ($row3 = mssql_fetch_object($result3)) {
			$n = 0;
			$row3->title = trim($row3->title);
			$row3->user_name = trim($row3->user_name);

			if (isset($this->user_remap[trim($row3->title.' '.$row3->user_name)])) {
				$found_user = User::model()->findByPk($this->user_remap[trim($row3->title.' '.$row3->user_name)]);
				$n = 1;
			} else {
				foreach (User::model()->findAll() as $user) {
					if (trim($user->title.' '.$user->first_name.' '.$user->last_name) == trim($row3->title.' '.$row3->user_name)) {
						$n++;
						$found_user = $user;
					}
				}
			}

			if ($n == 1) {
				return $found_user;
			} else {
				echo "Couldn't find user $row3->title $row3->user_name [$n matches]\n";
			}
		}

		return false;
	}
}
