<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

class LinkOpnotesCommand extends CConsoleCommand {
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
	public $completed;

	public function run($args) {
		$this->completed = OphTrOperation_Operation_Status::model()->find('name=?',array('Completed'));

		foreach (Yii::app()->db->createCommand()
			->select("e.*")
			->from("event e")
			->join("episode ep","e.episode_id = ep.id")
			->join("et_ophtroperationnote_procedurelist","et_ophtroperationnote_procedurelist.event_id = e.id")
			->where("e.deleted = 0 and ep.deleted = 0")
			->queryAll() as $row) {

			if ($this->inferOperation($row['id'])) {
				echo ".";
			} else {
				echo "x";
			}
		}

		echo "\n";
	}

	public function createLink($opnote_event_id, $operation_event_id) {
		if (!$proclist = ElementProcedureList::model()->find('event_id=?',array($opnote_event_id))) {
			echo "Error: opnote event $opnote_event_id has no procedurelist!\n";
			return false;
		}
		if (!$eo = Element_OphTrOperation_Operation::model()->findByPk($operation_event_id)) {
			echo "Error: operation event $operation_event_id has no operation element!\n";
			return false;
		}

		Yii::app()->db->createCommand("update et_ophtroperationnote_procedurelist set booking_event_id = $operation_event_id where id = $proclist->id")->query();

		$update = "status_id = $this->completed";

		if (strtotime($eo->last_modified_date) < strtotime($proclist->created_date)) {
			$update .= ", last_modified_date = '$proclist->created_date', last_modified_user_id = $proclist->created_user_id";
		}
	 
		Yii::app()->db->createCommand("update et_ophtroperation_operation set $update where id = $eo->id")->query();
	}

	public function inferOperation($event_id) {
		if (isset($this->event_map[$event_id])) {
			$this->createLink($event_id, $this->event_map[$event_id]);
			return true;
		}

		$event = Event::model()->findByPk($event_id);

		foreach (Yii::app()->db->createCommand()
			->select("event.*")
			->from("event")
			->join("episode","event.episode_id = episode.id")
			->where("episode_id = $event->episode_id and datetime < '$event->datetime' and event.deleted = 0 and episode.deleted = 0")
			->order("datetime desc")
			->queryAll() as $event2) {

			if ($event2['event_type_id'] == $op->id) {
				$operation = Element_OphTrOperation_Operation::model()->find('event_id=?',array($event2['id']));
				if (in_array($operation->status->name,array('Scheduled','Rescheduled'))) {
					$priorOperations[] = $event2;
				}
			}

			if ($event2['event_type_id'] == $opnote->id) {
				break;
			}
		}

		if (count($priorOperations) == 1) {
			$this->createLink($event_id, $priorOperations[0]['id']);
			return true;
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

				$operation = Element_OphTrOperation_Operation::model()->find('event_id=?',array($event2['id']));
				if (in_array($operation->status->name,array('Scheduled','Rescheduled'))) {
					$priorOperations[] = $event2;
				}
			}

			if (count($priorOperations) == 1) {
				$this->createLink($event_id, $priorOperations[0]['id']);
				return true;
			}

			if (count($priorOperations) == 0) {
				return false;
			}

			if ($this->operation_matches($priorOperations[0], $event)) {
				$this->createLink($event_id, $priorOperations[0]['id']);
				return true;
			}
		}

		$matches = array();

		foreach ($priorOperations as $i => $operation) {
			if ($this->operation_matches($operation, $event)) {
				$matches[] = $operation;

				if ($i == 0) {
					$this->createLink($event_id, $operation['id']);
					return true;
				}
			}
		}

		if (count($matches) == 1) {
			$this->createLink($event_id, $matches[0]);
			return true;
		}

		return false;
	}

	public function operation_matches($operation_event, $opnote_event) {
		if (!$proclist = ElementProcedureList::model()->find('event_id=?',array($opnote_event->id))) {
			return false;
		}
		if (!$operation = Element_OphTrOperation_Operation::model()->find('event_id=?',array($operation_event['id']))) {
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
	}
}
