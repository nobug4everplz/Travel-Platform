CREATE DATABASE IF NOT EXISTS travel_platform_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE travel_platform_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS email_delivery_logs;
DROP TABLE IF EXISTS trip_daily_unique_views;
DROP TABLE IF EXISTS notification_preferences;
DROP TABLE IF EXISTS login_events;
DROP TABLE IF EXISTS trusted_devices;
DROP TABLE IF EXISTS favorite_planners;
DROP TABLE IF EXISTS favorite_trips;
DROP TABLE IF EXISTS trip_participations;
DROP TABLE IF EXISTS trips;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(120) NULL,
  avatar_url VARCHAR(2048) NULL,
  role ENUM('traveler', 'planner', 'admin') NOT NULL DEFAULT 'traveler',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email),
  KEY users_role_index (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  cover_image VARCHAR(2048) NULL,
  summary TEXT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  author_id INT UNSIGNED NOT NULL,
  average_rating DECIMAL(3,1) NULL,
  review_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY trips_author_id_index (author_id),
  KEY trips_is_published_index (is_published),
  CONSTRAINT trips_author_id_foreign
    FOREIGN KEY (author_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trip_participations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  trip_id INT UNSIGNED NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY trip_participations_user_trip_unique (user_id, trip_id),
  KEY trip_participations_trip_id_index (trip_id),
  CONSTRAINT trip_participations_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT trip_participations_trip_id_foreign
    FOREIGN KEY (trip_id) REFERENCES trips(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorite_trips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  trip_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY favorite_trips_user_trip_unique (user_id, trip_id),
  KEY favorite_trips_trip_id_index (trip_id),
  CONSTRAINT favorite_trips_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT favorite_trips_trip_id_foreign
    FOREIGN KEY (trip_id) REFERENCES trips(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorite_planners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  traveler_id INT UNSIGNED NOT NULL,
  planner_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY favorite_planners_traveler_planner_unique (traveler_id, planner_id),
  KEY favorite_planners_planner_id_index (planner_id),
  CONSTRAINT favorite_planners_traveler_id_foreign
    FOREIGN KEY (traveler_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT favorite_planners_planner_id_foreign
    FOREIGN KEY (planner_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reviewer_id INT UNSIGNED NOT NULL,
  trip_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY reviews_reviewer_trip_unique (reviewer_id, trip_id),
  KEY reviews_trip_id_index (trip_id),
  CONSTRAINT reviews_reviewer_id_foreign
    FOREIGN KEY (reviewer_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT reviews_trip_id_foreign
    FOREIGN KEY (trip_id) REFERENCES trips(id)
    ON DELETE CASCADE,
  CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trusted_devices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  device_label VARCHAR(160) NOT NULL,
  user_agent VARCHAR(512) NULL,
  first_ip VARCHAR(45) NULL,
  last_ip VARCHAR(45) NULL,
  first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY trusted_devices_token_hash_unique (token_hash),
  KEY trusted_devices_user_index (user_id, revoked_at, expires_at),
  CONSTRAINT trusted_devices_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  logged_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  trusted_device_id INT UNSIGNED NULL,
  is_new_device TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY login_events_time_index (logged_in_at),
  KEY login_events_user_time_index (user_id, logged_in_at),
  CONSTRAINT login_events_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT login_events_device_foreign FOREIGN KEY (trusted_device_id) REFERENCES trusted_devices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_preferences (
  user_id INT UNSIGNED NOT NULL,
  popular_digest_enabled TINYINT(1) NOT NULL DEFAULT 1,
  planner_digest_enabled TINYINT(1) NOT NULL DEFAULT 0,
  winback_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT notification_preferences_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trip_daily_unique_views (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  trip_id INT UNSIGNED NOT NULL,
  view_date DATE NOT NULL,
  viewer_key_hash CHAR(64) NOT NULL,
  first_viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY trip_daily_views_unique (trip_id, view_date, viewer_key_hash),
  KEY trip_daily_views_date_index (view_date),
  CONSTRAINT trip_daily_views_trip_foreign FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_delivery_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mail_type VARCHAR(60) NOT NULL,
  recipient_email VARCHAR(255) NOT NULL,
  user_id INT UNSIGNED NULL,
  reference_key VARCHAR(255) NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('sent', 'failed') NOT NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY email_delivery_reference_index (reference_key, status),
  KEY email_delivery_time_index (created_at, status),
  CONSTRAINT email_delivery_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
