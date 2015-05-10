DROP PROCEDURE IF EXISTS extract_patient_data;
DELIMITER $$
CREATE PROCEDURE extract_patient_data(IN hopital_number integer(10))
    BEGIN

      SET @patient_id = (SELECT id FROM patient WHERE hos_num = hopital_number);

      IF (@patient_id IS NOT NULL) THEN

        SET @currentTable = 'patient';
        SET @sql = '';
        SET @fileLocation = concat('/tmp/',  @patient_id, '_' , UNIX_TIMESTAMP(), '.csv');

        SET @sql = concat('SELECT * FROM patient INTO OUTFILE', ' ', @fileLocation);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;


      END IF;

    END $$

DELIMITER ;


call extract_patient_data(1000001);