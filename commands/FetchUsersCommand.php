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

class FetchUsersCommand extends CConsoleCommand
{
	public function getName()
	{
		return 'FetchUsers';
	}

	public function getHelp()
	{
		return 'Fetches all the Users from the MEH central user database and puts them in the OpenEyes DB.';
	}

	public function run($args)
	{
		$dbMuuParams = require_once(Yii::app()->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db_muu.php');

		$hostname = trim(`/bin/hostname`);

		ini_set("display_errors", 1);
		$server = $dbMuuParams['host'] . ":1433\\" . $dbMuuParams['Database'];

		$link = mssql_connect($dbMuuParams['host'], $dbMuuParams['UID'], $dbMuuParams['PWD']);

		if (!$link) {
			mail(Yii::app()->params['alerts_email'],"[$hostname] FetchUsersCommand failed","Something went wrong while connecting to MSSQL");
			exit;
		}

		if (!$selected = mssql_select_db($dbMuuParams['Database'], $link)) {
			mail(Yii::app()->params['alerts_email'],"[$hostname] FetchUsersCommand failed","Couldn’t open database: $myDB");
			exit;
		}

		$offset = 0;
		$limit = 100;
		$errors = '';

		while (1) {
			$result = mssql_query("
				;WITH Results_CTE AS
				(
						SELECT
							MUUID_Staff_MUUID,
							MUUID_Staff_DomainUsername,
							MUUID_Staff_NameFirst,
							MUUID_Staff_NameLast,
							MUUID_Staff_EmailAddress,
							MUUID_Staff_Title,
							EPR_MedicalDegrees,
							EPR_JobDescription,
							MUUID_Staff_LeftMEH,
							MUUID_Staff_JobTitle,
							ROW_NUMBER() OVER (ORDER BY MUUID_Staff_MUUID) AS RowNum
						FROM
							MUUID_Staff_Table
						WHERE
							MUUID_Staff_Table.MUUID_Staff_DomainUsername NOT LIKE 'XXX%'
						AND
							LEN(MUUID_Staff_Table.MUUID_Staff_DomainUsername) > 0
				)
				SELECT *
				FROM Results_CTE
				WHERE RowNum >= $offset
				AND RowNum < ".($offset+$limit)."
			");

			$i=0;
			while ($row = mssql_fetch_array($result)) {
				if (!$user = User::model()->find('code = ?', array($row['MUUID_Staff_MUUID']))) {
					$user = new User;
					$user->active = 0;
					$user->setIsNewRecord(true);
					$preexists = 'no';
				} else {
					$preexists = 'yes';
				}

				$user->code = $row['MUUID_Staff_MUUID'];
				$user->username = $row['MUUID_Staff_DomainUsername'];
				$user->first_name = $row['MUUID_Staff_NameFirst'];
				$user->last_name = $row['MUUID_Staff_NameLast'];
				$user->email = $row['MUUID_Staff_EmailAddress'];
				$user->title = $row['MUUID_Staff_Title'];
				$user->qualifications = $row['EPR_MedicalDegrees'];
				$user->role = $row['MUUID_Staff_JobTitle'];
				$user->password = 'faed6633f5a86241f3e0c2bb2bb768fd';

				//if ($user->hasProperty('is_doctor')) {
					$user->is_doctor = ($user->qualifications != '' && $user->qualifications != '.') ? 1 : 0;
				//}

				// temporary stopgap
				$user->active = !$row['MUUID_Staff_LeftMEH'];
				$user->global_firm_rights = 1;

				if (!$user->save(false)) {
					$errors .= "Failed to save user:\n".var_export($user->getErrors(),true)."\n";
				}

				// create a contact for the user
				if ($preexists != 'yes') {
					$contact = new Contact;
				} else {
					if ($uca = UserContactAssignment::model()->find('user_id=?',array($user->id))) {
						$contact = $uca->contact;
					} else {
						$contact = new Contact;
					}
				}

				$contact->nick_name = $user->first_name;
				$contact->title = $user->title;
				$contact->first_name = $user->first_name;
				$contact->last_name = $user->last_name;
				$contact->qualifications = $user->qualifications;
				if (!$contact->save()) {
					$errors .= "Failed to save user contact:\n".var_export($contact->getErrors(),true)."\n";
				}

				if ($preexists != 'yes' || !$uca) {
					$uca = new UserContactAssignment;
					$uca->user_id = $user->id;
					$uca->contact_id = $contact->id;
					if (!$uca->save()) {
						$errors .= "Failed to save user contact assignment:\n".var_export($uca->getErrors(),true)."\n";
					}
				}

				$i++;
				echo ".";
			}

			if ($i == 0) break;

			$offset += 100;
		}

		if ($errors) {
			mail(Yii::app()->params['alerts_email'],"[$hostname] FetchUsersCommand failed",$errors);
		}

		echo "\n";
	}
}
