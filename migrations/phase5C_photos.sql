-- ============================================================
-- Phase 5C Migration: Trip Photos (照片牆)
-- Creates trip_photos table for traveler photo uploads
-- ============================================================

CREATE TABLE IF NOT EXISTS trip_photos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  trip_id INT UNSIGNED NOT NULL,
  spot_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  caption VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY trip_photos_trip_id_index (trip_id),
  KEY trip_photos_spot_id_index (spot_id),
  KEY trip_photos_user_id_index (user_id),
  CONSTRAINT trip_photos_trip_id_foreign
    FOREIGN KEY (trip_id) REFERENCES trips(id)
    ON DELETE CASCADE,
  CONSTRAINT trip_photos_spot_id_foreign
    FOREIGN KEY (spot_id) REFERENCES trip_spots(id)
    ON DELETE SET NULL,
  CONSTRAINT trip_photos_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
