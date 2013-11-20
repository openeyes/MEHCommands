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

class BillCommand extends CConsoleCommand {
	public $examination;

	public function run($args) {
		$this->examination = EventType::model()->find('class_name=?',array('OphCiExamination'));

		$rows = 1000;
		$until = "2013-07-31";
		$since = "2013-05-29";

		$phako_iol = 308;

		$data = //array_reverse(Yii::app()->db->createCommand()
			Yii::app()->db->createCommand()
			->select("e.id, p.gender, p.dob, p.date_of_death, e.episode_id, e.created_date, e2.created_date as booking_created_date, pl.eye_id, u.role as surgeon_role, at.name as anaesthetic_type, ad.name as anaesthetic_delivery, cat.id as cat_id, b.session_date, si.name as site_name, eye.name as eye, cat.predicted_refraction, cat.complication_notes")
			->from("event e")
			->join("episode ep","e.episode_id = ep.id")
			->join("patient p","ep.patient_id = p.id")
			->join("et_ophtroperationnote_cataract cat","cat.event_id = e.id")
			->join("et_ophtroperationnote_procedurelist pl","pl.event_id = e.id")
			->join("eye","pl.eye_id = eye.id")
			->join("et_ophtroperationnote_procedurelist_procedure_assignment pa","pa.procedurelist_id = pl.id and pa.proc_id = $phako_iol")
			->join("event e2","e2.id = pl.booking_event_id")
			->join("et_ophtroperationbooking_operation eo","eo.event_id = e2.id")
			->join("ophtroperationbooking_operation_booking b","b.element_id = eo.id and b.booking_cancellation_date is null")
			->join("ophtroperationbooking_operation_theatre t","b.session_theatre_id = t.id")
			->join("site si","si.id = t.site_id")
			->join("et_ophtroperationnote_surgeon su","su.event_id = e.id")
			->join("et_ophtroperationnote_anaesthetic an","an.event_id = e.id")
			->join("anaesthetic_type at","at.id = an.anaesthetic_type_id")
			->join("anaesthetic_delivery ad","ad.id = an.anaesthetic_delivery_id")
			->join("user u","su.surgeon_id = u.id")
			->where("e.deleted = 0 and ep.deleted = 0 and e.created_date >= '$since 00:00:00'") //e.created_date <= '$until 23:59:59'")
			->order("e.created_date asc")
			->limit($rows)
			->queryAll(); //);

		echo "Surgery date,Surgery site,Eye,Age,Gender,BCVA preop,Target refraction,Comorbidities,Surgeon role,Anaesthetic type,Anaesthetic delivery,Complications,BCVA postop,Refraction preop,Refraction postop,Notes indicate pc rupture,Notes indicate vitreous loss\n";

		foreach ($data as $row) {
			$age = Helper::getAge($row['dob'], $row['date_of_death']);

			$bcva_preop = $this->findBCVA_preop($row['episode_id'], $row['created_date'], $row['booking_created_date'], $row['eye_id']);
			//$target_refraction = $this->findTargetRefraction($row['episode_id'], $row['created_date'], $row['booking_created_date'], $row['eye_id']);
			$target_refraction = $row['predicted_refraction'];
			$comorbidities = $this->findComorbidities($row['episode_id'], $row['created_date'], $row['booking_created_date'], $row['eye_id']);
			$bcva_postop = $this->findBCVA_postop($row['episode_id'], $row['created_date'], $row['eye_id']);

			$refraction_preop = $this->findRefractionPreop($row['episode_id'], $row['created_date'], $row['booking_created_date'], $row['eye_id']);
			$refraction_postop = $this->findRefractionPostop($row['episode_id'], $row['created_date'], $row['booking_created_date'], $row['eye_id']);

			if ($row['anaesthetic_type'] == 'GA') {
				$row['anaesthetic_delivery'] = 'N/A';
			}

			$complications = '';
			foreach (CataractComplication::model()->findAll('cataract_id=?',array($row['cat_id'])) as $i => $complication) {
				if ($i >0) {
					$complications .= ', ';
				}
				$complications .= $complication->complication->name;
			}

			$vitreous_loss = preg_match('/vitreous loss/',strtolower($row['complication_notes'])) && !preg_match('/no vitreous loss/',strtolower($row['complication_notes']));
			$pc_rupture = preg_match('/pc rupture/',strtolower($row['complication_notes'])) && !preg_match('/no pc rupture/',strtolower($row['complication_notes']));

			echo "\"{$row['session_date']}\",\"{$row['site_name']}\",\"{$row['eye']}\",\"$age\",\"{$row['gender']}\",\"'$bcva_preop\",\"$target_refraction\",\"$comorbidities\",\"{$row['surgeon_role']}\",\"{$row['anaesthetic_type']}\",\"{$row['anaesthetic_delivery']}\",\"$complications\",\"'$bcva_postop\",\"$refraction_preop\",\"$refraction_postop\",".($pc_rupture ? 'Yes' : 'No').",".($vitreous_loss ? 'Yes' : 'No')."\n";
		}
	}

	public function findExamination($episode_id, $created_date, $booking_created_date, $eye_id, $table) {
		$eye = Eye::model()->findByPk($eye_id);
		$eye = strtolower($eye->name);

		if ($examination = Yii::app()->db->createCommand()
			->select("e.id, va.id as va_id")
			->from("event e")
			->join("$table va","va.event_id = e.id")
			->where("e.deleted = 0 and e.episode_id = $episode_id and e.event_type_id = {$this->examination->id} and e.created_date < '$booking_created_date'")
			->order("e.created_date desc")
			->queryRow()) {

			return $examination;
		}

		if ($examination = Yii::app()->db->createCommand()
			->select("e.id, va.id as va_id")
			->from("event e")
			->join("$table va","va.event_id = e.id")
			->where("e.deleted = 0 and e.episode_id = $episode_id and e.event_type_id = {$this->examination->id} and e.created_date < '$created_date'")
			->order("e.created_date desc")
			->queryRow()) {

			return $examination;
		}

		return false;
	}

	public function findExamination2($episode_id, $created_date, $eye_id, $table) {
		$eye = Eye::model()->findByPk($eye_id);
		$eye = strtolower($eye->name);

		if ($examination = Yii::app()->db->createCommand()
			->select("e.id, va.id as va_id")
			->from("event e")
			->join("$table va","va.event_id = e.id")
			->where("e.deleted = 0 and e.episode_id = $episode_id and e.event_type_id = {$this->examination->id} and e.created_date > '$created_date'")
			->order("e.created_date asc")
			->queryRow()) {

			return $examination;
		}

		return false;
	}

	public function findBCVA_preop($episode_id, $created_date, $booking_created_date, $eye_id) {
		$eye = strtolower(Eye::model()->findByPk($eye_id)->name);

		if ($examination = $this->findExamination($episode_id, $created_date, $booking_created_date, $eye_id, 'et_ophciexamination_visualacuity')) {
			$va = Element_OphCiExamination_VisualAcuity::model()->findByPk($examination['va_id']);

			if ($r = $va->getBestReading($eye)) {
				return $r->convertTo($r->value);
			}
		}

		return 'Not recorded';
	}

	public function findBCVA_postop($episode_id, $created_date, $eye_id) {
		$eye = strtolower(Eye::model()->findByPk($eye_id)->name);

		if ($examination = $this->findExamination2($episode_id, $created_date, $eye_id, 'et_ophciexamination_visualacuity')) {
			$va = Element_OphCiExamination_VisualAcuity::model()->findByPk($examination['va_id']);

			if ($r = $va->getBestReading($eye)) {
				return $r->convertTo($r->value);
			}
		}

		return 'Not recorded';
	}

	public function findComorbidities($episode_id, $created_date, $booking_created_date, $eye_id) {
		$eye = strtolower(Eye::model()->findByPk($eye_id)->name);

		$comorbidities = array();

		if ($examination = $this->findExamination($episode_id, $created_date, $booking_created_date, $eye_id, 'et_ophciexamination_comorbidities')) {
			$va = Element_OphCiExamination_Comorbidities::model()->findByPk($examination['va_id']);

			foreach ($va->items as $i => $item) {
				$comorbidities[] = $item->name;
			}
		}

		if ($examination = $this->findExamination($episode_id, $created_date, $booking_created_date, $eye_id, 'et_ophciexamination_anteriorsegment')) {
			$as = Element_OphCiExamination_AnteriorSegment::model()->findByPk($examination['va_id']);

			if ($as->{$eye.'_pxe'}) {
				$comorbidities[] = 'PXF';
			}

			if ($as->{$eye.'_phako'}) {
				$comorbidities[] = 'Phakodonesis';
			}
		}

		return !empty($comorbidities) ? implode(', ',$comorbidities) : 'Not recorded';
	}

	public function findTargetRefraction($episode_id, $created_date, $booking_created_date, $eye_id) {
		$eye = strtolower(Eye::model()->findByPk($eye_id)->name);

		if ($examination = $this->findExamination($episode_id, $created_date, $booking_created_date, $eye_id, 'et_ophciexamination_cataractmanagement')) {
			$tr = Element_OphCiExamination_CataractManagement::model()->findByPk($examination['va_id']);

			return $tr->target_postop_refraction;
		}

		return 'Not recorded';
	}

	public function findRefractionPreop($episode_id, $created_date, $booking_created_date, $eye_id) {
		$eye = strtolower(Eye::model()->findByPk($eye_id)->name);

		if ($examination = $this->findExamination($episode_id, $created_date, $booking_created_date, $eye_id, 'et_ophciexamination_refraction')) {
			$re = Element_OphCiExamination_Refraction::model()->findByPk($examination['va_id']);

			return $re->getCombined($eye);
		}

		return 'Not recorded';
	}

	public function findRefractionPostop($episode_id, $created_date, $booking_created_date, $eye_id) {
		$eye = strtolower(Eye::model()->findByPk($eye_id)->name);

		if ($examination = $this->findExamination2($episode_id, $created_date, $eye_id, 'et_ophciexamination_refraction')) {
			$re = Element_OphCiExamination_Refraction::model()->findByPk($examination['va_id']);

			return $re->getCombined($eye);
		}

		return 'Not recorded';
	}
}
