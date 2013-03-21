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

class OpnoteOperationLinkCommand extends CConsoleCommand {
	public $count0 = 0;
	public $count1 = 0;
	public $count2 = 0;
	public $countA = 0;

	public $event_map = array(
		30945 => 30155,
		25698 => 23667,
		30945 => 30155,
		34640 => 10265,
		34802 => 22622,
		36128 => 23989,
		39942 => 18280,
		42033 => 33877,
		43086 => 23342,
		44437 => 25220,
		47521 => 46629,
		52285 => 20067,
		54277 => 25092,
		56925 => 53469,
		57885 => 54194,
		59309 => 39117,
		60450 => 23806,
		61370 => 61287,
		67092 => 32562,
		67662 => 50762,
		71024 => 69804,
		71556 => 66097,
		2123607 => 43389,
		2123684 => 51836,
		2126882 => 49374,
		2131883 => 69270,
		2132477 => 69532,
		2140847 => 24302,
		2143476 => 2142529,
		2146035 => 2145254,
		2146175 => 28529,
		2146665 => 2128900,
		2147606 => 32206,
		2154081 => 47902,
		2154317 => 5763,
		2161551 => 2141719,
		2163400 => 50377,
		2167757 => 2166965,
		2168413 => 25685,
		2169111 => 2143289,
	);

	public function run($args) {
		$episode = Episode::model()->findByPk(200);
		echo $episode->firm->serviceSubspecialtyAssignment->subspecialty->name;
		exit;
		$et_opnote = EventType::model()->find('class_name=?',array('OphTrOperationnote'));

		foreach (Yii::app()->db->createCommand()
			->select("e.*")
			->from("event e")
			->join("episode ep","e.episode_id = ep.id")
			#->join("et_ophtroperationnote_cataract","et_ophtroperationnote_cataract.event_id = e.id")
			->join("et_ophtroperationnote_procedurelist","et_ophtroperationnote_procedurelist.event_id = e.id")
			->where("e.deleted = 0 and ep.deleted = 0")
			->queryAll() as $row) {

			$event = Event::model()->findByPk($row['id']);

			if ($this->inferOperation($event)) {
				#echo ".";
			} else {
				#echo "x";
			}

		}

		echo "\n";
		echo "	total: ".($this->count0+$this->count1+$this->count2+$this->countA)."\n";
		echo "matched: $this->count1\n";
		echo "0-match: $this->count0\n";
		echo "n-match: $this->count2\n";
		echo "a-match: $this->countA\n";
		echo "\n";

		/*foreach (Event::model()->findAll('event_type_id=?',array($et_opnote->id)) as $event) {

		}*/
	}

	public function inferOperation($event) {
		if (isset($this->event_map[$event->id])) {
			echo ".";
			$this->count1++;
			return;
		}

		$op = EventType::model()->find('class_name=?',array('OphTrOperationbooking'));
		$opnote = EventType::model()->find('class_name=?',array('OphTrOperationnote'));

		$priorOperations = array();

		foreach (Yii::app()->db->createCommand()
			->select("event.*")
			->from("event")
			->join("episode","event.episode_id = episode.id")
			->where("episode_id = $event->episode_id and datetime < '$event->datetime' and event.deleted = 0 and episode.deleted = 0")
			->order("datetime desc")
			->queryAll() as $event2) {

			if ($event2['event_type_id'] == $op->id) {
				$operation = ElementOperation::model()->find('event_id=?',array($event2['id']));
				if (in_array($operation->status,array(1,3))) {
					$priorOperations[] = $event2;
				}
			}

			if ($event2['event_type_id'] == $opnote->id) {
				break;
			}
		}

		if (count($priorOperations) == 1) {
			echo ".";
			$this->count1++;
			return;
		}

		if (count($priorOperations) == 0) {
			// look for operations in all episodes
			$patient_id = $event->episode->patient_id;

			foreach (Yii::app()->db->createCommand()
				->select("event.*")
				->from("event")
				->join("episode","event.episode_id = episode.id")
				->where("datetime < '$event->datetime' and event.deleted = 0 and episode.deleted = 0 and episode.patient_id = $patient_id and event.event_type_id = $op->id")
				->order("datetime desc")
				->queryAll() as $event2) {

				$operation = ElementOperation::model()->find('event_id=?',array($event2['id']));
				if (in_array($operation->status,array(1,3))) {
					$priorOperations[] = $event2;
				}
			}

			if (count($priorOperations) == 1) {
				echo ".";
				$this->count1++;
				return true;
			}
	
			if (count($priorOperations) == 0) {
				#echo "Error: opnote found with no prior operations.\n";
				echo "0";
				$this->logm(0,$event->id);
				$this->count0++;
				return false;
			}

			if ($this->operation_matches($priorOperations[0], $event)) {
				echo ".";
				$this->count1++;
				return true;
			}
		}

		$matches = 0;

		foreach ($priorOperations as $i => $operation) {
			if ($this->operation_matches($operation, $event)) {
				$matches++;

				if ($i == 0) {
					echo ".";
					$this->count1++;
					return true;
				}
			}
		}

		if ($matches == 1) {
			echo ".";
			$this->count1++;
		} else if ($matches == 0) {
			echo "A";
			$this->countA++;
			$this->logm('a',$event->id);
			#echo "Error: opnote had no matches to booked operations.\n";
			return false;
		} else if ($matches >1) {
			echo "2";
			$this->count2++;
			$this->logm(2,$event->id);
			#echo "Error: opnote had multiple matches to booked operations.\n";
			return false;
		}
	}

	public function operation_matches($operation_event, $opnote_event) {
		if (!$proclist = ElementProcedureList::model()->find('event_id=?',array($opnote_event->id))) {
			return false;
		}
		if (!$operation = ElementOperation::model()->find('event_id=?',array($operation_event['id']))) {
			return false;
		}

		if ($operation->eye_id != 3 && $proclist->eye_id != $operation->eye_id) {
			return false;
		}

		$proc_ids1 = array();
		foreach ($proclist->procedures as $procedure) {
			$proc_ids1[] = $procedure->id;
		}

		$proc_ids2 = array();
		foreach ($operation->procedures as $procedure) {
			$proc_ids2[] = $procedure->id;
		}

		// check that booked procedures are all in the opnote
		$match = true;
		foreach ($proc_ids2 as $proc_id) {
			if (!in_array($proc_id,$proc_ids1)) {
				$match = false;
			}
		}

		if ($match) return true;

		$match = true;
		foreach ($proc_ids1 as $proc_id) {
			if (!in_array($proc_id,$proc_ids2)) {
				$match = false;
			}
		}

		return $match;
/*
		sort($proc_ids1);
		sort($proc_ids2);

		return ($proc_ids1 == $proc_ids2);
		*/
	}

	public function logm($type,$event_id) {
		$fp = fopen("$type.log","a+");
		fwrite($fp,"http://openeyes.moorfields.nhs.uk/OphTrOperationnote/default/view/$event_id\n");
		fclose($fp);
	}
}
