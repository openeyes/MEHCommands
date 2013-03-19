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

class Test5Command extends CConsoleCommand {
	public function run($args) {
		Yii::import('application.modules.OphCiExamination.models.*');

		Yii::app()->session['user'] = User::model()->findByPk(1);
		Yii::app()->session['selected_firm_id'] = 1;
		Yii::app()->session['selected_site_id'] = 1;

		#echo "Hospital no,Opnote date,First name,Last name,Left VA,Right VA\n";

		foreach (Yii::app()->db->createCommand()
			->select('p.id as patient_id, p.hos_num, ep.id as episode_id, e.datetime, c.first_name, c.last_name, pl.eye_id')
			->from('patient p')
			->join('contact c',"c.parent_class='Patient' and c.parent_id = p.id")
			->join('episode ep','ep.patient_id = p.id')
			->join('event e','e.episode_id = ep.id')
			->join('et_ophtroperationnote_procedurelist pl','pl.event_id = e.id')
			->join('et_ophtroperationnote_cataract cat','cat.event_id = e.id')
			->where('e.deleted = 0 and ep.deleted = 0')
			->order('e.datetime desc')
			->limit(1500)
			->queryAll() as $row) {

			echo "{$row['hos_num']} {$row['first_name']} {$row['last_name']}: ";

/*
			$x=0;
			foreach (Yii::app()->db->createCommand()
				->select('vis.id')
				->from('et_ophciexamination_visualacuity vis')
				->join('event e','vis.event_id = e.id')
				->where("e.episode_id = {$row['episode_id']} and e.datetime < '{$row['datetime']}' and e.deleted = 0")
				->order('e.datetime desc')
				->limit(1)
				->queryAll() as $i => $row2) {
				$x++;
			}
*/
			$x=0;
			foreach (Yii::app()->db->createCommand()
				->select('vis.id')
				->from('et_ophciexamination_visualacuity vis')
				->join('event e','vis.event_id = e.id')
				->join('episode ep','e.episode_id = ep.id')
				->join('patient p','ep.patient_id = p.id')
				->where("p.id = {$row['patient_id']} and e.datetime < '{$row['datetime']}' and ep.deleted = 0 and e.deleted = 0")
				->order('e.datetime desc')
				->limit(1)
				->queryAll() as $i => $row2) {

				$element = Element_OphCiExamination_VisualAcuity::model()->findByPk($row2['id']);

				if ($row['eye_id'] == 1 && $element->hasLeft()) {
					foreach ($element->left_readings as $reading) {
						if ($reading->method->name == 'Glasses') {
							$value = $reading->convertTo($reading->value);
							echo "left: $value ";
							/*
							if (preg_match('/\//',$value)) {
								$e = explode('/',$value);
								echo implode(' / ',$e);
							} else {
								echo $value;
							}
							*/
							//echo '"'.$value.'"';
						}
					}
				}

				if ($row['eye_id'] == 2 && $element->hasRight()) {
					foreach ($element->right_readings as $reading) {
						if ($reading->method->name == 'Glasses') {
							$value = $reading->convertTo($reading->value);
							/*if (preg_match('/\//',$value)) {
								$e = explode('/',$value);
								echo implode(' / ',$e);
							} else {
								echo $value;
							}*/
							//echo '"'.$value.'"';
							echo "right: $value ";
						}
					}
				}
			}

			echo "\n";

/*			if ($x == 1) {
				echo "OK\n";
			} else if ($x >1) {
				echo "MANY\n";
			} else {
				echo "\n";
			}
			*/
		}
	}
}
