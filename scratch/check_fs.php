<?php
include 'db.php';
$res = mysqli_query($conn, 'SELECT * FROM flash_sales');
echo "Count: " . mysqli_num_rows($res) . "\n";
print_r(mysqli_fetch_all($res, MYSQLI_ASSOC));
?>
