DROP PROCEDURE IF EXISTS emptyET_tables;
DROP PROCEDURE IF EXISTS emptyOEDatabase;
DROP PROCEDURE IF EXISTS emptyMainTables;
DROP PROCEDURE IF EXISTS emptyVersionTables;

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

# empty all version tables
CREATE PROCEDURE emptyVersionTables()
BEGIN
 DECLARE done INT DEFAULT FALSE;
 DECLARE table_n VARCHAR(255);
 DECLARE cur1 CURSOR FOR SELECT table_name FROM information_schema.tables WHERE table_name LIKE '%\_version' AND table_schema=(SELECT DATABASE()) AND table_type='BASE TABLE';
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
    TRUNCATE contact;
    TRUNCATE address;
    TRUNCATE episode;
    TRUNCATE family_history;
    TRUNCATE medication_adherence;
    TRUNCATE patient_allergy_assignment;
    TRUNCATE patient_risk_assignment;
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
    TRUNCATE audit;
    TRUNCATE audit_ipaddr;
    TRUNCATE audit_model;
    TRUNCATE audit_module;
    TRUNCATE audit_server;
    TRUNCATE audit_type;
    TRUNCATE audit_useragent;
    DELETE FROM user WHERE id != 1;
    DELETE FROM firm WHERE id != 1;
    DELETE FROM site WHERE id != 1;
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
    CALL emptyVersionTables;

    SET foreign_key_checks = 1;

END $$

DELIMITER ;

CALL emptyOEDatabase;
