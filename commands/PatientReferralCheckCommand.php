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

class PatientReferralCheckCommand extends CConsoleCommand {
	public function getHelp()
	{
		return <<<EOH
./yiic patientreferralcheck
Check that patients who have shown activity in OpenEyes had open referrals at the time of the activity

Requires:
MEHPas

EOH;
	}

	public function run($args)
	{
		// GET the event_type id for the activity we are checking against
		if (!$event_type = EventType::model()->find('class_name = ?', array('OphTrOperationbooking'))) {
			throw new Exception('Cannot find event type for activity checking');
		}
		$missing_patients = array();

		$criteria = new CdbCriteria();
		$criteria->addCondition('t.deleted = 0');
		$criteria->addCondition('t.event_type_id = :eid');
		$criteria->params = array(':eid' => $event_type->id);
		$count = 0;
		$rep = "";
		foreach (Event::model()->with('episode')->findAll($criteria) as $event) {
			$patient = Patient::model()->noPas()->findByPk($event->episode->patient_id);
			if ($count % 100 == 0) {
				echo ".";
			}
			$count++;

			if (@$missing_patients[$event->episode->patient_id]) {
				continue;
			}
			try {
				$referrals = $this->getReferralsForPatient($patient);
			}
			catch (PatientNotPasLinked $e) {
				$missing_patients[$event->episode->patient_id] = true;
				continue;
			}
			if (!empty($referrals) && $referrals[0]->DT_REC <= $event->created_date) {
				continue;
			}
			echo "X";
			$rep .= "no referral for " . $patient->hos_num . " for operation " . $event->id . " created " . $event->created_date . "\n";
		}

		echo $rep;
	  if ($missing = count(array_keys($missing_patients))) {
			echo "missing " . $missing . " patients\n";
		}
		echo "\nanalysis done, checked " . $count . " events\n";

	}

	protected $referral_cache = array();
	protected $referral_criteria;
	/**
	 * cache of patient pas referrals
	 *
	 * @param Pateitn $patient
	 */
	protected function getReferralsForPatient($patient)
	{
		if (!isset($this->referral_cache[$patient->id])) {
			if (!$ass = PasAssignment::model()->findByInternal('Patient', $patient->id)) {
				throw new PatientNotPasLinked();
			}
			if (!$this->referral_criteria) {
				$this->referral_criteria = new CdbCriteria();
				$this->referral_criteria->addCondition('X_CN = :xcn');
				$this->referral_criteria->order = 'DT_REC ASC';
			}
			$this->referral_criteria->params = array(':xcn' => $ass->external_id);
			$this->referral_cache[$patient->id] = PAS_Referral::model()->findAll($this->referral_criteria);
		}
		return $this->referral_cache[$patient->id];
	}

}

class PatientNotPasLinked extends Exception
{

}

