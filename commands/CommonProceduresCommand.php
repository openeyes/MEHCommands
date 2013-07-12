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

class CommonProceduresCommand extends CConsoleCommand
{
	public function run($args)
	{
		$oph = Specialty::model()->find('code=?',array('OPH'));

		foreach (Subspecialty::model()->findAll('specialty_id=?',array($oph->id)) as $subspecialty) {
			foreach (array('172581008','231755001') as $code) {
				$proc = Procedure::model()->find('snomed_code=?',array($code));

				if (!ProcedureSubspecialtyAssignment::model()->find('proc_id=? and subspecialty_id=?',array($proc->id,$subspecialty->id))) {
					$psa = new ProcedureSubspecialtyAssignment;
					$psa->proc_id = $proc->id;
					$psa->subspecialty_id = $subspecialty->id;

					if (!$psa->save()) {
						throw new Exception("Unable to save procedure subspecialty assignment: ".print_r($psa->getErrors(),true));
					}
				}
			}
		}
	}
}
