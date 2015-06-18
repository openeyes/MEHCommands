DELIMITER //
DROP FUNCTION IF EXISTS str_random_lipsum;
//

CREATE FUNCTION str_random_lipsum(p_max_words SMALLINT
                                 ,p_min_words SMALLINT
                                 ,p_start_with_lipsum TINYINT(1)
                                 )
    RETURNS VARCHAR(10000)
    NO SQL
    BEGIN
    /**
    * String function. Returns a random Lorum Ipsum string of nn words
    * <br>
    * %author Ronald Speelman
    * %version 1.0
    * Example usage:
    * SELECT str_random_lipsum(5,NULL,NULL) AS fiveWordsExactly;
    * SELECT str_random_lipsum(10,5,0) AS five-tenWords;
    * SELECT str_random_lipsum(50,10,1) AS startWithLorumIpsum;
    * See more complex examples and a description on www.moinne.com/blog/ronald
    *
    * %param p_max_words         Number: the maximum amount of words, if no
    *                                    min_words are provided this will be the
    *                                    exaxt amount of words in the result
    *                                    Default = 50
    * %param p_min_words         Number: the minimum amount of words in the
    *                                    result, By providing the parameter, you provide a range
    *                                    Default = 0
    * %param p_start_with_lipsum Boolean:if "1" the string will start with
    *                                    'Lorum ipsum dolor sit amet.', Default = 0
    * %return String
    */

        DECLARE v_max_words SMALLINT DEFAULT 50;
        DECLARE v_random_item SMALLINT DEFAULT 0;
        DECLARE v_random_word VARCHAR(25) DEFAULT '';
        DECLARE v_start_with_lipsum TINYINT DEFAULT 0;
        DECLARE v_result VARCHAR(10000) DEFAULT '';
        DECLARE v_iter INT DEFAULT 1;
        DECLARE v_text_lipsum VARCHAR(1500) DEFAULT 'a ac accumsan ad adipiscing aenean aliquam aliquet amet ante aptent arcu at auctor augue bibendum blandit class commodo condimentum congue consectetuer consequat conubia convallis cras cubilia cum curabitur curae; cursus dapibus diam dictum dignissim dis dolor donec dui duis egestas eget eleifend elementum elit enim erat eros est et etiam eu euismod facilisi facilisis fames faucibus felis fermentum feugiat fringilla fusce gravida habitant hendrerit hymenaeos iaculis id imperdiet in inceptos integer interdum ipsum justo lacinia lacus laoreet lectus leo libero ligula litora lobortis lorem luctus maecenas magna magnis malesuada massa mattis mauris metus mi molestie mollis montes morbi mus nam nascetur natoque nec neque netus nibh nisi nisl non nonummy nostra nulla nullam nunc odio orci ornare parturient pede pellentesque penatibus per pharetra phasellus placerat porta porttitor posuere praesent pretium primis proin pulvinar purus quam quis quisque rhoncus ridiculus risus rutrum sagittis sapien scelerisque sed sem semper senectus sit sociis sociosqu sodales sollicitudin suscipit suspendisse taciti tellus tempor tempus tincidunt torquent tortor tristique turpis ullamcorper ultrices ultricies urna ut varius vehicula vel velit venenatis vestibulum vitae vivamus viverra volutpat vulputate';
        DECLARE v_text_lipsum_wordcount INT DEFAULT 180;
        DECLARE v_sentence_wordcount INT DEFAULT 0;
        DECLARE v_sentence_start BOOLEAN DEFAULT 1;
        DECLARE v_sentence_end BOOLEAN DEFAULT 0;
        DECLARE v_sentence_lenght TINYINT DEFAULT 9;

        SET v_max_words := COALESCE(p_max_words, v_max_words);
        SET v_start_with_lipsum := COALESCE(p_start_with_lipsum , v_start_with_lipsum);

        IF p_min_words IS NOT NULL THEN
            SET v_max_words := FLOOR(p_min_words + (RAND() * (v_max_words - p_min_words)));
        END IF;

        IF v_max_words < v_sentence_lenght THEN
            SET v_sentence_lenght := v_max_words;
        END IF;

        IF p_start_with_lipsum = 1 THEN
            SET v_result := CONCAT(v_result,'Lorem ipsum dolor sit amet.');
            SET v_max_words := v_max_words - 5;
        END IF;

        WHILE v_iter <= v_max_words DO
            SET v_random_item := FLOOR(1 + (RAND() * v_text_lipsum_wordcount));
            SET v_random_word := REPLACE(SUBSTRING(SUBSTRING_INDEX(v_text_lipsum, ' ' ,v_random_item),
                                           CHAR_LENGTH(SUBSTRING_INDEX(v_text_lipsum,' ', v_random_item -1)) + 1),
                                           ' ', '');

            SET v_sentence_wordcount := v_sentence_wordcount + 1;
            IF v_sentence_wordcount = v_sentence_lenght THEN
                SET v_sentence_end := 1 ;
            END IF;

            IF v_sentence_start = 1 THEN
                SET v_random_word := CONCAT(UPPER(SUBSTRING(v_random_word, 1, 1))
                                            ,LOWER(SUBSTRING(v_random_word FROM 2)));
                SET v_sentence_start := 0 ;
            END IF;

            IF v_sentence_end = 1 THEN
                IF v_iter <> v_max_words THEN
                    SET v_random_word := CONCAT(v_random_word, '.');
                END IF;
                SET v_sentence_lenght := FLOOR(9 + (RAND() * 7));
                SET v_sentence_end := 0 ;
                SET v_sentence_start := 1 ;
                SET v_sentence_wordcount := 0 ;
            END IF;

            SET v_result := CONCAT(v_result,' ', v_random_word);
            SET v_iter := v_iter + 1;
        END WHILE;

        RETURN TRIM(CONCAT(v_result,'.'));
END;
//
DELIMITER ;

DROP PROCEDURE IF EXISTS shuffleAddress;
DROP PROCEDURE IF EXISTS shuffleContact;
DROP PROCEDURE IF EXISTS anonymize_data;
DROP FUNCTION IF EXISTS shuffleAddress;
DROP FUNCTION IF EXISTS shuffleContact;
DROP FUNCTION IF EXISTS anonymize_data;

DELIMITER $$

CREATE FUNCTION shuffleAddress() RETURNS boolean
BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE myId integer;
DECLARE pc_cursor CURSOR FOR SELECT id FROM address;
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
DECLARE pc_cursor CURSOR FOR SELECT id FROM contact ;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

OPEN pc_cursor;

pc_loop: LOOP
FETCH pc_cursor INTO myId;
IF done THEN
LEAVE pc_loop;
END IF;
SELECT t.firstn, t.title FROM (SELECT first_name AS firstn, title  FROM contact WHERE id = (SELECT a.id FROM contact a JOIN (SELECT (rand()*max(id)) AS rand_id FROM contact) AS r WHERE a.id>=r.rand_id LIMIT 1)) t INTO myFirstName, myTitle;
UPDATE contact SET first_name=myFirstName, title=myTitle WHERE id=myId;
UPDATE contact SET last_name=(SELECT t.lastn FROM (SELECT last_name AS lastn FROM contact WHERE id = (SELECT a.id FROM contact a JOIN (SELECT (rand()*max(id)) AS rand_id FROM contact) AS r WHERE a.id>=r.rand_id LIMIT 1)) t ) WHERE id=myId;
#UPDATE patient SET gender=(SELECT CASE WHEN myTitle = 'Mr' THEN 'M' WHEN myTitle='Ms' OR myTitle='Mrs' OR myTitle='Miss' THEN 'F' END) WHERE contact_id=myId;
END LOOP;

CLOSE pc_cursor;

RETURN 1;
END $$

DELIMITER ;

DELIMITER $$

CREATE PROCEDURE anonymize_data()
BEGIN
SELECT shuffleAddress();
SELECT shuffleContact();
UPDATE address SET email=CONCAT(SUBSTRING(email,1,LOCATE('@',email)),'moorfields.com');
UPDATE contact SET primary_phone=CONCAT(SUBSTRING(primary_phone, 1, 9), '0000');
UPDATE patient SET dob=DATE_FORMAT(dob, CONCAT('%Y-',FLOOR(1+RAND()*(12-1)),'-',FLOOR(1+RAND()*(28-1))));
UPDATE patient SET date_of_death=DATE_FORMAT(date_of_death, '%Y-01-01');
UPDATE et_ophcocorrespondence_letter SET body=(SELECT str_random_lipsum(500,200,1)), address=(SELECT str_random_lipsum(5,2,1)), re=(SELECT str_random_lipsum(15,5,1)), cc=concat('Patient:',(SELECT str_random_lipsum(15,5,1))), footer=concat('Yours sincerely\n\n',(SELECT str_random_lipsum(5,2,1))), direct_line=CONCAT(SUBSTRING(direct_line, 1, 9), '0000'), fax=CONCAT(SUBSTRING(fax, 1, 9), '0000');

END $$

DELIMITER ;

CALL anonymize_data;