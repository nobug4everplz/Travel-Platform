-- ============================================================
-- Phase 5B Migration: Budget & Currency
-- Applies to existing databases to add:
--   1) trips.budget    DECIMAL(10,2) NULL
--   2) trips.currency  VARCHAR(3) DEFAULT 'TWD'
-- ============================================================

USE travel_platform_db;

ALTER TABLE trips
  ADD COLUMN budget DECIMAL(10,2) NULL AFTER end_date,
  ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'TWD' AFTER budget;
