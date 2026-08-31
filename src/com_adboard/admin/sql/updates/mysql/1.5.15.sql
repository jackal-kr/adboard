-- v1.5.15: drop checked_out / checked_out_time columns
-- com_adboard does not use Joomla checkout locking (uses moderation workflow).
-- These columns caused confusion in Global Check-in and could not be cleared.
ALTER TABLE `#__adboard`
    DROP COLUMN IF EXISTS `checked_out`,
    DROP COLUMN IF EXISTS `checked_out_time`;
