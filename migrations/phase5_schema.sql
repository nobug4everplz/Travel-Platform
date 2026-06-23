CREATE TABLE trip_gear (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  trip_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  icon VARCHAR(100) NULL,
  affiliate_url VARCHAR(2048) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY trip_gear_trip_id_index (trip_id),
  CONSTRAINT trip_gear_trip_id_foreign
    FOREIGN KEY (trip_id) REFERENCES trips(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE trips ADD COLUMN start_date DATE NULL AFTER review_count;
ALTER TABLE trips ADD COLUMN end_date DATE NULL AFTER start_date;
