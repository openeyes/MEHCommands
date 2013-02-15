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

class FirmSecretaryFaxNumbersCommand extends CConsoleCommand {
	
	public function getName() {
	}
	
	public function getHelp() {
	}

	public function run($args) {
		$fp = fopen("/tmp/fax.csv","r");

		while ($data = fgetcsv($fp)) {
			$firm_code = $data[0];

			preg_match('/\([\s\t]*(.*?)[\s\t]*\)/',$data[1],$m);
			$subspecialty = $m[1];

			$fax = $data[2];

			if ($firm = $this->getFirm($firm_code, $subspecialty)) {
				if (!$sec = FirmSiteSecretary::model()->find('site_id=? and firm_id=?',array(1,$firm->id))) {
					$sec = new FirmSiteSecretary;
					$sec->site_id = 1;
					$sec->firm_id = $firm->id;
				}
				$sec->fax = $fax;
				if (!$sec->save()) {
					echo "Fatal: unable to save fax number: ".print_r($fax->getErrors(),true);
					exit;
				} else {
					echo "saving sec $sec->id";
				}
			}
		}

		fclose($fp);
	}

	public function getFirm($firm_code, $subspecialty_name) {
		if (!$subspecialty = Subspecialty::model()->find('name=?',array($subspecialty_name))) {
			echo "Subspecialty not found: $subspecialty_name\n";
		}

		foreach (Firm::model()->findAll('pas_code=?',array($firm_code)) as $firm) {
			if ($firm->serviceSubspecialtyAssignment->subspecialty_id == $subspecialty->id) {
				return $firm;
			}
		}

		return false;
	}
}
