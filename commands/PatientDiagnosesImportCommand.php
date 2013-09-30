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

class PatientDiagnosesImportCommand extends CConsoleCommand {
	public function getHelp()
	{
		return <<<EOH
./yiic patientdiagnosesimport <filename>
Import diagnoses for patients. Expected file format is (with single header row):
hos num,pas key,snomed,date

Note that by default the date is not imported, as the data that was used for this initially did not have useable data for the date

Requires:
MEHPas

EOH;
	}

	const USE_DATE = false;
	const TREE_CHECK = true;

	public function run($args)
	{
		// Initialise db
		$connection = Yii::app()->db;
		if (count($args) != 1) {
			echo "wrong arguments\n\n";
			echo $this->getHelp();
			exit();
		}
		$fname = $args[0];

		if (!file_exists($fname) || !is_readable($fname)) {
			echo "cannot find/read file " . $fname . "\n\n";
			exit();
		}

		$header = null;

		$patient_count = 0;
		$diagnosis_count = 0;
		$failed_patients = array();
		$invalid_disorders = array();
		foreach (file($fname) as $idx => $line) {
			$data = str_getcsv($line, ',', '"');
			if (!$header) {
				$header = $data;
				continue;
			}
			if (!strlen(trim($line))) {
				// skip empty line
				continue;
			}

			if (sizeof($data) != 4) {
				echo "ERROR: " . $idx . " not enough data";
			}
			$patient_count++;
			try {
				if ($this->processPatient($data[0], $data[1], $data[2], $data[3])) {
					$diagnosis_count++;
				}
			}
			catch (PatientCreationException $e) {
				$failed_patients[] = $idx;
			}
			catch (InvalidDisorderException $e) {
				$invalid_disorders[] = $e->disorder_id;
			}
		}

		if (count($failed_patients)) {
			echo "FAILED to create some patients: ";
			echo implode(", ", array_slice($failed_patients, 0, 5));
			if ($left_over = count($failed_patients) - 5 && $left_over > 0) {
				echo " ... (" . $left_over . " more)";
			}
			echo "\n";
		}
		if (count($invalid_disorders)) {
			echo "Invalid disorders: ";
			echo implode(",", array_unique($invalid_disorders)) . "\n";
			echo "\n";
		}
		echo $patient_count . " rows processed\n";
		echo $patient_count - $diagnosis_count . " already had diagnosis set\n";
	}

	protected $disorder_cache = array();

	/**
	 * cache of disorders
	 *
	 * @param $snomed
	 * @return CActiveRecord
	 * @throws InvalidDisorderException
	 */
	protected function getDisorder($snomed)
	{
		if (!isset($this->disorder_cache[$snomed])) {
			if (!$disorder = Disorder::model()->findByPk($snomed)) {
				$e = new InvalidDisorderException();
				$e->disorder_id = $snomed;
				throw $e;
			}
			if (!$disorder->systemic) {
				echo "WARN: non systemic disorder: " . $snomed . ", " . $disorder->term;
			}
			$this->disorder_cache[$snomed] = $disorder;
		}
		return $this->disorder_cache[$snomed] = $disorder;
	}

	/**
	 * set a diagnosis on a patient for the given parameters, creating pas link if needed (i.e. they've not yet been
	 * pulled across from PAS) returns true if the diagnosis is added, false if it wasn't necessary (already set)
	 *
	 * @param $hos_num
	 * @param $pas_key
	 * @param $snomed
	 * @param null $date
	 * @return bool
	 * @throws PatientCreationException
	 */
	protected function processPatient($hos_num, $pas_key, $snomed, $date=null)
	{
		if (!$patient = Patient::model()->noPas()->find('hos_num = ?', array($hos_num))) {
			// create a new blank patient that the PAS can populate at a later stage
			$transaction = Yii::app()->db->beginTransaction();
			try {
				$patient = new Patient();
				$patient->pas_key = $pas_key;
				$patient->hos_num = $hos_num;

				$contact = new Contact();
				$contact->save();
				$patient->contact_id = $contact->id;
				$patient->save();
				$assignment = new PasAssignment();
				$assignment->external_id = $pas_key;
				$assignment->external_type = 'PAS_Patient';
				$assignment->internal_type = 'Patient';
				$assignment->internal_id = $patient->id;
				$assignment->save();

				$transaction->commit();
			}
			catch (Exception $e) {
				$transaction->rollback();
				throw new PatientCreationException();
			}
		}

		$psds = $patient->secondarydiagnoses;
		if (count($psds)) {
			$psd_ids = array();
			foreach ($psds as $psd) {
				$psd_ids[] = $psd->id;
			}
			if (in_array($snomed, $psd_ids)) {
				return false;
			}
			if ($this::TREE_CHECK) {
				// get the disorder object for this snomed
				$disorder = $this->getDisorder($snomed);

				// check if any of psd are parents or (grand)children of the disorder
				if ($disorder->ancestorOfIds($psd_ids)
					|| array_intersect($psd_ids, $disorder->descendentIds()) ) {
					return false;
				}

			}
		}

		$sd = new SecondaryDiagnosis();
		$sd->patient_id = $patient->id;
		$sd->disorder_id = $snomed;

		if ($this::USE_DATE) {
			// TODO: set the date value on the secondary diagnosis
			throw new Exception("Date code not implemented yet");
		}

		$sd->save();
	}
}

class PatientCreationException extends Exception
{

}

class InvalidDisorderException extends Exception
{
	public $disorder_id = null;

}