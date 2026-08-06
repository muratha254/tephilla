-- Add driver_commission_rate column to setting table
ALTER TABLE `setting` 
ADD COLUMN `driver_commission_rate` DECIMAL(5,2) DEFAULT 0 AFTER `diskon`;






