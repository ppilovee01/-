<?php
include 'db.php';
$res = mysqli_query($conn, "DESCRIBE users");
echo "<pre>";
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
echo "</pre>";
?>
