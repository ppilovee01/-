<?php
include 'db.php';

echo "Altering start_date and expiry_date to DATETIME...\n";
$res1 = mysqli_query($conn, "ALTER TABLE coupons MODIFY COLUMN start_date DATETIME NULL DEFAULT NULL");
$res2 = mysqli_query($conn, "ALTER TABLE coupons MODIFY COLUMN expiry_date DATETIME NULL DEFAULT NULL");

if ($res1 && $res2) {
    echo "Columns altered successfully.\n";
    
    // Update existing expiry dates to end of day to prevent premature expiration at midnight
    $res3 = mysqli_query($conn, "UPDATE coupons SET expiry_date = CONCAT(DATE(expiry_date), ' 23:59:59') WHERE TIME(expiry_date) = '00:00:00'");
    if ($res3) {
        echo "Updated existing coupons' expiration times to 23:59:59.\n";
    } else {
        echo "Warning updating expiration times: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Error altering table: " . mysqli_error($conn) . "\n";
}
?>
