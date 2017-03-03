SELECT `cmf_schedule`.*
FROM `cmf_schedule`
INNER JOIN `cmf_exercise_club_address` ON `cmf_exercise_club_address`.`id`=`cmf_schedule`.`club_address_id`
INNER JOIN `cmf_club_address` ON `cmf_club_address`.`id`=`cmf_exercise_club_address`.`club_address_id`
INNER JOIN `cmf_schedule_time` ON `cmf_schedule_time`.`id`=`cmf_schedule`.`schedule_time_begin_id`
LEFT JOIN `cmf_dictionary_metro` ON `cmf_dictionary_metro`.`id`=`cmf_club_address`.`metro_id`
WHERE (
        `cmf_schedule`.`day`='2017-03-03')
    AND (
            (
            2 * 6371 * ASIN(SQRT(
                SIN(
                    (RADIANS(`cmf_club_address`.`latitude`) - RADIANS('55.766194269665384')) / 2
                )*SIN(
                    (RADIANS(`cmf_club_address`.`latitude`) - RADIANS('55.766194269665384')) / 2
                )+SIN(
                    (RADIANS(`cmf_club_address`.`longitude`) - RADIANS('37.61184753417968')) / 2
                )*SIN(
                    (RADIANS(`cmf_club_address`.`longitude`) - RADIANS('37.61184753417968')) / 2
                )* COS(RADIANS(`cmf_club_address`.`latitude`)) * COS(RADIANS('55.766194269665384'))
            ))
            ) < '20'
        )
    AND
        (TIME_FORMAT(`cmf_schedule_time`.`time`, "%l") >= 6)
    AND (TIME_FORMAT(`cmf_schedule_time`.`time`, "%l") <= 24)
    AND (`cmf_schedule`.`exercise_id`='2')
    AND (`cmf_schedule`.`club_site_id`='3')
    AND (`cmf_dictionary_metro`.`id`='2')
    ORDER BY `cmf_schedule_time`.`time` LIMIT 5