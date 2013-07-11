<?php
class ImportLasersCommand extends CConsoleCommand
{
	public function getName()
	{
		return 'ImportLasers';
	}

	public function getHelp()
	{
		return 'Populate lasers by site from LaserSuite application';
	}

	private static $site_map = array(
		'St Annes'   => "St Ann's",
		'St Ann`s'   => "St Ann's",
		'St Georges' => "St George's",
		'St George`s' => "St George's",
		'City_Road'  => "City Road",
		'City_Road Clinic 3' => "City Road",
	);

	public function run($args)
	{
		$dbMlsParams = require_once(Yii::app()->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db_mls.php');

		ini_set("display_errors", 1);
		$server = $dbMlsParams['host'] . ":1433\\" . $dbMlsParams['Database'];

		$link = mssql_connect($dbMlsParams['host'], $dbMlsParams['UID'], $dbMlsParams['PWD']);

		if (!$link) {
			echo "Something went wrong while connecting to MSSQL";
			exit;
		}

		if (!$selected = mssql_select_db($dbMlsParams['Database'], $link)) {
			echo "Couldn’t open database: $myDB";
			exit;
		}

		$query = mssql_query("
			SELECT
              LaserName,
              LaserType, 
              LaserWaveLength,
              LaserSite
            FROM
              LaserDetails_Table
            WHERE
              IsInUse = 'True'
		");
		
		$created = 0;
		do {
			while ($row = mssql_fetch_row($query)) {
				
				$lkup_name = array_key_exists($row[3], self::$site_map) ? self::$site_map[$row[3]] : $row[3];
				
				if ( $site = Site::model()->find('short_name = ?', array($lkup_name)) ) {
					// check the laser hasn't already been created
					if (!Element_OphTrLaser_Site_Laser::model()->find('name = ? AND site_id = ?', array($row[0], $site->id) )) {
						$laser = new Element_OphTrLaser_Site_Laser;
						$laser->name = $row[0];
						$laser->type = $row[1];
						$laser->wavelength = $row[2];
						$laser->site_id = $site->id;

						$criteria = new CdbCriteria;
						$criteria->order = 'display_order DESC';
						$criteria->limit = 1;
						if ($mx_row = Element_OphTrLaser_Site_Laser::model()->find($criteria) ) {
							$laser->display_order = $mx_row->display_order + 1;	
						}
						else {
							$laser->display_order = 1;
						}
						
						$laser->save();
						$created++;
					}
					
				}
				else {
					echo "could not find site for name '" . $lkup_name . "'\n";
					exit;
				}

			}
		} while (mssql_next_result($query));

		if ($created) {
			echo $created . " added\n";
		}
		echo "complete ... \n";
	}
}
