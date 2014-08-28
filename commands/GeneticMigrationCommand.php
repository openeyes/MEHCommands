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

class GeneticMigrationCommand extends CConsoleCommand {
	public $matched = 0;
	public $matched_hosnum = 0;
	public $nomatch = 0;
	public $nomatch_hosnum = 0;
	public $matched_n = 0;
	public $matched_n_hosnum = 0;
	public $fp_matched;
	public $fp_matched_hosnum;
	public $fp_nomatch;
	public $fp_nomatch_hosnum;
	public $fp_matched_n;
	public $fp_matched_n_hosnum;

	public function run($args) {
		Yii::import('application.modules.Genetics.models.*');

		$this->fp_matched = fopen("/tmp/.matched","w");
		$this->fp_matched_hosnum = fopen("/tmp/.matched_hosnum","w");
		$this->fp_nomatch = fopen("/tmp/.nomatch","w");
		$this->fp_nomatch_hosnum = fopen("/tmp/.nomatch_hosnum","w");
		$this->fp_matched_n = fopen("/tmp/.matched_n","w");
		$this->fp_matched_n_hosnum = fopen("/tmp/.matched_n_hosnum","w");

		if (!file_exists("/tmp/Diagnoses.csv")) {
			throw new Exception("File not found: /tmp/Diagnoses.csv");
		}

		$diagnosis_map = array();

		$fp = fopen("/tmp/Diagnoses.csv","r");

		while ($data = fgetcsv($fp)) {
			if ($data[1]) {
				$diagnosis_map[$data[0]] = $data[1];
			}
		}

		fclose($fp);

		$missing_diagnoses = array();

		echo "Importing genes: ";

		foreach (Yii::app()->db2->createCommand()->select("*")->from("genelist")->queryAll() as $gene) {
			if (!$_gene = PedigreeGene::model()->findByPk($gene['geneid'])) {
				$_gene = new PedigreeGene;
				$_gene->id = $gene['geneid'];
			}

			$_gene->name = $gene['gene'];
			$_gene->location = $gene['location'];
			if($_gene->location == null) $_gene->location = '';
			$_gene->priority = $gene['priority'];
			$_gene->description = $gene['descritption'];
			if($_gene->description == null) $_gene->description = '';
			$_gene->details = $gene['details'];
			if($_gene->details == null) $_gene->details = '';
			$_gene->refs = $gene['refs'];
			if($_gene->refs == null) $_gene->refs = '';

			if (!$_gene->save(false)) {
				throw new Exception("Unable to save gene: ".print_r($_gene->getErrors(),true));
			}

			echo ".";
		}

		echo "\n";

		echo "Importing pedigrees: ";

		foreach (Yii::app()->db2->createCommand()->select("*")->from("pedigree")->queryAll() as $pedigree) {
			if (!$_pedigree = Pedigree::model()->findByPk($pedigree['newgc'])) {
				$_pedigree = new Pedigree;
				$_pedigree->id = $pedigree['newgc'];
			}

			if ($pedigree['Inheritance']) {
				if (!$inheritance = PedigreeInheritance::model()->find('name=?',array($pedigree['Inheritance']))) {
					$inheritance = new PedigreeInheritance;
					$inheritance->name = $pedigree['Inheritance'];

					if (!$inheritance->save()) {
						throw new Exception("Unable to save inheritance: ".print_r($inheritance->getErrors(),true));
					}
				}
				$inheritance_id = $inheritance->id;
			} else {
				$inheritance_id = null;
			}

			if (!$pedigree['lastupdatedby'] || !$user = User::model()->find('lower(concat(first_name," ",last_name)) = ?',array($pedigree['lastupdatedby']))) {
				$user = User::model()->findByPk(1);
			}

			if ($pedigree['diagnosis'] == 'Not known') {
				$disorder_id = null;
			} else {
				if (isset($diagnosis_map[$pedigree['diagnosis']])) {
					$pedigree['diagnosis'] = $diagnosis_map[$pedigree['diagnosis']];
				}

				if (!$disorder = Disorder::model()->find('lower(term) = ?',array(strtolower($pedigree['diagnosis'])))) {
					if (!in_array($pedigree['diagnosis'],$missing_diagnoses)) {
						$missing_diagnoses[] = $pedigree['diagnosis'];
					}
					echo " missing ".$pedigree['diagnosis'];
					echo var_export($missing_diagnoses);
					continue;
				}

				$disorder_id = $disorder->id;
			}

			$_pedigree->inheritance_id = $inheritance_id;
			$_pedigree->comments = utf8_encode($pedigree['FreeText']);
			if($_pedigree->comments == null) $_pedigree->comments = '';
			$_pedigree->consanguinity = $pedigree['consanguinity'] == 'Y' ? 1 : 0;
			$_pedigree->gene_id = $pedigree['geneid'];
			$_pedigree->base_change = $pedigree['basechange'];
			if($_pedigree->base_change == null) $_pedigree->base_change = '';
			$_pedigree->amino_acid_change = $pedigree['aminoacidchange'];
			if($_pedigree->amino_acid_change==null)  $_pedigree->amino_acid_change='';
			$_pedigree->last_modified_user_id = $user->id;
			$_pedigree->last_modified_date = $pedigree['timestamp'];
			$_pedigree->created_user_id = $user->id;
			$_pedigree->created_date = $pedigree['timestamp'];
			$_pedigree->disorder_id = $disorder_id;

			if (!$_pedigree->save()) {
				throw new Exception("Unable to save pedigree: ".print_r($_pedigree->getErrors(),true));
			}

			echo ".";
		}

		echo "\n";

		$ophthalmology = Specialty::model()->find('code=?',array(130));

		if (!$genetics = Subspecialty::model()->find('specialty_id=? and name=?',array($ophthalmology->id,'Genetics'))) {
			$genetics = new Subspecialty;
			$genetics->specialty_id = $ophthalmology->id;
			$genetics->name = 'Genetics';
			$genetics->ref_spec = 'GE';

			if (!$genetics->save()) {
				throw new Exception("Unable to save subspecialty: ".print_r($genetics->getErrors(),true));
			}
		}

		if (!$service = Service::model()->find('name=?',array('Genetics Service'))) {
			$service = new Service;
			$service->name = 'Genetics Service';

			if (!$service->save()) {
				throw new Exception("Unable to save service: ".print_r($service->getErrors(),true));
			}
		}

		if (!$ssa = ServiceSubspecialtyAssignment::model()->find('service_id=? and subspecialty_id=?',array($service->id,$genetics->id))) {
			$ssa = new ServiceSubspecialtyAssignment;
			$ssa->service_id = $service->id;
			$ssa->subspecialty_id = $genetics->id;

			if (!$ssa->save()) {
				throw new Exception("Unable to save ssa: ".print_r($ssa->getErrors(),true));
			}
		}

		if (!$firm = Firm::model()->find('service_subspecialty_assignment_id=? and name=?',array($ssa->id,'Webster Andrew'))) {
			$firm = new Firm;
			$firm->service_subspecialty_assignment_id = $ssa->id;
			$firm->name = 'Webster Andrew';

			if (!$firm->save()) {
				throw new Exception("Unable to save firm: ".print_r($firm->getErrors(),true));
			}
		}

		$et_sample = EventType::model()->find('class_name=?',array('OphInBloodsample'));
		$et_dna = EventType::model()->find('class_name=?',array('OphInDnaextraction'));
		$et_genetictest = EventType::model()->find('class_name=?',array('OphInGenetictest'));

		echo "Importing subjects and samples: ";

		foreach (Yii::app()->db2->createCommand()->select("*")->from("subject")->queryAll() as $i => $subject) {
			if (!$subject['forename']) {
				$subject['forename'] = $subject['initial'];
			}

			if ($patient = $this->getPatient($subject)) {
				if (Pedigree::model()->findByPk($subject['newgc'])) {
					if ($subject['status'] == null) {
						$subject['status'] = 'Unknown';
					}

					$status = PedigreeStatus::model()->find('lower(name) = ?',array(strtolower($subject['status'])));

					if (!$pp = PatientPedigree::model()->find('patient_id=?',array($patient->id))) {
						$pp = new PatientPedigree;
						$pp->patient_id = $patient->id;
					}

					$pp->status_id = $status->id;
					$pp->pedigree_id = $subject['newgc'];

					if ($subject_extra = Yii::app()->db2->createCommand()->select("*")->from("subjectextra")->where("SubjectID = :subjectid",array(":subjectid" => $subject['subjectid']))->queryRow()) {
						if (trim($subject_extra['Free_text'])) {
							$pp->comments = trim($subject_extra['Free_text']);
						}
					}

					if($pp->comments == null) $pp->comments='';
					$pp->comments = utf8_encode($pp->comments);

					if (!$pp->save()) {
						throw new Exception("Unable to save PatientPedigree: ".print_r($pp->getErrors(),true));
					}
				}
			} else {
				$patient = $this->createPatient($subject);
				//echo "p";
			}

			if ($i %10 == 0) {
				echo ".";
			}

			foreach (Yii::app()->db2->createCommand()->select("*")->from("diagnosis")->where("subjectid = :subjectid",array(":subjectid" => $subject['subjectid']))->queryAll() as $diagnosis) {
				if (isset($diagnosis_map[$diagnosis['diagnosis']])) {
					$diagnosis['diagnosis'] = $diagnosis_map[$diagnosis['diagnosis']];
				}

				if (!$disorder = Disorder::model()->find('lower(term) = ?',array(strtolower($diagnosis['diagnosis'])))) {
					if (!in_array($diagnosis['diagnosis'],$missing_diagnoses)) {
						$missing_diagnoses[] = $diagnosis['diagnosis'];
						$ppn = PatientPedigree::model()->find('patient_id=?',array($patient->id));
						$ppn->comments += "{Missing SNOMED on import for diagnosis: ". $diagnosis['diagnosis'].'}';
						$ppn->save();
						echo '\nMissing diagnosis for patient '.$patient->id.' comments saved\n';
					}
					echo " missing ".$pedigree['diagnosis'];
					echo var_export($missing_diagnoses);
					continue;
				}

				if (!$sd = SecondaryDiagnosis::model()->find('patient_id=? and disorder_id=?',array($patient->id,$disorder->id))) {
					$sd = new SecondaryDiagnosis;
					$sd->patient_id = $patient->id;
					$sd->disorder_id = $disorder->id;
					$sd->date='';

					if (!$sd->save()) {
						throw new Exception("Unable to save SecondaryDiagnosis: ".print_r($sd->getErrors(),true));
					}
				}
			}

			$samples = Yii::app()->db2->createCommand()->select("*")->from("sample")->where("subjectid = :subjectid",array(":subjectid" => $subject['subjectid']))->queryAll();

			if (!empty($samples)) {
				$date = date('Y-m-d');

				foreach ($samples as $sample) {
					if (in_array(strtolower($sample['type']),array('dna','rna'))) {
						$type = strtoupper($sample['type']);
					} else {
						$type = ucfirst(strtolower($sample['type']));
					}

					if (!$_type = OphInBloodsample_Sample_Type::model()->find('name=?',array($type))) {
						throw new Exception("Unknown sample type: $type");
					}

					$user_id = $this->findUserIDForString($sample['loggedby']);

					if (!$_sample = Element_OphInBloodsample_Sample::model()->findByPk($sample['dnano'])) {
						$_sample = new Element_OphInBloodsample_Sample;
						$_sample->id = $sample['dnano'];

						$event = $this->createEvent($et_sample, $patient, $firm, $sample, $user_id, 'timelogged');

						$_sample->event_id = $event->id;
					}

					$_sample->old_dna_no = $sample['OldDNANo'];
					$_sample->blood_date = $sample['bloodtaken'];
					$_sample->blood_location = $sample['bloodlocation'];
					$_sample->comments = $sample['comment'];
					if($_sample->comments == null) $_sample->comments = '';
					$_sample->type_id = $_type->id;
					$_sample->volume = 10;
					$_sample->created_date = $sample['timelogged'];
					$_sample->last_modified_date = $sample['timelogged'];
					$_sample->created_user_id = $user_id;
					$_sample->last_modified_user_id = $user_id;

					if (!$_sample->save(true,null,true)) {
						throw new Exception("Unable to save sample: ".print_r($_sample->getErrors(),true));
					}

					foreach (Yii::app()->db2->createCommand()->select("*")->from("address")->where("dnano = :dnano",array(":dnano" => $sample['dnano']))->queryAll() as $address) {
						$box = OphInDnaextraction_DnaExtraction_Box::model()->find('value=?',array($address['box']));
						$letter = OphInDnaextraction_DnaExtraction_Letter::model()->find('value=?',array($address['letter']));
						$number = OphInDnaextraction_DnaExtraction_Number::model()->find('value=?',array($address['number']));

						$user_id = $this->findUserIDForString($address['extractedby']);

						if (!$dna = Element_OphInDnaextraction_DnaExtraction::model()->find('box_id=? and letter_id=? and number_id=?',array($box->id,$letter->id,$number->id))) {
							$dna = new Element_OphInDnaextraction_DnaExtraction;
							$dna->box_id = $box->id;
							$dna->letter_id = $letter->id;
							$dna->number_id = $number->id;

							$event = $this->createEvent($et_dna, $patient, $firm, $sample, $user_id, 'timelogged');

							$dna->event_id = $event->id;
						}

						$dna->orientry = $address['orientry'];
						$dna->extracted_date = $address['extracted'];
						$dna->extracted_by = $address['extractedby'];
						$dna->comments = $address['comment'];

						if (!$dna->save()) {
							throw new Exception("Unable to save dna extraction: ".print_r($dna->getErrors(),true));
						}

						echo "-";
					}
				}

				echo ".";
			}

			$assays = Yii::app()->db2->createCommand()->select("*")->from("assay")->where("subjectid = :subjectid",array(":subjectid" => $subject['subjectid']))->queryAll();

			if (!empty($assays)) {
				foreach ($assays as $assay) {
					if (!$test = Element_OphInGenetictest_Test::model()->findByPk($assay['testid'])) {
						if ($assay['method'] === null) {
							$method_id = null;
						} else {
							$method_id = OphInGenetictest_Test_Method::model()->find('name=?',array($assay['method']))->id;
						}

						if ($assay['effect'] === null) {
							$effect_id = null;
						} else {
							$effect_id = OphInGenetictest_Test_Effect::model()->find('name=?',array($assay['effect']))->id;
						}

						if ($gene = PedigreeGene::model()->findByPk($assay['geneid'])) {
							$gene_id = $gene->id;
						} else {
							$gene_id = new CDbExpression('NULL');
						}

						$user_id = $this->findUserIDForString($assay['enteredby']);

						$event = $this->createEvent($et_genetictest, $patient, $firm, $assay, $user_id, 'timestamp');

						$test = new Element_OphInGenetictest_Test;
						$test->id = $assay['testid'];
						$test->event_id = $event->id;
						$test->gene_id = $gene_id;
						$test->method_id = $method_id;
						$test->result = $assay['result'];
						$test->result_date = $assay['resultdate'];
						$test->comments = $assay['comment'];
						$test->exon = $assay['exon'];
						$test->prime_rf = $assay['primerf'];
						$test->prime_rr = $assay['primerr'];
						$test->base_change = $assay['basechange'];
						$test->amino_acid_change = $assay['aminoacidchange'];
						$test->assay = $assay['assay'];
						$test->homo = $assay['homo'] == 'Y' ? 1 : 0;
						$test->created_user_id = $user_id;
						$test->last_modified_user_id = $user_id;
						$test->created_date = $assay['timestamp'];
						$test->last_modified_date = $assay['timestamp'];

						if (!$test->save(true,null,true)) {
							throw new Exception("Unable to save Element_OphInGenetictest_Test: ".print_r($test->getErrors(),true));
						}
					}
				}
			}
		}

		echo "\n";

		echo "Total: ".($this->matched + $this->matched_hosnum + $this->nomatch + $this->nomatch_hosnum + $this->matched_n)."\n";
		echo "Matched (with hosnum): $this->matched_hosnum\n";
		echo "Matched (without hosnum): $this->matched\n";
		echo "No-match (with hosnum): $this->nomatch_hosnum\n";
		echo "No-match (without hosnum): $this->nomatch\n";
		echo "Matched n (with hosnum): $this->matched_n\n";
		echo "Matched n (without hosnum): $this->matched_n_hosnum\n";

		echo var_export($missing_diagnoses);

		echo "\n";
	}

	public function findUserIDForString($user_name)
	{
		$user_id = 1;

		if ($user_name) {
			if ($user = User::model()->find('lower(concat(title," ",first_name," ",last_name)) = ?',array(strtolower($user_name)))) {
				$user_id = $user->id;
			} else if ($user = User::model()->find('lower(concat(first_name," ",last_name)) = ?',array(strtolower($user_name)))) {
				$user_id = $user->id;
			}
		}

		return $user_id;
	}

	public function createEvent($event_type, $patient, $firm, $object, $user_id, $timeField=false)
	{
		if ($timeField) {
			$date = date('Y-m-d');

			if (strtotime($object[$timeField]) < strtotime($date)) {
				$date = substr($object[$timeField],0,10);
				$created_date = $object[$timeField];
			}

			if ($date=='0000-00-00' || $date == date('Y-m-d')) {
				$date = '1970-01-01';
				$created_date = '1970-01-01';
			}

		} else {
			$date = date('Y-m-d');
			$created_date = date('Y-m-d');
		}

		if (!$episode = Episode::model()->find('patient_id=? and firm_id=? and end_date is null',array($patient->id,$firm->id))) {
			$episode = new Episode;
			$episode->patient_id = $patient->id;
			$episode->firm_id = $firm->id;
			$episode->start_date = $date;
			$episode->created_user_id = $user_id;
			$episode->last_modified_user_id = $user_id;

			if (!$episode->save(true,null,true)) {
				throw new Exception("Unable to save episode: ".print_r($episode->getErrors(),true));
			}
		}

		$event = new Event;
		$event->event_type_id = $event_type->id;
		$event->episode_id = $episode->id;
		if (isset($created_date)) {
			$event->created_date = $created_date;
			$event->event_date = $created_date;
			$event->last_modified_date = $created_date;
		}
		$event->created_user_id = $user_id;
		$event->last_modified_user_id = $user_id;
		$event->delete_pending = 0;

		if (!$event->save(true,null,true)) {
			echo var_export($created_date);
			echo var_export($event);
			echo var_export($object);
			throw new Exception("Unable to save event: ".print_r($event->getErrors(),true));
		}

		return $event;
	}

	public function createPatient($subject) {
		$contact = new Contact;
		$contact->first_name = $subject['forename'];
		if($contact->first_name==null)$contact->first_name='';
		$contact->last_name = $subject['surname'];
		if($contact->last_name == null )$contact->last_name ='';
		$contact->maiden_name = $subject['maiden'];

		if (!$contact->save()) {
			throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
		}

		$patient = new Patient;
		$patient->dob = $subject['dob'];
		$patient->gender = !empty($subject['gender']) ? $subject['gender'][0] : '';
		$patient->contact_id = $contact->id;
		$patient->yob = $subject['yob'];

		if (!$patient->save()) {
			throw new Exception("Unable to save patient: ".print_r($patient->getErrors(),true));
		}

		if ($subject['status'] === null) {
			$subject['status'] = 'Unknown';
		}

		$status = PedigreeStatus::model()->find('lower(name) = ?',array(strtolower($subject['status'])));

		if (Pedigree::model()->findByPk($subject['newgc'])) {
			$pp = new PatientPedigree;
			$pp->patient_id = $patient->id;
			$pp->pedigree_id = $subject['newgc'];
			$pp->status_id = $status->id;
			$pp->comments='';

			if (!$pp->save()) {
				throw new Exception("Unable to save PatientPedigree: ".print_r($pp->getErrors(),true));
			}
		}

		return $patient;
	}

	public function getPatient($subject) {
		$_GET['sort_by'] = 'HOS_NUM*1';

		if ($subject['mehno']) {
			if ($patient = Patient::model()->with('contact')->find('hos_num = ? and length(hos_num) > ?',array($subject['mehno'],0))) {
				if ($patient->dob == $subject['dob'] || (strtolower($patient->first_name) == strtolower($subject['forename']) && strtolower($patient->last_name) == strtolower($subject['surname']))) {
					fwrite($this->fp_matched_hosnum,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->matched_hosnum++;
					return $patient;
				}
				fwrite($this->fp_nomatch_hosnum,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
				$this->nomatch_hosnum++;
				return false;
			}
		}

		if ($subject['dob']) {
			if ($patient = Patient::model()->with('contact')->find('lower(first_name) = ? and lower(last_name) = ? and dob = ? and length(hos_num) > ?',array(strtolower($subject['forename']),strtolower($subject['surname']),$subject['dob'],0))) {
				if ($subject['mehno']) {
					fwrite($this->fp_matched_hosnum,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->matched_hosnum++;
				} else {
					fwrite($this->fp_matched,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->matched++;
				}
				return $patient;
			}

			$patient = new Patient;

			$dataProvider = $patient->search(array(
				'currentPage' => 1,
				'pageSize' => 30,
				'sortBy' => 'HOS_NUM*1',
				'sortDir' => 'asc',
				'first_name' => CHtml::decode($subject['forename']),
				'last_name' => CHtml::decode($subject['surname']),
			));

			$results = array();

			foreach ($dataProvider->getData() as $patient) {
				if ($patient->dob == $subject['dob']) {
					$results[] = $patient;
				}
			}

			if (count($results) == 1) {
				if ($subject['mehno']) {
					fwrite($this->fp_matched_hosnum,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->matched_hosnum++;
				} else {
					fwrite($this->fp_matched,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->matched++;
				}
				return $results[0];
			} else if (count($results) >1) {
				if ($subject['mehno']) {
					fwrite($this->fp_matched_n_hosnum,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->matched_n_hosnum++;
				} else {
					fwrite($this->fp_matched_n,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->matched_n++;
				}
			} else {
				if ($subject['mehno']) {
					fwrite($this->fp_nomatch_hosnum,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->nomatch_hosnum++;
				} else {
					fwrite($this->fp_nomatch,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
					$this->nomatch++;
				}
			}

			return Patient::model()->noPas()->with('contact')->find('lower(first_name) = ? and lower(last_name) = ? and length(hos_num) = ?',array(strtolower($subject['forename']),strtolower($subject['surname']),0));
		}

		$patient = new Patient;

		$dataProvider = $patient->search(array(
			'currentPage' => 1,
			'pageSize' => 30,
			'sortBy' => 'HOS_NUM*1',
			'sortDir' => 'asc',
			'first_name' => CHtml::decode($subject['forename']),
			'last_name' => CHtml::decode($subject['surname']),
		));

		$results = array();

		foreach ($dataProvider->getData() as $patient) {
			$results[] = $patient;
		}

		if (count($results) == 1) {
			if ($subject['mehno']) {
				fwrite($this->fp_matched_hosnum,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
				$this->matched_hosnum++;
			} else {
				fwrite($this->fp_matched,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
				$this->matched++;
			}
			return $results[0];
		}

		if ($subject['mehno']) {
			fwrite($this->fp_nomatch_hosnum,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
			$this->nomatch_hosnum++;
		} else {
			fwrite($this->fp_nomatch,"{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}\n");
			$this->nomatch++;
		}

		return Patient::model()->noPas()->with('contact')->find('lower(first_name) = ? and lower(last_name) = ? and length(hos_num) = ?',array(strtolower($subject['forename']),strtolower($subject['surname']),0));
	}
}
