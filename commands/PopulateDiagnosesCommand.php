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

class PopulateDiagnosesCommand extends CConsoleCommand {
	public function run($args) {
		$episode_diagnoses = array();

		foreach (Yii::app()->db->createCommand()
			->select("p.id as patient_id, di.disorder_id, di.eye_id, di.created_date")
			->from("et_ophtroperationbooking_diagnosis di")
			->join("event e","di.event_id = e.id")
			->join("episode ep","e.episode_id = ep.id")
			->join("patient p","p.id = ep.patient_id")
			->where("e.deleted = 0 and ep.deleted = 0")
			->queryAll() as $row) {

			if (!isset($episode_diagnoses[$row['patient_id']])) {
				$episode_diagnoses[$row['patient_id']] = $this->getEpisodeDiagnoses($row['patient_id']);
			}

			if (!isset($secondary_diagnoses[$row['patient_id']])) {
				$secondary_diagnoses[$row['patient_id']] = $this->getSecondaryDiagnoses($row['patient_id']);
			}

			if (!$this->diagnosis_in_list($row['disorder_id'],$row['eye_id'],$episode_diagnoses) &&
				!$this->diagnosis_in_list($row['disorder_id'],$row['eye_id'],$secondary_diagnoses)) {

				$sd = new SecondaryDiagnosis;
				$sd->patient_id = $row['patient_id'];
				$sd->date = $row['created_date'];
				$sd->disorder_id = $row['disorder_id'];
				$sd->eye_id = $row['eye_id'];

				if (!$sd->save()) {
					throw new Exception("Unable to save secondary diagnosis: ".print_r($sd->getErrors(),true));
				}

				echo ".";
			}
		}

		echo "\n";
	}

	public function diagnosis_in_list($disorder_id, $eye_id, $list) {
		foreach ($list as $listItem) {
			if ($disorder_id == $listItem['disorder_id']) {
				if ($eye_id == $listItem['eye_id'] || $eye_id == 3 || $listItem['eye_id'] == 3) {
					return true;
				}
			}
		}

		return false;
	}

	public function getEpisodeDiagnoses($patient_id) {
		$diagnoses = array();

		foreach (Episode::model()->findAll('patient_id=?',array($patient_id)) as $episode) {
			$diagnoses[] = array(
				'disorder_id' => $episode->disorder_id,
				'eye_id' => $episode->eye_id,
			);
		}

		return $diagnoses;
	}

	public function getSecondaryDiagnoses($patient_id) {
		$diagnoses = array();

		foreach (SecondaryDiagnosis::model()->findAll('patient_id=?',array($patient_id)) as $sd) {
			$diagnoses[] = array(
				'disorder' => $sd->disorder_id,
				'eye_id' => $sd->eye_id,
			);
		}

		return $diagnoses;
	}
}
