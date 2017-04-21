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
class GeneticMigrationCommand extends CConsoleCommand
{
    public $matched = 0;
    public $matched_hosnum = 0;
    public $nomatch = 0;
    public $nomatch_hosnum = 0;
    public $matched_n = 0;
    public $matched_n_hosnum = 0;
    public $created_patients = 0;
    /**
     * Number of patient found by genetics_patient PK (which is same as iedd.sample.subjectid)
     * @var int
     */
    public $found_by_pk = 0;

    // File handle variables for writing out results during import
    public $fp_matched;
    public $fp_matched_hosnum;
    public $fp_nomatch;
    public $fp_nomatch_hosnum;
    public $fp_matched_n;
    public $fp_matched_n_hosnum;

    /**
     *
     * @var
     */
    public $fp_found_by_pk;

    /**
     * General log
     * @var
     */
    public $fp_general_logfile;

    public $fp_verbose_log_file;

    protected $source;
    protected $unknown_effect;
    protected $unknown_method;

    public $subject_limit;
    public $subject_offset;

    public function getName()
    {
        return 'Import Genetics Data from the IEDD';
    }

    public function getHelp()
    {
        return <<<EOH
Perform the import of genetic data from the IEDD database.
1) IEDD DB connection must be configued as db2
2) A Diagnosis map file is required to indicate what diagnoses should be recorded for a patient based on the values in IEDD.
3) Will output a series of logfiles to /tmp regarding the matching with patients in the OpenEyes instance you are importing to.
EOH;
    }

    protected $diagnosis_map;
    protected $missing_diagnoses = array();

    /**
     * @param string $file
     * @throws Exception
     */
    protected function initialiseDiagnosisMap($file = "/tmp/Diagnoses.csv")
    {
        if (!file_exists($file)) {
            throw new Exception("File not found: {$file}");
        }
        $this->diagnosis_map = array();

        $fp = fopen($file, "r");

        while ($data = fgetcsv($fp)) {
            if ($data[1]) {
                if ($disorder = Disorder::model()->findByPk($data[1])) {
                    $this->diagnosis_map[$data[0]] = $disorder;
                } else {
                    throw new Exception('Mapped diagnosis not found for ' . print_r($data, true));
                }
            }
        }

        fclose($fp);
    }

    /**
     * Returns the firm under which all imported records should be stored.
     *
     * @return \Firm
     * @throws Exception
     */
    protected function initialiseFirm()
    {
        echo "Adding specialty" . PHP_EOL;

        $ophthalmology = Specialty::model()->find('code=?', array(130));

        if (!$genetics = Subspecialty::model()->find('specialty_id=? and name=?', array($ophthalmology->id, 'Genetics'))) {
            $genetics = new Subspecialty();
            $genetics->specialty_id = $ophthalmology->id;
            $genetics->name = 'Genetics';
            $genetics->ref_spec = 'GE';

            if (!$genetics->save()) {
                throw new Exception("Unable to save subspecialty: " . print_r($genetics->getErrors(), true));
            }
        }

        if (!$service = Service::model()->find('name=?', array('Genetics Service'))) {
            $service = new Service();
            $service->name = 'Genetics Service';

            if (!$service->save()) {
                throw new Exception("Unable to save service: " . print_r($service->getErrors(), true));
            }
        }

        if (!$ssa = ServiceSubspecialtyAssignment::model()->find('service_id=? and subspecialty_id=?', array($service->id, $genetics->id))) {
            $ssa = new ServiceSubspecialtyAssignment();
            $ssa->service_id = $service->id;
            $ssa->subspecialty_id = $genetics->id;

            if (!$ssa->save()) {
                throw new Exception("Unable to save ssa: " . print_r($ssa->getErrors(), true));
            }
        }

        if (!$firm = Firm::model()->find('service_subspecialty_assignment_id=? and name=?', array($ssa->id, 'Webster Andrew'))) {
            $firm = new Firm();
            $firm->service_subspecialty_assignment_id = $ssa->id;
            $firm->name = 'Webster Andrew';

            if (!$firm->save()) {
                throw new Exception("Unable to save firm: " . print_r($firm->getErrors(), true));
            }
        }

        return $firm;
    }

    /**
     * @var EventType
     */
    protected $sample_event_type;

    public function getSampleEventType()
    {
        if (!$this->sample_event_type) {
            $this->sample_event_type = EventType::model()->find('class_name=?', array('OphInDnasample'));
        }

        return $this->sample_event_type;
    }

    /**
     * @var EventType
     */
    protected $extraction_event_type;

    public function getExtractionEventType()
    {
        if (!$this->extraction_event_type) {
            $this->extraction_event_type = EventType::model()->find('class_name=?', array('OphInDnaextraction'));
        }

        return $this->extraction_event_type;
    }

    /**
     * @var EventType
     */
    protected $genetic_test_event_type;

    public function getGeneticTestEventType()
    {
        if (!$this->genetic_test_event_type) {
            $this->genetic_test_event_type = EventType::model()->find('class_name=?', array('OphInGeneticresults'));
        }

        return $this->genetic_test_event_type;
    }

    public function actionIndex()
    {
        Yii::import('application.modules.Genetics.models.*');

        // check if directory exists
        if (!is_dir('/tmp/genetics_import_logs')) {
            mkdir('/tmp/genetics_import_logs');
            echo "ALERT! Directory /tmp/genetics_import_logs has been created!";
        }

        $this->fp_general_logfile = fopen("/tmp/genetics_import_logs/.general_genetics_log", "w");
        $this->fp_matched = fopen("/tmp/genetics_import_logs/.matched", "w");
        $this->fp_matched_hosnum = fopen("/tmp/genetics_import_logs/.matched_hosnum", "w");
        $this->fp_nomatch = fopen("/tmp/genetics_import_logs/.nomatch", "w");
        $this->fp_nomatch_hosnum = fopen("/tmp/genetics_import_logs/.nomatch_hosnum", "w");
        $this->fp_matched_n = fopen("/tmp/genetics_import_logs/.matched_n", "w");
        $this->fp_matched_n_hosnum = fopen("/tmp/genetics_import_logs/.matched_n_hosnum", "w");
        $this->fp_found_by_pk = fopen("/tmp/genetics_import_logs/.found_by_pk", "w");
        $this->fp_verbose_log_file = fopen("/tmp/genetics_import_logs/.verbose_log", "w");

        $this->initialiseDiagnosisMap();
        $this->importGenes();
        $this->importPedigrees();
        $this->importDnaExtractionBoxes();
        $this->unknown_effect = $this->unknown(new OphInGeneticresults_Test_Effect());
        $this->unknown_method = $this->unknown(new OphInGeneticresults_Test_Method());

        $firm = $this->initialiseFirm();

        echo "Importing subjects and samples: ";

        $command = Yii::app()->db2->createCommand()->select("*")->from("subject");

        if($this->subject_limit){
            $command->limit($this->subject_limit);
        }

        if($this->subject_offset){
            $command->offset($this->subject_offset);
        }


        $subjects = $command->queryAll();
        $total = count($subjects);
        $this->log("Importing $total subjects and samples");

        foreach ($subjects as $i => $subject) {
            $this->verboseLog("Import subject id: " . $subject['subjectid']);

            if (!$subject['forename']) {
                $subject['forename'] = $subject['initial'];
            }

            $patient_comments = '';
            $genetics_patient = GeneticsPatient::model()->findByPk($subject['subjectid']);

            if($genetics_patient){
                fwrite($this->fp_found_by_pk, "Genetics Patient ID:{$subject['subjectid']}|{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}" . PHP_EOL);
                $this->found_by_pk++;
                $patient = Patient::model()->findByPk($genetics_patient->patient_id);

                $this->verboseLog("Genetics patient found : " . $genetics_patient->id);
                $this->verboseLog("Patient hos_num: " . $patient->hos_num);
            } else {
                $this->verboseLog("Genetics patient not found.");
                $patient = $this->getPatient($subject);
                if (!$patient) {
                    $this->verboseLog("Patient not found, creating patient.");
                    $patient = $this->createPatient($subject);
                }

                $genetics_patient = GeneticsPatient::model()->find('patient_id=?', array($patient->id));
            }

            if (!$genetics_patient) {
                $genetics_patient = new GeneticsPatient();

                $genetics_patient->id = $subject['subjectid'];
                $genetics_patient->patient_id = $patient->id;

                //saving without validation because genetics_patient cannot be saved with empty pedigree
                // and pedigree will be mapped later
                $genetics_patient->save(false);
                $this->verboseLog("Creating Genetics patient. ID: " . $genetics_patient->id);
            }

            $subject_extra = Yii::app()->db2->createCommand()->select("*")->from("subjectextra")->where(
                "SubjectID = :subjectid",
                array(":subjectid" => $subject['subjectid'])
            )->queryRow();

            if ($subject_extra) {
                if (trim($subject_extra['free_text'])) {
                    $patient_comments = trim($subject_extra['free_text']);
                }
            }

            // Ensure the subject comments are only added to the genetics patient if they are not already present
            if ($patient_comments && (strpos($genetics_patient->comments, $patient_comments) == false)) {
                $genetics_patient->comments .= $patient_comments;
                $this->verboseLog("Comment added : " . $genetics_patient->comments);
            }

            //creating GeneticsPatientPedigree
            $this->mapGeneticsPatientToPedigree($genetics_patient, $subject);

            //no validation as without pedigree we wouldn't be able
            if( !$genetics_patient->save()) {
                throw new Exception("Cannot save GeneticsPatient: " . print_r($genetics_patient->getErrors(), true));
            }

            // progress indicator.
            if ($i % 10 == 0) {
                echo ".";
            }

            $this->mapGeneticsPatientDiagnoses($genetics_patient, $subject['subjectid']);
            $this->mapGeneticsPatientSamples($genetics_patient, $subject['subjectid'], $firm);
            $this->mapGeneticsPatientTests($genetics_patient, $subject['subjectid'], $firm);

            $this->verboseLog("Subject imported." . PHP_EOL . PHP_EOL);
        }

        echo $stat = PHP_EOL .
            "Total matched: " . ($this->matched + $this->matched_hosnum + $this->nomatch + $this->nomatch_hosnum + $this->matched_n + $this->found_by_pk) . PHP_EOL .
            "Matched (with hosnum): $this->matched_hosnum" . PHP_EOL .
            "Matched (without hosnum): $this->matched" . PHP_EOL .
            "No-match (with hosnum): $this->nomatch_hosnum" . PHP_EOL .
            "No-match (without hosnum): $this->nomatch" . PHP_EOL .
            "Matched n (with hosnum): $this->matched_n" . PHP_EOL .
            "Matched n (without hosnum): $this->matched_n_hosnum" . PHP_EOL .
            "Matched PK (genetics_patient.id which is same as iedd.sample.sampleid): $this->found_by_pk" . PHP_EOL . PHP_EOL .
            "Total created patients: " . $this->created_patients;

        echo "Missing Diagnoses:" . PHP_EOL;
        echo var_export($this->missing_diagnoses);

        $this->log($stat);
        $this->log("Missing Diagnoses:" . PHP_EOL . (var_export($this->missing_diagnoses)));

        echo PHP_EOL;
    }

    public function findUserIDForString($user_name)
    {
        $user_id = 1;

        if ($user_name) {
            if ($user = User::model()->find('lower(concat(title," ",first_name," ",last_name)) = ?', array(strtolower($user_name)))) {
                $user_id = $user->id;
            } else {
                if ($user = User::model()->find('lower(concat(first_name," ",last_name)) = ?', array(strtolower($user_name)))) {
                    $user_id = $user->id;
                }
            }
        }

        $this->verboseLog("User ID to create model/event (created_user_id):" . $user_id);

        return $user_id;
    }

    /**
     * @param      $event_type
     * @param      $patient
     * @param      $firm
     * @param      $object
     * @param      $user_id
     * @param bool $timeField
     * @param null $parent_id
     * @return Event
     * @throws Exception
     */
    public function createEvent($event_type, $patient, $firm, $object, $user_id, $timeField = false, $parent_id = null)
    {
        // drive the event date from the object if possible, otherwise default to today
        $event_date = date('Y-m-d');

        if ($timeField) {
            $obj_date = substr($object[$timeField], 0, 10);
            if ($obj_date != '0000-00-00' && strtotime($object[$timeField]) < strtotime($event_date)) {
                $event_date = $obj_date;
            }
        }

        if (!$episode = Episode::model()->find('patient_id=? and firm_id=? and end_date is null', array($patient->id, $firm->id))) {
            $episode = new Episode();
            $episode->patient_id = $patient->id;
            $episode->firm_id = $firm->id;
            $episode->start_date = $event_date;
            $episode->created_user_id = $user_id;
            $episode->last_modified_user_id = $user_id;

            if (!$episode->save(true, null, true)) {
                throw new Exception("Unable to save episode: " . print_r($episode->getErrors(), true));
            }
        }

        $event = new Event();
        $event->event_type_id = $event_type->id;
        $event->episode_id = $episode->id;
        $event->parent_id = $parent_id;
        $event->event_date = $event_date;

        $event->created_user_id = $user_id;
        $event->last_modified_user_id = $user_id;
        $event->delete_pending = 0;

        if (!$event->save(true, null, true)) {
            echo var_export($event);
            echo var_export($object);
            throw new Exception("Unable to save event: " . print_r($event->getErrors(), true));
        }

        $this->verboseLog("Event created: " . $event->id . " | type: " . $event->eventType->name);
        return $event;
    }

    /**
     * Create a patient record for the given subject record
     *
     * @param $subject
     * @return Patient
     * @throws Exception
     */
    public function createPatient($subject)
    {
        $contact = new Contact();
        $contact->first_name = $subject['forename'];
        if ($contact->first_name == null) {
            $contact->first_name = '-';
        }
        $contact->last_name = $subject['surname'];
        if ($contact->last_name == null) {
            $contact->last_name = '-';
        }
        $contact->maiden_name = $subject['maiden'];

        if (!$contact->save()) {
            throw new Exception("Unable to save contact: " . print_r($contact->getErrors(), true));
        }
        $this->verboseLog("Contact saved. ID : " . $contact->id);

        $patient = new Patient();
        $patient->dob = $subject['dob'];
        $patient->gender = !empty($subject['gender']) ? $subject['gender'][0] : 'U'; // U - Unknown
        $patient->contact_id = $contact->id;
        $patient->use_pas = false;
        
        // not to use pas
        $patient->is_local = 1;
        
        // TODO: implement storage of YOB
        //$patient->yob = $subject['yob'];

        // skipping the validation because of the patient.dob cannot be blank (in the model), but here we don't always have
        if (!$patient->save(false)) {
            throw new Exception("Unable to save patient: " . print_r($patient->getErrors(), true));
        }
        $this->verboseLog("Patient saved. ID : " . $patient->id);
        $this->created_patients++;

        return $patient;
    }

    /**
     * @param GeneticsPatient $patient
     * @param                 $subject associative array of IEDD data for genetic subject
     * @throws Exception
     */
    public function mapGeneticsPatientToPedigree($patient, $subject)
    {
        $this->verboseLog("Map Genetics Patietn To Pedigree");
        if ($subject['status'] === null) {
            $subject['status'] = 'Unknown';
        }

        $status = PedigreeStatus::model()->find('lower(name) = ?', array(strtolower($subject['status'])));

        if (Pedigree::model()->findByPk($subject['newgc'])) {
            $pedigree = new GeneticsPatientPedigree();
            $pedigree->patient_id = $patient->id;
            $pedigree->pedigree_id = $subject['newgc'];
            $pedigree->status_id = $status->id;

            if (!$pedigree->save()) {
                throw new Exception("Unable to save PatientPedigree: " . print_r($pedigree->getErrors(), true));
            }
            $this->verboseLog("Genetics Patient Pedigree saved");
        }
    }

    /**
     * Create genetics diagnosis entries for each diagnosis on the given subject id
     *
     * @param $genetics_patient
     * @param $subject_id
     * @throws Exception
     */
    protected function mapGeneticsPatientDiagnoses($genetics_patient, $subject_id)
    {
        $diagnoses = Yii::app()->db2->createCommand()
            ->select("*")->from("diagnosis")
            ->join('diagnosislist l', 'diagnosis.diagnosis = l.diagnosis')
            ->where("subjectid = :subjectid", array(":subjectid" => $subject_id))->queryAll();

        $this->verboseLog("Diagnoses for subject id in iedd:" . PHP_EOL . print_r($diagnoses, true));
        foreach ($diagnoses as $diagnosis) {
            $disorder = null;

            if (isset($this->diagnosis_map[$diagnosis['diagnosisid']])) {
                $disorder = $this->diagnosis_map[$diagnosis['diagnosisid']];
                $this->verboseLog( $diagnosis['diagnosisid'] . ' is NOT set in diagnosis_map iedd.diagnosisid: ' . $diagnosis['diagnosisid']);
            } else {
                $this->verboseLog( $diagnosis['diagnosisid'] . ' IS SET in diagnosis_map');
                if (!$disorder = Disorder::model()->find('lower(term) = ?', array(strtolower($diagnosis['diagnosis'])))) {

                    $this->verboseLog( $diagnosis['diagnosis'] . ' NOT found in disorder table: ' . strtolower($diagnosis['diagnosis']));

                    if (!in_array($diagnosis['diagnosis'], $this->missing_diagnoses)) {
                        $this->missing_diagnoses[] = $diagnosis['diagnosis'];
                        $this->verboseLog( $diagnosis['diagnosis'] . ' - added to missing diagnoses');
                    }
                    $patient_comments = $diagnosis['diagnosis'];

                    //add comments to patient with missing diagnosis
                    if ($patient_comments && (strpos($genetics_patient->comments, $patient_comments) == false)) {
                        $genetics_patient->comments .= PHP_EOL . $patient_comments . PHP_EOL;
                    }

                    echo "Adding missing diagnosis comments to patient " . $genetics_patient->id . " " . $patient_comments . PHP_EOL;
                    //no validation because of the genetics result's Withdrawal source
                    if (!$genetics_patient->save(false)) {
                        throw new Exception("Unable to save genetics patient comments: " . print_r($genetics_patient->getErrors(), true));
                    }

                    $this->verboseLog( $diagnosis['diagnosis'] . ' added genetics_patient.comments');
                    echo " ... comments saved" . PHP_EOL;
                    $patient_comments = null;
                    $disorder = null;
                    continue;
                }
            }

            if (!$d = GeneticsPatientDiagnosis::model()->find('patient_id=? and disorder_id=?', array($genetics_patient->id, $disorder->id))) {
                $d = new GeneticsPatientDiagnosis;
                $d->patient_id = $genetics_patient->id;
                $d->disorder_id = $disorder->id;

                if (!$d->save()) {
                    throw new Exception("Unable to save GeneticsPatientDiagnosis: " . print_r($d->getErrors(), true));
                }
                $this->verboseLog('Diagnoses added - GeneticsPatientDiagnosis saved | id: ' . $d->id);
            }
        }
        $diagnoses = null;
        $disorder = null;
        $patient_comments = null;
        $genetics_patient = null;
        $d = null;
        $genetics_patient = null;
    }

    /**
     * Performs the matching for Patient against the given subject data
     *
     * @param $subject
     * @return array|mixed|null
     */
    public function getPatient($subject)
    {
        $this->verboseLog("Trying to get patient from other details");

        if ($subject['mehno'] && $subject['dob']) {
            $patient = Patient::model()->with('contact')->find('hos_num = ? and dob = ?', array($subject['mehno'], $subject['dob']) );

            if($patient){
                $this->verboseLog("Patient found by hos_num AND dob");
            }
            return $patient;
        }


        // if only $subject['mehno']
        if ($subject['mehno']) {
            if ($patient = Patient::model()->with('contact')->find('hos_num = ? and length(hos_num) > 0', array($subject['mehno']))) {

                fwrite($this->fp_matched_hosnum, "{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}" . PHP_EOL);
                $this->verboseLog("Patient matched with hos_num");
                $this->matched_hosnum++;

                return $patient;

            } else {
                fwrite($this->fp_nomatch_hosnum, "{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}" . PHP_EOL);
                $this->nomatch_hosnum++;

                $this->verboseLog("No match for Patient with hos_num : " . $subject['mehno']);

                return false;
            }
        }

        // if only $subject['dob'] we check the name as well
        if ($subject['dob']) {

            $patient = Patient::model()->with('contact')->find('lower(first_name) = ? and lower(last_name) = ? and dob = ? and length(hos_num) > 0',
                array(strtolower($subject['forename']), strtolower($subject['surname']), $subject['dob']));

            if ($patient) {

                $this->verboseLog("Patient found by first_name AND last_name AND dob AND length(hos_num) > 0");

                fwrite($this->fp_matched, "{$subject['mehno']}|{$subject['forename']}|{$subject['surname']}" . PHP_EOL);
                $this->matched++;

                return $patient;
            }
        }

        $this->verboseLog("Trying to find patient by forename and surname.");

        $patient = Patient::model()->noPas()->with('contact')->find('lower(first_name) = ? AND lower(last_name) = ? AND hos_num IS NULL AND dob is NULL',
            array(strtolower($subject['forename']), strtolower($subject['surname'])));

        if(!$patient){
            $this->verboseLog("Patient not found by name - WHERE lower(first_name) = ? AND lower(last_name) = ? AND hos_num IS NULL AND dob is NULL");
        }

        return $patient;

    }

    /**
     * @throws Exception
     */
    protected function importGenes()
    {
        echo "Importing genes: ";

        foreach (Yii::app()->db2->createCommand()->select("*")->from("genelist")->queryAll() as $gene) {
            if (!$_gene = PedigreeGene::model()->findByPk($gene['geneid'])) {
                $_gene = new PedigreeGene;
                $_gene->id = $gene['geneid'];
            }

            $_gene->name = $gene['gene'];
            $_gene->location = $gene['location'];
            if ($_gene->location == null) {
                $_gene->location = '';
            }
            $_gene->priority = $gene['priority'];
            $_gene->description = $gene['descritption'];
            if ($_gene->description == null) {
                $_gene->description = '';
            }
            $_gene->details = $gene['details'];
            if ($_gene->details == null) {
                $_gene->details = '';
            }
            $_gene->refs = $gene['refs'];
            if ($_gene->refs == null) {
                $_gene->refs = '';
            }

            if (!$_gene->save(false)) {
                throw new Exception("Unable to save gene: " . print_r($_gene->getErrors(), true));
            }

            echo ".";
        }

        echo PHP_EOL;
        echo "Gene Import Done.";
    }

    public function importPedigrees()
    {
        echo "Importing pedigrees: ";

        foreach (Yii::app()->db2->createCommand()->select("*")->from("pedigree")->queryAll() as $pedigree) {
            if (!$_pedigree = Pedigree::model()->findByPk($pedigree['newgc'])) {
                $_pedigree = new Pedigree;
                $_pedigree->id = $pedigree['newgc'];
            }

            if ($pedigree['Inheritance']) {
                if (!$inheritance = PedigreeInheritance::model()->find('name=?', array($pedigree['Inheritance']))) {
                    $inheritance = new PedigreeInheritance;
                    $inheritance->name = $pedigree['Inheritance'];

                    if (!$inheritance->save()) {
                        throw new Exception("Unable to save inheritance: " . print_r($inheritance->getErrors(), true));
                    }
                }
                $inheritance_id = $inheritance->id;
            } else {
                $inheritance_id = null;
            }

            if (!$pedigree['lastupdatedby'] || !$user = User::model()->find('lower(concat(first_name," ",last_name)) = ?', array($pedigree['lastupdatedby']))) {
                $user = User::model()->findByPk(1);
            }

            $disorder_id = null;

            if ($pedigree['diagnosis'] !== 'Not known') {
                if (isset($this->diagnosis_map[$pedigree['diagnosis']])) {
                    $disorder = $this->diagnosis_map[$pedigree['diagnosis']];
                } else {

                    if (!$disorder = Disorder::model()->find('lower(term) = ?', array(strtolower($pedigree['diagnosis'])))) {
                        if (!in_array($pedigree['diagnosis'], $this->missing_diagnoses)) {
                            $this->missing_diagnoses[] = $pedigree['diagnosis'];
                        }
                        echo " missing " . $pedigree['diagnosis'] . PHP_EOL;
                        continue;
                    }
                }

                $disorder_id = $disorder->id;
            }

            $_pedigree->inheritance_id = $inheritance_id;
            $_pedigree->comments = utf8_encode($pedigree['FreeText']);
            if ($_pedigree->comments == null) {
                $_pedigree->comments = '';
            }
            $_pedigree->consanguinity = $pedigree['consanguinity'] === 'Y' ? 1 : 0;
            $_pedigree->gene_id = $pedigree['geneid'];
            $_pedigree->base_change = $pedigree['basechange'];
            if ($_pedigree->base_change == null) {
                $_pedigree->base_change = '';
            }
            $_pedigree->amino_acid_change = $pedigree['aminoacidchange'];
            if ($_pedigree->amino_acid_change == null) {
                $_pedigree->amino_acid_change = '';
            }
            $_pedigree->last_modified_user_id = $user->id;
            $_pedigree->last_modified_date = $pedigree['timestamp'];
            $_pedigree->created_user_id = $user->id;
            $_pedigree->created_date = $pedigree['timestamp'];
            $_pedigree->disorder_id = $disorder_id;

            if (!$_pedigree->save()) {
                throw new Exception("Unable to save pedigree: " . print_r($_pedigree->getErrors(), true));
            }

            echo ".";
        }

        echo PHP_EOL;
    }

    protected function importDnaExtractionBoxes()
    {
        $addresses = Yii::app()->db2->createCommand()->select("*")->from("address")->queryAll();

        if (!empty($addresses)) {
            foreach ($addresses as $address) {
                $box = OphInDnaextraction_DnaExtraction_Box::model()->find('value=?', array($address['box']));

                if(!$box){
                    $box = new OphInDnaextraction_DnaExtraction_Box();
                    $box->value = $address['box'];
                    $box->maxletter = $address['letter'];
                    $box->maxnumber = $address['number'];

                    $box->save();
                }
            }
        }
    }

    protected function mapGeneticsPatientSamples($genetics_patient, $subject_id, $firm)
    {
        $samples = Yii::app()->db2->createCommand()->select("*")->from("sample")->where("subjectid = :subjectid", array(":subjectid" => $subject_id))->queryAll();

        if (!empty($samples)) {
            $this->verboseLog("Map GeneticsPatient Samples");

            foreach ($samples as $sample) {
                if (in_array(strtolower($sample['type']), array('dna', 'rna'))) {
                    $type = strtoupper($sample['type']);
                } else {
                    $type = ucfirst(strtolower($sample['type']));
                }

                if (!$_type = OphInDnasample_Sample_Type::model()->find('name=?', array($type))) {
                    throw new Exception("Unknown sample type: $type");
                }
                $this->verboseLog("Sample type: " . $_type->name);

                $user_id = $this->findUserIDForString($sample['loggedby']);

                if (!$_sample = Element_OphInDnasample_Sample::model()->findByPk($sample['dnano'])) {

                    $_sample = new Element_OphInDnasample_Sample;
                    $_sample->id = $sample['dnano'];

                    $event = $this->createEvent($this->getSampleEventType(), $genetics_patient->patient, $firm, $sample, $user_id, 'timelogged');
                    $_sample->event_id = $event->id;

                }

                $_sample->old_dna_no = $sample['OldDNANo'];
                $_sample->blood_date = $sample['bloodtaken'];
                $_sample->comments = $sample['comment'];
                if ($_sample->comments == null) {
                    $_sample->comments = '';
                }
                $_sample->type_id = $_type->id;
                $_sample->volume = 10;
                $_sample->created_date = $sample['timelogged'];
                $_sample->last_modified_date = $sample['timelogged'];
                $_sample->created_user_id = $user_id;
                $_sample->last_modified_user_id = $user_id;
                $_sample->consented_by = $user_id;
                $_sample->is_local = 1;

                if (!$_sample->save(false, null, true)) {
                    throw new Exception("Unable to save sample: " . print_r($_sample->getErrors(), true));
                }
                $this->verboseLog("Element_OphInDnasample_Sample saved | ID : " . $_sample->id);

                foreach (Yii::app()->db2->createCommand()->select("*")->from("address")->where("dnano = :dnano", array(":dnano" => $sample['dnano']))->queryAll() as $address) {
                    $box = OphInDnaextraction_DnaExtraction_Box::model()->find('value=?', array($address['box']));

                    //update maxletter and maxnumber as it wasn't provided
                    $letter = strtoupper($address['letter']);
                    if( $box->maxletter == null || (strcmp(strtoupper($box->maxletter), $letter) < 0) ){
                        $box->maxletter = $letter;
                    }

                    if( $box->maxnumber == null || $box->maxnumber < $address['number']){
                        $box->maxnumber = $address['number'];
                    }

                    if(!$box->save()){
                        throw new Exception("Unable to save DnaExtraction Box: " . print_r($box->getErrors(), true));
                    }
                    $this->verboseLog("OphInDnaextraction_DnaExtraction_Box saved");

                    $user_id = $this->findUserIDForString($address['extractedby']);

                    $storage = OphInDnaextraction_DnaExtraction_Storage::model()->find('box_id=? and letter=? and number=?', array($box->id, $address['letter'], $address['number']));

                    $was_storage_exist = $storage ? true :false;

                    if(!$storage){
                        $storage = new OphInDnaextraction_DnaExtraction_Storage();
                        $storage->box_id = $box->id;
                        $storage->letter = $address['letter'];
                        $storage->number = $address['number'];

                        if(!$storage->save()){
                            throw new Exception("Unable to save DnaExtraction Storage: " . print_r($storage->getErrors(), true));
                        }
                        $this->verboseLog("OphInDnaextraction_DnaExtraction_Storage saved");

                    }

                    // if the storage did not exist before, the DnaExtraction did not exist either, so we do not need to check
                    //if the storage was already saved in the DB we check if an element belongs to it
                    $dna = null;
                    if($was_storage_exist){
                        //check if the Episode/event/element are already exist for the patient
                        $criteria = new CDbCriteria();
                        $criteria->join = "JOIN event ON t.event_id = event.id";
                        $criteria->join .= " JOIN episode ON event.episode_id = episode.id";

                        $criteria->compare('episode.patient_id', $genetics_patient->patient->id);
                        $criteria->compare('event.event_type_id', $this->getExtractionEventType()->id);
                        $criteria->compare('t.storage_id', $storage->id);

                        $dna = Element_OphInDnaextraction_DnaExtraction::model()->find($criteria);
                    }

                    if(!$dna){
                        $dna = new Element_OphInDnaextraction_DnaExtraction();
                        $dna->storage_id = $storage->id;

                        $event = $this->createEvent($this->getExtractionEventType(), $genetics_patient->patient, $firm, $sample, $user_id, 'timelogged', $_sample->event_id);
                        $dna->event_id = $event->id;
                    }

                    $dna->extracted_date = $address['extracted'];
                    $dna->extracted_by_text = $address['extractedby'];
                    $dna->comments = $address['comment'];

                    if (!$dna->save()) {
                        throw new Exception("Unable to save dna extraction: " . print_r($dna->getErrors(), true));
                    }
                    $this->verboseLog("Element_OphInDnaextraction_DnaExtraction saved");

                    $dna_tests = new Element_OphInDnaextraction_DnaTests();
                    $dna_tests->event_id = $dna->event_id;

                    if (!$dna_tests->save()) {
                        throw new Exception("Unable to save dna tests element: " . print_r($dna->getErrors(), true));
                    }

                    echo "-";
                }
            }

            echo ".";
        }
    }

    /**
     * @param $genetics_patient
     * @param $subject_id
     * @param $firm
     * @throws Exception
     */
    protected function mapGeneticsPatientTests($genetics_patient, $subject_id, $firm)
    {
        $assays = Yii::app()->db2->createCommand()->select("*")->from("assay")->where("subjectid = :subjectid", array(":subjectid" => $subject_id))->queryAll();

        if (!empty($assays)) {
            $this->verboseLog("Map GeneticsPatient Tests");

            foreach ($assays as $assay) {
                $test = Element_OphInGeneticresults_Test::model()->findByPk($assay['testid']);
                if (!$test) {
                    $method_id = $this->unknown_method->id;
                    if ($assay['method']) {
                        $method = OphInGeneticresults_Test_Method::model()->find('name=?', array($assay['method']));
                        if ($method) {
                            $this->verboseLog("Method name: " . $method->name);
                            $method_id = $method->id;
                        } else {
                            $this->verboseLog("Method name: unknown");
                        }
                    }

                    $effect_id = $this->unknown_effect->id;
                    if ($assay['effect']) {
                        $effect = OphInGeneticresults_Test_Effect::model()->find('name=?', array($assay['effect']));
                        if ($effect) {
                            $this->verboseLog("Effect name: " . $effect->name);
                            $effect_id = $effect->id;
                        } else{
                            $this->verboseLog("Effect name: unknown");
                        }
                    }

                    $gene = PedigreeGene::model()->findByPk($assay['geneid']);
                    if ($gene) {
                        $gene_id = $gene->id;
                        $this->verboseLog("Gene ID:" . $gene->id);
                    } else {
                        $gene_id = new CDbExpression('NULL');
                        $this->verboseLog("Gene ID: null");
                    }

                    $user_id = $this->findUserIDForString($assay['enteredby']);

                    $event = $this->createEvent($this->getGeneticTestEventType(), $genetics_patient->patient, $firm, $assay, $user_id, 'timestamp');

                    $test = new Element_OphInGeneticresults_Test();
                    $test->id = $assay['testid'];
                    $test->event_id = $event->id;
                    $test->gene_id = $gene_id;
                    $test->method_id = $method_id;
                    $test->effect_id = $effect_id;
                    $test->result = $assay['result'];
                    $test->result_date = $assay['resultdate'];
                    $test->comments = $assay['comment'];
                    $test->exon = $assay['exon'];
                    $test->base_change = $assay['basechange'];
                    $test->amino_acid_change = $assay['aminoacidchange'];
                    $test->assay = $assay['assay'];
                    $test->homo = $assay['homo'] === 'Y' ? 1 : 0;
                    $test->created_user_id = $user_id;
                    $test->last_modified_user_id = $user_id;
                    $test->created_date = $assay['timestamp'];
                    $test->last_modified_date = $assay['timestamp'];
                    $test->result = $assay['result'];

                    if (!$test->result) {
                        $test->result = 'Unknown on import';
                    }

                    if (strtolower($assay['method']) === 'sanger') {
                        if (!$test->exon) {
                            $test->exon = 'Unknown on import';
                        }
                    }

                    if (!$test->save(false, null, true)) {
                        throw new Exception("Unable to save Element_OphInGenetictest_Test: " . print_r($test->getErrors(), true));
                    }
                    $this->verboseLog("Element_OphInGeneticresults_Test saved.");
                }
            }
        }
    }

    /**
     * @param $model
     * @return mixed
     */
    protected function unknown($model)
    {
        $unknown = $model->findByAttributes(array('name' => 'Unknown'));
        if (!$unknown) {
            $unknown = $model;
            $unknown->name = 'Unknown';
            $unknown->save();
        }

        return $unknown;
    }

    /**
     * Writes into the General log file
     * @param $message
     */
    protected function log($message)
    {
        $datetime = new DateTime();
        fwrite($this->fp_general_logfile, "[". $datetime->format('Y-m-d H:i:s') . "] : " . $message . PHP_EOL);
    }

    /**
     * Much more detailed log
     */
    protected function verboseLog($message)
    {
        $datetime = new DateTime();
        fwrite($this->fp_verbose_log_file, "[". $datetime->format('Y-m-d H:i:s') . "] : " . $message . PHP_EOL);
    }
}
