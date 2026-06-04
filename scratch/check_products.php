<?php
include 'db.php';
$res = mysqli_query($conn, 'SELECT id, name FROM products');
echo "Products in DB:\n";
while($r = mysqli_fetch_assoc($res)) {
    echo "ID: " . $r['id'] . " - " . $r['name'] . "\n";
}
?>
