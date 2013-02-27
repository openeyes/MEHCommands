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

class AddFirmCommand extends CConsoleCommand {
	public function run($args) {
		if (count($args) <3) {
			echo "Usage: ./yiic addfirm <Firm name> <pas code> <subspecialty> [username / user_id]\n";
			echo "\nUser is only required if different from the firm name.\n";
			exit;
		}

		if (!$subspecialty = Subspecialty::model()->find('name=?',array($args[2]))) {
			echo "Subspecialty not found: {$args[2]}\n";
			exit;
		}

		$criteria = new CDbCriteria;
		$criteria->compare('subspecialty_id',$subspecialty->id);
		$criteria->compare('name',$args[0]);

		if ($firm = Firm::model()
			->with('serviceSubspecialtyAssignment')
			->find($criteria)) {
			echo "Firm with this subspecialty already exists.\n";
			exit;
		}

		if (isset($args[3])) {
			if (ctype_digit($args[3])) {
				$user = User::model()->findByPk($args[3]);
			} else {
				$user = User::model()->find('username=?',array($args[3]));
			}
			if (!$user) {
				echo "Unable to find user: {$args[3]}\n";
				exit;
			}
		} else {
			$ex = explode(' ',$args[0]);
			$criteria = new CDbCriteria;
			$criteria->compare('first_name',$ex[1]);
			$criteria->compare('last_name',$ex[0]);

			if (!$user = User::model()->find('first_name=? and last_name=?',array($ex[1],$ex[0]))) {
				echo "Unable to find user with name {$ex[1]} {$ex[0]}\n";
				echo "Please specify username or userid.\n";
				exit;
			}
		}

		if (!$uca = UserContactAssignment::model()->find('user_id=?',array($user->id))) {
			$contact = new Contact;
			$contact->nick_name = $user->first_name;
			$contact->title = $user->title;
			$contact->first_name = $user->first_name;
			$contact->last_name = $user->last_name;
			$contact->qualifications = $user->qualifications;
			if (!$contact->save()) {
				throw new Exception("Unable to save contact: ".print_r($contact->getErrors(),true));
			}

			$uca = new UserContactAssignment;
			$uca->user_id = $user->id;
			$uca->contact_id = $contact->id;
			if (!$uca->save()) {
				throw new Exception("Unable to save user_contact_assignment: ".print_r($uca->getErrors(),true));
			}
		}

		$contact = $uca->contact;

		if ($contact->parent_class != 'Consultant') {
			$consultant = new Consultant;
			if (!$consultant->save(false)) {
				throw new Exception("Unable to save consultant: ".print_r($consultant->getErrors(),true));
			}
		} else {
			if (!$consultant = Consultant::model()->findByPk($contact->parent_id)) {
				$consultant = new Consultant;
				if (!$consultant->save(false)) {
					throw new Exception("Unable to save consultant: ".print_r($consultant->getErrors(),true));
				}
			}
		}

		if (!$ssa = ServiceSubspecialtyAssignment::Model()->find('subspecialty_id=?',array($subspecialty->id))) {
			throw new Exception("ServiceSubspecialtyAssignment for subspecialty $subspecialty->name not found");
		}

		$firm = new Firm;
		$firm->name = $args[0];
		$firm->pas_code = $args[1];
		$firm->service_subspecialty_assignment_id = $ssa->id;

		if (!$firm->save()) {
			throw new Exception("Unable to save firm: ".print_r($firm->getErrors(),true));
		}

		$fua = new FirmUserAssignment;
		$fua->firm_id = $firm->id;
		$fua->user_id = $user->id;

		if (!$fua->save()) {
			throw new Exception("Unable to save firm_user_assignment: ".print_r($fua->getErrors(),true));
		}

		echo "Firm created.\n";
	}
}
