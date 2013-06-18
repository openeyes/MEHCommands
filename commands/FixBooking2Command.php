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

class FixBooking2Command extends CConsoleCommand {
	public function run($args) {
		Yii::import('application.modules.OphTrOperationbooking.models.*');

		$bookings = unserialize(file_get_contents("/home/mark/bookings.dat"));

		foreach ($bookings as $booking) {
			if ($_booking = Yii::app()->db->createCommand()->select("*")->from("ophtroperationbooking_operation_booking")->where("id=:id",array(':id'=>$booking['id']))->queryRow()) {
				if ($_booking['created_user_id'] != 1) {
					echo "ERROR: booking {$_booking['id']} created_user_id is not 1.\n";
					exit;
				}
				$a = 0;
				foreach ($_booking as $key => $value) {
					if ($value != $booking[$key]) {
						if (!in_array($key,array('created_user_id','created_date','last_modified_date','last_modified_user_id','transport_arranged_date'))) {
							echo "$key: => ".$booking[$key]." => $value\n";
							$a++;
						}
					}
				}
				if ($a == 0) echo ".";
			} else {
				echo "Booking not found: {$booking['id']}\n";
				exit;
			}
		}
	}
}
