<?php
include 'db.php';

// Check if point_history table already exists
$check = mysqli_query($conn, "SHOW TABLES LIKE 'point_history'");
if (mysqli_num_rows($check) > 0) {
    echo "Table 'point_history' already exists.\n";
    exit();
}

$sql = "CREATE TABLE point_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

if (mysqli_query($conn, $sql)) {
    echo "Table 'point_history' created successfully.\n";
} else {
    echo "Error creating table 'point_history': " . mysqli_error($conn) . "\n";
}
