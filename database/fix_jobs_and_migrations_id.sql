-- Fix: Field 'id' doesn't have a default value
-- Run this in MySQL (e.g. phpMyAdmin) using your app database, then run: php artisan migrate

-- Fix migrations table (so Laravel can record new migrations)
ALTER TABLE `migrations` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

-- Fix jobs table (so queue jobs can be inserted)
ALTER TABLE `jobs` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;





