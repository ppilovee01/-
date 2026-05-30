<?php
include 'db.php';
$check = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'shipping_carrier'");
if ($check && mysqli_num_rows($check) == 0) {
    $res = mysqli_query($conn, "ALTER TABLE orders ADD COLUMN shipping_carrier VARCHAR(100) NULL AFTER tracking_no");
    if ($res) {
        echo "Column shipping_carrier added successfully!";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
} else {
    echo "Column shipping_carrier already exists!";
}
?>
