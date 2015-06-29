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
      INTO @Sels from information_schema.columns where table_schema=in_db and table_name=tablename and column_name not like '%user\_id' AND column_name != 'last_firm_id' AND column_name != 'last_site_id' AND column_name != 'latest_booking_id';

      #SELECT @Sels;

      select group_concat(column_name)
      INTO @Columns from information_schema.columns where table_schema=in_db and table_name=tablename and column_name not like '%user\_id' AND column_name != 'last_firm_id' AND column_name != 'last_site_id' AND column_name != 'latest_booking_id';

      #SELECT @Columns;

      -- Comma separated column names - used for Group By --
      select group_concat('`',column_name,'`')
      INTO @Whrs from information_schema.columns where table_schema=in_db and table_name=tablename and column_name not like '%user\_id' AND column_name != 'last_firm_id' AND column_name != 'last_site_id' AND column_name != 'latest_booking_id';

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
    SET @count = in_count;
    SET @ids = NULL;
    SET @ids = concat(',',in_id_array);

    -- Cycle through each id to get the individual row for that id --
    if( @count > 0 AND @ids IS NOT NULL) THEN
      WHILE (LOCATE(',', @ids) > 0) DO
        SET @ids = SUBSTRING(@ids, LOCATE(',', @ids) + 1);
        SET @current_id =  (SELECT TRIM(SUBSTRING_INDEX(@ids, ',', 1)));
        SET @current_id = TRIM(@current_id);

        call InsGen(in_db, in_table, in_ColumnName, in_ColumnValue, @current_id);

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
          SET @contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @contact_id);
          SET @institution_id = (SELECT institution_id FROM contact_location WHERE id = @location_id);

          SET @institiution_contact_id = (SELECT contact_id FROM institution WHERE id = @institution_id);
          SET @institiution_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @institiution_contact_id);

          call extract_row(1, @contact_label_id, 'openeyes', 'contact_label', 'id', @contact_label_id);
          call extract_row(1, @contact_id, 'openeyes', 'contact', 'id', @contact_id);
          call extract_row(1, @institiution_contact_label_id, 'openeyes', 'contact_label', 'id', @institiution_contact_label_id);
          call extract_row(1, @institiution_contact_id, 'openeyes', 'contact', 'id', @institiution_contact_id);
          call extract_row(1, @institution_id, 'openeyes', 'institution', 'id', @institution_id);
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

    SET @contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @contact_id);
    IF( @contact_label_id IS NOT NULL) THEN
      call extract_row(1, @contact_label_id, 'openeyes', 'contact_label', 'id', @contact_label_id);
    END IF;
    call extract_row(1, @contact_id, 'openeyes', 'contact', 'id', @contact_id);

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
          SET @contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @contact_id);
          call extract_row(1, @contact_label_id, 'openeyes', 'contact_label', 'id', @contact_label_id);
          call extract_row(1, @contact_id, 'openeyes', 'contact', 'id', @contact_id);
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

        IF (@table = 'et_ophtrintravitinjection_site') THEN
            SET @site_id = (SELECT site_id FROM et_ophtrintravitinjection_site WHERE id = @current_id);
        END IF;

        IF (@table = 'et_ophtroperationbooking_operation') THEN

          SET @site_id = (SELECT site_id FROM et_ophtroperationbooking_operation WHERE id = @current_id);

          SET @cancellation_user_id = (SELECT cancellation_user_id FROM et_ophtroperationbooking_operation WHERE id = @current_id);
          SET @cancellation_contact_id = (SELECT contact_id FROM user WHERE id = @cancellation_user_id);
          SET @cancellation_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @cancellation_contact_id);

          IF (@cancellation_user_id IS NOT NULL) THEN
            call extract_row(1,@cancellation_contact_label_id, 'openeyes', 'contact_label', 'id', @cancellation_contact_label_id);
            call extract_row(1, @cancellation_contact_id, 'openeyes', 'contact', 'id', @cancellation_contact_id);
            call extract_row(1, @cancellation_user_id, 'openeyes', 'user', 'id', @cancellation_user_id);
          END IF;

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
                call extract_row(1, @booking_id, 'openeyes', 'ophtroperationbooking_operation_booking', 'id', @booking_id);
                 -- ophtroperationbooking_operation_booking end

            END WHILE;
          END IF;


        END IF;

        IF(@site_id IS NOT NULL) THEN
          SET @contact_id = (SELECT contact_id FROM site WHERE id = @site_id);
          SET @contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @contact_id);
          SET @replyto_contact_id = (SELECT replyto_contact_id FROM site WHERE id = @site_id);
          SET @replyto_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @replyto_contact_id);

          IF (@contact_label_id IS NOT NULL) THEN
            call extract_row(1, @contact_label_id, 'openeyes', 'contact_label', 'id', @contact_label_id);
          END IF;

          IF (@contact_id IS NOT NULL) THEN
            call extract_row(1, @contact_id, 'openeyes', 'contact', 'id', @contact_id);
          END IF;

          IF (@replyto_contact_label_id IS NOT NULL) THEN
            call extract_row(1, @replyto_contact_label_id, 'openeyes', 'contact_label', 'id', @replyto_contact_label_id);
          END IF;

          IF (@replyto_contact_id IS NOT NULL) THEN
            call extract_row(1, @replyto_contact_id, 'openeyes', 'contact', 'id', @replyto_contact_id);
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
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
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

              SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_mrservicein WHERE event_id = @id);
              SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_mrservicein WHERE event_id=@id);
              IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
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

                  IF(@frequency_id IS NOT NULL) THEN
                    call extract_row(1, @frequency_id, 'openeyes', 'drug_frequency','id', @frequency_id);
                  END IF;

                  IF(@duration_id IS NOT NULL) THEN
                    call extract_row(1, @duration_id,'openeyes', 'drug_duration','id', @duration_id);
                  END IF;
                END WHILE;

                call extract_row(@count, @ids,'openeyes', 'ophdrprescription_item','prescription_id', (SELECT id FROM et_ophdrprescription_details WHERE event_id = @id));
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
                SET @consent_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @consent_contact_id);
                call extract_row(1, @consent_contact_label_id, 'openeyes', 'contact_label', 'id', @consent_contact_label_id);
                call extract_row(1, @consent_contact_id, 'openeyes', 'contact', 'id', @consent_contact_id);
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
                  SET @right_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @right_contact_id);

                  call extract_row(1, @right_contact_label_id, 'openeyes', 'contact_label', 'id', @right_contact_label_id);
                  call extract_row(1, @right_contact_id, 'openeyes', 'contact', 'id', @right_contact_id);
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

            /* Add surgeon id related to et_ophtroperationnote_surgeon first */
            SET @user_id = (SELECT surgeon_id FROM et_ophtroperationnote_surgeon WHERE event_id = @id);
            SET @contact_id = (SELECT contact_id from user WHERE id = @user_id);
            SET @contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @cotntact_id);

            if( @user_id IS NOT NULL) THEN
                call extract_row(1, @contact_label_id, 'openeyes', 'contact_label', 'id', @contact_label_id);
                call extract_row(1, @contact_id, 'openeyes', 'contact', 'id', @contact_id);
                call extract_row(1, @user_id, 'openeyes', 'user', 'id', @user_id);
            END IF;

            SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_surgeon WHERE event_id = @id);
            SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_surgeon_version WHERE event_id=@id);
            IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
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

    ALTER TABLE `event` CHANGE created_user_id created_user_id int(10) unsigned not null default 1;
    ALTER TABLE `event` MODIFY COLUMN created_user_id int(10) unsigned not null default 1;
    ALTER TABLE specialty CHANGE created_user_id created_user_id int(10) unsigned not null default 1;
    ALTER TABLE specialty CHANGE last_modified_user_id last_modified_user_id int(10) unsigned not null default 1;


    SET @patient_id = (SELECT id FROM patient WHERE hos_num=hospital_number);
    SET @contact_id = (SELECT contact_id FROM patient WHERE hos_num=hospital_number);
    SET @gp_id = (SELECT gp_id FROM patient WHERE id = @patient_id);
    SET @gp_contact_id = (SELECT contact_id FROM gp WHERE id = @gp_id);
    SET @gp_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @gp_contact_id);
    SET @practice_id = (SELECT practice_id from patient where id = @patient_id);
    SET @practice_contact_id = (SELECT contact_id from practice WHERE id = @practice_id);
    SET @practice_contact_label_id = (SELECT contact_label_id FROM contact WHERE id = @practice_contact_id);

    -- Get all the fields that are indirectly related to the patient --
    call extract_row(1, @practice_contact_label_id, 'openeyes', 'contact_label', 'id', @practice_contact_label_id);
    call extract_row(1, @practice_contact_id, 'openeyes', 'contact', 'id', @practice_contact_id);
    call extract_row(1, @practice_id, 'openeyes', 'practice', 'id', @practice_id);

    call extract_row(1, @gp_contact_label_id, 'openeyes', 'contact_label', 'id', @gp_contact_label_id);
    call extract_row(1, @gp_contact_id, 'openeyes', 'contact', 'id', @gp_contact_id);
    call extract_row(1, @gp_id, 'openeyes', 'gp', 'id', @gp_id);


    -- Start creating the inserts --
    SET  @count = (SELECT COUNT(*) FROM contact WHERE id = @contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM contact WHERE id=@contact_id);
    call extract_row(@count, @ids,'openeyes', 'contact', 'id', @contact_id);



    SET  @count = (SELECT COUNT(*) FROM address WHERE contact_id = @contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM address WHERE contact_id=@contact_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'address', 'contact_id', @contact_id);
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
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'referral', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM secondary_diagnosis WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM secondary_diagnosis WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
      call extract_row(@count, @ids,'openeyes', 'secondary_diagnosis', 'patient_id', @patient_id);
    END IF;

    SET  @count = (SELECT COUNT(*) FROM socialhistory WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM socialhistory WHERE patient_id = @patient_id);
    IF ( (@count > 0) AND (@ids IS NOT NULL)) THEN
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



call run_extractor(1639922);
#call run_extractor(1485025);
#call run_extractor(0846209);
#call run_extractor(1140873);
#call run_extractor(1882539);
#call run_extractor(1820253);
#call run_extractor(1141305);
#call run_extractor(651006);
#call run_extractor(1441450);
#call run_extractor(1835099);
#call run_extractor(1271105);
#call run_extractor(1899826);
#call run_extractor(1475558);
#call run_extractor(1194372);
#call run_extractor(1361965);
#call run_extractor(521135);
#call run_extractor(1266770);
#call run_extractor(2132397);
#call run_extractor(1912665);
#call run_extractor(2150781);
#call run_extractor(2163577);
