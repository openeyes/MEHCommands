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

class ConsentFormsCommand extends CConsoleCommand {
	public function run($args) {
		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Adnexal Patient information on Entropion";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 2;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Adult Squint - Surgery";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 14;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Anesthesia Moorfields (Long and Short version)";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 3;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Anti-VEGF intravitreal injection treatment";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Bevacizumab PIL";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Botulinum Toxin Treatment for Eye Conditions";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 14;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Cataract";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 4;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Cataract Surgery Ð 8-12s";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 4;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Cataract Surgery (teens)";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 4;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Corneal graft";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 6;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Dacryocystorhinostomy (DCR)";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 2;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Day-case surgery - information for parents";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 1;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 2;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 3;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 4;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 5;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 6;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 7;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 9;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 10;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 11;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 12;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 13;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 14;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 15;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 16;
		$cfs->save();

		$firm = Firm::model()->find('name=?',array('Optometry'));

		$cfs = new OphTrConsent_Leaflet_Firm;
		$cfs->leaflet_id = $cf->id;
		$cfs->firm_id = $firm->id;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "DCR post-operative advice for children - information for parents";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 2;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Electrophysiology Department";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = null;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Electrophysiology in Children";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = null;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Electrophysiology instructions and reminders";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = null;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Enucleation, evisceration & ball implantation";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 2;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Epiretinal membrane";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 16;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Exenteration";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 2;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Fluorescein (FFA) and indocyanine green (ICG) angiography patient information leaflet";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 16;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "General anaesthesia";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 3;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Glaucoma surgery - 8 to 12s";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 7;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Glaucoma surgery - teens";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 7;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Information and advice for patients having fluorescein angiography";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 16;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Information and advice for patients having laser treatment for diabetic retinopathy";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Instructions for Moorfields Patients having Penetrating Corneal Transplants (PK) or Endothelial Transplant Operations (EK or DSAEK)";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 6;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Intravitreal treatment for Wet Age-related Macular Degeneration (AMD).";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Laser Treatment for Diabetic Retinopathy";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Laser treatment for new vessels on the retina due to diabetes";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Lucentis Leaflet AMD";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Macular Hole";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 16;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Moorfields Private Patient Refractive Laser Surgery";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 13;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Paediatric Electrophysiology";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = null;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Post-operative posturing info sheets";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 16;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Ptosis";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 2;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Radiology - DCG test";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 2;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Refractive Laser Surgery";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 13;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Retinal Detachment";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 16;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Selective laser trabeculoplasty (SLT)";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 7;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Squint surgery in children - information for parents";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 11;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Steroid Injection and the Eye";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Steroid injections in the eye";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Thermal laser for choroidal neovascularisation";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 8;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Vitreoretinal - Retinal detachment, timing of surgery";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 16;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "YAG laser treatment for thickening of posterior capsule";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 4;
		$cfs->save();

		$cf = new OphTrConsent_Leaflet;
		$cf->name = "Your child's general anaesthetic";
		$cf->save();

		$cfs = new OphTrConsent_Leaflet_Subspecialty;
		$cfs->leaflet_id = $cf->id;
		$cfs->subspecialty_id = 3;
		$cfs->save();
	}
}

