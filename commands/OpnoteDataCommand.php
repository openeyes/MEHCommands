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

class OpnoteDataCommand extends CConsoleCommand
{
	public function getName()
	{
		return '';
	}

	public function getHelp()
	{
		return "";
	}

	public function run($args)
	{
		Yii::import('application.modules.OphTrOperationnote.models.*');


 		$drugs = array(
			'Cataract' => array(
				'Intracameral Cefuroxime',
				'Sub-conj Cephalexin 0.25 mg',
				'Sub-conj Gentimicin',
				'Sub-conj Dexamethasone 4mg',
				'Sub-conj Betnesol 4mg',
				'G. Chloramphenicol',
			),
		);

		$drug_defaults['Cataract']['Intracameral Cefuroxime'] = true;
		$drug_defaults['Cataract']['Sub-conj Cephalexin 0.25 mg'] = true;

		foreach ($drugs as $subspecialty_name => $s_drugs) {
			//$subspecialty = Subspecialty::model()->find('name=?',array($subspecialty_name));

			foreach (Subspecialty::model()->findAll() as $subspecialty) {
				foreach (Site::model()->findAll() as $site) {
					foreach (SiteSubspecialtyDrug::model()->findAll('site_id=? and subspecialty_id=?',array($site->id,$subspecialty->id)) as $ssd) {
						$ssd->delete();
					}
				}
			}
		}

		foreach ($drugs as $subspecialty_name => $s_drugs) {
			//$subspecialty = Subspecialty::model()->find('name=?',array($subspecialty_name));
			foreach (Subspecialty::model()->findAll() as $subspecialty) {
				foreach ($s_drugs as $drug) {
					if (!$d = OphTrOperationnote_PostopDrug::model()->find('name=?',array($drug))) {
						echo "Adding drug: $drug\n";

						$d = new OphTrOperationnote_PostopDrug;
						$d->name = $drug;
						$d->save();
					}

					foreach (Site::model()->findAll() as $site) {
						if (!$ssd = OphTrOperationnote_PostopSiteSubspecialtyDrug::model()->find('site_id = ? and subspecialty_id = ? and drug_id = ?',array($site->id,$subspecialty->id,$d->id))) {
							echo "Creating association: [$site->id][$subspecialty->id][$d->id]\n";
							$ssd = new OphTrOperationnote_PostopSiteSubspecialtyDrug;
							$ssd->site_id = $site->id;
							$ssd->subspecialty_id = $subspecialty->id;
							$ssd->drug_id = $d->id;
							$ssd->default = @$drug_defaults[$subspecialty->name][$drug] ? 1 : 0;
							$ssd->save();
						} else {
							$ssd->default = @$drug_defaults[$subspecialty->name][$drug] ? 1 : 0;
							$ssd->save();
						}
					}
				}
			}
		}

		$anaesthetic_agents = array(
			'Cataract' => array(
				'G Amethocaine',
				'G Benoxinate',
				'G Proxymetacaine',
				'Lignocaine 1%',
				'Bupivocaine',
				'Hyalase',
				'Intracameral Lignocaine 0.5%',
			),
		);

		foreach ($anaesthetic_agents as $subspecialty_name => $agents) {
			//$subspecialty = Subspecialty::model()->find('name=?',array($subspecialty_name));
			foreach (Subspecialty::model()->findAll() as $subspecialty) {
				foreach ($agents as $agent) {
					if (!$a = AnaestheticAgent::model()->find('name=?',array($agent))) {
						echo "Adding agent: $agent\n";

						$a = new AnaestheticAgent;
						$a->name = $agent;
						$a->save();
					}

					foreach (Site::model()->findAll() as $site) {
						if (!$saa = SiteSubspecialtyAnaestheticAgent::model()->find('site_id = ? and subspecialty_id = ? and anaesthetic_agent_id = ?',array($site->id,$subspecialty->id,$a->id))) {
							echo "Creating association: [$site->id][$subspecialty->id][$a->id]\n";
							$saa = new SiteSubspecialtyAnaestheticAgent;
							$saa->site_id = $site->id;
							$saa->subspecialty_id = $subspecialty->id;
							$saa->anaesthetic_agent_id = $a->id;
							$saa->save();
						}
					}
				}
			}
		}

		$operative_devices = array(
			'Cataract' => array(
				'Vision blue',
				'Intracameral phenylephrine',
				'Triamcinolone',
				'Healon',
				'Healon GV',
				'Provisc',
				'HPMC',
				'Healon 5',
				'Miochol',
				'Ocucoat',
			),
		);

		$operative_device_defaults['Cataract']['HPMC'] = true;
		
		foreach ($operative_devices as $subspecialty_name => $devices) {
			//$subspecialty = Subspecialty::model()->find('name=?',array($subspecialty_name));
			foreach (Subspecialty::model()->findAll() as $subspecialty) {
				foreach ($devices as $device) {
					if (!$a = OperativeDevice::model()->find('name=?',array($device))) {
						echo "Adding device: $device\n";

						$a = new OperativeDevice;
						$a->name = $device;
						$a->save();
					}

					foreach (Site::model()->findAll() as $site) {
						if (!$saa = SiteSubspecialtyOperativeDevice::model()->find('site_id = ? and subspecialty_id = ? and operative_device_id = ?',array($site->id,$subspecialty->id,$a->id))) {
							echo "Creating association: [$site->id][$subspecialty->id][$a->id]\n";
							$saa = new SiteSubspecialtyOperativeDevice;
							$saa->site_id = $site->id;
							$saa->subspecialty_id = $subspecialty->id;
							$saa->operative_device_id = $a->id;
							$saa->default = @$operative_device_defaults[$subspecialty->name][$device] ? 1 : 0;
							$saa->save();
						} else {
							$saa->default = @$operative_device_defaults[$subspecialty->name][$device] ? 1 : 0;
							$saa->save();
						}
					}
				}
			}
		}
		
		// Post-op instructions [cataract]
		$specialty = Specialty::model()->find('abbreviation=?',array('OPH'));
		$subspecialty = Subspecialty::model()->find('specialty_id=? and ref_spec=?',array($specialty->id,'CA'));
		
		foreach (OphTrOperationnote_PostopInstruction::model()->findAll('subspecialty_id=?',array($subspecialty->id)) as $pi) {
			$pi->delete();
		}

		foreach (array(
				'Check AC and discharge',
				'Check AC, IOP and discharge',
				'No post op assessment required on day of surgery. Discharge',
				'To be reviewed by surgeon before discharge',
			) as $instruction) {

			echo "Adding cataract post-op instruction: $instruction ";

			foreach (Site::model()->findAll() as $site) {
				$pi = new OphTrOperationnote_PostopInstruction;
				$pi->site_id = $site->id;
				$pi->subspecialty_id = $subspecialty->id;
				$pi->content = $instruction;
				$pi->save();
			}

			echo "\n";
		}

		// Post-op instructions [vitreoretinal]
		$specialty = Specialty::model()->find('abbreviation=?',array('OPH'));
		$subspecialty = Subspecialty::model()->find('specialty_id=? and ref_spec=?',array($specialty->id,'VR'));

		foreach (OphTrOperationnote_PostopInstruction::model()->findAll('subspecialty_id=?',array($subspecialty->id)) as $pi) {
			$pi->delete();
		}

		foreach (array(
			'Discharge without same day post-operative review',
			'To be reviewed by surgeon before discharge',
			'No posture',
			'Posture prone',
			'Posture supine',
			'Posture right cheek to pillow',
			'Posture left cheek to pillow',
			'Posture alternate cheeks to pillow',
			) as $instruction) {

			echo "Adding vitreoretinal post-op instruction: $instruction ";

			foreach (Site::model()->findAll() as $site) {
				$pi = new OphTrOperationnote_PostopInstruction;
				$pi->site_id = $site->id;
				$pi->subspecialty_id = $subspecialty->id;
				$pi->content = $instruction;
				$pi->save();
			}

			echo "\n";
		}
	}
}
