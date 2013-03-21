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

class Test6Command extends CConsoleCommand {
	public function run($args) {
		echo "Correspondence\n\n";

		foreach (Yii::app()->db->createCommand()
			->select("p.hos_num, c.first_name, c.last_name, e.id as event_id, e.datetime")
			->from("patient p")
			->join("contact c","c.parent_class = 'Patient' and c.parent_id = p.id")
			->join("episode ep","ep.patient_id = p.id")
			->join("event e","e.episode_id = ep.id")
			->join("et_ophcocorrespondence_letter l","l.event_id = e.id")
			->where("e.deleted = 0 and ep.deleted = 0 and (lower(l.body) like '%anterior capsule tear%' or lower(l.body) like '%anterior capsular tear%')")
			->order("e.datetime")
			->queryAll() as $row) {

			echo $row['hos_num'].",".$row['first_name'].",".$row['last_name'].",".date('j M Y',strtotime($row['datetime'])).",http://openeyes.moorfields.nhs.uk/OphCoCorrespondence/default/view/{$row['event_id']}\n";
		}

		echo "\nLegacy letters\n\n";

		foreach (Yii::app()->db->createCommand()
			->select("l.event_id, e.episode_id, l.epatient_hosnum, e.datetime")
			->from("et_ophleepatientletter_epatientletter l")
			->join("event e","l.event_id = e.id")
			->where("e.deleted = 0 and (lower(l.letter_html) like '%anterior capsule tear%' or lower(l.letter_html) like '%anterior capsular tear%')")
			->order('e.datetime')
			->queryAll() as $row) {

			if (!$patient = Patient::model()->find('hos_num=?',array($row['epatient_hosnum']))) {
				$patient = new Patient;
				$patient->hos_num = $row['epatient_hosnum'];
				$_GET['sort_by'] = 0;
				$data = $patient->search(array('first_name'=>'','last_name'=>'','sortBy'=>'HOS_NUM*1','sortDir'=>'ASC','pageSize'=>30,'currentPage'=>1));
				$nr = $patient->search_nr(array('first_name'=>'','last_name'=>''));
				if ($nr != 1) {
					echo "Warning: unable to find patient ".$row['epatient_hosnum']." in PAS\n";
					continue;
				}
				$patient = Patient::model()->find('hos_num=?',array($row['epatient_hosnum']));
			}

			if ($row['episode_id'] == null) {
				$this->associateLegacyEvents($patient);
			}

			echo $patient->hos_num.",".$patient->first_name.",".$patient->last_name.",".date('j M Y',strtotime($row['datetime'])).",http://openeyes.moorfields.nhs.uk/OphLeEpatientletter/default/view/{$row['event_id']}\n";
		}

		echo "\nOpnotes\n\n";

		foreach (Yii::app()->db->createCommand()
			->select("p.hos_num, c.first_name, c.last_name, e.id as event_id, e.datetime")
			->from("patient p")
			->join("contact c","c.parent_class = 'Patient' and c.parent_id = p.id")
			->join("episode ep","ep.patient_id = p.id")
			->join("event e","e.episode_id = ep.id")
			->join("et_ophtroperationnote_cataract cat","cat.event_id = e.id")
			->join("et_ophtroperationnote_cataract_complication com","com.cataract_id = cat.id")
			->where("e.deleted = 0 and ep.deleted = 0 and com.complication_id = 16")
			->order("e.datetime")
			->queryAll() as $row) {

			echo $row['hos_num'].",".$row['first_name'].",".$row['last_name'].",".date('j M Y',strtotime($row['datetime'])).",http://openeyes.moorfields.nhs.uk/OphTrOperationnote/default/view/{$row['event_id']}\n";
		}
	}

	public function associateLegacyEvents($patient) {
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

				if (strtotime($event->datetime) < $earliest) {
					$earliest = strtotime($event->datetime);
				}
			}

			$episode->start_date = date('Y-m-d H:i:s',$earliest);
		}
	}
}
