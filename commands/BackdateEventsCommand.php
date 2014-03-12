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

class BackdateEventsCommand extends CConsoleCommand
{
	public function run($args)
	{
		if(($fp = fopen("/tmp/backdated_events.csv","r")) === FALSE) {
			die("Couldn't open /tmp/backdated_events.csv\n");
		}
		$backdated_events = array();
		$first = true;
		$count = 0;
		$results = array(
			'skipped_rows' => 0,
			'completed_rows' => 0,
			'total_rows' => 0
		);
		while ($data = fgetcsv($fp)) {
			if($first) {
				$first = false;
				$columns = array_flip($data);
				if(!isset($columns['Appointment Date'])) {
					throw new CException('Missing Appointment Date column in data');
				}
				if(!isset($columns['Event ID'])) {
					throw new CException('Missing Event ID column in data');
				}
				continue;
			}
			$count++;
			$results['total_rows']++;

			$event_id = $data[$columns['Event ID']];
			$event_date = date('Y-m-d', strtotime($data[$columns['Appointment Date']]));
			if(!$event = Event::model()->findByPk($event_id)) {
				echo "Cannot find event id $event_id, line $count, skipping\n";
				print_r($data);
				$results['skipped_rows']++;
				continue;
			}
			echo "Processing ".$event_id."...\n";
			echo "- Changing created date from ".$event->created_date." to ".$event_date."\n";
			$event->created_date = $event_date;
			if(!$event->save(true,null,true)) {
				throw new CException('Could not save event');
			}
			$results['completed_rows']++;
		}
		fclose($fp);
		print_r($results);
	}
}
