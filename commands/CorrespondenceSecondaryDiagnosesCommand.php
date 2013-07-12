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

class CorrespondenceSecondaryDiagnosesCommand extends CConsoleCommand
{
	public function run($args)
	{
		Yii::import('application.modules.OphCoCorrespondence.models.*');

		$lsg = LetterStringGroup::model()->find('name=?',array('Diagnosis'));

		foreach (Site::model()->findAll('institution_id=1') as $site) {
			if (!$ls = LetterString::model()->find('letter_string_group_id=? and name=?',array($lsg->id,'Secondary'))) {
				$ls = new LetterString;
				$ls->letter_string_group_id = $lsg->id;
				$ls->name = 'Secondary';
				$ls->body = '[pos] secondary diagnoses are: [sdl]';
				$ls->display_order = 20;
				$ls->site_id = $site->id;
				if (!$ls->save()) {
					throw new Exception("Unable to save LetterString: ".print_r($ls->getErrors(),true));
				}
			}
		}
	}
}
