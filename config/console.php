<?php
$config = array(
	'commandMap' => array(),
	'params' => array(
		'import_contacts_username' => '',
		'import_contacts_password' => '',
		'epatient_hostname' => '',
		'epatient_username' => '',
		'epatient_password' => '',
		'epatient_database' => '',
	),
);

$dh = opendir(dirname(__FILE__)."/../commands");

while ($file = readdir($dh)) {
	if (preg_match('/^(.*?)Command\.php$/',$file,$m)) {
		$config['commandMap'][strtolower($m[1])] = array(
			'class' => "application.modules.MEHCommands.commands.{$m[1]}",
		);
	}
}

return $config;
