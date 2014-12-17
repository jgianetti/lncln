UPDATE user_acl SET module = '*' WHERE module = 'all';
UPDATE user_acl SET `action` = '*' WHERE `action` = 'all';
UPDATE user_acl SET `action_filter_criteria` = '*' WHERE `action_filter_criteria` = 'all';
UPDATE `lincoln`.`user_acl` SET `action`= NULL WHERE `id`='1';
