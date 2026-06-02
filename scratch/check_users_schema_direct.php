<?php
$conn = @mysqli_connect("127.0.0.1", "root", "", "fitness_db");
if (!$conn) {
    echo "Connection failed: " . mysqli_connect_error() . "\n";
    exit;
}
$res = mysqli_query($conn, "DESCRIBE users");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
