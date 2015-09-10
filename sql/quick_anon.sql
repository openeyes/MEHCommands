SET SESSION group_concat_max_len = 40000000000000;


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





DELIMITER $$
DROP PROCEDURE IF EXISTS shuffleAddress;
CREATE DEFINER=`root`@`localhost` PROCEDURE shuffleAddress()
  BEGIN


    CREATE TABLE IF NOT EXISTS temp_address AS (SELECT * FROM address);

    SET @min = 0;
    SET @max = 100;
    SET @random_id_1 = NULL;

    SELECT @min, @max;

    SELECT max(id) INTO @max_id FROM temp_address;

    WHILE(@max < @max_id) DO

      SET @random_id_1 = (SELECT a.id FROM temp_address a JOIN (SELECT (rand()*max(2500126)) AS rand_id FROM temp_address) AS r WHERE a.id>=r.rand_id LIMIT 1);


      SELECT address1, city INTO @address1, @city FROM temp_address WHERE id = @random_id_1;


      UPDATE address SET address1 = @address1, city=@city WHERE (id > @min) AND (id <= @max);

      SET @min = (@min + 100);
      SET @max = (@max + 100);
    END WHILE;


    DROP TABLE temp_address;

    UPDATE address SET postcode=CONCAT(SUBSTRING(postcode,1,LOCATE(' ',postcode)),'0XY');

  END $$

DELIMITER ;



DELIMITER $$
DROP PROCEDURE IF EXISTS shuffleContact;
CREATE DEFINER=`root`@`localhost` PROCEDURE shuffleContact()
  BEGIN



    CREATE TABLE IF NOT EXISTS temp_contact AS (SELECT * FROM contact);

    SET @min = 0;
    SET @max = 100;
    SET @random_id_1 = NULL;
    SET @random_id_2 = NULL;

    SELECT max(id) INTO @max_id FROM temp_contact;

    WHILE(@max < @max_id) DO

      SET @random_id_1 = (SELECT a.id FROM temp_contact a JOIN (SELECT (rand()*max(581815)) AS rand_id FROM temp_contact) AS r WHERE a.id>=r.rand_id LIMIT 1);
      SET @random_id_2 = (SELECT a.id FROM temp_contact a JOIN (SELECT (rand()*max(581815)) AS rand_id FROM temp_contact) AS r WHERE a.id>=r.rand_id LIMIT 1);

      SELECT title, first_name INTO @title, @first_name FROM temp_contact WHERE id = @random_id_1;
      SELECT last_name INTO @last_name FROM temp_contact WHERE id = @random_id_2;

      UPDATE contact SET title = @title, first_name=@first_name, last_name = @last_name WHERE (id > @min) AND (id <= @max);

      SET @min = (@min + 100);
      SET @max = (@max + 100);
    END WHILE;


    DROP TABLE temp_contact;

  END $$

DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS anonymize_data;
CREATE PROCEDURE anonymize_data()
  BEGIN
    call shuffleContact();
    call shuffleAddress();
    UPDATE address SET email=CONCAT(SUBSTRING(email,1,LOCATE('@',email)),'moorfields.com');
    UPDATE contact SET primary_phone=CONCAT(SUBSTRING(primary_phone, 1, 9), '0000');
    UPDATE practice SET phone=CONCAT(SUBSTRING(phone, 1, 9), '0000');
    UPDATE patient SET dob=DATE_FORMAT(dob, CONCAT('%Y-',FLOOR(1+RAND()*(12-1)),'-',FLOOR(1+RAND()*(28-1))));
    UPDATE patient SET date_of_death=DATE_FORMAT(date_of_death, '%Y-01-01') WHERE date_of_death IS NOT NULL;
    UPDATE et_ophcocorrespondence_letter SET body=(SELECT str_random_lipsum(500,200,1)), address=(SELECT str_random_lipsum(5,2,1)), re=(SELECT str_random_lipsum(15,5,1)), cc=concat('Patient:',(SELECT str_random_lipsum(15,5,1))), footer=concat('Yours sincerely\n\n',(SELECT str_random_lipsum(5,2,1))), direct_line=CONCAT(SUBSTRING(direct_line, 1, 9), '0000'), fax=CONCAT(SUBSTRING(fax, 1, 9), '0000');
    UPDATE user SET first_name=(SELECT first_name FROM contact WHERE id = contact_id), last_name=(SELECT last_name FROM contact WHERE id = contact_id), username=(SELECT last_name FROM contact WHERE id = contact_id),email='' WHERE username != 'admin';
    UPDATE firm SET name = concat((SELECT first_name FROM user WHERE id=consultant_id),' ',(SELECT last_name FROM user WHERE id=consultant_id)) WHERE consultant_id IS NOT NULL;

  END $$

DELIMITER ;

call anonymize_data;



