# AI Instructions & Project Context Checkpoint

This document acts as a persistent memory checkpoint for the AI agent to prevent context loss or forgetting instructions across turns.

---

## 🎨 Project Identity & Aesthetics
*   **Project Name:** พ่อแม่ เบ็ดเตล็ด (Por Mae Bet Taled) - *Branding intentionally reverted from 'FitGear' per user preference.*
*   **Theme:** Modern Blue/Pastel theme with responsive mobile design.
*   **Database:** `fitness_db`
*   **Environment:** Runs offline-first. Credentials and keys are managed in `.env`.

---

## 🌟 User Convenience Features & Specifications

### 1. Auto-Select Last Used Address (`cart.php`)
*   Query the customer's last completed/successful order to retrieve the shipping address.
*   Standardize comparison by removing extra spaces/newlines using `trim(preg_replace('/\s+/', ' ', ...))` to compare plain-text order address strings with structured `user_addresses` records.
*   If a match is found, check it by default. If no match is found, default to the latest added address. No manual checking is required by the user.

### 2. Quick Re-Order (`my_orders.php` -> `ajax.php`)
*   Provide a "สั่งซื้ออีกครั้ง" (Re-order) button inside the order cards in `my_orders.php`.
*   Ensure the AJAX handler `action=reorder` checks current product stocks. Out-of-stock items must be skipped gracefully and reported to the user.
*   Copy valid products to the cart session, generating a correct key (hash key `product_id_md5(options)` if options exist).
*   Open the Cart Drawer automatically upon success.

### 3. Wishlist to Cart (`wishlist.php` -> `ajax.php`)
*   Add an "เพิ่มลงตะกร้า" (Add to cart) button on wishlist items.
*   If the product has no options, transfer it to the cart session immediately and delete the item from the wishlist database.
*   If the product has options, open a Bootstrap option-selection modal, allowing choice of options and quantity, then transfer to the cart and delete from wishlist.

### 4. Cart Drawer UX Rules (`header.php`)
*   **Continue Shopping Button:** The top button in the bottom section must be "เลือกสินค้าต่อ" (Continue Shopping), configured to close the drawer using `window.toggleCartDrawer()` without redirecting.
*   **Footer Visibility:** Hide the bottom summary and checkout footer (`cart-drawer-footer`) completely when the cart is empty (`cart_count === 0`). Apply this logic initially via PHP on page load and dynamically via JavaScript during cart modifications.

---

## 🔒 Security Requirements & Hardening Specifications

### 1. User-Facing Pages (`index.php`, `product_detail.php`, `my_orders.php`, `wishlist.php`)
*   **XSS Protection:** Escape all database-sourced variables output in HTML/JS using `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`. Do not double-escape if already handled.
*   **File Upload Security:**
    *   Validate file MIME type using `finfo_file()` or `getimagesize()` on the server side (allowing only `image/jpeg`, `image/png`, `image/gif`, `image/webp`).
    *   Limit upload file size to 5MB max (`$_FILES['...']['size'] > 5 * 1024 * 1024`).
*   **Redirection Security:** In `wishlist.php`, replace client-side JS redirects with PHP `header('Location: login.php'); exit();`.
*   **Error Handling:** Never expose raw SQL errors (`mysqli_error`) to the user. Log them to the server error log instead.

### 2. Core Endpoints (`ajax.php`, `verify_slip.php`, `cart.php`)
*   **Address Output XSS:** Escape all fields (name, phone, address, etc.) returned as HTML in `ajax.php`.
*   **Search SQL Injection:** In `search_suggest` action, escape wildcard characters `%` and `_` using `addcslashes($q, '%_')` after standard escaping.
*   **Password Length Validation:** Enforce minimum password length of 6 characters in registration/password changes.
*   **Slip Verification Debug Data:** Remove debug/raw response details in JSON return payload in `verify_slip.php`; write debug details to `error_log` instead.
*   **Server-Side Price Calculation (`cart.php`):** Do not trust client-submitted hidden inputs for total price, discount, or final price. Always calculate the cart totals and apply discounts/coupons server-side using database lookups.

### 3. Admin Pages (`admin.php`, `admin_dashboard.php`, `admin_orders.php`, `admin_payments.php`, `admin_users.php`)
*   **XSS Protection:** Sanitize all output fields from database (product names, category names, payment types, etc.).
*   **File Upload Validation:** Check MIME type for product image uploads.
*   **Input Validation:** Restrict role input in `admin_users.php` to allowed roles (`user`, `admin`).
*   **No mysqli_error Leakage:** Ensure no raw database errors leak in the admin UI.

### 4. Auth Pages (`login.php`, `forgot_password.php`, `reset_password.php`, `logout.php`)
*   **Rate Limiting:** Implement session-based login rate limiting (max 5 attempts per 15 minutes) and OTP request rate limiting (max 3 requests per 30 minutes).
*   **OTP Security:**
    *   Use cryptographically secure random integers via `random_int(100000, 999999)` for OTP.
    *   Brute-force lockout for OTP: Lock account for 30 minutes after 5 consecutive failed OTP entries.
    *   Prevent User Enumeration: Show identical success messages regardless of whether the email exists in the database.
*   **Logout Hardening:** Clean up session cookies via `setcookie()` and clear `$_SESSION` array before `session_destroy()`.

---

## 🛠️ Verification & Syntax Check
*   Always run `php -l <filename>` on modified files to verify syntax before finishing work.
