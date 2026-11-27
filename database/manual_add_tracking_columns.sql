-- Run once on the host database after confirming the api_* table names.
-- Existing rows keep NULL timestamps and creator/updater IDs.

ALTER TABLE `api_counties`
    ADD COLUMN `created_at` TIMESTAMP NULL,
    ADD COLUMN `updated_at` TIMESTAMP NULL,
    ADD COLUMN `created_by` BIGINT UNSIGNED NULL,
    ADD CONSTRAINT `api_counties_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `api_users` (`id`) ON DELETE SET NULL,
    ADD COLUMN `updated_by` BIGINT UNSIGNED NULL,
    ADD CONSTRAINT `api_counties_updated_by_foreign`
        FOREIGN KEY (`updated_by`) REFERENCES `api_users` (`id`) ON DELETE SET NULL;

ALTER TABLE `api_cities`
    ADD COLUMN `created_at` TIMESTAMP NULL,
    ADD COLUMN `updated_at` TIMESTAMP NULL,
    ADD COLUMN `created_by` BIGINT UNSIGNED NULL,
    ADD CONSTRAINT `api_cities_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `api_users` (`id`) ON DELETE SET NULL,
    ADD COLUMN `updated_by` BIGINT UNSIGNED NULL,
    ADD CONSTRAINT `api_cities_updated_by_foreign`
        FOREIGN KEY (`updated_by`) REFERENCES `api_users` (`id`) ON DELETE SET NULL;

ALTER TABLE `api_postcodes`
    ADD COLUMN `created_at` TIMESTAMP NULL,
    ADD COLUMN `updated_at` TIMESTAMP NULL,
    ADD COLUMN `created_by` BIGINT UNSIGNED NULL,
    ADD CONSTRAINT `api_postcodes_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `api_users` (`id`) ON DELETE SET NULL,
    ADD COLUMN `updated_by` BIGINT UNSIGNED NULL,
    ADD CONSTRAINT `api_postcodes_updated_by_foreign`
        FOREIGN KEY (`updated_by`) REFERENCES `api_users` (`id`) ON DELETE SET NULL;

-- Register these migrations so a later `php artisan migrate` does not run them again.
SET @tracking_migration_batch = (
    SELECT COALESCE(MAX(`batch`), 0) + 1
    FROM `api_migrations`
);

INSERT INTO `api_migrations` (`migration`, `batch`)
VALUES
    ('2026_11_27_091944_add_timestamps_and_audit_columns_to_counties_table', @tracking_migration_batch),
    ('2026_11_27_091945_add_timestamps_and_audit_columns_to_cities_table', @tracking_migration_batch),
    ('2026_11_27_091947_add_timestamps_and_audit_columns_to_postcodes_table', @tracking_migration_batch);
