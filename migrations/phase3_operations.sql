USE travel_platform_db;

CREATE TABLE IF NOT EXISTS trusted_devices (
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

CREATE TABLE IF NOT EXISTS login_events (
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

CREATE TABLE IF NOT EXISTS notification_preferences (
  user_id INT UNSIGNED NOT NULL,
  popular_digest_enabled TINYINT(1) NOT NULL DEFAULT 1,
  planner_digest_enabled TINYINT(1) NOT NULL DEFAULT 0,
  winback_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT notification_preferences_user_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trip_daily_unique_views (
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

CREATE TABLE IF NOT EXISTS email_delivery_logs (
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
