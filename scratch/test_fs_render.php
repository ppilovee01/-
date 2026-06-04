<?php
session_start();
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
ob_start();
include 'admin_flash_sale.php';
$html = ob_get_clean();
if (strpos($html, 'campaign-mob-row-') !== false) {
    echo "Found campaign-mob-row in output!\n";
} else {
    echo "NOT found campaign-mob-row in output!\n";
}
if (preg_match('/<tbody id="campaigns-tbody">(.*?)<\/tbody>/s', $html, $matches)) {
    echo "Tbody Content Length: " . strlen($matches[1]) . "\n";
    echo "Tbody Content Snippet:\n" . substr(trim($matches[1]), 0, 500) . "\n";
} else {
    echo "Tbody not found!\n";
}
?>
