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

class HousekeepingCommand extends CConsoleCommand {

	const ARCHIVE_FOLDER = 'data/archive';

	public function getName() {
		return 'Housekeeping Command.';
	}

	public function getHelp() {
		return "Various housekeeping procedures.\n";
	}

	public function run($args) {
		$this->deceasedPatients();
		//$this->archiveAuditTrail();
	}

	// Check for operations where patient is deceased and cancel them
	protected function deceasedPatients() {

		echo "Cancelling operations for deceased patients...";
		
		// TODO: This needs to be made more robust
		$cancellation_reason = CancellationReason::model()->find("text = 'Patient has died'");
		if(!$cancellation_reason) {
			throw new CException('Cannot find cancellation code for "patient has died"');
		}

		foreach (Yii::app()->db->createCommand()
			->select("element_operation.id")
			->from("element_operation")
			->join("event","element_operation.event_id = event.id")
			->join("episode","event.episode_id = episode.id")
			->join("patient","episode.patient_id = patient.id")
			->leftJoin("booking","booking.element_operation_id = element_operation.id")
			->leftJoin("session","booking.session_id = session.id")
			->where("element_operation.status != :cancelled and patient.date_of_death is not null and patient.date_of_death < NOW()",array(':cancelled'=>ElementOperation::STATUS_CANCELLED))
			->queryAll() as $operation) {
			$operation = ElementOperation::model()->findByPk($operation['id']);
			$operation->cancel($cancellation_reason->id, 'Booking cancelled automatically');
		}

		echo "done.\n";
		
	}

	// Archive audit trail records older than 2 months
	protected function archiveAuditTrail() {

		echo "Archiving old audit trail records (> 2 months)...\n";
		
		$connection = Yii::app()->db;
		
		$path = Yii::app()->basePath . '/' . self::ARCHIVE_FOLDER . '/';
		if(!file_exists($path)) {
			if(!mkdir($path)) {
				throw new CException('Could not create archive folder');
			}
		}
		
		$to_date = date('Y-m-d', mktime(0, 0, 0, date("m") - 2, date("d"),   date("Y")));
		$file_path = $path . 'tbl_audit_trail.' . $to_date . '.csv';
		if(file_exists($file_path)) {
			throw new CException("Archive file already exists for $to_date");
		}
		
		$data = $connection->createCommand("SELECT * from `tbl_audit_trail` WHERE stamp < '$to_date'")->queryAll();
		if($data) {
			$file_output = fopen($file_path, 'w+');
			$records = 0;
			foreach($data as $record) {
				fputcsv($file_output, $record, ',', '"');
				$records++;
			}
			fclose($file_output);
			$data = $connection->createCommand("DELETE from `tbl_audit_trail` WHERE stamp < '$to_date'")->query();
			echo "$records records archived.\n";
			echo "compressing...";
			exec("bzip2 $file_path");
			echo "done.\n";
		} else {
			echo "no records to archive\n";
		}
		echo "done.\n";
	}

}
