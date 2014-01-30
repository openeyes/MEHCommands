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

class AnonymiseDataCommand extends CConsoleCommand
{
	public $core = array(
		'patient' => array(
			'dob' => array(
				'type' => 'date',
			),
			'nhs_num' => array(
				'type' => 'number',
				'length' => 10,
				'unique' => true,
			),
			'hos_num' => array(
				'type' => 'number',
				'length' => 7,
				'unique' => true,
			),
			'_relations' => array(
				'contact' => array(
					'relation_field' => 'contact_id',
					'fields' => array(
						'primary_phone' => array(
							'type' => 'telephone',
						),
						'first_name' => array(
							'type' => 'first_name',
						),
						'last_name' => array(
							'type' => 'last_name',
						),
					),
				),
			),
		),
	);

	public $male_first = array();
	public $male_last = array();
	public $female_first = array();
	public $female_last = array();
	public $lorum_ipsum;

	public $unique = array();
	public $related_data = array();
	public $threads = 8;
	public $thread_workload = 1000;

	public function run()
	{
		foreach (array('male_first','male_last','female_first','female_last','lorum_ipsum') as $file) {
			if (!file_exists("/tmp/$file")) {
				die("File /tmp/$file is missing.\n");
			}
		}

		if (!function_exists('pcntl_fork')) {
			die("pcntl_fork() is not available.\n");
		}

		$this->male_first = explode("\n",trim(file_get_contents("/tmp/male_first")));
		$this->male_last = explode("\n",trim(file_get_contents("/tmp/male_last")));
		$this->female_first = explode("\n",trim(file_get_contents("/tmp/female_first")));
		$this->female_last = explode("\n",trim(file_get_contents("/tmp/female_last")));
		$this->lorum_ipsum = file_get_contents("/tmp/lorum_ipsum");

		$this->processCoreTables();
		$this->processModuleTables();
		$this->processAddresses();
		$this->processAuditData();
	}

	public function processCoreTables()
	{
		echo "Loading related data ...";

		foreach ($this->core as $table => $fields) {
			if (isset($fields['_relations'])) {
				$this->related_data[$table] = array();

				foreach ($fields['_relations'] as $related_table => $params) {
					$this->related_data[$table][$related_table] = array();

					$fields = "$related_table.id, $related_table.".implode(", $related_table.",array_keys($params['fields']));

					foreach (Yii::app()->getDb()->createCommand()
						->select($fields)
						->from($related_table)
						->join($table,"$table.{$params['relation_field']} = $related_table.id")
						->queryAll() as $related_item) {

						$this->related_data[$table][$related_table][$related_item['id']] = $related_item;
					}
				}
			}
		}

		echo " done\n";

		foreach ($this->core as $table => $fields) {
			$this->parallelise($table, $fields, 'processCoreData');
		}
	}

	public function getSelectClause($fields)
	{
		$select = "id";

		foreach ($fields as $field => $params) {
			if (is_array($params)) {
				if ($field == '_relations') {
					foreach ($params as $related_table => $properties) {
						$select .= ", ".$properties['relation_field'];
					}
				} else {
					$select .= ", ".$field;
				}
			} else {
				$select .= ", ".$params;
			}
		}
		
		return $select;
	}

	public function parallelise($table, $fields, $processMethod)
	{
		echo "$table:";

		while(1) {
			$pids = array();

			for ($thread_id = 0; $thread_id < $this->threads; $thread_id++) {
				Yii::app()->getDb()->setActive(false);

				if (($pid = pcntl_fork()) == -1) {
					die("Could not fork()\n");
				}

				Yii::app()->getDb()->setActive(true);

				if ($pid == 0) {
					$thread_offset = $offset + ($thread_id * $this->thread_workload);

					$data = Yii::app()->getDb()->createCommand()
						->select($this->getSelectClause($fields))
						->from($table)
						->order('id asc')
						->limit($this->thread_workload)
						->offset($thread_offset)
						->queryAll();

					if (empty($data)) exit(1);

					$this->{$processMethod}($table, $data, $fields);
					exit;

				} else {
					$pids[] = $pid;
				}
			}

			foreach ($pids as $pid) {
				pcntl_waitpid($pid, $status);

				if ($status != 0) {
					$done = true;
				}
			}

			if ($done) break;

			$offset += ($this->thread_workload * $this->threads);
		}

		echo "\n";
	}

	public function processCoreData($table, $data, $fields)
	{
		foreach ($data as $i => $row) {
			$update = array();

			foreach ($fields as $field => $params) {
				if ($field == '_relations') {
					foreach ($params as $related_table => $properties) {
						if ($related = $this->related_data[$table][$related_table][$row[$properties['relation_field']]]) {
							$related_update = array();

							foreach ($properties['fields'] as $related_field => $related_params) {
								$related_update[$related_field] = $this->getFieldOfType($related_field, $related_params['type'], @$related_params['length'], @$related_params['unique'], @$row['gender']);
							}

							Yii::app()->getDb()->createCommand()->update($related_table,$related_update,"id = {$related['id']}");
						}
					}
				} else {
					$update[$field] = $this->getFieldOfType($field, $params['type'], @$params['length'], @$params['unique']);
				}
			}

			Yii::app()->getDb()->createCommand()->update($table,$update,"id = {$row['id']}");

			if ($i %10 == 0) {
				echo ".";
			}
		}
	}

	public function processModuleTables()
	{
		foreach (Yii::app()->getDb()->getSchema()->getTables() as $table) {
			if ((preg_match('/^et_/',$table->name) || preg_match('/^oph/',$table->name)) && !preg_match('/_version$/',$table->name)) {
				$this->processModuleTable($table);
			}
		}
	}

	public function processModuleTable($table)
	{
		if (!$this->isReferenceTable($table)) {
			$offset = 0;

			$fields = array();

			foreach ($table->columns as $column => $properties) {
				if (preg_match('/char/i',$properties->dbType) || preg_match('/text/i',$properties->dbType)) {

					// Test for eyedraw columns
					if ($row = Yii::app()->getDb()->createCommand()->select("*")->from($table->name)->where("$column is not null and $column != ''")->queryRow()) {
						if (!@json_decode($row[$column])) {
							$fields[] = $column;
						}
					}
				}
			}

			if (!empty($fields)) {
				$this->parallelise($table->name, $fields, 'processModuleData');
			}
		}
	}

	public function processModuleData($table, $data, $fields)
	{
		foreach ($data as $i => $row) {
			$update = array();

			foreach ($fields as $field) {
				if (strlen($row[$field]) >0) {
					$update[$field] = substr($this->lorum_ipsum,0,strlen($row[$field]));
				}
			}

			if (!empty($update)) {
				Yii::app()->getDb()->createCommand()->update($table,$update,"id = {$row['id']}");

				if ($i %10 == 0) {
					echo ".";
				}
			}
		}
	}

	// If the table has a foreign key pointing to an element table then it's not a reference table
	public function isReferenceTable($table)
	{
		if (preg_match('/^et_/',$table->name)) return false;

		foreach ($table->foreignKeys as $field => $properties) {
			if (preg_match('/^et_/',$properties[0])) {
				return false;
			}
		}

		return true;
	}

	public function getFieldOfType($field, $type, $length=false, $unique=false, $gender=false)
	{
		switch ($type) {
			case 'date':
				return date('Y-m-d',rand(strtotime('1910-01-01'),strtotime('2014-01-01')));
			case 'number':
				while (1) {
					$value = rand(str_repeat('0',$length-1).'1',str_repeat('9',$length));

					if (!@$unique || !isset($this->unique[$field]) || !in_array($value,$this->unique[$field])) break;
				}

				if (!isset($this->unique[$field])) {
					$this->unique[$field] = array();
				}

				$this->unique[$field][] = $value;

				return $value;
			case 'telephone':
				return '07'.rand(0,9).rand(1,9).rand(0,9).' '.rand(0,9).rand(1,9).rand(0,9).rand(0,9).rand(0,9).rand(1,9);
			case 'first_name':
				if ($gender == 'M') {
					return $this->male_first[rand(0,count($this->male_first)-1)];
				} else {
					return $this->female_first[rand(0,count($this->female_first)-1)];
				}
			case 'last_name':
				if ($gender == 'M') {
					return $this->male_last[rand(0,count($this->male_last)-1)];
				} else {
					return $this->female_last[rand(0,count($this->female_last)-1)];
				}
			default:
				throw new Exception("Unhandled field type: {$type}");
		}
	}

	// Randomise patient addresses
	public function processAddresses()
	{
		$address1 = array();
		$postcode = array();
		$address_ids = array();

		$offset = 0;

		echo "address:";

		$thread_id = 0;
		$workload = array();

		foreach (Yii::app()->getDb()->createCommand()
			->select("address.id, address.address1, address.postcode")
			->from("address")
			->where("address1 != '' or postcode != ''")
			->join("contact c","address.parent_class = 'Contact' and address.parent_id = c.id")
			->join("patient p","p.contact_id = c.id")
			->queryAll() as $address) {

			$address1[] = $address['address1'];
			$postcode[] = $address['postcode'];

			$workload[$thread_id++][] = $address['id'];

			if ($thread_id >= $this->threads) {
				$thread_id = 0;
			}
		}

		$pids = array();

		for ($thread_id = 0; $thread_id < $this->threads; $thread_id++) {
			Yii::app()->getDb()->setActive(false);

			if (($pid = pcntl_fork()) == -1) {
				die("Could not fork()\n");
			}

			Yii::app()->getDb()->setActive(true);

			if ($pid == 0) {
				foreach ($workload[$thread_id] as $i => $id) {
					Yii::app()->getDb()->createCommand()->update('address',array('address1' => $address1[rand(0,count($address1)-1)], 'postcode' => $postcode[rand(0,count($postcode)-1)]), "id = $id");

					if ($i %10 == 0) {
						echo ".";
					}
				}
				exit;

			} else {
				$pids[] = $pid;
			}
		}

		foreach ($pids as $pid) {
			pcntl_waitpid($pid, $status);
		}

		echo "\n";
	}

	public function processAuditData()
	{
		echo "audit:";

		foreach (Yii::app()->getDb()->createCommand()->select("id")->from("audit")->where("data like 'a:%:{%'")->queryAll() as $row) {
			$ids[] = $row['id'];

			if (count($ids) >= 1000) {
				Yii::app()->getDb()->createCommand()->update('audit',array('data' => null),"id in (".implode(',',$ids).")");
				$ids = array();
				echo ".";
			}
		}

		if (!empty($ids)) {
			Yii::app()->getDb()->createCommand()->update('audit',array('data' => null),"id in (".implode(',',$ids).")");
			echo ".";
		}

		echo "\n";
	}
}
