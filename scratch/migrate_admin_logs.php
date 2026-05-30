<?php
include 'db.php';

$check = mysqli_query($conn, "SHOW TABLES LIKE 'admin_logs'");
if ($check && mysqli_num_rows($check) == 0) {
    $sql = "CREATE TABLE admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        admin_name VARCHAR(150) NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        details TEXT NOT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    $res = mysqli_query($conn, $sql);
    if ($res) {
        echo "Table admin_logs created successfully!\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Table admin_logs already exists!\n";
}
?>
