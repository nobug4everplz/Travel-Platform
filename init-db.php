<?php
// Auto-run on Fly startup: create tables if not exist
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

    // Seed users
    $hash = password_hash('password123', PASSWORD_DEFAULT);
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

    echo "DB init OK\n";
} catch (Exception $e) {
    echo "DB init ERROR: " . $e->getMessage() . "\n";
}
