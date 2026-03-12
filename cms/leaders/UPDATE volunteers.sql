UPDATE volunteers 
SET next_check_in = DATE_ADD(CURDATE(), INTERVAL FLOOR(RAND() * 30) DAY) 
WHERE next_check_in IS NULL;