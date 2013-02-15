<?php
class ImportEpatientletters2Command extends CConsoleCommand
{
	public function getName() {
		return 'ImportEpatientletters';
	}

	public function getHelp() {
		return '
Slurps all correspondence out of the live epatient system and stores it in the OphLeEpatientletter module
Usage: ./yiic importepatientletters

';
	}

	public function run($args) {
		$tempdir = '/dev/shm';
		// get the event type id for epatient legacy letters without hard coding the id
		$event_type_id = Yii::app()->db->createCommand()->select('id')->from('event_type')->where('class_name=:cn', array(':cn'=>'OphLeEpatientletter'))->queryScalar();

		// connect to epatient
		$dbe = mssql_connect(Yii::app()->params['epatient_hostname'],Yii::app()->params['epatient_username'],Yii::app()->params['epatient_password']);
		if (!$dbe) {
			echo "\nError connecting to SQL Server, User = '" . "" . "', p/w = '" . "" . "' \n\n";
			die('Could not connect to server: ' . mssql_get_last_message());
		}
		mssql_select_db(Yii::app()->params['epatient_database'], $dbe);

		// begin at the row after the last one we already have
		if (!$count = Yii::app()->db->createCommand()->select('MAX(epatient_id)')->from('et_ophleepatientletter_epatientletter')->queryScalar()) { $count = 0; echo "Count is zero\n";}

		if (!file_exists($tempdir . '/legacyletters')) { mkdir($tempdir . '/legacyletters'); }   // we need to write things to disk for munging, so this is just a holding area

		//while ("chalk" != "cheese") {   // continue until we're killed or die of natural causes

			// we process in blocks of 1000 at a time.  more than this can cause epatient to choke.  
			// if we are running the script after an aborted attempt it should continue at the point of failure with the first one that failed + the following 999 
			echo "Processing from " . ($count + 1) . "\n";
			$query = 'select dbo.letters.id, dbo.letters.letterdate, dbo.letters.recipienttype, dbo.letters.createdby, dbo.letters.recipientdata, dbo.letters.contactdata, dbo.letters.datedata, dbo.letters.letterbody, dbo.letters.printed, dbo.letters.patientepisodeid, dbo.letters.locationid, dbo.letters.ccgp, dbo.letters.letterset, dbo.letters.pers_id, dbo.patients.pers_id, dbo.patients.hosnum from dbo.letters, dbo.patients where dbo.letters.Pers_ID=dbo.patients.pers_id and dbo.letters.id > ' . $count . ' order by dbo.letters.id asc';

			// get out of our infinite loop if there are no more records or there is a db error
			$result = mssql_query($query);
			if ((gettype($result) == 'boolean')and($result == TRUE)) {
				echo "No more records\n"; exit;
			} elseif (gettype($result) == 'boolean') {
				echo "Database error\n"; 
				echo mssql_get_last_message() . "\n";
				echo $query . "\n";
				exit;
			}

			// for each of the current batch of 1000 records
			while ($row = mssql_fetch_object($result)) {
				$row->hosnum = trim($row->hosnum);
				echo "\t" . $row->id . ' (hosnum: ' . $row->hosnum . ')' . " LetterDate: " . $row->letterdate . "\n";

				// create event
				$event = new Event;
				$event->episode_id = NULL;
				$event->datetime = date( 'Y-m-d H:i:s', strtotime($row->letterdate));
				$event->event_type_id = $event_type_id;
				$event->created_user_id = 1;
				$event->last_modified_user_id = 1;
				$event->created_date = date( 'Y-m-d H:i:s', strtotime($row->letterdate));
				if (!$event->save(true,$event,true)) {
					echo "Unable to save event\n";
					echo var_export($event->getErrors(), true); exit;
				}

				// create element tied to the event
				$letter = new Element_OphLeEpatientletter_EpatientLetter;
				$letter->event_id = $event->id;
				$letter->epatient_id = $row->id;

				$letter->epatient_printed = $row->printed;
				$letter->epatient_cc_gp = $row->ccgp;
				$letter->epatient_hosnum = $row->hosnum;

				// blocks like the following handle writing the rtf out to disk.  
				// the preg_match/breaks handle places where we need to truncate the rtf block to avoid getting information we don't want.. eg: multiple recipient addresses delimited as separate rtf blocks in the epatient db, where we only care about the first
				$res = ''; $i = 0; $epatient_letter_body = '';
				foreach (preg_split("/\r\n|\n|\r/", $row->letterbody) as $line) {
					if ( ($i != 0) and (preg_match("/^\{\\rtf|^\<CC\>\{\\rtf/", $line)) ) {break;}; $res .= $line . "\n"; $i++;
				} ; $epatient_letter_body = $res; $epatient_letter_body = preg_replace("/[\n]+$|^[\n]+/", "", trim($epatient_letter_body));
				$rtffile = $tempdir . '/legacyletters/' . $letter->epatient_id . '.rtf';
				$htmlfile = $tempdir . '/legacyletters/' . $letter->epatient_id . '.html';
				$fh = fopen($rtffile,'w'); fwrite($fh, $epatient_letter_body); fclose($fh);

				$res = ''; $i = 0; $epatient_date_data = '';
				foreach (preg_split("/\r\n|\n|\r/", $row->datedata) as $line) {
					if ( ($i != 0) and (preg_match("/^\{\\rtf/", $line)) ) {break;}; $res .= $line . "\n"; $i++;
				} ; $epatient_date_data = $res; $epatient_date_data = preg_replace("/[\n]+$|^[\n]+/", "", trim($epatient_date_data));
				$rtffile_date = $tempdir . '/legacyletters/' . $letter->epatient_id . '.date.rtf';
				$htmlfile_date = $tempdir . '/legacyletters/' . $letter->epatient_id . '.date.html';
				$fh = fopen($rtffile_date,'w'); fwrite($fh, $epatient_date_data); fclose($fh);

				$res = ''; $i = 0; $epatient_recipient_data = '';
				foreach (preg_split("/\r\n|\n|\r/", $row->recipientdata) as $line) {
					if (strpos($line, '\rtf')) {
						if ($i > 0) {
							break;
						} else {
							$i++;
							$res .= $line . "\n";
						}
					} else {
						$res .= $line . "\n";
					}
				} ; $epatient_recipient_data = $res; $epatient_recipient_data = preg_replace("/[\n]+$|^[\n]+/", "", trim($epatient_recipient_data));

				$rtffile_recipient = $tempdir . '/legacyletters/' . $letter->epatient_id . '.recipient.rtf';
				$htmlfile_recipient = $tempdir . '/legacyletters/' . $letter->epatient_id . '.recipient.html';
				$fh = fopen($rtffile_recipient,'w'); fwrite($fh, $epatient_recipient_data); fclose($fh);
		
				// letters:	
				// use the commandline unrtf program to turn the rtf file into plaintext (confusingly we refer to this as html throughout this script for legacy reasons)
				// then read the plaintext into the relevant field of the event, munging it along the way to remove comments added by unrtf	
				exec('/usr/bin/timelimit -t5 /usr/bin/unrtf --text ' . $rtffile . ' > ' . $htmlfile, $output, $return); if ($return != 0) { $letter->importinfo = "failed"; }			
				$letter->letter_html = file_get_contents($htmlfile);
				$res = ''; $i = 0;
				foreach (explode("\n", $letter->letter_html) as $line) {
					if (!($i == 0 and preg_match("/^$|^ $/", $line))) {
						if (!preg_match("/^\#\#\#|^\-\-\-\-\-\-/", $line)) { $line = preg_replace("%(###|;|(//)).*%","",$line); $res .= $line . "\n"; } 
					}
					$i++;
				} ; $letter->letter_html = preg_replace("/[\n]+$|^[\n]+/", "", trim($res));

				// dates:	
				// use the commandline unrtf program to turn the rtf file into plaintext (confusingly we refer to this as html throughout this script for legacy reasons)
				// then read the plaintext into the relevant field of the event, munging it along the way to remove comments added by unrtf	
				exec('/usr/bin/timelimit -t5 /usr/bin/unrtf --text ' . $rtffile_date . ' > ' . $htmlfile_date, $output, $return); if ($return != 0) { $letter->importinfo = "failed"; }			
				$letter->date_html = file_get_contents($htmlfile_date);	
				$res = ''; $i = 0;
				foreach (explode("\n", $letter->date_html) as $line) {
					if (!($i == 0 and preg_match("/^$|^ $/", $line))) {
						if (!preg_match("/^\#\#\#|^\-\-\-\-\-\-/", $line)) { $line = preg_replace("%(###|;|(//)).*%","",$line); $res .= $line . "\n"; } 
					}
					$i++;
				} ; $letter->date_html = preg_replace("/[\n]+$|^[\n]+/", "", trim($res));

				// recipients:	
				// use the commandline unrtf program to turn the rtf file into plaintext (confusingly we refer to this as html throughout this script for legacy reasons)
				// then read the plaintext into the relevant field of the event, munging it along the way to remove comments added by unrtf	
				exec('/usr/bin/timelimit -t5 /usr/bin/unrtf --text ' . $rtffile_recipient . ' > ' . $htmlfile_recipient, $output, $return); if ($return != 0) { $letter->importinfo = "failed"; }
				$letter->recipient_html = file_get_contents($htmlfile_recipient);	
				$res = ''; $i = 0; $x = 0;
				$info = "Letter to:\n";
				foreach (explode("\n", $letter->recipient_html) as $line) {
					if (!($i == 0 and preg_match("/^$|^ $/", $line))) {
						if (!preg_match("/^\#\#\#|^\-\-\-\-\-\-/", $line)) {
							$line = preg_replace("%(###|;|(//)).*%","",$line); 
							$res .= $line . "\n"; 
							if (!preg_match("/^$|^ $/", $line)) {
								if ($x == 0) { $info .= $line . ", "; }
								if ($x == 1) { $info .= $line; }
								$x++;
							}	
						} 
					}
					$i++;
				} ; $letter->recipient_html = preg_replace("/[\n]+$|^[\n]+/", "", trim($res));

				// save the event
				$event->info = $info; $event->save();

				// tidying up
				unlink($rtffile); unlink($htmlfile); unlink($rtffile_date); unlink($htmlfile_date); unlink($rtffile_recipient); unlink($htmlfile_recipient);

				// try to associate the element with a patient
				$letter->patient_id = Yii::app()->db->createCommand()->select('id')->from('patient')->where('hos_num=:hosnum', array(':hosnum'=>$row->hosnum))->queryScalar();
				if ($letter->patient_id == '') {
					$letter->patient_id = NULL;
				}
				$letter->last_modified_user_id = 1;
				$letter->created_user_id = 1;

				// save the element 
				if (!$letter->save()) {
					echo "Unable to save letter\n";
					echo var_export($letter->getErrors(), true); exit;
				} else {
					# echo "Letter saved with id: " . $letter->id . "\n";
				}

				// if we successfuly associated the element with a patient in openeyes above, we can create/update a legacy episode and create the episode association
				if ($letter->patient_id) {
					echo "\t\tWe have a patient id: " . $letter->patient_id . "\n";
					// does a legacy episode already exist for this patient and with a null firm_id?
					if ($legacy_episode = Episode::Model()->findAllByAttributes(array('patient_id' => $letter->patient_id, 'legacy' => 1))) {
						 echo "\t\t\tExisting legacy episode found, associating event with this episode (episode id: " . $legacy_episode[0]->id . ")\n"; 
						$event->episode_id = $legacy_episode[0]->id; $event->save();
					} else {
						echo "\t\t\tNo existing legacy episode found, creating one then associating event with the episode\n";
						$legacy_episode = new Episode;
						$legacy_episode->patient_id = $letter->patient_id;
						$legacy_episode->firm_id = NULL;
						$legacy_episode->start_date = $event->datetime;
						$legacy_episode->legacy = 1;
						$legacy_episode->save();

						$event->episode_id = $legacy_episode->id; $event->save();
					}
				} else {
					echo "\t\tNo matching patient id\n";
				}
			}
			$count = $count + 1000;
	}
}
?>
