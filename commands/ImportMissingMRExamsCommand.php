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

class ImportMissingMRExamsCommand extends CConsoleCommand
{
	public function run($args)
	{
		if(($fp = fopen("/tmp/missing_grades.csv","r")) === FALSE) {
			die("Couldn't open /tmp/missing_grades.csv\n");
		}
		$missing_grades = array();
		$first = true;
		$count = 0;
		$results = array(
			'created_episodes' => 0,
			'created_events' => 0,
			'skipped_rows' => 0,
			'completed_rows' => 0,
			'total_rows' => 0
		);
		while ($data = fgetcsv($fp)) {
			if($first) {
				$first = false;
				$columns = array_flip($data);
				if(!isset($columns['hosnum'])) {
					throw new CException('Missing hosnum column in data');
				}
				if(!isset($columns['consultant'])) {
					throw new CException('Missing consultant column in data');
				}
				if(!isset($columns['date'])) {
					throw new CException('Missing date column in data');
				}
				if(!isset($columns['service'])) {
					throw new CException('Missing service column in data');
				}
				continue;
			}
			$count++;
			$results['total_rows']++;
			if(!$subspecialty = Subspecialty::model()->find('ref_spec = :ref_spec', array(':ref_spec' => $data[$columns['service']]))) {
				echo "Cannot find subspecialty for line $count, skipping\n";
				print_r($data);
				$results['skipped_rows']++;
				continue;
			}

			if(!$firm = Firm::model()->find('pas_code = :pas_code AND service_subspecialty_assignment_id = :ssa_id',
				 array(':pas_code' => $data[$columns['consultant']], ':ssa_id' => $subspecialty->serviceSubspecialtyAssignment->id))) {
				echo "Cannot find firm for line $count, skipping\n";
				print_r($data);
				$results['skipped_rows']++;
				continue;
			}
			$hosnum = sprintf('%07s', $data[$columns['hosnum']]);
			if (preg_match('/^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{1,2})$/',$data[$columns['date']],$m)) {
				$date = date('Y-m-d',mktime(0,0,0,$m[2],$m[1],((integer)$m[3])+2000));
			} else {
				$date = date('Y-m-d',strtotime($data[$columns['date']]));
			}
			$missing_grades[] = array(
				'hosnum' => $hosnum,
				'date' => $date,
				'firm' => $firm,
				'subspecialty' => $subspecialty
			);
		}
		fclose($fp);
		
		if(!$event_type = EventType::model()->find('class_name = ?', array('OphCiExamination'))) {
			throw new CException('Cannot find examination event type');
		}
		foreach($missing_grades as $missing_grade) {
			echo "Processing ".$missing_grade['hosnum']."...\n";
			if(!$patient = Patient::model()->find('hos_num = ?', array($missing_grade['hosnum']))) {
				// Try searching PAS
				$dp = Patient::model()->search(array(
					'first_name' => null,
					'last_name' => null,
					'hos_num' => $missing_grade['hosnum'],
					'nhs_num' => null,
					'pageSize' => 20,
					'currentPage' => 0,
					'sortBy' => 'hos_num*1',
					'sortDir' => 'asc',
				));
				$data = $dp->getData();
				if(!$patient = Patient::model()->find('hos_num = ?', array($missing_grade['hosnum']))) {
					echo "- Patient not found, skipping\n";
					$results['skipped_rows']++;
					continue;
				}
			}
			if(!$episode = Episode::model()->getBySubspecialtyAndPatient($missing_grade['subspecialty']->id, $patient->id)) {
				echo "- Cannot find episode, creating...";
				$episode = new Episode;
				$episode->patient_id = $patient->id;
				$episode->firm_id = $missing_grade['firm']->id;
				$episode->start_date = $missing_grade['date'];
				if(!$episode->save()) {
					throw new CException('Could not save new episode');
				}
				$results['created_episodes']++;
				echo "episode id ".$episode->id."\n";
			}
			$exam_event = Event::model()->find('episode_id = :episode_id AND event_type_id = :event_type_id AND created_date BETWEEN :date AND DATE_ADD(:date, INTERVAL 5 DAY)', array(':episode_id' => $episode->id, ':event_type_id' => $event_type->id, 'date' => $missing_grade['date']));
			if(!$exam_event) {
				echo "- No examination event found, creating...";
				$event = new Event();
				$event->episode_id = $episode->id;
				$event->created_date = $missing_grade['date'];
				$event->event_type_id = $event_type->id;
				$event->created_user_id = 1;
				$event->last_modified_user_id = 1;
				$event->last_modified_date = $missing_grade['date'];
				if(!$event->save(true,null,true)) {
					throw new CException('Could not save new exam event');
				}
				$results['created_events']++;
				echo "event_id  ".$event->id."\n";
				echo "- Adding a history element...";
				$element = new Element_OphCiExamination_History();
				$element->event_id = $event->id;
				$element->created_date = $event->created_date;
				$element->last_modified_date = $event->last_modified_date;
				$element->created_user_id = 1;
				$element->last_modified_user_id = 1;
				$element->description = 'PLACEHOLDER FOR DR GRADING IMPORT';
				if(!$element->save(true,null,true)) {
					throw new CException('Could not save new history element');
				}
				echo "element_id  ".$element->id."\n";
				if(date('Y-m-d',strtotime($episode->start_date)) > $missing_grade['date']) {
					echo "- Episode start_date needs adjusting: ".date('Y-m-d',strtotime($episode->start_date))." > ".$missing_grade['date']."\n";
				}
			}
			$results['completed_rows']++;
		}
		print_r($results);
	}
}
