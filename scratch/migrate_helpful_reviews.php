<?php
include 'c:/xampp/htdocs/FitGear/db.php';

// Enable error reporting for diagnostics
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting migration: create review_votes table...\n";

$sql = "CREATE TABLE IF NOT EXISTS review_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_review (review_id, user_id),
    FOREIGN KEY (review_id) REFERENCES product_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $sql)) {
    echo "Table 'review_votes' created successfully or already exists.\n";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}
?>
