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
        google_maps_url VARCHAR(2048)
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(trip_id, user_id)
    )");

    // Trip Favorites
    $db->exec("CREATE TABLE IF NOT EXISTS trip_favorites (
        id SERIAL PRIMARY KEY,
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        user_id INT NOT NULL REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(trip_id, user_id)
    )");

    // Planner Favorites
    $db->exec("CREATE TABLE IF NOT EXISTS planner_favorites (
        id SERIAL PRIMARY KEY,
        planner_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        user_id INT NOT NULL REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(planner_id, user_id)
    )");

    // Reviews
    $db->exec("CREATE TABLE IF NOT EXISTS reviews (
        id SERIAL PRIMARY KEY,
        trip_id INT NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
        user_id INT NOT NULL REFERENCES users(id),
        rating SMALLINT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(trip_id, user_id)
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
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Notifications
    $db->exec("CREATE TABLE IF NOT EXISTS notification_preferences (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) UNIQUE,
        daily_popular BOOLEAN DEFAULT true,
        planner_digest BOOLEAN DEFAULT false,
        winback BOOLEAN DEFAULT false,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Trusted Devices
    $db->exec("CREATE TABLE IF NOT EXISTS trusted_devices (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        device_token VARCHAR(255) NOT NULL,
        device_name VARCHAR(255),
        trusted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

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
    ON CONFLICT DO NOTHING");

    // Seed participations
    $db->exec("INSERT INTO trip_participations (trip_id, user_id) VALUES (2, 2) ON CONFLICT DO NOTHING");

    echo "DB init OK - " . $db->query("SELECT count(*) FROM users")->fetchColumn() . " users, " . $db->query("SELECT count(*) FROM trips")->fetchColumn() . " trips\n";
} catch (Exception $e) {
    echo "DB init ERROR: " . $e->getMessage() . "\n";
}
