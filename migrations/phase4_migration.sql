-- ============================================================
-- Phase 4 Migration: Geographic Location Data
-- Applies to existing databases to add:
--   1) trips table: latitude, longitude, place_id, address
--   2) trip_spots table (行程景點)
--   3) traveler_footprints table (旅行者足跡)
--
-- Encoding: utf8mb4 · Engine: InnoDB
-- ============================================================

USE travel_platform_db;

-- -----------------------------------------------------------
-- 1. Add geographic columns to trips
-- -----------------------------------------------------------
ALTER TABLE trips
  ADD COLUMN latitude DECIMAL(10,7) NULL AFTER updated_at,
  ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
  ADD COLUMN place_id VARCHAR(255) NULL AFTER longitude,
  ADD COLUMN address VARCHAR(512) NULL AFTER place_id;

-- -----------------------------------------------------------
-- 2. Create trip_spots table (行程景點)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS trip_spots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  trip_id INT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  place_id VARCHAR(255) NULL,
  address VARCHAR(512) NULL,
  google_maps_url VARCHAR(2048) NULL,
  notes TEXT NULL,
  PRIMARY KEY (id),
  KEY trip_spots_trip_id_index (trip_id),
  CONSTRAINT trip_spots_trip_id_foreign
    FOREIGN KEY (trip_id) REFERENCES trips(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 3. Create traveler_footprints table (旅行者足跡)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS traveler_footprints (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  trip_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  place_id VARCHAR(255) NULL,
  visited_at TIMESTAMP NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY traveler_footprints_user_id_index (user_id),
  KEY traveler_footprints_trip_id_index (trip_id),
  CONSTRAINT traveler_footprints_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT traveler_footprints_trip_id_foreign
    FOREIGN KEY (trip_id) REFERENCES trips(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
