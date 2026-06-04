<?php
$file = 'admin.php';
$content = file_get_contents($file);

$target = '            .td-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px dashed #eee;
            }
            .td-actions > button, .td-actions > a {
                flex: 1;
                justify-content: center;
                margin: 0 !important;
            }
            .btn-circle-mobile {
                flex-grow: 0 !important;
                width: 40px; height: 40px;
                display: flex; align-items: center; justify-content: center;
            }';

$replacement = '            .td-actions {
                display: flex;
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: center;
                gap: 8px;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px dashed #eee;
                width: 100%;
            }
            .td-actions > button:first-child {
                flex: 1 1 auto;
                margin: 0 !important;
                justify-content: center;
            }
            .btn-circle-mobile {
                flex: 0 0 40px !important;
                width: 40px !important;
                height: 40px !important;
                border-radius: 12px !important;
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                margin: 0 !important;
                padding: 0 !important;
            }';

// Normalize line endings to find match regardless of \r\n or \n
$normalized_content = str_replace("\r\n", "\n", $content);
$normalized_target = str_replace("\r\n", "\n", $target);
$normalized_replacement = str_replace("\r\n", "\n", $replacement);

if (strpos($normalized_content, $normalized_target) !== false) {
    $normalized_content = str_replace($normalized_target, $normalized_replacement, $normalized_content);
    // Restore windows line endings if the original file had them
    if (strpos($content, "\r\n") !== false) {
        $normalized_content = str_replace("\n", "\r\n", $normalized_content);
    }
    file_put_contents($file, $normalized_content);
    echo "SUCCESS: CSS patched successfully!\n";
} else {
    echo "ERROR: Target CSS block not found in $file!\n";
}
?>
