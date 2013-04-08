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

class FixPatientContactsCommand extends CConsoleCommand {
	public function run($args) {
		$_GET['sort_by'] = 'hos_num';

		foreach (Yii::app()->db->createCommand()
			->select("patient.*")
			->from("patient")
			->leftJoin("contact","contact.parent_class = 'Patient' and contact.parent_id = patient.id")
			->where("contact.id is null")
			->queryAll() as $patient) {

			echo "Fixing patient {$patient['hos_num']} ... ";

			$model = new Patient();
			$model->hos_num = $patient['hos_num'];
			$model->nhs_num = '';
			$dataProvider = $model->search(array(
				'pageSize' => 20,
				'currentPage' => 0,
				'sortBy' => 'hos_num*1',
				'sortDir' => 'asc',
				'first_name' => '',
				'last_name' => '',
			));

			if (Yii::app()->db->createCommand()->select("*")->from("contact")->where("parent_class = 'Patient' and parent_id = {$patient['id']}")->queryRow()) {
				echo "OK\n";
			} else {
				echo "FAILED\n";
			}
		}
	}
}
