<?php
include 'c:/xampp/htdocs/FitGear/db.php';

echo "Migrating shop_settings columns...\n";

// Check if columns already exist, if not, add them
$cols = [
    'discord_webhook_url' => "TEXT NULL",
    'telegram_bot_token' => "VARCHAR(255) NULL",
    'telegram_chat_id' => "VARCHAR(100) NULL",
    'slack_webhook_url' => "TEXT NULL",
    'custom_webhook_url' => "TEXT NULL"
];

foreach ($cols as $col => $definition) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `shop_settings` LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        $alter_sql = "ALTER TABLE `shop_settings` ADD `$col` $definition";
        if (mysqli_query($conn, $alter_sql)) {
            echo "Column '$col' added successfully.\n";
        } else {
            echo "Error adding column '$col': " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "Column '$col' already exists.\n";
    }
}

// Add the initial Discord Webhook URL to database from cart.php to preserve it!
$discord_default = "https://discord.com/api/webhooks/1473327005234761760/yOg6j2pYCa0DDSnqUxs5yCL3mlODeIrnYNNo1nJJldGFjnvDHQalkSHzd6RM0691w-b4";
$check_val = mysqli_fetch_assoc(mysqli_query($conn, "SELECT discord_webhook_url FROM shop_settings WHERE id = 1"));
if (empty($check_val['discord_webhook_url'])) {
    $up_sql = "UPDATE shop_settings SET discord_webhook_url = '$discord_default' WHERE id = 1";
    mysqli_query($conn, $up_sql);
    echo "Default Discord webhook seeded into shop_settings.\n";
}

// Also verify .env file has these variables
$env_path = 'c:/xampp/htdocs/FitGear/.env';
updateEnv('DISCORD_WEBHOOK_URL', $discord_default, $env_path);
updateEnv('TELEGRAM_BOT_TOKEN', '', $env_path);
updateEnv('TELEGRAM_CHAT_ID', '', $env_path);
updateEnv('SLACK_WEBHOOK_URL', '', $env_path);
updateEnv('CUSTOM_WEBHOOK_URL', '', $env_path);

echo "Migration completed.\n";
?>
