-- Configuration settings for this script --
SET SESSION group_concat_max_len = 100000;
SET max_sp_recursion_depth = 1024;



-- The insert genaration proedure --
DELIMITER $$

DROP PROCEDURE IF EXISTS InsGen;

CREATE DEFINER=`root`@`localhost` PROCEDURE InsGen(
  in_db varchar(20),
  in_table text,
  in_ColumnName text,
  in_ColumnValue text,
  in_row_id varchar(10)
)
  BEGIN

    declare Whrs text;
    declare Sels text;
    declare Inserts text;
    declare tablename text;
    declare ColName text;
    declare tmp text;
    declare Columns text;

    SET tablename=in_table;
    SET @in_row_id = TRIM(in_row_id);
    SET @Sels = NULL;
    SET @Inserts = NULL;
    SET @current_table = NULL;

    IF( (@in_row_id IS NOT NULL) AND (@in_row_id != '')) THEN

      -- Comma separated column names - used for Select --
      select group_concat(concat("if(",column_name," IS NOT NULL, concat(\"'\",replace(",column_name,",\"'\",\"''\"),\"'\"), 'NULL')"))
      INTO @Sels from information_schema.columns where table_schema=in_db and table_name=tablename and column_name !=  'last_modified_user_id' AND column_name!='created_user_id' AND column_name != 'last_firm_id' AND column_name != 'last_site_id' AND column_name != 'latest_booking_id';

      #SELECT @Sels;

      select group_concat(column_name)
      INTO @Columns from information_schema.columns where table_schema=in_db and table_name=tablename and column_name !=  'last_modified_user_id' AND column_name!='created_user_id' AND column_name != 'last_firm_id' AND column_name != 'last_site_id' AND column_name != 'latest_booking_id';

      #SELECT @Columns;

      -- Comma separated column names - used for Group By --
      select group_concat('`',column_name,'`')
      INTO @Whrs from information_schema.columns where table_schema=in_db and table_name=tablename and column_name !=  'last_modified_user_id' AND column_name!='created_user_id' AND column_name != 'last_firm_id' AND column_name != 'last_site_id' AND column_name != 'latest_booking_id';

      #SELECT @Whrs;

      SET @current_table = tablename;

      -- Main Select Statement for fetching comma separated table value --

     SET @Inserts= concat('(select concat("insert into ', @current_table,' (',@Columns,') values(",concat_ws(",",',@Sels,'),");")
        as MyColumn from ', @current_table, ' where ', in_ColumnName, ' = ' , in_ColumnValue, ' AND id = ' , @in_row_id,' group by ',@Whrs, ' INTO @tmp);');

      #SELECT @Inserts;

      IF ((@Inserts IS NOT NULL) AND (@Sels IS NOT NULL)) THEN
        PREPARE Inserts FROM @Inserts ;

        #SELECT @tmp;

        EXECUTE Inserts;

        IF (@tmp IS NOT NULL) THEN
          INSERT INTO patient_data_extract VALUES ('', @tmp);

          # we also need to update the event_type_id here
          IF( tablename = 'event' ) THEN
            SELECT class_name INTO @class_n FROM event_type WHERE id = (SELECT event_type_id FROM event WHERE id = @in_row_id);
            SET @update_str = CONCAT("UPDATE event SET event_type_id=(SELECT id FROM event_type WHERE class_name='", @class_n ,"') WHERE id=", @in_row_id,";");
            INSERT INTO patient_data_extract VALUES ('', @update_str );
          END IF;
        END IF;
      END IF;

    END IF;
  END $$

DELIMITER ;




-- Extract each individual row of data : calls InsGen() --
DELIMITER $$
DROP PROCEDURE IF EXISTS extract_row;
CREATE DEFINER=`root`@`localhost` PROCEDURE extract_row(
  in_count integer(10),
  in_id_array text,
  in_db varchar(20),
  in_table text,
  in_ColumnName varchar(200),
  in_ColumnValue varchar(50)
)
  BEGIN

    SET @table = in_table;
    SET @column = in_ColumnName;
    SET @value = in_ColumnValue;
    SET @ext_row_count = in_count;
    SET @ext_row_ids = NULL;
    SET @ext_row_ids = concat(',',in_id_array);

    -- Cycle through each id to get the individual row for that id --
    if( @ext_row_count > 0 AND @ext_row_ids IS NOT NULL) THEN
      WHILE (LOCATE(',', @ext_row_ids) > 0) DO
        SET @ext_row_ids = SUBSTRING(@ext_row_ids, LOCATE(',', @ext_row_ids) + 1);
        SET @current_ext_row_id =  (SELECT TRIM(SUBSTRING_INDEX(@ext_row_ids, ',', 1)));
        SET @current_ext_row_id = TRIM(@current_ext_row_id);

        call InsGen(in_db, in_table, in_ColumnName, in_ColumnValue, @current_ext_row_id);

      END WHILE;

    END IF;
  END $$

DELIMITER ;

-- Extract all event rows: calls extract_row() --
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
    SET @row_ids = concat(',',in_id_array);



    IF ( (in_count > 0) AND (@row_ids IS NOT NULL)) THEN
      WHILE (LOCATE(',', @row_ids) > 0) DO
        SET @row_ids = SUBSTRING(@row_ids, LOCATE(',', @row_ids) + 1);
        SET @id = (SELECT TRIM(SUBSTRING_INDEX(@row_ids, ',', 1)));


        IF ( in_table = 'episode') THEN
          SET @event_ids = NULL;
          SET @event_count = NULL;
          SET @event_ids = (SELECT group_concat(id separator ',') FROM event WHERE episode_id = @id AND
          event_type_id IN (SELECT id FROM event_type WHERE class_name IN ('OphCiPhasing','OphTrOperationnote','OphDrPrescription','OphTrLaser','OphCoCorrespondence','OphCiExamination',
          'OphOuAnaestheticsatisfactionaudit','OphTrOperationbooking','OphCiPhasing','OphTrConsent','OphTrIntravitrealinjection',
          'OphCoTherapyapplication')));
          SET @event_count = (SELECT COUNT(*) FROM event WHERE episode_id = @id);
          call extract_row(@event_count, @event_ids,'openeyes', 'event', 'episode_id', @id);
          #call extract_row(@event_count, @event_ids,'openeyes', 'event_version', 'episode_id', @id);

          SET @measurement_reference_ids = (SELECT group_concat(id separator ',') FROM measurement_reference where episode_id = @id);
          SET @measurement_reference_count = (SELECT COUNT(*) FROM measurement_reference WHERE episode_id = @id);
          call extract_row(@measurement_reference_count, @measurement_reference_ids,'openeyes', 'measurement_reference', 'episode_id', @id);
          #call extract_row(@measurement_reference_count, @measurement_reference_ids,'openeyes', 'measurement_reference_version', 'episode_id', @id);

          SET @referral_episode_assignment_ids = (SELECT group_concat(id separator ',') FROM referral_episode_assignment where episode_id = @id);
          SET @referral_episode_assignment_count = (SELECT COUNT(*) FROM referral_episode_assignment WHERE episode_id = @id);
          call extract_row(@referral_episode_assignment_count, @referral_episode_assignment_ids,'openeyes', 'referral_episode_assignment', 'episode_id', @id);
         # call extract_row(@referral_episode_assignment_count, @referral_episode_assignment_ids,'openeyes', 'referral_episode_assignment_version', 'episode_id', @id);
        END IF;

       END WHILE;

    END IF;



  END $$

DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS get_contact_locations;
CREATE DEFINER=`root`@`localhost` PROCEDURE get_contact_locations(
  in_count integer(10),
  in_id_array varchar(200),
  in_db varchar(20),
  in_table varchar(200)
)
  BEGIN
    SET @table = in_table;
    SET @row_ids = concat(',',in_id_array);

    SET @location_id = NULL;
    SET @contact_id = NULL;


    IF ( (in_count > 0) AND (@row_ids IS NOT NULL)) THEN
      WHILE (LOCATE(',', @row_ids) > 0) DO
        SET @row_ids = SUBSTRING(@row_ids, LOCATE(',', @row_ids) + 1);
        SET @id = (SELECT TRIM(SUBSTRING_INDEX(@row_ids, ',', 1)));
        SET @location_ids = NULL;
        SET @location_count = NULL;

        IF (in_table = 'patient_contact_assignment') THEN
          SET @location_id = (SELECT location_id FROM patient_contact_assignment WHERE id = @id);
          SET @contact_id = (SELECT contact_id from contact_location WHERE id = @location_id);
          SET @site_id = (SELECT site_id FROM contact_location WHERE id=@location_id);
          SET @institution_id = (SELECT institution_id FROM contact_location WHERE id = @location_id);

          SET @institiution_contact_id = (SELECT contact_id FROM institution WHERE id = @institution_id);

          call extract_contact(@contact_id);
          call extract_contact(@institiution_contact_id);
          call extract_row(1, @institution_id, 'openeyes', 'institution', 'id', @institution_id);

          SET @site_contact_id = (SELECT contact_id FROM site WHERE id = @site_id);
          SET @site_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @site_contact_id);
          call extract_site(@site_id);
          call extract_row(1, @location_id, 'openeyes', 'contact_location', 'id', @location_id);

        END IF;

      END WHILE;

    END IF;

  END $$
DELIMITER ;

-- Extract one contact --
DELIMITER $$
DROP PROCEDURE IF EXISTS extract_contact;
CREATE DEFINER=`root`@`localhost` PROCEDURE extract_contact(
  in_contact_id integer(10)
)
  BEGIN
    SET @contact_id = in_contact_id;

    SET  @address_count = (SELECT COUNT(*) FROM address WHERE contact_id = @contact_id);
    SET  @address_ids = (SELECT group_concat(id separator ',') FROM address WHERE contact_id = @contact_id);

    SET @contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @contact_id);
    IF( @contact_label_id IS NOT NULL) THEN
      call extract_row(1, @contact_label_id, 'openeyes', 'contact_label', 'id', @contact_label_id);
    END IF;
    call extract_row(1, @contact_id, 'openeyes', 'contact', 'id', @contact_id);
    call extract_row(@address_count, @address_ids, 'openeyes', 'address', 'contact_id', @contact_id);

  END $$

DELIMITER ;

-- Extract one user --
DELIMITER $$
DROP PROCEDURE IF EXISTS extract_user;
CREATE DEFINER=`root`@`localhost` PROCEDURE extract_user(
  in_user_id integer(10)
)
  BEGIN

    SET @user_id = in_user_id;
    IF( @user_id IS NOT NULL) THEN
      call extract_contact((SELECT contact_id FROM user WHERE id = @user_id));
      call extract_row(1, @user_id, 'openeyes', 'user', 'id', @user_id);
    END IF;
  END $$

DELIMITER ;

-- Extract one firm --
DELIMITER $$
DROP PROCEDURE IF EXISTS extract_firm;
CREATE DEFINER=`root`@`localhost` PROCEDURE extract_firm(
  in_firm_id integer(10)
)
  BEGIN

    SET @firm_id = in_firm_id;
    call extract_user((SELECT consultant_id FROM firm WHERE id = @firm_id));
    SET @ssa_id = (SELECT service_subspecialty_assignment_id FROM firm WHERE id = @firm_id);
    IF( @ssa_id IS NOT NULL) THEN
      call extract_row(1, (SELECT service_id FROM service_subspecialty_assignment WHERE id = @ssa_id), 'openeyes', 'service', 'id', (SELECT service_id FROM service_subspecialty_assignment WHERE id = @ssa_id));
      call extract_row(1, (SELECT subspecialty_id FROM service_subspecialty_assignment WHERE id = @ssa_id), 'openeyes', 'subspecialty', 'id', (SELECT subspecialty_id FROM service_subspecialty_assignment WHERE id = @ssa_id));
    END IF;
    call extract_row(1, @firm_id, 'openeyes', 'firm', 'id', @firm_id);
  END $$

DELIMITER ;

-- Extract one site --
DELIMITER $$
DROP PROCEDURE IF EXISTS extract_site;
CREATE DEFINER=`root`@`localhost` PROCEDURE extract_site(
  in_site_id integer(10)
)
  BEGIN

    SET @site_id = in_site_id;
    call extract_contact((SELECT contact_id FROM site WHERE id = @site_id));
    SET @institution_id = (SELECT institution_id FROM site WHERE id = @site_id);
    IF(@institution_id IS NOT NULL) THEN
      call extract_contact((SELECT contact_id FROM institution WHERE id = @institution_id));
      call extract_row(1, (SELECT source_id FROM institution WHERE id = @institution_id), 'openeyes', 'import_source', 'id', (SELECT source_id FROM institution WHERE id = @institution_id));
      call extract_row(1, @institution_id, 'openeyes', 'institution', 'id', @institution_id);
    END IF;

    call extract_contact((SELECT replyto_contact_id FROM site WHERE id = @site_id));
    call extract_row(1, (SELECT source_id FROM site WHERE id = @site_id), 'openeyes', 'import_source', 'id', (SELECT source_id FROM site WHERE id = @site_id));
    call extract_row(1, @site_id, 'openeyes', 'site', 'id', @site_id);
  END $$

DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS get_episode_related_rows;
CREATE DEFINER =`root`@`localhost` PROCEDURE get_episode_related_rows(
  episode_ids text,
  count integer(10)
)
  BEGIN

    SET @count = count;
    SET @ep_ids = concat(',',episode_ids);

    #SELECT @count, @ids;

    if( @count > 0 AND @ep_ids IS NOT NULL) THEN
      WHILE (LOCATE(',', @ep_ids) > 0) DO
        SET @ep_ids = SUBSTRING(@ep_ids, LOCATE(',', @ep_ids) + 1);
        SET @current_id =  (SELECT TRIM(SUBSTRING_INDEX(@ep_ids, ',', 1)));
        SET @current_id = TRIM(@current_id);

        SET @firm_id = (SELECT firm_id FROM episode WHERE id = @current_id );

        IF (@firm_id IS NOT NULL) THEN
          SET @firm_consultant_id = (SELECT consultant_id from firm where id = @firm_id);
        END IF;

        IF (@firm_id IS NOT NULL) THEN
          SET @firm_service_subspecialty_assignment_id = (SELECT service_subspecialty_assignment_id from firm WHERE id = @firm_id);
        END IF;

        IF (@firm_service_subspecialty_assignment_id IS NOT NULL) THEN
          SET @service_subspecialty_assignment_service_id = (SELECT service_id from service_subspecialty_assignment where id = @firm_service_subspecialty_assignment_id);
        END IF;

        IF(@firm_service_subspecialty_assignment_id IS NOT NULL) THEN
          SET @service_subspecialty_assignment_subspecialty_id = (SELECT subspecialty_id FROM service_subspecialty_assignment where id = @firm_service_subspecialty_assignment_id);
        END IF;

        IF (@service_subspecialty_assignment_subspecialty_id IS NOT NULL) THEN
          SET @subspecialty_specialty_id = (SELECT specialty_id FROM subspecialty WHERE id = @service_subspecialty_assignment_subspecialty_id);
        END IF;

        IF (@subspecialty_specialty_id IS NOT NULL) THEN
          SET @specialty_specialty_type_id = (SELECT specialty_type_id FROM specialty WHERE id = @subspecialty_specialty_id);
        END IF;

        IF (@specialty_specialty_type_id IS NOT NULL) THEN
          call extract_row(1, @specialty_specialty_type_id, 'openeyes', 'specialty_specialty_type', 'id', @specialty_specialty_type_id);
        END IF;


        IF (@subspecialty_specialty_id IS NOT NULL) THEN
          call extract_row(1, @subspecialty_specialty_id, 'openeyes', 'subspecialty_specialty', 'id', @specialty_specialty_id);
        END IF;

        IF (@service_subspecialty_assignment_subspecialty_id IS NOT NULL) THEN
          call extract_row(1, @service_subspecialty_assignment_subspecialty_id, 'openeyes', 'service_subspecialty_assignment', 'subspecialty_id', @service_subspecialty_assignment_subspecialty_id);
        END IF;

        IF (@service_subspecialty_assignment_service_id IS NOT NULL) THEN
          call extract_row(1, @service_subspecialty_assignment_service_id, 'openeyes', 'service_subspecialty_assignment', 'service_id', @service_subspecialty_assignment_service_id);
        END IF;

        IF(@firm_service_subspecialty_assignment_id IS NOT NULL) THEN
          call extract_row(1, @firm_service_subspecialty_assignment_id, 'openeyes', 'service_subspecialty_assignment', 'id', @firm_service_subspecialty_assignment_id);
        END IF;

        IF(@firm_consultant_id IS NOT NULL) THEN
          SET @contact_id = (SELECT contact_id FROM user WHERE id = @firm_consultant_id);
          call extract_contact(@contact_id);
          call extract_row(1, @firm_consultant_id, 'openeyes', 'user', 'id', @firm_consultant_id);
        END IF;

        IF (@firm_id IS NOT NULL) THEN
          call extract_row(1, @firm_id, 'openeyes', 'firm', 'id', @firm_id);
        END IF;

      END WHILE;

    END IF;

  END $$
DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS extract_et_data;
CREATE DEFINER =`root`@`localhost` PROCEDURE extract_et_data(
  in_table text,
  ids text,
  count integer(10)
)
  BEGIN
    SET @count = count;
    SET @et_ids = concat(',',ids);
    SET @table = in_table;
    if( @count > 0 AND @et_ids IS NOT NULL) THEN

      WHILE (LOCATE(',', @et_ids) > 0) DO
        SET @et_ids = SUBSTRING(@et_ids, LOCATE(',', @et_ids) + 1);
        SET @current_id =  (SELECT TRIM(SUBSTRING_INDEX(@et_ids, ',', 1)));
        SET @current_id = TRIM(@current_id);
        SET @site_id = NULL;

        IF (@table = 'et_ophtroperationnote_genericprocedure') THEN
          SET @proc_id = (SELECT proc_id FROM et_ophtroperationnote_genericprocedure WHERE id = @current_id);
          call extract_row(1, @proc_id, 'openeyes', 'proc', 'id', @proc_id);
        END IF;

        IF (@table = 'ophtroperationnote_procedurelist_procedure_assignment') THEN
          SET @proc_id = (SELECT proc_id FROM ophtroperationnote_procedurelist_procedure_assignment WHERE id = @current_id);
          call extract_row(1, @proc_id, 'openeyes', 'proc', 'id', @proc_id);
        END IF;

        IF (@table = 'ophtroperationbooking_operation_procedures_procedures') THEN
          SET @proc_id = (SELECT proc_id FROM ophtroperationbooking_operation_procedures_procedures WHERE id = @current_id);
          call extract_row(1, @proc_id, 'openeyes', 'proc', 'id', @proc_id);
        END IF;

        IF (@table = 'et_ophtrlaser_site') THEN
          SET @operator_id = (SELECT operator_id FROM et_ophtrlaser_site WHERE id = @current_id);
          call extract_user(@operator_id);

          SET @laser_id = (SELECT laser_id FROM et_ophtrlaser_site WHERE id = @current_id);
          call extract_row(1, @laser_id, 'openeyes', 'ophtrlaser_site_laser', 'id', @laser_id);
        END IF;

        IF (@table = 'et_ophtrintravitinjection_site') THEN
            SET @site_id = (SELECT site_id FROM et_ophtrintravitinjection_site WHERE id = @current_id);
        END IF;

        IF (@table = 'et_ophtroperationbooking_operation') THEN

          SET @site_id = (SELECT site_id FROM et_ophtroperationbooking_operation WHERE id = @current_id);

          SET @cancellation_user_id = (SELECT cancellation_user_id FROM et_ophtroperationbooking_operation WHERE id = @current_id);

          call extract_user(@cancellation_user_id);

          SET @cancellation_reason_id = (SELECT cancellation_reason_id FROM et_ophtroperationbooking_operation WHERE id = @current_id);

          IF (@cancellation_reason_id IS NOT NULL) THEN
            call extract_row(1, @cancellation_reason_id, 'openeyes', 'ophtroperationbooking_operation_cancellation_reason', 'id', @cancellation_reason_id);
          END IF;

          call extract_row(1, @current_id, 'openeyes', 'et_ophtroperationbooking_operation', 'id', @current_id);

          SET @booking_count = (SELECT count(id) FROM ophtroperationbooking_operation_booking WHERE element_id = @current_id);
          SET @booking_ids = (SELECT concat(',',group_concat(id separator ',')) FROM ophtroperationbooking_operation_booking WHERE element_id = @current_id);

          IF(@booking_ids IS NOT NULL) THEN
             WHILE (LOCATE(',', @booking_ids) > 0) DO
                SET @booking_ids = SUBSTRING(@booking_ids, LOCATE(',', @booking_ids) + 1);
                SET @booking_id =  (SELECT TRIM(SUBSTRING_INDEX(@booking_ids, ',', 1)));
                SET @booking_id = TRIM(@booking_id);

                -- ophtroperationbooking_operation_booking start

                -- session start
                SET @firm_id = (SELECT firm_id FROM ophtroperationbooking_operation_session WHERE id = (SELECT session_id FROM ophtroperationbooking_operation_booking WHERE id= @booking_id));
                IF(@firm_id IS NOT NULL) THEN
                  call extract_firm(@firm_id);
                END IF;

                -- sequence start

                SET @sequence_id=(SELECT sequence_id FROM ophtroperationbooking_operation_session WHERE id=( SELECT session_id FROM ophtroperationbooking_operation_booking WHERE id= @booking_id));
                call extract_firm((SELECT firm_id FROM ophtroperationbooking_operation_sequence WHERE id = @sequence_id));

                call extract_row(1, (SELECT interval_id FROM ophtroperationbooking_operation_sequence WHERE id = @sequence_id), 'openeyes', 'ophtroperationbooking_operation_sequence_interval', 'id', (SELECT interval_id FROM ophtroperationbooking_operation_sequence WHERE id = @sequence_id));

                SET @theatre_id = (SELECT theatre_id FROM ophtroperationbooking_operation_sequence WHERE id = @sequence_id);
                call extract_site((SELECT site_id FROM ophtroperationbooking_operation_theatre WHERE id = @theatre_id));
                SET @ward_id = (SELECT ward_id FROM ophtroperationbooking_operation_theatre WHERE id = @theatre_id);
                call extract_site((SELECT site_id FROM ophtroperationbooking_operation_ward WHERE id = @ward_id));
                call extract_row(1, @ward_id, 'openeyes', 'ophtroperationbooking_operation_ward', 'id', @ward_id);
                call extract_row(1, @theatre_id, 'openeyes', 'ophtroperationbooking_operation_theatre', 'id', @theatre_id);

                call extract_row(1, @sequence_id, 'openeyes', 'ophtroperationbooking_operation_sequence', 'id', @sequence_id);
                -- sequence end


                -- cancellation start
                SET @booking_cancellation_id = (SELECT cancellation_reason_id FROM ophtroperationbooking_operation_booking WHERE id = @booking_id);
                call extract_row(1, @booking_cancellation_id, 'openeyes', 'ophtroperationbooking_operation_cancellation_reason', 'id', @booking_cancellation_id);



                SET @theatre_id2 = (SELECT theatre_id FROM ophtroperationbooking_operation_session WHERE id= @booking_id);
                call extract_site((SELECT site_id FROM ophtroperationbooking_operation_theatre WHERE id = @theatre_id2));
                SET @ward_id2 = (SELECT ward_id FROM ophtroperationbooking_operation_theatre WHERE id = @theatre_id2);
                call extract_site((SELECT site_id FROM ophtroperationbooking_operation_ward WHERE id = @ward_id2));
                call extract_row(1, @ward_id2, 'openeyes', 'ophtroperationbooking_operation_ward', 'id', @ward_id2);

                call extract_row(1, @theatre_id2, 'openeyes', 'ophtroperationbooking_operation_theatre', 'id', @theatre_id2);

                call extract_row(1, (SELECT unavailablereason_id FROM ophtroperationbooking_operation_session WHERE id= ( SELECT session_id FROM ophtroperationbooking_operation_booking WHERE id= @booking_id)), 'openeyes', 'ophtroperationbooking_operation_session_unavailreason', 'id', (SELECT unavailablereason_id FROM ophtroperationbooking_operation_session WHERE id= ( SELECT session_id FROM ophtroperationbooking_operation_booking WHERE id= @booking_id)));
                call extract_row(1, (SELECT session_id FROM ophtroperationbooking_operation_booking WHERE id= @booking_id), 'openeyes', 'ophtroperationbooking_operation_session', 'id', (SELECT session_id FROM ophtroperationbooking_operation_booking WHERE id= @booking_id));
                -- session end

                -- session_theatre_id for booking start
                SET @theatre_id3= (SELECT session_theatre_id FROM ophtroperationbooking_operation_booking WHERE id = @booking_id);
                call extract_site((SELECT site_id FROM ophtroperationbooking_operation_theatre WHERE id = @theatre_id3));
                SET @ward_id3 = (SELECT ward_id FROM ophtroperationbooking_operation_theatre WHERE id = @theatre_id3);
                call extract_site((SELECT site_id FROM ophtroperationbooking_operation_ward WHERE id = @ward_id3));
                call extract_row(1, @ward_id3, 'openeyes', 'ophtroperationbooking_operation_ward', 'id', @ward_id3);

                call extract_row(1, @theatre_id3, 'openeyes', 'ophtroperationbooking_operation_theatre', 'id', @theatre_id3);
                -- session_theatre_id for booking_booking end

                SET @ward_id4 = (SELECT ward_id FROM ophtroperationbooking_operation_booking WHERE id = @booking_id);
                call extract_site((SELECT site_id FROM ophtroperationbooking_operation_ward WHERE id = @ward_id4));
                call extract_row(1, @ward_id4, 'openeyes', 'ophtroperationbooking_operation_ward', 'id', @ward_id4);

                call extract_user((SELECT cancellation_user_id FROM ophtroperationbooking_operation_booking WHERE id=@booking_id));

                SET @element_id = (SELECT element_id from ophtroperationbooking_operation_booking WHERE id = @booking_id);
                call extract_row(1, @element_id, 'openeyes', 'et_ophtroperationbooking_operation', 'id', @element_id);


                call extract_row(1, @booking_id, 'openeyes', 'ophtroperationbooking_operation_booking', 'id', @booking_id);
                 -- ophtroperationbooking_operation_booking end

            END WHILE;
          END IF;


        END IF;

        IF(@site_id IS NOT NULL) THEN
          SET @contact_id = (SELECT contact_id FROM site WHERE id = @site_id);
          SET @replyto_contact_id = (SELECT replyto_contact_id FROM site WHERE id = @site_id);


          IF (@contact_id IS NOT NULL) THEN
            call extract_contact(@contact_id);
          END IF;



          IF (@replyto_contact_id IS NOT NULL) THEN
            call extract_contact(@replyto_contact_id);
          END IF;

          IF(@site_id IS NOT NULL) THEN
            call extract_row(1, @site_id, 'openeyes', 'site', 'id', @site_id);
          END IF;

        END IF;


       IF (@table = 'et_ophtrintravitinjection_postinject') THEN

          SET @right_drops_id = (SELECT right_drops_id FROM et_ophtrintravitinjection_postinject WHERE id = @current_id);
          SET @left_drops_id = (SELECT left_drops_id FROM et_ophtrintravitinjection_postinject WHERE id = @current_id);

          IF(@right_drops_id IS NOT NULL) THEN
            call extract_row(1, @right_drops_id, 'openeyes', 'ophtrintravitinjection_postinjection_drops', 'id', @right_drops_id);
          END IF;

          IF(@left_drops_id IS NOT NULL) THEN
            call extract_row(1, @left_drops_id, 'openeyes', 'ophtrintravitinjection_postinjection_drops', 'id', @left_drops_id);
          END IF;

        END IF;

        IF (@table = 'et_ophcotherapya_patientsuit') THEN

          SET @left_treatment_id = (SELECT left_treatment_id FROM et_ophcotherapya_patientsuit WHERE id = @current_id);
          SET @right_treatment_id = (SELECT right_treatment_id FROM et_ophcotherapya_patientsuit WHERE id = @current_id);

          IF(@left_treatment_id IS NOT NULL) THEN
              SET @decisiontree_id = (SELECT decisiontree_id FROM ophcotherapya_treatment WHERE id = @left_treatment_id);
              IF( @decisiontree_id IS NOT NULL ) THEN
                call extract_row(1,@decisiontree_id, 'openeyes', 'ophcotherapya_decisiontree', 'id', @decisiontree_id );
              END IF;
              call extract_row(1,@left_treatment_id, 'openeyes', 'ophcotherapya_treatment', 'id', @left_treatment_id );
          END IF;

          IF(@right_treatment_id IS NOT NULL) THEN
            SET @decisiontree_id = (SELECT decisiontree_id FROM ophcotherapya_treatment WHERE id = @right_treatment_id);
            IF( @decisiontree_id IS NOT NULL ) THEN
              call extract_row(1,@decisiontree_id, 'openeyes', 'ophcotherapya_decisiontree', 'id', @decisiontree_id );
            END IF;
            call extract_row(1,@right_treatment_id, 'openeyes', 'ophcotherapya_treatment', 'id', @right_treatment_id );
          END IF;

        END IF;

        IF (@table = 'et_ophtrintravitinjection_anaesthetic') THEN
          SET @left_anaestheticagent_id = (SELECT left_anaestheticagent_id FROM et_ophtrintravitinjection_anaesthetic WHERE id = @current_id);
          SET @right_anaestheticagent_id = (SELECT right_anaestheticagent_id FROM et_ophtrintravitinjection_anaesthetic WHERE id = @current_id);

          IF(@left_anaestheticagent_id IS NOT NULL) THEN
            call extract_row(1,@left_anaestheticagent_id, 'openeyes', 'anaesthetic_agent', 'id', @left_anaestheticagent_id );
          END IF;

          IF(@right_anaestheticagent_id IS NOT NULL) THEN
            call extract_row(1,@right_anaestheticagent_id, 'openeyes', 'anaesthetic_agent', 'id', @right_anaestheticagent_id );
          END IF;
        END IF;
      END WHILE;
    END IF;
  END $$
DELIMITER ;


-- Lists all event id related elements : calls extract_row() --
DROP PROCEDURE IF EXISTS get_events;
DELIMITER $$
CREATE DEFINER =`root`@`localhost` PROCEDURE get_events(
  episode_count integer(10),
  episode_id_array varchar(200),
  in_db varchar(20),
  in_table varchar(200),
  patient_id varchar(50)
)
  BEGIN
    SET @episode_row_ids = concat(',',episode_id_array);
    SET @episode_count = episode_count;

    # A loop events for every episode

    IF ( @episode_count > 0) THEN
      WHILE (LOCATE(',', @episode_row_ids) > 0) DO
        SET @episode_row_ids = SUBSTRING(@episode_row_ids, LOCATE(',', @episode_row_ids) + 1);
        SET @episode_id = (SELECT TRIM(SUBSTRING_INDEX(@episode_row_ids, ',', 1)));

        # Event ids array and count
        SET @event_ids = (SELECT group_concat(id separator ',') FROM event WHERE episode_id = @episode_id);
        SET @event_count = (SELECT COUNT(*) FROM event WHERE episode_id = @episode_id);

        SET @event_ids = concat(',', @event_ids);

        # Cycle through the event ids to get the related event data
        WHILE (LOCATE(',', @event_ids) > 0) DO
          SET @event_ids = SUBSTRING(@event_ids, LOCATE(',', @event_ids) + 1);
          SET @id = (SELECT TRIM(SUBSTRING_INDEX(@event_ids, ',', 1)));
          SET @count = 0;
          SET @ids = NULL;

          SET @event_type = (SELECT class_name FROM event_type WHERE id = (SELECT event_type_id FROM `event` WHERE id = @id));

          #SELECT @episode_count,@event_count,@event_ids, @id as current_id;

          SET  @count = (SELECT COUNT(*) FROM event_issue WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM event_issue WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'event_issue', 'event_id', @id);

          IF(@event_type = 'OphCiExamination') THEN
              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_adnexalcomorbidity WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_adnexalcomorbidity WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_adnexalcomorbidity', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_history WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_history WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_history', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_anteriorsegment WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_anteriorsegment WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_anteriorsegment', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_anteriorsegment_cct WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_anteriorsegment WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_anteriorsegment_cct', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_anteriorsegment_cct_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_bleb_assessment WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_bleb_assessment WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_bleb_assessment', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_cataractsurgicalmanagement WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_cataractsurgicalmanagement WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_cataractsurgicalmanagement', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_cataractsurgicalmanagement_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_clinicoutcome WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_clinicoutcome WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_clinicoutcome', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_clinicoutcome_version', 'event_id', @id);
              END IF;


              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_colourvision WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_colourvision WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_colourvision', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_colourvision_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_comorbidities WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_comorbidities WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_comorbidities', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_comorbidities_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_conclusion WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_conclusion WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_conclusion', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_conclusion_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_currentmanagementplan WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_currentmanagementplan WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_currentmanagementplan', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_currentmanagementplan_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_diagnoses WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_diagnoses WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_diagnoses', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_diagnoses_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM ophciexamination_diagnosis WHERE element_diagnoses_id = (SELECT id FROM et_ophciexamination_diagnoses WHERE event_id = @id));
              SET  @ids = (SELECT group_concat(id separator ',') FROM ophciexamination_diagnosis WHERE element_diagnoses_id = (SELECT id FROM et_ophciexamination_diagnoses WHERE event_id = @id));
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'ophciexamination_diagnosis','element_diagnoses_id', (SELECT id FROM et_ophciexamination_diagnoses WHERE event_id = @id));
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_dilation WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_dilation WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_dilation', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_dilation_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM ophciexamination_dilation_treatment WHERE element_id = (SELECT id FROM et_ophciexamination_dilation WHERE event_id = @id));
              SET  @ids = (SELECT group_concat(id separator ',') FROM ophciexamination_dilation_treatment WHERE element_id = (SELECT id FROM et_ophciexamination_dilation WHERE event_id = @id));
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'ophciexamination_dilation_treatment','element_id', (SELECT id FROM et_ophciexamination_dilation WHERE event_id = @id));
              END IF;


              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_further_findings WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_further_findings WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_further_findings', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_further_findings_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_glaucomarisk WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_glaucomarisk WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_glaucomarisk', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_glaucomarisk_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_gonioscopy WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_gonioscopy WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_gonioscopy', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_gonioscopy_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_injectionmanagement WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_injectionmanagement WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_injectionmanagement', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_injectionmanagement_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_injectionmanagementcomplex WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_injectionmanagementcomplex WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_injectionmanagementcomplex', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_injectionmanagementcomplex_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_intraocularpressure WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_intraocularpressure WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_intraocularpressure', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_intraocularpressure_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM ophciexamination_intraocularpressure_value WHERE element_id = (SELECT id FROM et_ophciexamination_intraocularpressure WHERE event_id = @id));
              SET  @ids = (SELECT group_concat(id separator ',') FROM ophciexamination_intraocularpressure_value WHERE element_id = (SELECT id FROM et_ophciexamination_intraocularpressure WHERE event_id = @id));
              SET @tmp_ids = concat(',',@ids);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                WHILE (LOCATE(',', @booking_ids) > 0) DO
                  SET @tmp_ids = SUBSTRING(@tmp_ids, LOCATE(',', @tmp_ids) + 1);
                  SET @tmp_id =  (SELECT TRIM(SUBSTRING_INDEX(@tmp_ids, ',', 1)));
                  SET @tmp_id = TRIM(@tmp_id);

                  SET @instrument_id = (SELECT instrument_id FROM ophciexamination_intraocularpressure_value WHERE id = @tmp_id);
                  call extract_row(1,@instrument_id, 'openeyes', 'ophciexamination_instrument', 'id', @instrument_id);

                END WHILE;
                call extract_row(@count, @ids,'openeyes', 'ophciexamination_intraocularpressure_value','element_id', (SELECT id FROM et_ophciexamination_intraocularpressure WHERE event_id = @id));
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_investigation WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_investigation WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_investigation', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_investigation_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_lasermanagement WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_lasermanagement WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_lasermanagement', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_lasermanagement_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_management WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_management WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_management', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_management_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_oct WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_oct WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_oct', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_oct_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_opticdisc WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_opticdisc WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_opticdisc', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_opticdisc_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_overallmanagementplan WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_overallmanagementplan WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_overallmanagementplan', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_overallmanagementplan_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_posteriorpole WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_posteriorpole WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_posteriorpole', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_posteriorpole_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_pupillaryabnormalities WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_pupillaryabnormalities WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_pupillaryabnormalities' , 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_pupillaryabnormalities_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_refraction WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_refraction WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_refraction', 'event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_refraction_version', 'event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_risks WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_risks WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_risks','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_risks_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_visualacuity WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_visualacuity WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_visualacuity','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_visualacuity_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM ophciexamination_visualacuity_reading WHERE element_id = (SELECT id FROM et_ophciexamination_visualacuity WHERE event_id = @id));
              SET  @ids = (SELECT group_concat(id separator ',') FROM ophciexamination_visualacuity_reading WHERE element_id = (SELECT id FROM et_ophciexamination_visualacuity WHERE event_id = @id));
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'ophciexamination_visualacuity_reading','element_id', (SELECT id FROM et_ophciexamination_visualacuity WHERE event_id = @id));
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_visualfunction WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_visualfunction WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_visualfunction','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_visualfunction_version','event_id', @id);
              END IF;
          END IF;


          IF (@event_type = 'OphCiPhasing') THEN
              SET  @count = (SELECT COUNT(*) FROM et_ophciphasing_intraocularpressure WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciphasing_intraocularpressure WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophciphasing_intraocularpressure','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophciphasing_intraocularpressure_version','event_id', @id);
              END IF;
          END IF;

          IF (@event_type = 'OphCoCorrespondence') THEN
              SET  @count = (SELECT COUNT(*) FROM et_ophcocorrespondence_letter WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcocorrespondence_letter WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_site((SELECT site_id FROM et_ophcocorrespondence_letter WHERE event_id = @id));
                call extract_row(@count, @ids,'openeyes', 'et_ophcocorrespondence_letter','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophcocorrespondence_letter_version','event_id', @id);
              END IF;
          END IF;


          IF (@event_type = 'OphCoTherapyapplication') THEN
              SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_exceptional WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_exceptional WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_exceptional','event_id', @id);
                # call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_exceptional_version','event_id', @id);
              END IF;

              SET @count = (SELECT COUNT(*) FROM et_ophcotherapya_mrservicein WHERE event_id = @id);
              SET @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_mrservicein WHERE event_id=@id);
              SET @et_ids = concat(',',@ids);

              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                WHILE (LOCATE(',', @et_ids) > 0) DO
                  SET @et_ids = SUBSTRING(@et_ids, LOCATE(',', @et_ids) + 1);
                  SET @et_id = (SELECT TRIM(SUBSTRING_INDEX(@et_ids, ',', 1)));
                  SET @et_id = TRIM(@et_id);

                  SET @firm_id = (SELECT consultant_id FROM et_ophcotherapya_mrservicein WHERE id = @et_id);
                  call extract_firm(@firm_id);

                END WHILE;
                call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_mrservicein','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_mrservicein_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_patientsuit WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_patientsuit WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_et_data('et_ophcotherapya_patientsuit', @ids, @count);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_patientsuit WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_patientsuit WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN

                call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_patientsuit','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_patientsuit_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_relativecon WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_relativecon WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_relativecon','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_relativecon_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_therapydiag WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_therapydiag WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_therapydiag','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_therapydiag_version','event_id', @id);
              END IF;
          END IF;


          IF (@event_type = 'OphDrPrescription') THEN
              SET  @count = (SELECT COUNT(*) FROM et_ophdrprescription_details WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophdrprescription_details WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophdrprescription_details','event_id', @id);
              END IF;

              SET  @pr_item_count = (SELECT COUNT(*) FROM ophdrprescription_item WHERE prescription_id = (SELECT id FROM et_ophdrprescription_details WHERE event_id = @id));
              SET  @pr_item_ids = (SELECT concat(',',group_concat(id separator ',')) FROM ophdrprescription_item WHERE prescription_id = (SELECT id FROM et_ophdrprescription_details WHERE event_id = @id));

              IF ((@pr_item_count > 0) AND (@pr_item_ids IS NOT NULL)) THEN
                WHILE (LOCATE(',', @pr_item_ids) > 0) DO
                  SET @pr_item_ids = SUBSTRING(@pr_item_ids, LOCATE(',', @pr_item_ids) + 1);
                  SET @pr_item_id = (SELECT TRIM(SUBSTRING_INDEX(@pr_item_ids, ',', 1)));

                  SET @frequency_id = (SELECT frequency_id FROM ophdrprescription_item WHERE id = @pr_item_id);
                  SET @duration_id = (SELECT duration_id FROM ophdrprescription_item WHERE id = @pr_item_id);
                  SET @drug_id = (SELECT drug_id FROM ophdrprescription_item WHERE id = @pr_item_id);
                  SET @route_id = (SELECT route_id FROM ophdrprescription_item WHERE id = @pr_item_id);

                  SET @prescription_id = (SELECT id FROM et_ophdrprescription_details WHERE event_id = @id);

                  IF(@frequency_id IS NOT NULL) THEN
                    call extract_row(1, @frequency_id, 'openeyes', 'drug_frequency','id', @frequency_id);
                  END IF;

                  IF(@duration_id IS NOT NULL) THEN
                    call extract_row(1, @duration_id,'openeyes', 'drug_duration','id', @duration_id);
                  END IF;

                  IF (@drug_id IS NOT NULL) THEN
                    call extract_row(1, @drug_id, 'openeyes', 'drug','id', @drug_id);
                  END IF;

                  IF (@route_id IS NOT NULL) THEN
                    call extract_row(1, @route_id, 'openeyes', 'drug_route','id', @route_id);
                  END IF;

                END WHILE;

                SET @pr_ids_count = (SELECT COUNT(*) FROM ophdrprescription_item WHERE prescription_id = @prescription_id);
                SET @pr_ids = (SELECT group_concat(id separator ',') FROM ophdrprescription_item WHERE prescription_id = @prescription_id);
                call extract_row(@pr_ids_count, @pr_ids,'openeyes', 'ophdrprescription_item','prescription_id', @prescription_id);
              END IF;
          END IF;

          # TODO: Tapers here!!!

          #SET  @count = (SELECT COUNT(*) FROM ophdrprescription_item_taper WHERE event_id = @id);
          #SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophdrprescription_details WHERE event_id=@id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophdrprescription_details','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophdrprescription_details_version','event_id', @id);


          -- Module currently in progress --
          /*SET  @count = (SELECT COUNT(*) FROM et_ophinbiometry_biometrydat WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophinbiometry_biometrydat WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophinbiometry_biometrydat','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophinbiometry_biometrydat_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophinbiometry_calculation WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophinbiometry_calculation WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophinbiometry_calculation','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophinbiometry_calculation_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophinbiometry_lenstype WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophinbiometry_lenstype WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophinbiometry_lenstype','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophinbiometry_lenstype_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophinbiometry_selection  WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophinbiometry_selection WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophinbiometry_selection','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophinbiometry_selection_version','event_id', @id);

          */


          IF(@event_type = 'OphOuAnaestheticsatisfactionaudit') THEN
              SET  @count = (SELECT COUNT(*) FROM et_ophouanaestheticsataudit_anaesthetis WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophouanaestheticsataudit_anaesthetis WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_anaesthetis','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_anaesthetis_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophouanaestheticsataudit_notes WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophouanaestheticsataudit_notes WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_notes','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_notes_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophouanaestheticsataudit_satisfactio WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophouanaestheticsataudit_satisfactio WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_satisfactio','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_satisfactio_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophouanaestheticsataudit_vitalsigns WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophouanaestheticsataudit_vitalsigns WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_vitalsigns','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_vitalsigns_version','event_id', @id);
              END IF;
          END IF;


          IF (@event_type = 'OphTrConsent') THEN
              SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_benfitrisk WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_benfitrisk WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_benfitrisk','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_benfitrisk_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_leaflets WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_leaflets WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_leaflets','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_leaflets_version','event_id', @id);

                SET @consent_consultant_id  = (SELECT consultant_id FROM et_ophtrconsent_other WHERE event_id=@id);
                SET @consent_contact_id = (SELECT contact_id FROM user WHERE id = @consent_consultant_id);
                call extract_contact(@consent_contact_id);
                call extract_row(1, @consent_consultant_id, 'openeyes', 'user', 'id', @consent_consultant_id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_other WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_other WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_other','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_other_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_permissions WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_permissions WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_permissions','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_permissions_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_procedure WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_procedure WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_procedure','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_procedure_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_type WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_type WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_type','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_type_version ','event_id', @id);
              END IF;
          END IF;


          IF (@event_type = 'OphTrIntravitrealinjection') THEN

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_anaesthetic WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_anaesthetic WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_et_data('et_ophtrintravitinjection_anaesthetic', @ids, @count);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_anaesthetic WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_anaesthetic WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_anaesthetic','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_anaesthetic_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_anteriorseg WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_anteriorseg WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_anteriorseg','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_anteriorseg_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_complications WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_complications WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_complications','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_complications_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_postinject WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_postinject WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_et_data('et_ophtrintravitinjection_postinject', @ids, @count);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_postinject WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_postinject WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_postinject','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_postinject_version','event_id', @id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_site WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_site WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_et_data('et_ophtrintravitinjection_site', @ids, @count);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_site WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_site WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
                call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_site','event_id', @id);
                #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_site_version','event_id', @id);
              END IF;


              SET @right_injection_given_by_id = (SELECT right_injection_given_by_id FROM et_ophtrintravitinjection_treatment WHERE event_id=@id);
              SET @left_injection_given_by_id = (SELECT left_injection_given_by_id FROM et_ophtrintravitinjection_treatment WHERE event_id=@id);

              IF(@right_injection_given_by_id IS NOT NULL) THEN
                  SET @right_contact_id = (SELECT contact_id FROM user WHERE id = @right_injection_given_by_id);


                  call extract_contact(@right_contact_id);
                  call extract_row(1, @right_injection_given_by_id, 'openeyes', 'user', 'id', @right_injection_given_by_id);

              END IF;

              IF(@left_injection_given_by_id IS NOT NULL) THEN
                  SET @left_contact_id = (SELECT contact_id FROM user WHERE id = @left_injection_given_by_id);
                  SET @left_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @left_contact_id);

                  #SELECT @left_injection_given_by_id, @left_contact_id, @left_contact_label_id;

                  call extract_row(1, @left_contact_label_id, 'openeyes', 'contact_label', 'id', @left_contact_label_id);
                  call extract_row(1, @left_contact_id, 'openeyes', 'contact', 'id', @left_contact_id);
                  call extract_row(1, @left_injection_given_by_id, 'openeyes', 'user', 'id', @left_injection_given_by_id);
              END IF;

              SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_treatment WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_treatment WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_treatment','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_treatment_version','event_id', @id);
            END IF;
          END IF;

          IF(@event_type = 'OphTrLaser') THEN
            SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_anteriorseg WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_anteriorseg WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_anteriorseg','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_anteriorseg_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_comments WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_comments WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_comments','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_comments_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_fundus WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_fundus WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_fundus','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_fundus_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_posteriorpo WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_posteriorpo WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_posteriorpo','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_posteriorpo_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_site WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_site WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN

              call extract_et_data('et_ophtrlaser_site', @ids, @count);
              call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_site','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_site_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_treatment WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_treatment WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_treatment','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_treatment_version','event_id', @id);
            END IF;
          END IF;


          IF(@event_type = 'OphTrOperationbooking') THEN

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationbooking_diagnosis WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationbooking_diagnosis WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_diagnosis','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_diagnosis_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationbooking_operation WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationbooking_operation WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              SET @site_id = (SELECT site_id FROM et_ophtroperationbooking_operation WHERE event_id=@id );
              call extract_site(@site_id);
              call extract_et_data('et_ophtroperationbooking_operation', @ids, @count);
            END IF;


            #SET  @count = (SELECT COUNT(*) FROM et_ophtroperationbooking_operation WHERE event_id = @id);
            #SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationbooking_operation WHERE event_id=@id);
            #IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_operation','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_operation_version','event_id', @id);
            #END IF;

            SET  @count = (SELECT COUNT(*) FROM ophtroperationbooking_operation_procedures_procedures WHERE element_id = (SELECT id FROM et_ophtroperationbooking_operation WHERE event_id = @id));
            SET  @ids = (SELECT group_concat(id separator ',') FROM ophtroperationbooking_operation_procedures_procedures WHERE element_id = (SELECT id FROM et_ophtroperationbooking_operation WHERE event_id = @id));
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_et_data('ophtroperationbooking_operation_procedures_procedures', @ids, @count);
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_operation','id', (SELECT id FROM et_ophtroperationbooking_operation WHERE event_id = @id));
              call extract_row(@count, @ids,'openeyes', 'ophtroperationbooking_operation_procedures_procedures','element_id', (SELECT id FROM et_ophtroperationbooking_operation WHERE event_id = @id));
            END IF;


            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationbooking_scheduleope WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationbooking_scheduleope WHERE event_id=@id);

            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              SET @schedule_options_id = (SELECT schedule_options_id FROM et_ophtroperationbooking_scheduleope WHERE event_id = @id);
              IF(@schedule_options_id IS NOT NULL) THEN
                call extract_row(1,@schedule_options_id, 'openeyes', 'ophtroperationbooking_scheduleope_schedule_options','id',@schedule_options_id);
              END IF;

              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_scheduleope','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_scheduleope_version','event_id', @id);
            END IF;

          END IF;


          if( @event_type = 'OphTrOperationnote') THEN

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_anaesthetic WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_anaesthetic WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_anaesthetic','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_anaesthetic_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_buckle WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_buckle WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_buckle ','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_buckle_version','event_id', @id);
            END IF;



            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_cataract WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_cataract WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              SET @iol_type_id = (SELECT iol_type_id FROM et_ophtroperationnote_cataract WHERE event_id = @id);
              IF(@iol_type_id IS NOT NULL) THEN
                call extract_row(1, @iol_type_id, 'openeyes', 'ophtroperationnote_cataract_iol_type', 'id', @iol_type_id);
              END IF;
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_cataract','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_cataract_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_comments WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_comments WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_comments','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_comments_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_genericprocedure WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_genericprocedure WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_et_data('et_ophtroperationnote_genericprocedure', @ids, @count);
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_genericprocedure','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_genericprocedure_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_glaucomatube WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_glaucomatube WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_glaucomatube','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_glaucomatube_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_membrane_peel WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_membrane_peel WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_membrane_peel','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_membrane_peel_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_mmc WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_mmc WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_mmc','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_mmc_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_personnel WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_personnel WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_personnel','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_personnel_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_postop_drugs WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_postop_drugs WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_postop_drugs','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_postop_drugs_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_preparation WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_preparation WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_preparation','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_preparation_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_procedurelist WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_procedurelist WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_procedurelist','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_procedurelist_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM ophtroperationnote_procedurelist_procedure_assignment WHERE procedurelist_id = (SELECT id FROM et_ophtroperationnote_procedurelist WHERE event_id = @id));
            SET  @ids = (SELECT group_concat(id separator ',') FROM ophtroperationnote_procedurelist_procedure_assignment WHERE procedurelist_id = (SELECT id FROM et_ophtroperationnote_procedurelist WHERE event_id = @id));
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'ophtroperationnote_procedurelist_procedure_assignment','procedurelist_id', (SELECT id FROM et_ophtroperationnote_procedurelist WHERE event_id = @id));
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_surgeon WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_surgeon WHERE event_id=@id);

            SET @surg_event_ids = concat(',', @ids);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              WHILE (LOCATE(',', @surg_event_ids) > 0) DO
                SET @surg_event_ids = SUBSTRING(@surg_event_ids, LOCATE(',', @surg_event_ids) + 1);
                SET @surg_event_id = (SELECT TRIM(SUBSTRING_INDEX(@surg_event_ids, ',', 1)));
                SET @surg_event_id = TRIM(@surg_event_id);

                SET @user_id = (SELECT surgeon_id FROM et_ophtroperationnote_surgeon WHERE id = @surg_event_id);
                call extract_user(@user_id);

              END WHILE;

              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_surgeon','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_surgeon_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_tamponade WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_tamponade WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_tamponade','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_tamponade_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_trabectome WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_trabectome WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_trabectome','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_trabectome_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_trabeculectomy WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_trabeculectomy WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_trabeculectomy ','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_trabeculectomy_version','event_id', @id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_vitrectomy WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_vitrectomy WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
              call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_vitrectomy','event_id', @id);
              #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_vitrectomy_version','event_id', @id);
            END IF;

          END IF;


        END WHILE;



      END WHILE;
    END IF;
  END $$


DELIMITER ;



-- Calls extract_row and get_events --
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

    SELECT @file AS OUTPUT_FILE;


    INSERT INTO patient_data_extract VALUES ('', 'ALTER TABLE `event` CHANGE created_user_id created_user_id int(10) unsigned not null default 1;');
    INSERT INTO patient_data_extract VALUES ('', 'ALTER TABLE `event` MODIFY COLUMN created_user_id int(10) unsigned not null default 1;');
    INSERT INTO patient_data_extract VALUES ('', 'ALTER TABLE specialty CHANGE created_user_id created_user_id int(10) unsigned not null default 1;');
    INSERT INTO patient_data_extract VALUES ('', 'ALTER TABLE specialty CHANGE last_modified_user_id last_modified_user_id int(10) unsigned not null default 1;');


    SET @patient_id = (SELECT id FROM patient WHERE hos_num=hospital_number);
    SET @gp_id = (SELECT gp_id FROM patient WHERE id = @patient_id);
    SET @gp_contact_id = (SELECT contact_id FROM gp WHERE id = @gp_id);
    SET @practice_id = (SELECT practice_id from patient where id = @patient_id);
    SET @practice_contact_id = (SELECT contact_id from practice WHERE id = @practice_id);


    -- Get all the fields that are indirectly related to the patient --
    call extract_contact(@practice_contact_id);
    call extract_row(1, @practice_id, 'openeyes', 'practice', 'id', @practice_id);

    call extract_contact(@gp_contact_id);
    call extract_row(1, @gp_id, 'openeyes', 'gp', 'id', @gp_id);


    -- Start creating the inserts --
    SET @contact_id = (SELECT contact_id FROM patient WHERE hos_num=hospital_number);
    SET  @count = (SELECT COUNT(*) FROM contact WHERE id = @contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM contact WHERE id=@contact_id);
    call extract_row(@count, @ids,'openeyes', 'contact', 'id', @contact_id);



    SET  @count = (SELECT COUNT(*) FROM address WHERE contact_id = @contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM address WHERE contact_id=@contact_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'address', 'contact_id', @contact_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM address WHERE contact_id = @practice_contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM address WHERE contact_id=@practice_contact_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'address', 'contact_id', @practice_contact_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM address WHERE contact_id = @gp_contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM address WHERE contact_id=@gp_contact_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'address', 'contact_id', @gp_contact_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM `patient` WHERE id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM `patient` WHERE hos_num=hospital_number );
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'patient', 'id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM episode WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call get_episode_related_rows(@ids, @count);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM episode WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'episode', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM family_history WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM family_history WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'family_history', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM medication WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'family_history', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM medication_adherence WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication_adherence WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'medication_adherence', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM patient_allergy_assignment WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_allergy_assignment WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'patient_allergy_assignment', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM patient_contact_assignment WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_contact_assignment WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call get_contact_locations(@count, @ids, 'openeyes', 'patient_contact_assignment');
      call extract_row(@count, @ids,'openeyes', 'patient_contact_assignment', 'patient_id', @patient_id);
      call extract_dependant_row(@count, @ids,'openeyes', 'patient_contact_assignment', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM patient_measurement WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_measurement WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'patient_measurement', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM previous_operation WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM previous_operation WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'previous_operation', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM referral WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM referral WHERE patient_id = @patient_id);
    SET @ref_ids = concat(',',@ids);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      WHILE (LOCATE(',', @ref_ids) > 0) DO
        SET @ref_ids = SUBSTRING(@ref_ids, LOCATE(',', @ref_ids) + 1);
        SET @ref_id =  (SELECT TRIM(SUBSTRING_INDEX(@ref_ids, ',', 1)));
        SET @ref_id = TRIM(@ref_id);

        SET @referral_type_id = (SELECT referral_type_id FROM referral WHERE id = @ref_id);
        call extract_row(1, @referral_type_id, 'openeyes', 'referral_type', 'id', @referral_type_id);

        SET @referral_firm_id = (SELECT firm_id FROM referral WHERE id = @ref_id);
        call extract_firm(@referral_firm_id);

      END WHILE;

      call extract_row(@count, @ids,'openeyes', 'referral', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM secondary_diagnosis WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM secondary_diagnosis WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'secondary_diagnosis', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM socialhistory WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM socialhistory WHERE patient_id = @patient_id);

    SET @sh_ids = concat(',', @ids);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      WHILE (LOCATE(',', @sh_ids) > 0) DO
        SET @sh_ids = SUBSTRING(@sh_ids, LOCATE(',', @sh_ids) + 1);
        SET @sh_id =  (SELECT TRIM(SUBSTRING_INDEX(@sh_ids, ',', 1)));
        SET @sh_id = TRIM(@sh_id);



        SET @sh_occupation_id = (SELECT occupation_id FROM socialhistory WHERE id = @sh_id);
        call extract_row(1, @sh_occupation_id, 'openeyes', 'socialhistory_occupation', 'id', @sh_occupation_id);

        SET @smoking_status_id = (SELECT smoking_status_id FROM socialhistory WHERE id = @sh_id);
        call extract_row(1, @smoking_status_id, 'openeyes', 'socialhistory_smoking_status', 'id', @smoking_status_id);

        SET @accomodation_id = (SELECT accommodation_id FROM socialhistory WHERE id = @sh_id);
        call extract_row(1, @accomodation_id, 'openeyes', 'socialhistory_accommodation', 'id', @accomodation_id);

        SET @carer_id = (SELECT carer_id FROM socialhistory WHERE id = @sh_id);
        call extract_row(1, @carer_id, 'openeyes', 'socialhistory_carer', 'id', @carer_id);

        SET @substance_misuse_id = (SELECT substance_misuse_id FROM socialhistory WHERE id = @sh_id);
        call extract_row(1, @substance_misuse_id, 'openeyes', 'socialhistory_substance_misuse', 'id', @substance_misuse_id);

      END WHILE;

      call extract_row(@count, @ids,'openeyes', 'socialhistory', 'patient_id', @patient_id);
    END IF;


    SET  @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    SET  @episode_ids = (SELECT group_concat(id separator ',') FROM episode WHERE patient_id = @patient_id);

    call extract_dependant_row(@count, @episode_ids,'openeyes', 'episode', 'patient_id', @patient_id);

    SET @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    call get_events(@count,@episode_ids, 'openeyes', 'episode' ,@patient_id );

    # Write all the inserts to file
    SET @query = concat("SELECT query  INTO OUTFILE '", @file,"' LINES TERMINATED BY '\n' FROM patient_data_extract");

    PREPARE qry FROM @query;

    EXECUTE qry;

    # SELECT * FROM patient_data_extract;

    # Delete the temporary table
    DROP TEMPORARY TABLE IF EXISTS patient_data_extract;


  END $$
DELIMITER ;

DELIMITER $$

DROP PROCEDURE IF EXISTS extract_all_patients;
CREATE DEFINER=`root`@`localhost` PROCEDURE extract_all_patients()
  BEGIN
    SET @all_patients = (SELECT concat(',',group_concat(hos_num separator ',')) FROM patient);
    WHILE (LOCATE(',', @all_patients) > 0) DO
      SET @all_patients = SUBSTRING(@all_patients, LOCATE(',', @all_patients) + 1);
      SET @current_patient_id =  (SELECT TRIM(SUBSTRING_INDEX(@all_patients, ',', 1)));
      SET @current_patient_id = TRIM(@current_patient_id);

      call run_extractor(@current_patient_id);

    END WHILE;
  END $$
DELIMITER ;

#call extract_all_patients;

/*
call run_extractor(1639922);
call run_extractor(1485025);
call run_extractor(0846209);
call run_extractor(1140873);
call run_extractor(1882539);
call run_extractor(1820253);
call run_extractor(1141305);
call run_extractor(651006);
call run_extractor(1441450);
call run_extractor(1835099);
call run_extractor(1271105);
call run_extractor(1899826);
call run_extractor(1475558);
call run_extractor(1194372);
call run_extractor(1361965);
call run_extractor(521135);
call run_extractor(1266770);
call run_extractor(2132397);
call run_extractor(1912665);
call run_extractor(2150781);
call run_extractor(2163577);

call run_extractor(35937);
call run_extractor(64156);
call run_extractor(82453);
call run_extractor(200990);
call run_extractor(221476);
call run_extractor(275507);
call run_extractor(496621);
call run_extractor(498842);
call run_extractor(518407);
call run_extractor(572059);
call run_extractor(718086);
call run_extractor(723175);
call run_extractor(735430);
call run_extractor(755231);
call run_extractor(835634);
call run_extractor(839528);
call run_extractor(888911);
call run_extractor(949319);
call run_extractor(958227);
call run_extractor(999162);
call run_extractor(1010323);
call run_extractor(1033923);
call run_extractor(1039017);
call run_extractor(1055319);
call run_extractor(1069677);
call run_extractor(1071547);
call run_extractor(1076022);
call run_extractor(1093073);
call run_extractor(1117467);
call run_extractor(1119927);
call run_extractor(1131565);
call run_extractor(1152572);
call run_extractor(1155437);
call run_extractor(1156466);
call run_extractor(1172099);
call run_extractor(1189584);
call run_extractor(1265750);
call run_extractor(1376915);
call run_extractor(1413028);
call run_extractor(1421335);
call run_extractor(1448673);
call run_extractor(1461752);
call run_extractor(1490186);
call run_extractor(1496780);
call run_extractor(1519029);
call run_extractor(1535587);
call run_extractor(1538023);
call run_extractor(1544543);
call run_extractor(1597768);
call run_extractor(1606806);
call run_extractor(1608342);
call run_extractor(1609707);
call run_extractor(1625080);
call run_extractor(1634789);
call run_extractor(1659099);
call run_extractor(1673316);
call run_extractor(1684478);
call run_extractor(1744624);
call run_extractor(1757957);
call run_extractor(1771975);
call run_extractor(1840181);
call run_extractor(1848544);
call run_extractor(1856681);
call run_extractor(1859510);
call run_extractor(1859546);
call run_extractor(1868268);
call run_extractor(1869032);
call run_extractor(1895223);
call run_extractor(1897143);
call run_extractor(1898278);
call run_extractor(1906100);
call run_extractor(1911438);
call run_extractor(1915601);
call run_extractor(1916578);
call run_extractor(1932578);
call run_extractor(1935649);
call run_extractor(1935673);
call run_extractor(1936607);
call run_extractor(1939768);
call run_extractor(1943500);
call run_extractor(1950471);
call run_extractor(1958643);
call run_extractor(1959126);
call run_extractor(1965221);
call run_extractor(1965412);
call run_extractor(1971206);
call run_extractor(1973349);
call run_extractor(1982799);
call run_extractor(2018400);
call run_extractor(2055535);
call run_extractor(2099999);
call run_extractor(2103267);
call run_extractor(2112867);
call run_extractor(2113395);
call run_extractor(2128460);
call run_extractor(2136051);
call run_extractor(2153354);
call run_extractor(22144);
call run_extractor(28055);
call run_extractor(65535);
call run_extractor(76185);
call run_extractor(524237);
call run_extractor(764362);
call run_extractor(821561);
call run_extractor(822532);
call run_extractor(826858);
call run_extractor(864619);
call run_extractor(882850);
call run_extractor(897332);
call run_extractor(936087);
call run_extractor(955727);
call run_extractor(969190);
call run_extractor(977618);
call run_extractor(983300);
call run_extractor(1006169);
call run_extractor(1059402);
call run_extractor(1060900);
call run_extractor(1073695);
call run_extractor(1095005);
call run_extractor(1116873);
call run_extractor(1137220);
call run_extractor(1304794);
call run_extractor(1329932);
call run_extractor(1352825);
call run_extractor(1353845);
call run_extractor(1402293);
call run_extractor(1418524);
call run_extractor(1439842);
call run_extractor(1447065);
call run_extractor(1475569);
call run_extractor(1486715);
call run_extractor(1499167);
call run_extractor(1550719);
call run_extractor(1554593);
call run_extractor(1586830);
call run_extractor(1595432);
call run_extractor(1596581);
call run_extractor(1606323);
call run_extractor(1622763);
call run_extractor(1650732);
call run_extractor(1656153);
call run_extractor(1661782);
call run_extractor(1702570);
call run_extractor(1718101);
call run_extractor(1720531);
call run_extractor(1742904);
call run_extractor(1754632);
call run_extractor(1776515);
call run_extractor(1788661);
call run_extractor(1790394);
call run_extractor(1796962);
call run_extractor(1800300);
call run_extractor(1803462);
call run_extractor(1804040);
call run_extractor(1811994);
call run_extractor(1818268);
call run_extractor(1831360);
call run_extractor(1837162);
call run_extractor(1840562);
call run_extractor(1841637);
call run_extractor(1842954);
call run_extractor(1847335);
call run_extractor(1852246);
call run_extractor(1855626);
call run_extractor(1856548);
call run_extractor(1856980);
call run_extractor(1861472);
call run_extractor(1863814);
call run_extractor(1867267);
call run_extractor(1868199);
call run_extractor(1869264);
call run_extractor(1879848);
call run_extractor(1879909);
call run_extractor(1894730);
call run_extractor(1896879);
call run_extractor(1929685);
call run_extractor(1003415);


call run_extractor(1583709);
call run_extractor(1719214);
call run_extractor(1176765);
call run_extractor(1257614);
call run_extractor(1513741);
call run_extractor(1105637);
call run_extractor(1711084);
call run_extractor(1281980);
call run_extractor(1297235);
call run_extractor(760681);
call run_extractor(1713619);
call run_extractor(1266280);
call run_extractor(1761924);
call run_extractor(1307142);
call run_extractor(554467);
call run_extractor(693254);
call run_extractor(1580797);
call run_extractor(1838957);
call run_extractor(1839887);
call run_extractor(510281);
call run_extractor(1140425);
call run_extractor(1683499);
call run_extractor(1424875);
call run_extractor(1466617);
call run_extractor(1102867);
call run_extractor(432169);
call run_extractor(1848953);
call run_extractor(1567033);
call run_extractor(1049774);
call run_extractor(1616192);
call run_extractor(1864957);
call run_extractor(910861);
call run_extractor(1651854);
call run_extractor(1880389);
call run_extractor(1489261);
call run_extractor(1877747);
call run_extractor(1682150);
call run_extractor(1199271);
call run_extractor(799446);
call run_extractor(1439627);
call run_extractor(1719371);
call run_extractor(1678453);
call run_extractor(1885407);
call run_extractor(1238464);
call run_extractor(1818782);
call run_extractor(1818782);
call run_extractor(1740854);
call run_extractor(1895648);
call run_extractor(1232945);
call run_extractor(1339942);
call run_extractor(1686972);
call run_extractor(980840);
call run_extractor(1451960);
call run_extractor(1899925);
call run_extractor(712066);
call run_extractor(1662776);
call run_extractor(1926053);
call run_extractor(843437);
call run_extractor(623777);
call run_extractor(1957123);
call run_extractor(1765256);
call run_extractor(1971574);
call run_extractor(1974250);
call run_extractor(1989382);
call run_extractor(1987534);
call run_extractor(460496);
call run_extractor(2101332);
call run_extractor(2135210);
call run_extractor(2132878);
call run_extractor(994996);
call run_extractor(1016270);
call run_extractor(2147825);
call run_extractor(2153504);
call run_extractor(2161861);
call run_extractor(1719731);
call run_extractor(2166303);
call run_extractor(2166293);
call run_extractor(1487982);
call run_extractor(1487982);
call run_extractor(21166);
call run_extractor(823419);
call run_extractor(1494787);
call run_extractor(1518864);
call run_extractor(230148);
call run_extractor(1718462);
call run_extractor(1220884);
call run_extractor(1548458);
call run_extractor(1457075);
call run_extractor(875853);
call run_extractor(815988);
call run_extractor(387410);
call run_extractor(1826845);
call run_extractor(547286);
call run_extractor(133979);
call run_extractor(1661840);
call run_extractor(666779);
call run_extractor(1572339);
call run_extractor(1135475);
call run_extractor(1648245);
call run_extractor(1542140);
call run_extractor(1880909);
call run_extractor(1683680);
call run_extractor(1878001);
call run_extractor(1552987);
call run_extractor(1465937);
call run_extractor(1327045);
call run_extractor(1641156);
call run_extractor(1640907);
call run_extractor(1281490);
call run_extractor(521290);
call run_extractor(521290);
call run_extractor(1797802);
call run_extractor(1917113);
call run_extractor(1926619);
call run_extractor(1934231);
call run_extractor(1992520);
call run_extractor(2105797);
call run_extractor(1453136);
call run_extractor(2107159);
call run_extractor(868849);
call run_extractor(1583709);

*/
