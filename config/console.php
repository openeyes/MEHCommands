<?php
return array(
	'import' => array(
		'application.modules.MEHCommands.components.*',
	),
	'commandMap' => array(
		'checkfirms' => array(
			'class' => 'application.modules.MEHCommands.commands.CheckFirmsCommand',
		),
		'checkintegrity' => array(
			'class' => 'application.modules.MEHCommands.commands.CheckIntegrityCommand',
		),
		'consolidatemigrations' => array(
			'class' => 'application.modules.MEHCommands.commands.ConsolidateMigrationsCommand',
		),
		'correspondencedata' => array(
			'class' => 'application.modules.MEHCommands.commands.CorrespondenceDataCommand',
		),
		'erodrules' => array(
			'class' => 'application.modules.MEHCommands.commands.ErodRulesCommand',
		),
		'exportdata' => array(
			'class' => 'application.modules.MEHCommands.commands.ExportDataCommand',
		),
		'fetchusers' => array(
			'class' => 'application.modules.MEHCommands.commands.FetchUsersCommand',
		),
		'firmsecretaryfaxnumbers' => array(
			'class' => 'application.modules.MEHCommands.commands.FirmSecretaryFaxNumbersCommand',
		),
		'fixsites' => array(
			'class' => 'application.modules.MEHCommands.commands.FixSitesCommand',
		),
		'generatesessions' => array(
			'class' => 'application.modules.MEHCommands.commands.GenerateSessionsCommand',
		),
		'grepletters' => array(
			'class' => 'application.modules.MEHCommands.commands.GrepLettersCommand',
		),
		'housekeeping' => array(
			'class' => 'application.modules.MEHCommands.commands.HousekeepingCommand',
		),
		'importconsultants' => array(
			'class' => 'application.modules.MEHCommands.commands.ImportConsultantsCommand',
		),
		'importcontacts' => array(
			'class' => 'application.modules.MEHCommands.commands.ImportContactsCommand',
		),
		'importdrugs' => array(
			'class' => 'application.modules.MEHCommands.commands.ImportDrugsCommand',
		),
		'importmacros' => array(
			'class' => 'application.modules.MEHCommands.commands.ImportMacrosCommand',
		),
		'importdata' => array(
			'class' => 'application.modules.MEHCommands.commands.ImportDataCommand',
		),
		'importepatientletters2' => array(
			'class' => 'application.modules.MEHCommands.commands.ImportEpatientletters2Command',
		),
		'importepatientletterssincelaunch' => array(
			'class' => 'application.modules.MEHCommands.commands.ImportEPatientLettersSinceLaunchCommand',
		),
		'lettergrepper' => array(
			'class' => 'application.modules.MEHCommands.commands.LetterGrepperCommand',
		),
		'opnotedata' => array(
			'class' => 'application.modules.MEHCommands.commands.OpnoteDataCommand',
		),
		'opnoteoperationlink' => array(
			'class' => 'application.modules.MEHCommands.commands.OpnoteOperationLinkCommand',
		),
		'optometrymacros' => array(
			'class' => 'application.modules.MEHCommands.commands.OptometryMacrosCommand',
		),
		'orthopticmacros' => array(
			'class' => 'application.modules.MEHCommands.commands.OrthopticMacrosCommand',
		),
		'rbac' => array(
			'class' => 'application.modules.MEHCommands.commands.RbacCommand',
		),
		'relatedimportcomplex' => array(
			'class' => 'application.modules.MEHCommands.commands.RelatedImportComplexCommand',
		),
		'report' => array(
			'class' => 'application.modules.MEHCommands.commands.ReportCommand',
		),
		'test5' => array(
			'class' => 'application.modules.MEHCommands.commands.Test5Command',
		),
		'test6' => array(
			'class' => 'application.modules.MEHCommands.commands.Test6Command',
		),
	),
	'params' => array(
		'import_contacts_username' => '',
		'import_contacts_password' => '',
		'epatient_hostname' => '',
		'epatient_username' => '',
		'epatient_password' => '',
		'epatient_database' => '',
	),
);
