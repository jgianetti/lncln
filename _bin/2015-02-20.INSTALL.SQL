USE 'lincoln';

UPDATE `cc_purchase` SET closed_on = opened_on, closed_by = opened_by, status = 2 WHERE closed_on IS NULL;

-- Add cc_purchase.status
ALTER TABLE `cc_purchase` DROP COLUMN `status`;
