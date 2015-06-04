-- Configuration settings for this script --
SET SESSION group_concat_max_len = 100000;



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

    SET tablename=in_table;
    SET @in_row_id = TRIM(in_row_id);
    SET @Sels = NULL;
    SET @Inserts = NULL;
    SET @current_table = NULL;

    IF( (@in_row_id IS NOT NULL) AND (@in_row_id != '')) THEN

      -- Comma separated column names - used for Select --
      select group_concat(concat('concat(\'"\',','ifnull(',column_name,','''')',',\'"\')'))
      INTO @Sels from information_schema.columns where table_schema=in_db and table_name=tablename;

      SELECT @Sels;

      -- Comma separated column names - used for Group By --
      select group_concat('`',column_name,'`')
      INTO @Whrs from information_schema.columns where table_schema=in_db and table_name=tablename;

      #SELECT @Whrs;

      SELECT /*tablename, @Sels, in_ColumnName, in_ColumnValue, @in_row_id, @Whrs,*/ @tmp;
      SET @current_table = tablename;

      -- Main Select Statement for fetching comma separated table value --
      SET @Inserts= concat("(select concat('insert into ", in_db,".",@current_table," values(',concat_ws(',',",@Sels,"),');')
        as MyColumn from ", in_db,".",@current_table, " where ", in_ColumnName, " = " , in_ColumnValue, " AND id = " , @in_row_id," group by ",@Whrs, " INTO @tmp);");

      #SELECT @Inserts;

      IF ((@Inserts IS NOT NULL) AND (@Sels IS NOT NULL)) THEN
        PREPARE Inserts FROM @Inserts ;

        SELECT @tmp;

        EXECUTE Inserts;

        IF (@tmp IS NOT NULL) THEN
          INSERT INTO patient_data_extract VALUES ('', @tmp);
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
        SET @event_ids = NULL;
        SET @event_count = NULL;

        IF ( in_table = 'episode') THEN
          SET @event_ids = (SELECT group_concat(id separator ',') FROM event WHERE episode_id = @id);
          SET @event_count = (SELECT COUNT(*) FROM event WHERE episode_id = @id);


          SET @measurement_reference_ids = (SELECT group_concat(id separator ',') FROM measurement_reference where episode_id = @id);
          SET @measurement_reference_count = (SELECT COUNT(*) FROM measurement_reference WHERE episode_id = @id);


          SET @referral_episode_assignment_ids = (SELECT group_concat(id separator ',') FROM referral_episode_assignment where episode_id = @id);
          SET @referral_episode_assignment_count = (SELECT COUNT(*) FROM referral_episode_assignment WHERE episode_id = @id);


          call extract_row(@event_count, @event_ids,'openeyes', 'event', 'episode_id', @id);
          #call extract_row(@event_count, @event_ids,'openeyes', 'event_version', 'episode_id', @id);


          call extract_row(@measurement_reference_count, @measurement_reference_ids,'openeyes', 'measurement_reference', 'episode_id', @id);
          call extract_row(@measurement_reference_count, @measurement_reference_ids,'openeyes', 'measurement_reference_version', 'episode_id', @id);

          call extract_row(@referral_episode_assignment_count, @referral_episode_assignment_ids,'openeyes', 'referral_episode_assignment', 'episode_id', @id);
          call extract_row(@referral_episode_assignment_count, @referral_episode_assignment_ids,'openeyes', 'referral_episode_assignment_version', 'episode_id', @id);


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

          #SELECT @episode_count,@event_count,@event_ids, @id as current_id;

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_adnexalcomorbidity WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_adnexalcomorbidity WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_adnexalcomorbidity', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_adnexalcomorbidity_version WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_adnexalcomorbidity_version WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_adnexalcomorbidity_version', 'event_id', @id);


          SET  @count = (SELECT COUNT(*) FROM event_issue WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM event_issue WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'event_issue', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM event_issue_version WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM event_issue_version WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'event_issue_version', 'event_id', @id);


          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_anteriorsegment WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_anteriorsegment WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_anteriorsegment', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_anteriorsegment_cct WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_anteriorsegment WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_anteriorsegment_cct', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_anteriorsegment_cct_version', 'event_id', @id);


          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_anteriorsegment_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_bleb_assessment WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_bleb_assessment WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_bleb_assessment', 'event_id', @id);

          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_bleb_assessment_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_cataractsurgicalmanagement WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_cataractsurgicalmanagement WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_cataractsurgicalmanagement', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_cataractsurgicalmanagement_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_clinicoutcome WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_clinicoutcome WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_clinicoutcome', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_clinicoutcome_version', 'event_id', @id);


          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_colourvision WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_colourvision WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_colourvision', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_colourvision_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_comorbidities WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_comorbidities WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_comorbidities', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_comorbidities_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_conclusion WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_conclusion WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_conclusion', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_conclusion_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_currentmanagementplan WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_currentmanagementplan WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_currentmanagementplan', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_currentmanagementplan_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_diagnoses WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_diagnoses WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_diagnoses', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_diagnoses_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_dilation WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_dilation WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_dilation', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_dilation_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_further_findings WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_further_findings WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_further_findings', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_further_findings_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_glaucomarisk WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_glaucomarisk WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_glaucomarisk', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_glaucomarisk_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_gonioscopy WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_gonioscopy WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_gonioscopy', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_gonioscopy_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_injectionmanagement WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_injectionmanagement WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_injectionmanagement', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_injectionmanagement_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_injectionmanagementcomplex WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_injectionmanagementcomplex WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_injectionmanagementcomplex', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_injectionmanagementcomplex_version', 'event_id', @id);


          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_intraocularpressure WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_intraocularpressure WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_intraocularpressure', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_intraocularpressure_version', 'event_id', @id);


          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_investigation WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_investigation WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_investigation', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_investigation_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_lasermanagement WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_lasermanagement WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_lasermanagement', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_lasermanagement_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_management WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_management WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_management', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_management_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_oct WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_oct WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_oct', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_oct_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_opticdisc WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_opticdisc WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_opticdisc', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_opticdisc_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_overallmanagementplan WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_overallmanagementplan WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_overallmanagementplan', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_overallmanagementplan_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_posteriorpole WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_posteriorpole WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_posteriorpole', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_posteriorpole_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_pupillaryabnormalities WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_pupillaryabnormalities WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_pupillaryabnormalities' , 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_pupillaryabnormalities_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_refraction WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_refraction WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_refraction', 'event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_refraction_version', 'event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_risks WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_risks WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_risks','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_risks_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_visualacuity WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_visualacuity WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_visualacuity','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_visualacuity_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciexamination_visualfunction WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciexamination_visualfunction WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_visualfunction','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciexamination_visualfunction_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophciphasing_intraocularpressure WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophciphasing_intraocularpressure WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophciphasing_intraocularpressure','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophciphasing_intraocularpressure_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophcocorrespondence_letter WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcocorrespondence_letter WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophcocorrespondence_letter','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophcocorrespondence_letter_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_exceptional WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_exceptional WHERE event_id=@id);
          # call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_exceptional','event_id', @id);
          # call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_exceptional_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_mrservicein WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_mrservicein WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_mrservicein','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_mrservicein_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_patientsuit WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_patientsuit WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_patientsuit','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_patientsuit_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_relativecon WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_relativecon WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_relativecon','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_relativecon_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophcotherapya_therapydiag WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophcotherapya_therapydiag WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_therapydiag','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophcotherapya_therapydiag_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophdrprescription_details WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophdrprescription_details WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophdrprescription_details','event_id', @id);
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

          SET  @count = (SELECT COUNT(*) FROM et_ophouanaestheticsataudit_anaesthetis WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophouanaestheticsataudit_anaesthetis WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_anaesthetis','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_anaesthetis_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophouanaestheticsataudit_notes WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophouanaestheticsataudit_notes WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_notes','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_notes_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophouanaestheticsataudit_satisfactio WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophouanaestheticsataudit_satisfactio WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_satisfactio','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_satisfactio_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophouanaestheticsataudit_vitalsigns WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophouanaestheticsataudit_vitalsigns WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_vitalsigns','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophouanaestheticsataudit_vitalsigns_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_benfitrisk WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_benfitrisk WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_benfitrisk','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_benfitrisk_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_leaflets WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_leaflets WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_leaflets','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_leaflets_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_other WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_other WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_other','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_other_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_permissions WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_permissions WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_permissions','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_permissions_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_procedure WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_procedure WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_procedure','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_procedure_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrconsent_type WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrconsent_type WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_type ','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrconsent_type_version ','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_anaesthetic WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_anaesthetic WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_anaesthetic','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_anaesthetic_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_anteriorseg WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_anteriorseg WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_anteriorseg','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_anteriorseg_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_complications WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_complications WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_complications','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_complications_version','event_id', @id)

          SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_postinject WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_postinject WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_postinject','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_postinject_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_site WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_site WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_site','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_site_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrintravitinjection_treatment WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrintravitinjection_treatment WHERE event_id=@id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_treatment','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrintravitinjection_treatment_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_anteriorseg WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_anteriorseg WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_anteriorseg','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_anteriorseg_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_comments WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_comments WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_comments','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_comments_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_fundus WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_fundus WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_fundus','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_fundus_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_posteriorpo WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_posteriorpo WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_posteriorpo','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_posteriorpo_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_site WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_site WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_site','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_site_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtrlaser_treatment WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtrlaser_treatment WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_treatment','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtrlaser_treatment_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationbooking_diagnosis WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationbooking_diagnosis WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_diagnosis','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_diagnosis_version','event_id', @id);


          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationbooking_operation WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationbooking_operation WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_operation','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_operation_version','event_id', @id);


          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationbooking_scheduleope WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationbooking_scheduleope WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_scheduleope','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationbooking_scheduleope_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_anaesthetic WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_anaesthetic WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_anaesthetic','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_anaesthetic_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_buckle WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_buckle WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_buckle ','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_buckle_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_cataract WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_cataract WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_cataract','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_cataract_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_comments WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_comments WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_comments','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_comments_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_genericprocedure WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_genericprocedure WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_genericprocedure','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_genericprocedure_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_glaucomatube WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_glaucomatube WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_glaucomatube','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_glaucomatube_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_membrane_peel WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_membrane_peel WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_membrane_peel','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_membrane_peel_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_mmc WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_mmc WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_mmc','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_mmc_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_personnel WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_personnel WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_personnel','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_personnel_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_postop_drugs WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_postop_drugs WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_postop_drugs','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_postop_drugs_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_preparation WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_preparation WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_preparation','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_preparation_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_procedurelist WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_procedurelist WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_procedurelist','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_procedurelist_version','event_id', @id);


          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_surgeon WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_surgeon_version WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_surgeon','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_surgeon_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_tamponade WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_tamponade WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_tamponade','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_tamponade_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_trabectome WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_trabectome WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_trabectome','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_trabectome_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_trabeculectomy WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_trabeculectomy WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_trabeculectomy ','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_trabeculectomy_version','event_id', @id);

          SET  @count = (SELECT COUNT(*) FROM et_ophtroperationnote_vitrectomy WHERE event_id = @id);
          SET  @ids = (SELECT group_concat(id separator ',') FROM et_ophtroperationnote_vitrectomy WHERE event_id=@id);
          call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_vitrectomy','event_id', @id);
          #call extract_row(@count, @ids,'openeyes', 'et_ophtroperationnote_vitrectomy_version','event_id', @id);



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

    SELECT @file;

    -- Set all primary keys, index and foreign keys --
    SET @patient_id = (SELECT id FROM patient WHERE hos_num=hospital_number);
    SET @contact_id = (SELECT contact_id FROM patient WHERE hos_num=hospital_number);
    SET @contact_label_id = (SELECT id FROM contact_label WHERE id = (SELECT contact_label_id FROM contact WHERE id = @contact_id));

    SELECT @contact_label_id;




    #Start creating the inserts;

    SET  @count = (SELECT COUNT(*) FROM `patient` WHERE id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM `patient` WHERE hos_num=hospital_number );
    call extract_row(@count, @ids,'openeyes', 'patient', 'id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM contact WHERE id = @contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM contact WHERE id=@contact_id);
    call extract_row(@count, @ids,'openeyes', 'contact', 'id', @contact_id);

    SET  @count = (SELECT COUNT(*) FROM address WHERE contact_id = @contact_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM address WHERE contact_id=@contact_id);
    call extract_row(@count, @ids,'openeyes', 'address', 'contact_id', @contact_id);

    SET  @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM episode WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'episode', 'patient_id', @patient_id);

    /*SET  @count = (SELECT COUNT(*) FROM episode_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM episode_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'episode', 'patient_id', @patient_id);
   */

    SET  @count = (SELECT COUNT(*) FROM family_history WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM family_history WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'family_history', 'patient_id', @patient_id);

    /*SET  @count = (SELECT COUNT(*) FROM family_history_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM family_history_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'family_history_version', 'patient_id', @patient_id);
   */

    SET  @count = (SELECT COUNT(*) FROM medication WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'family_history', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM medication_adherence WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication_adherence WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'medication_adherence', 'patient_id', @patient_id);

    /*SET  @count = (SELECT COUNT(*) FROM medication_adherence_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication_adherence_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'medication_adherence_version', 'patient_id', @patient_id);


    SET  @count = (SELECT COUNT(*) FROM medication_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM medication_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'medication_version', 'patient_id', @patient_id);
    */

    SET  @count = (SELECT COUNT(*) FROM patient_allergy_assignment WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_allergy_assignment WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_allergy_assignment', 'patient_id', @patient_id);

    /*
    SET  @count = (SELECT COUNT(*) FROM patient_allergy_assignment_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_allergy_assignment_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_allergy_assignment_version', 'patient_id', @patient_id);
   */

    SET  @count = (SELECT COUNT(*) FROM patient_contact_assignment WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_contact_assignment WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_contact_assignment', 'patient_id', @patient_id);


    /*SET  @count = (SELECT COUNT(*) FROM patient_contact_assignment_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_contact_assignment_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_contact_assignment_version', 'patient_id', @patient_id);
    */

    SET  @count = (SELECT COUNT(*) FROM patient_measurement WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_measurement WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_measurement', 'patient_id', @patient_id);


    /*SET  @count = (SELECT COUNT(*) FROM patient_measurement_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_measurement_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_measurement_version', 'patient_id', @patient_id);

    SET  @count = (SELECT COUNT(*) FROM patient_oph_info_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM patient_oph_info_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'patient_oph_info_version', 'patient_id', @patient_id);
   */

    SET  @count = (SELECT COUNT(*) FROM previous_operation WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM previous_operation WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'previous_operation', 'patient_id', @patient_id);


    /*SET  @count = (SELECT COUNT(*) FROM previous_operation_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM previous_operation_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'previous_operation_version', 'patient_id', @patient_id);
    */


    SET  @count = (SELECT COUNT(*) FROM referral WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM referral WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'referral', 'patient_id', @patient_id);


    /*SET  @count = (SELECT COUNT(*) FROM referral_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM referral_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'referral_version', 'patient_id', @patient_id);
   */


    SET  @count = (SELECT COUNT(*) FROM secondary_diagnosis WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM secondary_diagnosis WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'secondary_diagnosis', 'patient_id', @patient_id);

    /*SET  @count = (SELECT COUNT(*) FROM secondary_diagnosis_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM secondary_diagnosis_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'secondary_diagnosis_version', 'patient_id', @patient_id);
    */

    SET  @count = (SELECT COUNT(*) FROM socialhistory WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM socialhistory WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'socialhistory', 'patient_id', @patient_id);

    /*SET  @count = (SELECT COUNT(*) FROM socialhistory_version WHERE patient_id = @patient_id);
    SET  @ids = (SELECT group_concat(id separator ',') FROM socialhistory_version WHERE patient_id = @patient_id);
    call extract_row(@count, @ids,'openeyes', 'socialhistory_version', 'patient_id', @patient_id);
    */

    SET  @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    SET  @episode_ids = (SELECT group_concat(id separator ',') FROM episode WHERE patient_id = @patient_id);

    call extract_dependant_row(@count, @episode_ids,'openeyes', 'episode', 'patient_id', @patient_id);






    SET @count = (SELECT COUNT(*) FROM episode WHERE patient_id = @patient_id);
    call get_events(@count,@episode_ids, 'openeyes', 'episode' ,@patient_id );

    # Write all the inserts to file
    SET @query = concat("SELECT query  INTO OUTFILE '", @file,"' LINES TERMINATED BY '\n' FROM patient_data_extract");

    PREPARE qry FROM @query;

    EXECUTE qry;

    SELECT * FROM patient_data_extract;

    # Delete the temporary table
    DROP TEMPORARY TABLE IF EXISTS patient_data_extract;


  END $$
DELIMITER ;



call run_extractor(1639922);
call run_extractor(1485025);
