DELIMITER $$

CREATE FUNCTION shuffleAddress() RETURNS boolean
BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE myId integer;
DECLARE pc_cursor CURSOR FOR SELECT id FROM address LIMIT 10;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

OPEN pc_cursor;

pc_loop: LOOP
FETCH pc_cursor INTO myId;
IF done THEN
LEAVE pc_loop;
END IF;
UPDATE address SET postcode=(SELECT t.postc FROM (SELECT postcode AS postc FROM address WHERE id = (SELECT a.id FROM address a JOIN (SELECT (rand()*max(id)) AS rand_id FROM address) AS r WHERE a.id>=r.rand_id LIMIT 1)) t ) WHERE id=myId;
UPDATE address SET address1=(SELECT t.addr1 FROM (SELECT address1 AS addr1 FROM address WHERE id = (SELECT a.id FROM address a JOIN (SELECT (rand()*max(id)) AS rand_id FROM address) AS r WHERE a.id>=r.rand_id LIMIT 1)) t ) WHERE id=myId;
END LOOP;

CLOSE pc_cursor;

RETURN 1;
END $$

DELIMITER ;

DELIMITER $$

CREATE FUNCTION shuffleContact() RETURNS boolean
BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE myId integer;
DECLARE myTitle varchar(20);
DECLARE myFirstName varchar(100);
DECLARE pc_cursor CURSOR FOR SELECT id FROM contact LIMIT 10;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

OPEN pc_cursor;

pc_loop: LOOP
FETCH pc_cursor INTO myId;
IF done THEN
LEAVE pc_loop;
END IF;
SELECT t.firstn, t.title FROM (SELECT first_name AS firstn, title  FROM contact WHERE id = (SELECT a.id FROM contact a JOIN (SELECT (rand()*max(id)) AS rand_id FROM contact) AS r WHERE a.id>=r.rand_id LIMIT 1)) t INTO myFirstName, myTitle;
UPDATE contact SET first_name=myFirstName, title=myTitle WHERE id=myId;
#UPDATE contact SET last_name=(SELECT t.lastn FROM (SELECT last_name AS lastn FROM contact WHERE id = (SELECT a.id FROM contact a JOIN (SELECT (rand()*max(id)) AS rand_id FROM contact) AS r WHERE a.id>=r.rand_id LIMIT 1)) t ) WHERE id=myId;
#UPDATE patient SET gender=(SELECT CASE WHEN myTitle = 'Mr' THEN 'M' WHEN myTitle='Ms' OR myTitle='Mrs' OR myTitle='Miss' THEN 'F' END) WHERE contact_id=myId;
END LOOP;

CLOSE pc_cursor;

RETURN 1;
END $$

DELIMITER ;

DELIMITER $$

CREATE FUNCTION anonymize_data() RETURNS boolean
BEGIN
SELECT shuffleAddress();
SELECT shuffleContact();
UPDATE address SET email=CONCAT(SUBSTRING(email,1,LOCATE('@',email)),'moorfields.com');
UPDATE contact SET primary_phone=CONCAT(SUBSTRING(primary_phone, 1, 9), '0000');
UPDATE patient SET dob=DATE_FORMAT(dob, CONCAT('%Y-',FLOOR(1+RAND()*(12-1)),'-',FLOOR(1+RAND()*(28-1))));
UPDATE patient SET date_of_death=DATE_FORMAT(date_of_death, '%Y-01-01');


RETURN 1;
END $$

DELIMITER ;