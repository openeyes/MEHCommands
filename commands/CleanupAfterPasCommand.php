<?php
/**
 * (C) OpenEyes Foundation, 2014
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2014, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

/**
 * Clean up version tables that get spammed by mehpas
 */
class CleanupAfterPasCommand extends CConsoleCommand
{
	public function run($args)
	{
		echo "Deleting version records...\n";
		foreach (array('patient', 'gp', 'practice', 'commissioning_body', 'user') as $table) {
			$records = Yii::app()->db->createCommand(
				"select distinct id, contact_id from {$table}_version t"
			)->queryAll();
			foreach($records as $record) {
				$tran = Yii::app()->db->beginTransaction();	
				echo "$table: id=".$record['id'].", contact_id=".$record['contact_id']."\n";
				Yii::app()->db->createCommand(
					"delete from `address_version` where contact_id = :contact_id"
				)->execute(array(':contact_id' => $record['contact_id']));
				Yii::app()->db->createCommand(
					"delete from `contact_version` where id = :contact_id"
				)->execute(array(':contact_id' => $record['contact_id']));
				Yii::app()->db->createCommand(
					"delete from `{$table}_version` where id = :id and contact_id = :contact_id"
				)->execute(array(':id' => $record['id'], ':contact_id' => $record['contact_id']));
				$tran->commit();
			}
		}
		echo "done.\n";
	}
}
