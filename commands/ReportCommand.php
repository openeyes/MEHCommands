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

class ReportCommand extends CConsoleCommand
{
	public function run($args)
	{
		$letters = array();

		foreach (Yii::app()->db->createCommand()
			->select("e.id, epatient_hosnum, created_date")
			->from("et_ophleepatientletter_epatientletter epl")
			->join("event e","epl.event_id = e.id")
			->where("letter_html like '%Martina Suzani%' and created_date >= '2011-01-01 00:00:00' and e.deleted = 0")
			->queryAll() as $row) {

			$timestamp = strtotime($row['created_date']);

			while (isset($letters[$timestamp])) {
				$timestamp++;
			}

			$event = Event::model()->findByPk($row['id']);
			if ($event->episode_id) {
				$patient = $event->episode->patient;
				$hosnum = $patient->hos_num;
				$first_name = $patient->first_name;
				$last_name = $patient->last_name;
			} else {
				if ($patient = Patient::model()->find('hos_num=?',array($row['epatient_hosnum']))) {
					$hosnum = $patient->hos_num;
					$first_name = $patient->first_name;
					$last_name = $patient->last_name;
				} else {
					$patient = new Patient;
					$patient->hos_num = $row['epatient_hosnum'];
					$_GET['sort_by'] = 0;
					$data = $patient->search(array('first_name'=>'','last_name'=>'','sortBy'=>'HOS_NUM*1','sortDir'=>'ASC','pageSize'=>30,'currentPage'=>1));
					$nr = $patient->search_nr(array('first_name'=>'','last_name'=>''));
					if ($nr != 1) {
						$hosnum = $row['epatient_hosnum'];
						$first_name = 'Unknown';
						$last_name = 'Unknown';
						$patient = false;
					} else {
						$patient = Patient::model()->find('hos_num=?',array($row['epatient_hosnum']));
						$hosnum = $patient->hos_num;
						$first_name = $patient->first_name;
						$last_name = $patient->last_name;
					}
				}

				$patient && $this->associateLegacyEvents($patient);
			}

			$letters[$timestamp] = array(
				'hosnum' => $hosnum,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'event_id' => $row['id'],
				'type' => 'legacy',
			);
		}

		$criteria = new CDbCriteria;
		$criteria->compare('created_user_id',1873);

		foreach (ElementLetter::model()->findAll($criteria) as $letter) {
			if ($letter->event->deleted == 0 && $letter->event->episode->deleted == 0) {
				$timestamp = strtotime($letter->event->created_date);
				while (isset($letters[$timestamp])) $timestamp++;

				$letters[$timestamp] = array(
					'hosnum' => $letter->event->episode->patient->hos_num,
					'first_name' => $letter->event->episode->patient->first_name,
					'last_name' => $letter->event->episode->patient->last_name,
					'event_id' => $letter->event_id,
					'type' => 'correspondence',
				);
			}
		}

		foreach (Yii::app()->db->createCommand()
			->select("e.id, e.created_date, p.hos_num, c.first_name, c.last_name")
			->from("et_ophtroperationnote_surgeon s")
			->join("event e","s.event_id = e.id")
			->join("episode ep","e.episode_id = ep.id")
			->join("patient p","ep.patient_id = p.id")
			->join("contact c","c.parent_class = 'Patient' and c.parent_id = p.id")
			->where("(s.surgeon_id = 1873 or s.assistant_id = 1873) and e.deleted = 0 and ep.deleted = 0")
			->queryAll() as $row) {

			$timestamp = strtotime($row['created_date']);
			while (isset($letters[$timestamp])) $timestamp++;

			$letters[$timestamp] = array(
				'hosnum' => $row['hos_num'],
				'first_name' => $row['first_name'],
				'last_name' => $row['last_name'],
				'event_id' => $row['id'],
				'type' => 'opnote',
			);
		}

		ksort($letters);

		echo "Hospital no.,First name,Last name,Date,Type,URL\n";

		foreach ($letters as $timestamp => $data) {
			if ($data['type'] == 'correspondence') {
				$url = "http://openeyes.moorfields.nhs.uk/OphCoCorrespondence/default/view/".$data['event_id'];
			} else {
				$url = "http://openeyes.moorfields.nhs.uk/OphLeEpatientletter/default/view/".$data['event_id'];
			}
			echo $data['hosnum'].','.$data['first_name'].','.$data['last_name'].','.date('d.m.Y H:i',$timestamp).',';
			switch ($data['type']) {
				case 'correspondence':
					echo 'Correspondence,http://openeyes.moorfields.nhs.uk/OphCoCorrespondence/default/view/'.$data['event_id']."\n";
					break;
				case 'legacy':
					echo 'Legacy letter,http://openeyes.moorfields.nhs.uk/OphLeEpatientletter/default/view/'.$data['event_id']."\n";
					break;
				case 'opnote':
					echo 'Operation note,http://openeyes.moorfields.nhs.uk/OphTrOperationnote/default/view/'.$data['event_id']."\n";
					break;
			}
		}
	}

	public function associateLegacyEvents($patient)
	{
		if (Element_OphLeEpatientletter_EpatientLetter::model()->find('epatient_hosnum=?',array($patient->hos_num))) {
			$episode = new Episode;
			$episode->patient_id = $patient->id;
			$episode->firm_id = null;
			$episode->start_date = date('Y-m-d H:i:s');
			$episode->end_date = null;
			$episode->episode_status_id = 1;
			$episode->legacy = 1;
			if (!$episode->save()) {
				throw new Exception('Unable to save new legacy episode: '.print_r($episode->getErrors(),true));
			}

			$earliest = time();

			foreach (Element_OphLeEpatientletter_EpatientLetter::model()->findAll('epatient_hosnum=?',array($patient->hos_num)) as $letter) {
				$event = Event::model()->findByPk($letter->event_id);
				$event->episode_id = $episode->id;
				if (!$event->save()) {
					throw new Exception('Unable to associate legacy event with episode: '.print_r($event->getErrors(),true));
				}

				if (strtotime($event->created_date) < $earliest) {
					$earliest = strtotime($event->created_date);
				}
			}

			$episode->start_date = date('Y-m-d H:i:s',$earliest);
		}
	}
}
