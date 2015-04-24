drop procedure if exists load_new_site_data;

DELIMITER #

create procedure load_new_site_data()

        BEGIN


            SET @site_id=942; /* CHANGE THIS VALUE ONLY. This value has to be set to the new site id. new site has to have been created at this point */
            SET @row_iterator_max = 16;
            SET @max_count = 8;
            SET @strLen = 0;
            SET @subStrLen = 0;
            SET @index = 1;
            SET @removeStr = '';
            SET @device_default_value = 0;


            SET @contentList = 'Check AC and discharge,
                                Check AC, IOP and discharge,
                                No post op assessment required on day of surgery. Discharge,
                                To be reviewed by surgeon before discharge';



            WHILE @index <= 4 DO


                SET @content =  TRIM(SUBSTRING_INDEX(@contentList, ",", 1));

                SET @row_iterator = 1;
                WHILE @row_iterator <= @row_iterator_max DO

                    INSERT INTO ophtroperationnote_site_subspecialty_postop_instructions(id,  site_id,  subspecialty_id, content)
                    VALUES (NULL, @site_id, @row_iterator, @content);

                    SET @row_iterator = @row_iterator+1;
                END WHILE;

                SET @removeStr = CONCAT(@content, ",");

                SET @contentList = REPLACE( @contentList, @removeStr, "");

                SET @index = @index + 1;

            END WHILE;


            SET @specialty_counter = 1;
            WHILE @specialty_counter < @max_count DO
               SET @row_iterator = 1;
               WHILE @row_iterator <= @row_iterator_max DO

                 IF @specialty_counter = 7  THEN
                     SET @device_default_value = 1;
                 ELSE
                     SET @device_default_value = 0;
                 END IF;

                 INSERT INTO site_subspecialty_operative_device (`id`, `site_id`, `subspecialty_id`, `operative_device_id`, `default`, last_modified_date, created_date)
                 VALUES (NULL, @site_id, @row_iterator, @specialty_counter, @device_default_value, now(), now());

                 SET @row_iterator = @row_iterator+1;
               END WHILE;
               SET @specialty_counter = @specialty_counter+1;
            END WHILE;


            SET @specialty_counter = 1;
            WHILE @specialty_counter <= 7 DO
               SET @row_iterator = 1;
               WHILE @row_iterator <= @row_iterator_max DO
                 INSERT INTO site_subspecialty_anaesthetic_agent (id, site_id,  subspecialty_id, anaesthetic_agent_id) VALUES (NULL, @site_id, @row_iterator, @specialty_counter);
                 SET @row_iterator = @row_iterator+1;
               END WHILE;
               SET @specialty_counter = @specialty_counter+1;
            END WHILE;


            SET @specialty_counter = 1;
            WHILE @specialty_counter <= 2 DO
               SET @row_iterator = 1;
               WHILE @row_iterator <= @row_iterator_max DO
                 INSERT INTO site_subspecialty_anaesthetic_agent_default (id, site_id,  subspecialty_id, anaesthetic_agent_id) VALUES (NULL, @site_id, @row_iterator, @specialty_counter);
                 SET @row_iterator = @row_iterator+1;
               END WHILE;
               SET @specialty_counter = @specialty_counter+1;
            END WHILE;


            CREATE TEMPORARY TABLE tmptable SELECT * FROM ophtroperationnote_postop_site_subspecialty_drug where site_id=1;
            UPDATE tmptable SET site_id=@site_id;
            UPDATE tmptable SET id=NULL;
            INSERT INTO ophtroperationnote_postop_site_subspecialty_drug SELECT * FROM tmptable;
            DROP TABLE tmptable;

      END #

DELIMITER ;

call load_new_site_data();