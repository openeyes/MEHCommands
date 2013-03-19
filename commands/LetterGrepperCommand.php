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

class LetterGrepperCommand extends CConsoleCommand {
	public function run($args) {
		$letter_grepper = LetterGrepper::grep(array(
			//'sources' => array('correspondence','legacy','opnote'),
			'sources' => array('correspondence','legacy'),
			//'users' => array(''),
			//'phrases' => array('accessory canaliculus','accessory cannaliculus','accessory cannlic','accessory cannilic','accessory lacrimal'),
			//'phrases' => array('vitreomacula traction','Vitreo-macula traction','vmt'),
			//'phrases' => array('Open Globe Injury/Trauma','Penetrating Eye Injury','Intra ocular foreign body','Globe Rupture','Scleral Laceration','Corneal Laceration'),
			//'daterange' => array('from' => '2008-09-01', 'to' => '2010-09-01'),
			'phrases' => array('Congenital canalicular','Accessory canaliculus','accessory cancalicular',array('Canaliculus','congenital'),array('accessory','lacrimal'),'canalicular agenesis','bifid caruncle','ring intubation'),
		));
	}
}

class LetterGrepper {
	public $results = array();
	public $whereParams = array();

	static public function grep($params) {
		if (empty($params['phrases'])) {
			throw new Exception('At least one phrase must be specified');
		}

		$lg = new LetterGrepper;

		foreach ($params['phrases'] as $phrase) {
			if (!@$params['users']) {
				$params['users'] = array(false);
			}

			foreach ($params['users'] as $user) {
				in_array('correspondence',$params['sources']) && $lg->searchCorrespondence($phrase,$user,@$params['daterange']);
				in_array('legacy',$params['sources']) && $lg->searchLegacy($phrase,$user,@$params['daterange']);
				in_array('opnote',$params['sources']) && $lg->searchOpnote($phrase,$user,@$params['daterange']);
			}
		}

		foreach ($params['phrases'] as $phrase) {
			if (is_array($phrase)) {
				$phrase = implode(',',$phrase);
			}
			echo "Phrase: $phrase\n";
			if (isset($lg->results[$phrase])) {
				foreach ($lg->results[$phrase] as $user => $results1) {
					echo "\t$user\n";
					foreach ($results1 as $type => $results) {
						echo "\t\t$type\n";
						foreach ($results as $result) {
							echo $result;
						}
					}
				}
			}
		}
	}

	public function whereUser($user, $user_id_fields=true, $footer_field='l.footer') {
		$user = Yii::app()->db->createCommand()->select("user.*")->from("user")->where("lower(username) = '".strtolower($user)."'")->queryRow();
		$fullname = $user['first_name'].' '.$user['last_name'];
		return ' ( '.($user_id_fields ? "l.created_user_id = {$user['id']} or l.last_modified_user_id = {$user['id']} or $footer_field like '%$fullname%' " : "$footer_field like '%$fullname%' ").' ) ';
	}

	public function whereBody($phrase, $body_field='l.body') {
		$this->whereParams = array();

		if (is_array($phrase)) {
			$where = '';
			foreach ($phrase as $i => $p) {
				$p = strtolower($p);
				if ($i != 0) {
					$where .= " and ";
				}
				$where .= " lower($body_field) like :wherePhrase$i";
				$this->whereParams[":wherePhrase$i"] = "%$p%";
			}
			return $where;
		} else {
			$phrase = strtolower($phrase);
			$this->whereParams[":wherePhrase"] = "%$phrase%";
			return " lower($body_field) like :wherePhrase";
		}
	}

	public function whereDate($daterange) {
		$where = '';
		isset($daterange['from']) and $where .= " and datetime >= '{$daterange['from']} 00:00:00' ";
		isset($daterange['to']) and $where .= " and datetime <= '{$daterange['to']} 23:59:59' ";
		return $where;
	}

	public function searchCorrespondence($phrase, $user, $daterange=false) {
		$where = $user ? $this->whereUser($user).' and ' : '';

		$where .= $this->whereBody($phrase);
		$where .= $this->whereDate($daterange);

		foreach (Yii::app()->db->createCommand()
			->select("l.id, l.event_id, c.first_name, c.last_name, p.dob, p.hos_num, ev.datetime")
			->from("et_ophcocorrespondence_letter l")
			->join("event ev","l.event_id = ev.id")
			->join("episode e","ev.episode_id = e.id")
			->join("patient p","e.patient_id = p.id")
			->join("contact c","c.parent_id = p.id and c.parent_class = 'Patient'")
			->where($where,$this->whereParams)
			->queryAll() as $row) {

			if (!$user) $user = 'ALL USERS';

			if (is_array($phrase)) {
				$phrase = implode(',',$phrase);
			}

			$this->results[$phrase][$user]['correspondence'][] = "\t\t\t{$row['hos_num']}, {$row['first_name']} {$row['last_name']}, dob: {$row['dob']}  http://openeyes.moorfields.nhs.uk/OphCoCorrespondence/default/view/{$row['event_id']}\n";
		}
	}

	public function searchLegacy($phrase, $user=false, $daterange=false) {
		$where = $user ? $this->whereUser($user,false,'l.letter_html').' and ' : '';

		$where .= $this->whereBody($phrase,'l.letter_html');
		$where .= $this->whereDate($daterange);

		foreach (Yii::app()->db->createCommand()
			->select("l.id, l.event_id, ev.datetime, l.epatient_hosnum, ev.episode_id")
			->from("et_ophleepatientletter_epatientletter l")
			->join("event ev","l.event_id = ev.id")
			->where($where,$this->whereParams)
			->queryAll() as $row) {

			if (!$patient = Patient::model()->find('hos_num=?',array($row['epatient_hosnum']))) {
				$patient = new Patient;
				$patient->hos_num = $row['epatient_hosnum'];
				$_GET['sort_by'] = 0;
				$data = $patient->search(array('first_name'=>'','last_name'=>'','sortBy'=>'HOS_NUM*1','sortDir'=>'ASC','pageSize'=>30,'currentPage'=>1));
				$nr = $patient->search_nr(array('first_name'=>'','last_name'=>''));
				if ($nr != 1) {
					echo "Warning: unable to find patient ".$row['epatient_hosnum']." in PAS\n";
					continue;
				}
				$patient = Patient::model()->find('hos_num=?',array($row['epatient_hosnum']));
			}

			if ($row['episode_id'] == null) {
				$this->associateLegacyEvents($patient);
			}

			if (!$user) $user = 'ALL USERS';

			if (is_array($phrase)) {
				$phrase = implode(',',$phrase);
			}

			$this->results[$phrase][$user]['legacy'][] = "\t\t\t$patient->hos_num, $patient->first_name $patient->last_name, dob: $patient->dob  http://openeyes.moorfields.nhs.uk/OphLeEpatientletter/default/view/{$row['event_id']}\n";
		}
	}

	public function associateLegacyEvents($patient) {
		if (Element_OphLeEpatientletter_EpatientLetter::model()->find('epatient_hosnum=?',array($patient->hos_num))) {
			$episode = new Episode;
			$episode->patient_id = $patient->id;
			$episode->firm_id = null;
			$episode->start_date = date('Y-m-d H:i:s');
			$episode->end_date = null;
			$episode->episode_status_id = 1;
			$episode->legacy = 1;
			if (!$episode->save()) {
				throw new Exception('Unable to save new legacy episode: '.print_r($episode->getErrors(),true));
			}

			$earliest = time();

			foreach (Element_OphLeEpatientletter_EpatientLetter::model()->findAll('epatient_hosnum=?',array($patient->hos_num)) as $letter) {
				$event = Event::model()->findByPk($letter->event_id);
				$event->episode_id = $episode->id;
				if (!$event->save()) {
					throw new Exception('Unable to associate legacy event with episode: '.print_r($event->getErrors(),true));
				}

				if (strtotime($event->datetime) < $earliest) {
					$earliest = strtotime($event->datetime);
				}
			}

			$episode->start_date = date('Y-m-d H:i:s',$earliest);
		}
	}

	public function searchOpnote($phrase, $user, $daterange=false) {
		$fields = array();
		$bucket = 0;

		if ($user) {
			$user = Yii::app()->db->createCommand()->select("user.*")->from("user")->where("lower(username) = '".strtolower($user)."'")->queryRow();
			$userWhere = " and ( ev.created_user_id = {$user['id']} or ev.last_modified_user_id = {$user['id']} ) ";
		} else {
			$userWhere = '';
		}

		foreach (Yii::app()->getDb()->getSchema()->getTableNames() as $table) {
			if (preg_match('/ophtroperationnote/',$table)) {
				$tableSchema = Yii::app()->getDb()->getSchema()->getTable($table);

				$element_type = false;

				foreach ($tableSchema->getColumnNames() as $column) {
					if ($column == 'event_id') {
						$element_type = true;
					}

					$schema = $tableSchema->getColumn($column);

					if (preg_match('/varchar/',$schema->dbType) || preg_match('/text/',$schema->dbType)) {
						$fields[$bucket][$table][] = $column;
						if (count($fields[$bucket]) >= 50) {
							$bucket++;
						}
					}
				}

				if (!$element_type) {
					unset($fields[$bucket][$table]);
				}
			}
		}

		if (!$user) $user = 'ALL USERS';

		foreach ($fields as $bucket => $bucketFields) {
			$query = Yii::app()->db->createCommand()
				->select("ev.id as event_id, c.first_name, c.last_name, p.dob, p.hos_num, ev.datetime")
				->from("event ev")
				->join("episode e","ev.episode_id = e.id")
				->join("patient p","e.patient_id = p.id")
				->join("contact c","c.parent_id = p.id and c.parent_class = 'Patient'");

			$i = 0;
			$where = '';
			foreach ($bucketFields as $table => $tableFields) {
				$query->leftJoin("$table j$i","j$i.event_id = ev.id");

				foreach ($tableFields as $field) {
					if ($where) $where .= ' or ';
					$where .= "j$i.$field like '%$phrase%' ";
				}
				$i++;
			}

			$dateWhere = $this->whereDate($daterange);

			foreach ($query->where("ev.event_type_id = 4 and ($where) $userWhere $dateWhere")->queryAll() as $row) {
				$this->results[$phrase][$user]['opnote'][] = "\t\t\t{$row['hos_num']}, {$row['first_name']} {$row['last_name']}, dob: {$row['dob']}  http://openeyes.moorfields.nhs.uk/OphTrOperationnote/default/view/{$row['event_id']}\n";
			}
		}
	}
}
