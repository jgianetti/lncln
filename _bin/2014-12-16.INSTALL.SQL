USE 'lincoln';

UPDATE user_acl SET module = '*' WHERE module = 'all';
UPDATE user_acl SET `action` = '*' WHERE `action` = 'all';
UPDATE user_acl SET `action_filter_criteria` = '*' WHERE `action_filter_criteria` = 'all';
UPDATE `lincoln`.`user_acl` SET `action`= NULL WHERE `id`='1';

-- Add cc_purchase.status
ALTER TABLE `cc_purchase`
ADD COLUMN `status` TINYINT UNSIGNED NULL DEFAULT 0 AFTER `comments`;

-- Set cuurent purchases as Authorized
UPDATE cc_purchase SET status = '2';

-- clean comments
UPDATE `lincoln`.`cc_purchase` SET `comments`=NULL WHERE `comments` LIKE '%&nbsp;%';
-- remove tabs
UPDATE lincoln.cc_product SET name = TRIM(CHAR(9) FROM TRIM(name));