<?php
include 'c:/xampp/htdocs/FitGear/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "===========================================\n";
echo "Premium Features Verification Suite\n";
echo "===========================================\n";

// 1. Check review_votes table schema and counts
echo "\n[1] Verifying review_votes table structure...\n";
$table_desc = mysqli_query($conn, "DESCRIBE review_votes");
if ($table_desc) {
    echo "Columns in review_votes:\n";
    while ($col = mysqli_fetch_assoc($table_desc)) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} else {
    echo "❌ Error describing review_votes table!\n";
}

// 2. Verify Recommended Products query logic
echo "\n[2] Verifying Recommended Products logic...\n";
// Let's pick a random product ID from the products table
$prod_res = mysqli_query($conn, "SELECT id, category_id, name FROM products LIMIT 1");
if ($prod_res && mysqli_num_rows($prod_res) > 0) {
    $prod = mysqli_fetch_assoc($prod_res);
    $id = $prod['id'];
    $cat_id = $prod['category_id'];
    echo "Testing product ID #$id (Category #$cat_id: '{$prod['name']}')\n";
    
    $recommended_products = [];
    $recommended_ids = [];
    
    // ดึงสินค้าหมวดหมู่เดียวกัน (ยกเว้นตัวปัจจุบัน) LIMIT 8
    $rel_query = mysqli_query($conn, "SELECT p.id, p.name, p.category_id,
        (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
        FROM products p 
        WHERE p.category_id = '$cat_id' AND p.id != '$id' 
        LIMIT 8");
        
    while ($p = mysqli_fetch_assoc($rel_query)) {
        $recommended_products[] = $p;
        $recommended_ids[] = $p['id'];
    }
    
    echo "  - Category related products fetched: " . count($recommended_products) . "\n";
    
    // เติมด้วยสินค้าขายดีถ้าไม่ครบ 8
    $count_fetched = count($recommended_products);
    if ($count_fetched < 8) {
        $needed = 8 - $count_fetched;
        $not_in_clause = "";
        if (!empty($recommended_ids)) {
            $not_in_clause = "AND p.id NOT IN ('" . implode("','", $recommended_ids) . "')";
        }
        
        $best_query = mysqli_query($conn, "SELECT p.id, p.name, p.category_id,
            (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
            (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count,
            (SELECT IFNULL(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) as sales_volume
            FROM products p 
            WHERE p.id != '$id' $not_in_clause 
            ORDER BY sales_volume DESC, p.id DESC 
            LIMIT $needed");
            
        while ($p = mysqli_fetch_assoc($best_query)) {
            $recommended_products[] = $p;
        }
    }
    
    echo "  - Total recommended products fetched (max 8): " . count($recommended_products) . "\n";
    foreach ($recommended_products as $idx => $rp) {
        echo "    " . ($idx + 1) . ". ID #{$rp['id']} - {$rp['name']} (Category: {$rp['category_id']})\n";
    }
} else {
    echo "❌ No products found in the database to test recommendations.\n";
}

// 3. Verify Coupon Auto-Recommender logic
echo "\n[3] Verifying Coupon Auto-Recommender logic...\n";
// Create test coupons for validation
$subtotal = 500.0;
$shipping_free_threshold = 300;
$shipping_fee_fixed = 50;
echo "Assuming subtotal = ฿$subtotal, shipping free threshold = ฿$shipping_free_threshold, shipping fee = ฿$shipping_fee_fixed\n";

$now_time = date('Y-m-d H:i:s');
$coupon_query = mysqli_query($conn, "SELECT * FROM coupons WHERE status='active' AND (start_date IS NULL OR start_date <= '$now_time') AND expiry_date >= '$now_time'");

$best_coupon = null;
$best_coupon_value = 0;

if ($coupon_query && mysqli_num_rows($coupon_query) > 0) {
    while ($c = mysqli_fetch_assoc($coupon_query)) {
        if ($subtotal < floatval($c['min_spend'])) {
            continue;
        }
        
        // Calculate savings
        $val = 0;
        if ($c['discount_type'] == 'fixed') {
            $val = floatval($c['discount_value']);
        } elseif ($c['discount_type'] == 'percent') {
            $val = $subtotal * floatval($c['discount_value']) / 100;
            $max_disc = floatval($c['max_discount'] ?? 0);
            if ($max_disc > 0 && $val > $max_disc) {
                $val = $max_disc;
            }
        } elseif ($c['discount_type'] == 'free_shipping') {
            if ($subtotal < $shipping_free_threshold) {
                $val = $shipping_fee_fixed;
            }
        }
        
        if ($c['discount_type'] !== 'free_shipping' && $val > $subtotal) {
            $val = $subtotal;
        }
        
        echo "  - Coupon '{$c['code']}' type '{$c['discount_type']}': savings = ฿$val\n";
        
        if ($val > $best_coupon_value) {
            $best_coupon_value = $val;
            $best_coupon = $c;
        }
    }
}

if ($best_coupon) {
    echo "⭐ Best Coupon Recommended: '{$best_coupon['code']}' saving ฿$best_coupon_value\n";
} else {
    echo "ℹ️ No eligible coupons found for recommendation.\n";
}

echo "\nVerification script completed successfully!\n";
?>
