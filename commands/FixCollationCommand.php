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

class FixCollationCommand extends CConsoleCommand
{
	public function run($args)
	{
		foreach (Yii::app()->db->getSchema()->getTables() as $table) {
			echo "$table->name ... ";

			if (!in_array($table->name,array('authitem','authitemchild','authitem_type'))) {
				$create = Yii::app()->db->createCommand("show create table $table->name")->queryRow();
				if (!preg_match('/DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci/',$create['Create Table'])) {
					if ($table->name == 'authassignment') {
						Yii::app()->db->createCommand("alter table authassignment drop foreign key authassignment_itemname_fk")->query();
						Yii::app()->db->createCommand("alter table authitemchild drop foreign key authitemchild_parent_fk;")->query();
						Yii::app()->db->createCommand("alter table authitemchild drop foreign key authitemchild_child_fk;")->query();
					}

					Yii::app()->db->createCommand("alter table $table->name convert to character set utf8 collate utf8_unicode_ci;")->query();

					if ($table->name == 'authassignment') {
						Yii::app()->db->createCommand("alter table authitemchild convert to character set utf8 collate utf8_unicode_ci;")->query();
						Yii::app()->db->createCommand("alter table authitem convert to character set utf8 collate utf8_unicode_ci;")->query();
						Yii::app()->db->createCommand("alter table authitem_type convert to character set utf8 collate utf8_unicode_ci;")->query();
						Yii::app()->db->createCommand("alter table authitemchild add foreign key authitemchild_parent_fk (parent) references authitem (name);")->query();
						Yii::app()->db->createCommand("alter table authitemchild add foreign key authitemchild_child_fk (child) references authitem (name);")->query();
						Yii::app()->db->createCommand("alter table authassignment add foreign key authassignment_itemname_fk (itemname) references authitem (name);")->query();
					}
				}
			}

			echo "ok\n";
		}
	}
}
