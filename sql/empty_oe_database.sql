DROP PROCEDURE IF EXISTS emptyET_tables;
DROP PROCEDURE IF EXISTS emptyOEDatabase;
DROP PROCEDURE IF EXISTS emptyMainTables;

DELIMITER $$

# truncating et_tables with _versions
CREATE PROCEDURE emptyET_tables()
BEGIN
 DECLARE done INT DEFAULT FALSE;
 DECLARE table_n VARCHAR(255);
 DECLARE cur1 CURSOR FOR SELECT table_name FROM information_schema.tables WHERE table_name LIKE 'et\_%' AND table_schema=(SELECT DATABASE()) AND table_type='BASE TABLE';
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
 OPEN cur1;
 read_loop: LOOP
    FETCH cur1 INTO table_n;
    SET @truncQuery = CONCAT('TRUNCATE ', table_n);
    SELECT CONCAT('Will truncate: TRUNCATE ', table_n);
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
    SELECT 'Will truncate main tables...';
    TRUNCATE patient;
    TRUNCATE patient_version;
    TRUNCATE contact;
    TRUNCATE contact_version;
    TRUNCATE address;
    TRUNCATE address_version;
    TRUNCATE episode;
    TRUNCATE episode_version;
    TRUNCATE family_history;
    TRUNCATE family_history_version;
    TRUNCATE medication_adherence;
    TRUNCATE medication_adherence_version;
    TRUNCATE patient_allergy_assignment;
    TRUNCATE patient_allergy_assignment_version;
    TRUNCATE patient_risk_assignment;
    TRUNCATE patient_risk_assignment_version;
    TRUNCATE patient_contact_assignment;
    TRUNCATE patient_contact_assignment_version;
    TRUNCATE patient_measurement;
    TRUNCATE patient_measurement_version;
    TRUNCATE previous_operation;
    TRUNCATE previous_operation_version;
    TRUNCATE referral;
    TRUNCATE referral_version;
    TRUNCATE secondary_diagnosis;
    TRUNCATE secondary_diagnosis_version;
    TRUNCATE socialhistory;
    TRUNCATE socialhistory_version;
    TRUNCATE event;
    TRUNCATE event_version;
    TRUNCATE event_issue;
    TRUNCATE event_issue_version;
    TRUNCATE gp;
    TRUNCATE gp_version;
    TRUNCATE practice;
    TRUNCATE practice_version;
    TRUNCATE measurement_reference;
    TRUNCATE measurement_reference_version;
    TRUNCATE referral_episode_assignment;
    TRUNCATE referral_episode_assignment_version;
    TRUNCATE audit;
    TRUNCATE audit_action;
    TRUNCATE audit_ipaddr;
    TRUNCATE audit_model;
    TRUNCATE audit_module;
    TRUNCATE audit_server;
    TRUNCATE audit_type;
    TRUNCATE audit_useragent;
    DELETE FROM user WHERE id != 1;
    TRUNCATE user_version;
    DELETE FROM firm WHERE id != 1;
    TRUNCATE firm_version;
    DELETE FROM site WHERE id != 1;
    TRUNCATE site_version;
    SELECT 'Set default values for firm and site';
    INSERT INTO contact (first_name, last_name) VALUES ('John', 'Doe');
    INSERT INTO address (address1,country_id,contact_id,address_type_id) VALUES ('Default Address', 1, 1, 2);
    UPDATE firm SET name='Default Firm';
    UPDATE user SET last_firm_id = 1;
    UPDATE site SET name='Default Site', short_name='Default', telephone='123456789', remote_id='AAAA', contact_id=1;
    UPDATE institution SET name='Default Institution', short_name='Default', contact_id=1;
END $$

CREATE PROCEDURE emptyOEDatabase()
BEGIN
    SET foreign_key_checks = 0;

    CALL emptyET_tables;
    CALL emptyMainTables;

    SET foreign_key_checks = 1;

END $$

DELIMITER ;

CALL emptyOEDatabase;
