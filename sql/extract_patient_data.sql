SET max_sp_recursion_depth=255;

DELIMITER $$

DROP PROCEDURE IF EXISTS InsGen;

CREATE DEFINER=`root`@`localhost` PROCEDURE InsGen(
  in_db varchar(20),
  in_table varchar(200),
  in_ColumnName varchar(200),
  in_ColumnValue varchar(50),
  in_row_id varchar(10)
)
BEGIN

    declare Whrs varchar(500);
    declare Sels varchar(500);
    declare Inserts varchar(200);
    declare tablename varchar(20);
    declare ColName varchar(20);
    declare tmp varchar(500);




    set tablename=in_table;





    # Comma separated column names - used for Select
    select group_concat(concat('concat(\'"\',','ifnull(',column_name,','''')',',\'"\')'))
    INTO @Sels from information_schema.columns where table_schema=in_db and table_name=tablename;


    # Comma separated column names - used for Group By
    select group_concat('`',column_name,'`')
    INTO @Whrs from information_schema.columns where table_schema=in_db and table_name=tablename;


    #Main Select Statement for fetching comma separated table value

    set @Inserts= concat("(select concat('insert into ", in_db,".",tablename," values(',concat_ws(',',",@Sels,"),');')
 as MyColumn from ", in_db,".",tablename, " where ", in_ColumnName, " = " , in_ColumnValue, " AND id = " , in_row_id, " group by ",@Whrs, " INTO @tmp);");




    PREPARE Inserts FROM @Inserts ;

    SELECT @tmp;

    EXECUTE Inserts;



    INSERT INTO patient_data_extract VALUES (NULL, @tmp);




   #SELECT * FROM patient_date_extract;


  END $$

DELIMITER ;


DELIMITER $$
DROP PROCEDURE IF EXISTS extract_row;
CREATE DEFINER=`root`@`localhost` PROCEDURE extract_row(
  in_count integer(10),
  in_id_array varchar(200),
  in_db varchar(20),
  in_table varchar(200),
  in_ColumnName varchar(200),
  in_ColumnValue varchar(50)
)
  BEGIN

    SET @table = in_table;
    SET @column = in_ColumnName;
    SET @value = in_ColumnValue;
    set @ids = concat(',',in_id_array);

    if( in_count > 0) THEN
      WHILE (LOCATE(',', @ids) > 0) DO
        SET @ids = SUBSTRING(@ids, LOCATE(',', @ids) + 1);
        SET @current_id = (SELECT TRIM(SUBSTRING_INDEX(@ids, ',', 1)));

        call InsGen(in_db, in_table, in_ColumnName, in_ColumnValue, @current_id);


      END WHILE;

    END IF;



  END $$

DELIMITER ;


DELIMITER $$
DROP PROCEDURE IF EXISTS extract_dependant_row;
CREATE DEFINER=`root`@`localhost` PROCEDURE extract_dependant_row(
  in_count integer(10),
  in_id_array varchar(200),
  in_db varchar(20),
  in_table varchar(200),
  in_ColumnName varchar(200),
  in_ColumnValue varchar(50)
)
  BEGIN

    SET @table = in_table;
    SET @column = in_ColumnName;
    SET @value = in_ColumnValue;
    SET @ids = concat(',',in_id_array);

    IF ( in_count > 0) THEN
      WHILE (LOCATE(',', @ids) > 0) DO
        SET @ids = SUBSTRING(@ids, LOCATE(',', @ids) + 1);
        SET @current_id = (SELECT TRIM(SUBSTRING_INDEX(@ids, ',', 1)));

        #SELECT @ids;

        IF ( in_table = 'episode') THEN
          SET @event_ids = (SELECT group_concat(id separator ',') FROM event WHERE episode_id = @current_id);
          SET @event_count = (SELECT COUNT(*) FROM event WHERE episode_id = @current_id);

         # SELECT @event_ids;
          call extract_row(@event_count, @event_ids,'openeyes', 'event', 'episode_id', @current_id);
        END IF;

      END WHILE;

    END IF;



  END $$

DELIMITER ;



DELIMITER $$

DROP PROCEDURE IF EXISTS run_extractor;
CREATE DEFINER=`root`@`localhost` PROCEDURE run_extractor(IN hospital_number integer(10))
  BEGIN
    declare sqlStr varchar(500);

    DROP TABLE IF EXISTS patient_data_extract;
    CREATE TEMPORARY TABLE patient_data_extract(
      id int AUTO_INCREMENT,
      query LONGTEXT,
      PRIMARY KEY (id)
    );

    SET @query_string = '';
    SET @file = concat('/tmp/patient_', hospital_number, '.sql');

    SELECT @file;

    SET @patient_id = (SELECT id FROM patient WHERE hos_num=hospital_number);
    SET @contact_id = (SELECT contact_id FROM patient WHERE hos_num=hospital_number);



    #Start creating the inserts;

    SET  @count = (SELECT COUNT(*) FROM patient WHERE id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient WHERE hos_num=hospital_number );
    call extract_row(@count, @ids,'openeyes', 'patient', 'id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM contact WHERE id = @contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM contact WHERE id=@contact_id);
    call extract_row(@count, @ids,'openeyes', 'contact', 'id', @contact_id);

    SET  @count = (SELECT COUNT(*) FROM address WHERE contact_id = @contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM address WHERE contact_id=@contact_id);
    call extract_row(@count, @ids,'openeyes', 'address', 'contact_id', @contact_id);


    SET  @count = (SELECT COUNT(*) FROM commissioning_body_patient_assignment WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM commissioning_body_patient_assignment WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'commissioning_body_patient_assignment', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM commissioning_body_patient_assignment_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM commissioning_body_patient_assignment_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'commissioning_body_patient_assignment_version', 'patient_id', @patient_id);



    SET  @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM episode WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'episode', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM episode_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM episode_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'episode', 'patient_id', @patient_id);


    -- EVENTS --
    SET  @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM episode WHERE patient_id = @patient_id);
    call extract_dependant_row(@count, @ids,'openeyes', 'episode', 'patient_id', @patient_id);


    --  EVENT_VERSION --


    -- MEASUREMENT_REFERENCE --


    -- MEASUREMENT_REFERENCE_VERSION --


    -- REFERRAL_EPISODE_ASSIGNMENT --




    -- REFERRAL_EPISODE_ASSIGNMENT_VERSION --


    -- EVENT_ISSUE --


    -- EVENT_ISSUE_VERSION --


    -- measurement_reference --


    -- measurement_reference_version --






    SET  @count = (SELECT COUNT(*) FROM family_history WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM family_history WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'family_history', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM family_history_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM family_history_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'family_history_version', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM medication WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'family_history', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM medication_adherence WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication_adherence WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'medication_adherence', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM medication_adherence_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication_adherence_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'medication_adherence_version', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM medication_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'medication_version', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM patient_allergy_assignment WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_allergy_assignment WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_allergy_assignment', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM patient_allergy_assignment_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_allergy_assignment_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_allergy_assignment_version', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM patient_contact_assignment WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_contact_assignment WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_contact_assignment', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM patient_contact_assignment_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_contact_assignment_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_contact_assignment_version', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM patient_measurement WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_measurement WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_measurement', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM patient_measurement_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_measurement_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_measurement_version', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM patient_oph_info_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_oph_info_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_oph_info_version', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM previous_operation WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM previous_operation WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'previous_operation', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM previous_operation_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM previous_operation_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'previous_operation_version', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM referral WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM referral WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'referral', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM referral_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM referral_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'referral_version', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM secondary_diagnosis WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM secondary_diagnosis WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'secondary_diagnosis', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM secondary_diagnosis_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM secondary_diagnosis_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'secondary_diagnosis_version', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM socialhistory WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM socialhistory WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'socialhistory', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM socialhistory_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM socialhistory_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'socialhistory_version', 'patient_id', @patient_id);

















    # Write all the inserts to file
    /*SET @query = concat("SELECT query  INTO OUTFILE '", @file,"' LINES TERMINATED BY '\n' FROM patient_data_extract");

    PREPARE qry FROM @query;

    EXECUTE qry;

    SELECT * FROM patient_data_extract;

    # Delete the temporary table
    DROP TEMPORARY TABLE IF EXISTS patient_data_extract;*/



  END $$
DELIMITER ;

call run_extractor(1000001);