<?php
// Auto-run on Fly startup: create all tables + seed data
require __DIR__ . '/config/database.php';

try {
    $db = pdo();
    
    // Users
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL DEFAULT '',
        avatar_url VARCHAR(2048) NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'traveler',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Trips
    $db->exec("CREATE TABLE IF NOT EXISTS trips (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        cover_image VARCHAR(2048) NULL,
        summary TEXT NULL,
        is_published SMALLINT NOT NULL DEFAULT 0,
        author_id INT NOT NULL REFERENCES users(id),
        average_rating DECIMAL(3,2) DEFAULT 0,
        review_count INT DEFAULT 0,
        start_date DATE NULL,
        end_date DATE NULL,
        budget DECIMAL(10,2) NULL,
        currency VARCHAR(3) DEFAULT 'TWD',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        place_id VARCHAR(255) NULL,
        address VARCHAR(512) NULL
    )");

    $db->exec("
    DO $$
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='trips_title_author_key') THEN
            ALTER TABLE trips ADD CONSTRAINT trips_title_author_key UNIQUE (title, author_id);
        END IF;
    END $$");

    // Trip Spots
    $db->exec("CREATE TABLE IF NOT EXISTS trip_spots (
        id SERIAL PRIMARY KEY,
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        sort_order INT DEFAULT 0,
        name VARCHAR(255) NOT NULL,
        latitude DECIMAL(10,7),
        longitude DECIMAL(10,7),
        place_id VARCHAR(255),
        address VARCHAR(512),
        notes TEXT,
        google_maps_url VARCHAR(2048),
        UNIQUE(trip_id, name)
    )");

    // Trip Gear
    $db->exec("CREATE TABLE IF NOT EXISTS trip_gear (
        id SERIAL PRIMARY KEY,
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        name VARCHAR(255) NOT NULL,
        icon VARCHAR(100),
        affiliate_url VARCHAR(2048),
        sort_order INT DEFAULT 0
    )");

    // Trip Photos
    $db->exec("CREATE TABLE IF NOT EXISTS trip_photos (
        id SERIAL PRIMARY KEY,
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        spot_id INT REFERENCES trip_spots(id),
        user_id INT NOT NULL REFERENCES users(id),
        image_path VARCHAR(512) NOT NULL,
        caption VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Trip Participations
    $db->exec("CREATE TABLE IF NOT EXISTS trip_participations (
        id SERIAL PRIMARY KEY,
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        user_id INT NOT NULL REFERENCES users(id),
        status VARCHAR(20) DEFAULT 'active',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(trip_id, user_id)
    )");

    // Favorite Trips (was: trip_favorites)
    $db->exec("CREATE TABLE IF NOT EXISTS favorite_trips (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id),
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, trip_id)
    )");

    // Favorite Planners (was: planner_favorites)
    $db->exec("CREATE TABLE IF NOT EXISTS favorite_planners (
        id SERIAL PRIMARY KEY,
        traveler_id INT NOT NULL REFERENCES users(id),
        planner_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(traveler_id, planner_id)
    )");

    // Reviews
    $db->exec("CREATE TABLE IF NOT EXISTS reviews (
        id SERIAL PRIMARY KEY,
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        reviewer_id INT NOT NULL REFERENCES users(id),
        rating SMALLINT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(trip_id, reviewer_id)
    )");

    // Traveler Footprints
    $db->exec("CREATE TABLE IF NOT EXISTS traveler_footprints (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        trip_id INT REFERENCES trips(id) ON DELETE SET NULL,
        name VARCHAR(255) NOT NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        place_id VARCHAR(255),
        visited_at DATE DEFAULT CURRENT_DATE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // AI Usage Log
    $db->exec("CREATE TABLE IF NOT EXISTS ai_usage_log (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        ip_hash VARCHAR(32) NOT NULL,
        page_type VARCHAR(32) DEFAULT 'home',
        tokens_used INT DEFAULT 0,
        usage_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_user_date ON ai_usage_log(user_id, usage_date)");

    // Email Delivery Logs
    $db->exec("CREATE TABLE IF NOT EXISTS email_delivery_logs (
        id SERIAL PRIMARY KEY,
        mail_type VARCHAR(50),
        recipient_email VARCHAR(255),
        user_id INT,
        reference_key VARCHAR(255),
        subject VARCHAR(255),
        status VARCHAR(20),
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Notification Preferences
    $db->exec("CREATE TABLE IF NOT EXISTS notification_preferences (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) UNIQUE,
        popular_digest_enabled BOOLEAN DEFAULT true,
        planner_digest_enabled BOOLEAN DEFAULT false,
        winback_enabled BOOLEAN DEFAULT false,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Trusted Devices
    $db->exec("CREATE TABLE IF NOT EXISTS trusted_devices (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash CHAR(64) NOT NULL,
        device_label VARCHAR(255) NOT NULL DEFAULT '',
        user_agent TEXT,
        first_ip VARCHAR(45),
        last_ip VARCHAR(45),
        first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        revoked_at TIMESTAMP NULL
    )");

    // Trip Daily Unique Views
    $db->exec("CREATE TABLE IF NOT EXISTS trip_daily_unique_views (
        id SERIAL PRIMARY KEY,
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        view_date DATE NOT NULL,
        viewer_key_hash CHAR(64) NOT NULL,
        first_viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(trip_id, view_date, viewer_key_hash)
    )");

    // Login Events
    $db->exec("CREATE TABLE IF NOT EXISTS login_events (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id),
        logged_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent TEXT,
        trusted_device_id INT NULL REFERENCES trusted_devices(id),
        is_new_device BOOLEAN DEFAULT false
    )");

    // === IDEMPOTENT MIGRATION BLOCK ===

    $db->exec("
    DO $$
    BEGIN
        -- Table renames

        -- trip_favorites -> favorite_trips
        IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='trip_favorites' AND table_type='BASE TABLE')
           AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='favorite_trips' AND table_type='BASE TABLE') THEN
            ALTER TABLE trip_favorites RENAME TO favorite_trips;
            -- Columns already named correctly, just rename table
        END IF;

        -- planner_favorites -> favorite_planners
        IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='planner_favorites' AND table_type='BASE TABLE')
           AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='favorite_planners' AND table_type='BASE TABLE') THEN
            ALTER TABLE planner_favorites RENAME TO favorite_planners;
            ALTER TABLE favorite_planners RENAME COLUMN user_id TO traveler_id;
        END IF;
    END $$");

    // trusted_devices column adjustments (safe re-run)
    $db->exec("
    DO $$
    BEGIN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='device_name') THEN
            ALTER TABLE trusted_devices DROP COLUMN device_name;
        END IF;
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='trusted_at') THEN
            ALTER TABLE trusted_devices DROP COLUMN trusted_at;
        END IF;
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='device_token') THEN
            ALTER TABLE trusted_devices RENAME COLUMN device_token TO token_hash;
            ALTER TABLE trusted_devices ALTER COLUMN token_hash SET NOT NULL;
            ALTER TABLE trusted_devices ALTER COLUMN token_hash TYPE CHAR(64) USING LEFT(token_hash, 64);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='device_label') THEN
            ALTER TABLE trusted_devices ADD COLUMN device_label VARCHAR(255) NOT NULL DEFAULT '';
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='user_agent') THEN
            ALTER TABLE trusted_devices ADD COLUMN user_agent TEXT;
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='first_ip') THEN
            ALTER TABLE trusted_devices ADD COLUMN first_ip VARCHAR(45);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='last_ip') THEN
            ALTER TABLE trusted_devices ADD COLUMN last_ip VARCHAR(45);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='first_seen_at') THEN
            ALTER TABLE trusted_devices ADD COLUMN first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='last_seen_at') THEN
            ALTER TABLE trusted_devices ADD COLUMN last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='expires_at') THEN
            ALTER TABLE trusted_devices ADD COLUMN expires_at TIMESTAMP NOT NULL DEFAULT (NOW() + INTERVAL '30 days');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trusted_devices' AND column_name='revoked_at') THEN
            ALTER TABLE trusted_devices ADD COLUMN revoked_at TIMESTAMP NULL;
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='trusted_devices_token_hash_key') THEN
            ALTER TABLE trusted_devices ADD UNIQUE (token_hash);
        END IF;
    END $$");

    // notification_preferences column migration (old -> new column names)
    $db->exec("
    DO $$
    BEGIN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='daily_popular')
           AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='popular_digest_enabled') THEN
            ALTER TABLE notification_preferences RENAME COLUMN daily_popular TO popular_digest_enabled;
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='popular_digest_enabled') THEN
            ALTER TABLE notification_preferences ADD COLUMN popular_digest_enabled BOOLEAN DEFAULT true;
        END IF;

        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='planner_digest')
           AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='planner_digest_enabled') THEN
            ALTER TABLE notification_preferences RENAME COLUMN planner_digest TO planner_digest_enabled;
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='planner_digest_enabled') THEN
            ALTER TABLE notification_preferences ADD COLUMN planner_digest_enabled BOOLEAN DEFAULT false;
        END IF;

        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='winback')
           AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='winback_enabled') THEN
            ALTER TABLE notification_preferences RENAME COLUMN winback TO winback_enabled;
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='winback_enabled') THEN
            ALTER TABLE notification_preferences ADD COLUMN winback_enabled BOOLEAN DEFAULT false;
        END IF;

        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notification_preferences' AND column_name='updated_at') THEN
            ALTER TABLE notification_preferences ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
        END IF;
    END $$");

    // reviews: rename user_id -> reviewer_id if old column exists
    $db->exec("
    DO $$
    BEGIN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='reviews' AND column_name='user_id')
           AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='reviews' AND column_name='reviewer_id') THEN
            ALTER TABLE reviews RENAME COLUMN user_id TO reviewer_id;
        END IF;
    END $$");

    // trip_participations: add status column if missing
    $db->exec("
    DO $$
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trip_participations' AND column_name='status') THEN
            ALTER TABLE trip_participations ADD COLUMN status VARCHAR(20) DEFAULT 'active';
        END IF;
    END $$");

    // trip_participations: rename created_at -> joined_at (Phase 3.5)
    $db->exec("
    DO $$
    BEGIN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trip_participations' AND column_name='created_at')
           AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='trip_participations' AND column_name='joined_at') THEN
            ALTER TABLE trip_participations RENAME COLUMN created_at TO joined_at;
        END IF;
    END $$");

    // email_delivery_logs: rename sent_at -> created_at
    $db->exec("
    DO $$
    BEGIN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='email_delivery_logs' AND column_name='sent_at')
           AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='email_delivery_logs' AND column_name='created_at') THEN
            ALTER TABLE email_delivery_logs RENAME COLUMN sent_at TO created_at;
        END IF;
    END $$");

    // trip_spots: cleanup duplicates + add UNIQUE constraint (migration)
    $db->exec("
    DO $$
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='trip_spots_trip_id_name_key') THEN
            DELETE FROM trip_spots WHERE id NOT IN (SELECT MIN(id) FROM trip_spots GROUP BY trip_id, name);
            ALTER TABLE trip_spots ADD CONSTRAINT trip_spots_trip_id_name_key UNIQUE (trip_id, name);
        END IF;
    END $$");

    // === SEED DATA ===
    $hash = password_hash('password123', PASSWORD_DEFAULT);

    // Seed users
    $db->exec("INSERT INTO users (email, password, name, role) VALUES 
        ('admin@example.com', '$hash', 'Admin', 'admin'),
        ('traveler@example.com', '$hash', 'Traveler Jason', 'traveler'),
        ('planner@example.com', '$hash', 'Sarah Planner', 'planner')
    ON CONFLICT (email) DO NOTHING");

    // Seed trips
    $db->exec("INSERT INTO trips (title, cover_image, summary, is_published, author_id, start_date, end_date, budget, currency, latitude, longitude, address) VALUES
        ('Tokyo Anime Tour 5 Days', 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?q=80&w=1200', 'Akihabara, Asakusa, Odaiba Gundam, Shibuya Crossing!', 1, 3, '2026-08-10', '2026-08-15', 40000, 'TWD', 35.6762, 139.6503, 'Tokyo, Japan'),
        ('Kyoto Slow Travel', 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?q=80&w=1200', 'Old streets, tea ceremony, hidden gardens.', 1, 3, '2026-07-15', '2026-07-20', 35000, 'TWD', 35.0116, 135.7681, 'Kyoto, Japan')
    ON CONFLICT DO NOTHING");

    // Seed spots for Kyoto
    $db->exec("INSERT INTO trip_spots (trip_id, name, address, notes, sort_order, latitude, longitude) VALUES
        (2, 'Kiyomizu-dera', 'Higashiyama, Kyoto', 'UNESCO temple with panoramic views', 1, 34.9949, 135.7850),
        (2, 'Fushimi Inari', 'Fushimi, Kyoto', 'Thousands of red torii gates', 2, 34.9671, 135.7727)
    ON CONFLICT (trip_id, name) DO NOTHING");

    // Seed participations
    $db->exec("INSERT INTO trip_participations (trip_id, user_id) VALUES (2, 2) ON CONFLICT DO NOTHING");

    echo "DB init OK - " . $db->query("SELECT count(*) FROM users")->fetchColumn() . " users, " . $db->query("SELECT count(*) FROM trips")->fetchColumn() . " trips\n";
} catch (Exception $e) {
    echo "DB init ERROR: " . $e->getMessage() . "\n";
}
