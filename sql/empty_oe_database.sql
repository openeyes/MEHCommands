DROP PROCEDURE IF EXISTS emptyET_tables;
DROP PROCEDURE IF EXISTS emptyOEDatabase;
DROP PROCEDURE IF EXISTS emptyMainTables;
DROP PROCEDURE IF EXISTS emptyVersionTables;
DROP PROCEDURE IF EXISTS updateUserId;

DELIMITER $$

# truncating et_tables with _versions
CREATE PROCEDURE updateUserId()
BEGIN
 DECLARE done INT DEFAULT FALSE;
 DECLARE table_n VARCHAR(255);
 DECLARE column_n VARCHAR(255);
 DECLARE cur1 CURSOR FOR SELECT table_name, column_name FROM information_schema.columns WHERE (column_name LIKE '%_user_id' OR column_name = 'site_id' OR column_name = 'firm_id') AND table_schema=(SELECT DATABASE()) AND table_name NOT LIKE '%\_version';
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
 OPEN cur1;
 SELECT 'Updating user_id, site_id, firm_id columns...';
 read_loop: LOOP
    FETCH cur1 INTO table_n, column_n;
    SET @updateQuery = CONCAT('UPDATE ', table_n, ' SET ',column_n,'=1');
    PREPARE myQuery FROM @updateQuery;
    EXECUTE myQuery;
    DEALLOCATE PREPARE myQuery;
 IF done THEN
   LEAVE read_loop;
 END IF;
 END LOOP;
 CLOSE cur1;
END $$

CREATE PROCEDURE emptyET_tables()
BEGIN
 DECLARE done INT DEFAULT FALSE;
 DECLARE table_n VARCHAR(255);
 DECLARE cur1 CURSOR FOR SELECT table_name FROM information_schema.tables WHERE table_name LIKE 'et\_%' AND table_schema=(SELECT DATABASE()) AND table_type='BASE TABLE';
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
 OPEN cur1;
 SELECT 'Tuncating et_* tables...';
 read_loop: LOOP
    FETCH cur1 INTO table_n;
    SET @truncQuery = CONCAT('TRUNCATE ', table_n);
    PREPARE myQuery FROM @truncQuery;
    EXECUTE myQuery;
    DEALLOCATE PREPARE myQuery;
 IF done THEN
   LEAVE read_loop;
 END IF;
 END LOOP;
 CLOSE cur1;
END $$

# empty all version tables
CREATE PROCEDURE emptyVersionTables()
BEGIN
 DECLARE done INT DEFAULT FALSE;
 DECLARE table_n VARCHAR(255);
 DECLARE cur1 CURSOR FOR SELECT table_name FROM information_schema.tables WHERE table_name LIKE '%\_version' AND table_schema=(SELECT DATABASE()) AND table_type='BASE TABLE';
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
 OPEN cur1;
 SELECT 'Tuncating *_version tables...';
 read_loop: LOOP
    FETCH cur1 INTO table_n;
    SET @truncQuery = CONCAT('TRUNCATE ', table_n);
    PREPARE myQuery FROM @truncQuery;
    EXECUTE myQuery;
    DEALLOCATE PREPARE myQuery;
 IF done THEN
   LEAVE read_loop;
 END IF;
 END LOOP;
 CLOSE cur1;
END $$


# truncating main tables, keep 1 user, 1 site, 1 firm
CREATE PROCEDURE emptyMainTables()
BEGIN
    SELECT 'Truncating main tables...';
    TRUNCATE patient;
    TRUNCATE contact;
    TRUNCATE address;
    TRUNCATE episode;
    TRUNCATE family_history;
    TRUNCATE medication_adherence;
    TRUNCATE patient_allergy_assignment;
   # TRUNCATE patient_risk_assignment;
    TRUNCATE patient_contact_assignment;
    TRUNCATE patient_measurement;
    TRUNCATE previous_operation;
    TRUNCATE referral;
    TRUNCATE secondary_diagnosis;
    TRUNCATE socialhistory;
    TRUNCATE event;
    TRUNCATE event_issue;
    TRUNCATE gp;
    TRUNCATE practice;
    TRUNCATE measurement_reference;
    TRUNCATE referral_episode_assignment;
    TRUNCATE firm_user_assignment;
    TRUNCATE ophouanaestheticsataudit_anaesthetist_lookup;
    TRUNCATE ophtrintravitinjection_injectionuser;
    TRUNCATE ophtrlaser_laser_operator;
    TRUNCATE patientticketing_queuesetuser;
    TRUNCATE setting_user;
    TRUNCATE tbl_audit_trail;
    TRUNCATE user_firm;
    TRUNCATE user_firm_preference;
    TRUNCATE user_firm_rights;
    TRUNCATE user_service_rights;
    TRUNCATE user_site;
   # TRUNCATE audit;
    TRUNCATE audit_ipaddr;
    TRUNCATE audit_model;
    TRUNCATE audit_module;
    TRUNCATE audit_server;
    TRUNCATE audit_type;
    TRUNCATE audit_useragent;
    TRUNCATE contact_metadata;
    TRUNCATE firm_user_assignment;
    TRUNCATE medication;
    TRUNCATE medication_adherence;
    TRUNCATE patient_oph_info;
    TRUNCATE patientticketing_ticket;
    TRUNCATE patientticketing_queuesetuser;
    TRUNCATE patientticketing_ticketqueue_assignment;
    TRUNCATE patientticketing_queueoutcome;
    TRUNCATE person;
    TRUNCATE protected_file;
    TRUNCATE rtt;
    TRUNCATE setting_firm;
    TRUNCATE setting_institution;
    TRUNCATE setting_user;
    #TRUNCATE site_subspecialty_anaesthetic_agent;
    #TRUNCATE site_subspecialty_anaesthetic_agent_default;
    TRUNCATE site_subspecialty_drug;
    TRUNCATE site_subspecialty_operative_device;
    #TRUNCATE socialhistory_accommodation;
    #TRUNCATE socialhistory_carer;
    #TRUNCATE socialhistory_driving_status;
    #TRUNCATE socialhistory_driving_status_assignment;
    #TRUNCATE socialhistory_occupation;
    #TRUNCATE socialhistory_smoking_status;
    #TRUNCATE socialhistory_substance_misuse;
    TRUNCATE user_firm;
    TRUNCATE user_firm_preference;
    TRUNCATE user_firm_rights;
    TRUNCATE user_service_rights;
    TRUNCATE user_session;
    TRUNCATE user_site;
    TRUNCATE ophdrprescription_item;
    TRUNCATE ophdrprescription_item_taper;
    TRUNCATE ophtroperationbooking_operation_procedures_procedures;
    TRUNCATE ophtroperationnote_procedurelist_procedure_assignment;
    TRUNCATE ophciexamination_diagnosis;
    TRUNCATE ophciexamination_dilation_treatment;
    TRUNCATE ophciexamination_intraocularpressure_value;
    TRUNCATE ophciexamination_visualacuity_reading;
    TRUNCATE ophciphasing_reading;
    TRUNCATE ophcotherapya_email;
    #TRUNCATE ophtrconsent_leaflets;
    #TRUNCATE pas_assignment;
    TRUNCATE ophinvisualfields_field_measurement;
    TRUNCATE contact_location;
    #TRUNCATE mehbookinglogger_log;
    TRUNCATE ophtroperationbooking_operation_booking;
    TRUNCATE ophtroperationnote_postop_drugs_drug;
    TRUNCATE ophtroperationnote_anaesthetic_anaesthetic_agent;
    TRUNCATE ophtroperationbooking_operation_erod;
    DROP TABLE IF EXISTS paul_table;
    #TRUNCATE ophciexamination_nearvisualacuity_reading;

    DELETE FROM user WHERE id != 1;
    ALTER TABLE user AUTO_INCREMENT = 1;
    DELETE FROM firm WHERE id != 1;
    ALTER TABLE firm AUTO_INCREMENT = 1;
    DELETE FROM site WHERE id != 1;
    ALTER TABLE site AUTO_INCREMENT = 1;
    SELECT 'Set default values for firm and site';
    INSERT INTO contact (first_name, last_name) VALUES ('John', 'Doe');
    INSERT INTO address (address1,country_id,contact_id,address_type_id) VALUES ('Example Address', 1, 1, 2);
    UPDATE firm SET name='Example Firm', consultant_id=1;
    UPDATE user SET last_firm_id = 1;
    UPDATE site SET name='Example Site', short_name='Example', telephone='123456789', remote_id='AAAA', contact_id=1;
    UPDATE institution SET name='Example Institution', short_name='Example', contact_id=1;
    UPDATE user SET password='d45409ef1eaa57f5041bf3a1b510097b', salt='FbYJis0YG3';
    # inserting firms for all subspecialty
    INSERT INTO firm (service_subspecialty_assignment_id, name) SELECT ssa.id, concat(subspec.name, ' firm') FROM service_subspecialty_assignment ssa JOIN subspecialty subspec ON ssa.subspecialty_id=subspec.id;
END $$

CREATE PROCEDURE emptyOEDatabase()
BEGIN
    SET foreign_key_checks = 0;

    CALL emptyET_tables;
    CALL emptyVersionTables;
    CALL emptyMainTables;
    CALL updateUserId;

    SET foreign_key_checks = 1;

END $$

DELIMITER ;

CALL emptyOEDatabase;
