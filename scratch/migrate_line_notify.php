<?php
include 'db.php';
$check = mysqli_query($conn, "SHOW COLUMNS FROM shop_settings LIKE 'line_notify_token'");
if ($check && mysqli_num_rows($check) == 0) {
    $res = mysqli_query($conn, "ALTER TABLE shop_settings ADD COLUMN line_notify_token VARCHAR(255) NULL AFTER points_spend_rate");
    if ($res) {
        echo "Column line_notify_token added successfully!";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
} else {
    echo "Column line_notify_token already exists!";
}
?>
