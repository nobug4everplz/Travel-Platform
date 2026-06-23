-- ============================================================
-- Fix: trusted_devices token_hash migration
-- Applies to existing PostgreSQL databases to add the
-- missing token_hash column required by trusted-devices.php
--
-- Context: schema.sql has this column, but the PG demo DB
-- created from init-db.php does not, causing login crash:
--   PDOException: column "token_hash" of relation
--   "trusted_devices" does not exist
-- ============================================================

ALTER TABLE trusted_devices
  ADD COLUMN IF NOT EXISTS token_hash CHAR(64);

-- Add a unique constraint on token_hash.
-- PostgreSQL allows multiple NULLs in unique constraints,
-- so existing rows with NULL values are fine.
-- This uses a DO block to avoid error if the constraint
-- already exists.
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint
    WHERE conname = 'trusted_devices_token_hash_unique'
      AND conrelid = 'trusted_devices'::regclass
  ) THEN
    ALTER TABLE trusted_devices
      ADD CONSTRAINT trusted_devices_token_hash_unique
      UNIQUE (token_hash);
  END IF;
END $$;
