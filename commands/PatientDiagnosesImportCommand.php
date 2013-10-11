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
	// if a patient has a disorder in one, and the the disorder provided is in one of the other
	// trees of this disorder id, then throw an error as they clash.
	protected $distinct_disorders = array(44054006, 46635009);

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
		$clashing_patients = array();
		foreach (file($fname) as $idx => $line) {
			if ($patient_count % 100 == 0) {
				echo ".";
			}
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
			catch (DisorderClashException $e) {
				$clashing_patients[] = $e->hos_num;
			}
		}
		$error_count = count($failed_patients) + count($invalid_disorders) + count($clashing_patients);
		echo "\n";
		if (count($failed_patients)) {
			echo "FAILED to create some patients: ";
			echo implode(", ", array_slice($failed_patients, 0, 5));
			if (count($failed_patients) - 5 > 0) {
				echo " ... (" . count($failed_patients) - 5 . " more)";
			}
			echo "\n";
		}
		if (count($invalid_disorders)) {
			echo "Invalid disorders: ";
			echo implode(",", array_unique($invalid_disorders)) . "\n";
			echo "\n";
		}
		if (count($clashing_patients)) {
			echo count($clashing_patients) . " Patients with clashing disorders: ";
			echo implode(",", array_unique($clashing_patients)) . "\n";
			echo "\n";
		}
		echo $patient_count . " rows processed\n";
		echo $patient_count - $error_count - $diagnosis_count . " already had diagnosis set\n";
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
		return $this->disorder_cache[$snomed];
	}

	/**
	 * set a diagnosis on a patient for the given parameters, creating pas link if needed (i.e. they've not yet been
	 * pulled across from PAS) returns true if the diagnosis is added, false if it wasn't necessary (already set)
	 *
	 * @param $hos_num
	 * @param $pas_key
	 * @param $snomed
	 * @param null $date
	 * @throws PatientCreationException
	 * @throws DisorderClashException
	 * @throws Exception
	 * @return bool
	 */
	protected function processPatient($hos_num, $pas_key, $snomed, $date=null)
	{
		$hos_num = sprintf('%07s',$hos_num);

		if (!$patient = Patient::model()->noPas()->with('secondarydiagnoses')->find('hos_num = ?', array($hos_num))) {
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
				// set the assignment to stale to ensure that it is updated when patient first viewed.
				$assignment->created_date = date('Y-m-d H:i:s');
				$assignment->last_modified_date = '1970-01-01 00:00:00';
				$assignment->save(true, null, true);

				$transaction->commit();
			}
			catch (Exception $e) {
				$transaction->rollback();
				throw $e;
				throw new PatientCreationException();
			}
		}

		$psds = $patient->secondarydiagnoses;
		if (count($psds)) {
			$psd_ids = array();
			foreach ($psds as $psd) {
				$psd_ids[] = $psd->disorder_id;
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

				foreach ($this->distinct_disorders as $distinct) {
					if ($distinct != $snomed) {
						$distinct_disorder = $this->getDisorder($distinct);
						if (in_array($distinct, $psd_ids)
							|| array_intersect($psd_ids, $distinct_disorder->descendentIds())) {
							$e = new DisorderClashException();
							$e->hos_num = $hos_num;
							throw $e;
						}
					}
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

		return $sd->save();
	}
}

class PatientCreationException extends Exception
{

}

class InvalidDisorderException extends Exception
{
	public $disorder_id = null;

}

class DisorderClashException extends Exception
{
	public $hos_num = null;
}
