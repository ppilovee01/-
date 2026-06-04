<?php
/**
 * verify_slip.php — AI Slip Verification Endpoint
 * รับ: order_id, image path (จาก session/upload)
 * ใช้: OpenAI GPT-4o Vision API
 * คืน: JSON { status, is_slip, ai_amount, expected_amount, match, note }
 */
ob_start();
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// ดึงการตั้งค่า AI จาก DB
$settings_q = mysqli_query($conn, "SELECT slip_ai_provider, openai_api_key, gemini_api_key, claude_api_key FROM shop_settings WHERE id=1");
$settings = mysqli_fetch_assoc($settings_q);

$provider   = getenv('SLIP_AI_PROVIDER') !== false ? trim(getenv('SLIP_AI_PROVIDER')) : trim($settings['slip_ai_provider'] ?? 'none');
$openai_key = getenv('OPENAI_API_KEY') !== false ? trim(getenv('OPENAI_API_KEY')) : trim($settings['openai_api_key'] ?? '');
$gemini_key = getenv('GEMINI_API_KEY') !== false ? trim(getenv('GEMINI_API_KEY')) : trim($settings['gemini_api_key'] ?? '');
$claude_key = getenv('CLAUDE_API_KEY') !== false ? trim(getenv('CLAUDE_API_KEY')) : trim($settings['claude_api_key'] ?? '');

if (empty($provider) || $provider === 'none') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'ระบบสแกนสลิปด้วย AI ถูกปิดใช้งานอยู่ (ไปที่ตั้งค่าหลังบ้านเพื่อเปิดใช้งาน)']);
    exit();
}

$active_key = '';
if ($provider === 'openai') $active_key = $openai_key;
elseif ($provider === 'gemini') $active_key = $gemini_key;
elseif ($provider === 'claude') $active_key = $claude_key;

if (empty($active_key)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ตั้งค่า API Key สำหรับผู้ให้บริการที่เลือก (' . strtoupper($provider) . ')']);
    exit();
}

// --- รับข้อมูล ---
$order_id  = intval($_POST['order_id'] ?? 0);
$expected  = floatval($_POST['expected_amount'] ?? 0);
$slip_path = '';

// กรณีส่งมาจาก cart (อัปโหลดไฟล์ใหม่)
if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['slip_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'รองรับเฉพาะ jpg, png, webp']);
        exit();
    }
    if ($_FILES['slip_file']['size'] > 5 * 1024 * 1024) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'ไฟล์ใหญ่เกิน 5MB']);
        exit();
    }
    // อ่านเป็น base64
    $img_data = base64_encode(file_get_contents($_FILES['slip_file']['tmp_name']));
    $mime_type = mime_content_type($_FILES['slip_file']['tmp_name']);
    $slip_path = 'temp_verify';
} elseif (!empty($_POST['slip_filename'])) {
    // กรณีส่งชื่อไฟล์ที่บันทึกแล้ว (จาก admin)
    $slip_filename = basename($_POST['slip_filename']);
    $full_path = __DIR__ . '/uploads/' . $slip_filename;
    if (!file_exists($full_path)) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์สลิป']);
        exit();
    }
    $img_data = base64_encode(file_get_contents($full_path));
    $mime_type = mime_content_type($full_path);
    $slip_path = $slip_filename;
} else {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'ไม่ได้รับไฟล์สลิป']);
    exit();
}

// --- ดึงข้อมูลวิธีการชำระเงินและชื่อบัญชี ---
$pm_id = intval($_POST['payment_method_id'] ?? 0);
$expected_receiver = '';
if ($pm_id > 0) {
    $pm_q = mysqli_query($conn, "SELECT account_name FROM payment_methods WHERE id = '$pm_id'");
    if ($pm_q && mysqli_num_rows($pm_q) > 0) {
        $pm_row = mysqli_fetch_assoc($pm_q);
        $expected_receiver = trim($pm_row['account_name'] ?? '');
    }
}

if (empty($expected_receiver) && $order_id > 0) {
    $ord_q = mysqli_query($conn, "SELECT payment_method FROM orders WHERE id = '$order_id'");
    if ($ord_q && mysqli_num_rows($ord_q) > 0) {
        $ord_row = mysqli_fetch_assoc($ord_q);
        $pm_name = mysqli_real_escape_string($conn, trim($ord_row['payment_method'] ?? ''));
        if (!empty($pm_name)) {
            $pm_q = mysqli_query($conn, "SELECT account_name FROM payment_methods WHERE name = '$pm_name'");
            if ($pm_q && mysqli_num_rows($pm_q) > 0) {
                $pm_row = mysqli_fetch_assoc($pm_q);
                $expected_receiver = trim($pm_row['account_name'] ?? '');
            }
        }
    }
}

// --- สร้าง Prompt ให้ AI ---
$prompt = <<<PROMPT
คุณคือผู้ช่วยตรวจสอบสลิปโอนเงินของร้านค้าออนไลน์ไทย

กรุณาวิเคราะห์รูปภาพนี้และตอบกลับเป็น JSON เท่านั้น (ไม่ต้องมีข้อความอื่น) ด้วยรูปแบบนี้:
{
  "is_slip": true/false,
  "slip_type": "promptpay/bank_transfer/unknown",
  "amount": ยอดเงินที่ปรากฏในสลิป (ตัวเลขทศนิยม 2 ตำแหน่ง หรือ null ถ้าหาไม่เจอ),
  "transfer_date": "วันที่โอน เช่น 2025-06-02 หรือ null",
  "receiver": "ชื่อผู้รับเงินโอน หรือ null",
  "receiver_match": true/false (true ถ้าชื่อผู้รับเงินโอนตรงหรือใกล้เคียงกับ '{$expected_receiver}' มาก เช่น มีคำสะกดตรงกันหรือมีชื่อของเขาปรากฏในช่องผู้รับโอน, false ถ้าไม่ตรงเลย หรือไม่ใช่สลิป),
  "note": "สรุปสั้นๆ ว่าพบอะไรในรูป"
}

ยอดเงินที่คาดหวัง: {$expected} บาท
ชื่อผู้รับเงินโอนที่คาดหวัง: {$expected_receiver}

กฎ:
- ถ้าเป็นสลิปโอนเงินจริง: is_slip = true
- ถ้าเป็นรูปอื่น (ไม่ใช่สลิป): is_slip = false, amount = null, receiver_match = false
- ยอดให้ดูจากตัวเลขที่ชัดเจนที่สุดในสลิป
- ตอบกลับเป็น JSON เท่านั้น ห้ามมีข้อความอื่น
PROMPT;

// --- สร้าง Payload, URL และ Headers ตามผู้ให้บริการที่เลือก ---
$url = '';
$headers = [];
$payload = '';

if ($provider === 'openai') {
    $url = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_key,
    ];
    $payload = json_encode([
        'model' => 'gpt-4o-mini',
        'max_tokens' => 300,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$mime_type};base64,{$img_data}",
                            'detail' => 'high'
                        ]
                    ]
                ]
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
} elseif ($provider === 'gemini') {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $gemini_key;
    $headers = [
        'Content-Type: application/json'
    ];
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inlineData' => [
                            'mimeType' => $mime_type,
                            'data' => $img_data
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json'
        ]
    ], JSON_UNESCAPED_UNICODE);
} elseif ($provider === 'claude') {
    $url = 'https://api.anthropic.com/v1/messages';
    $headers = [
        'x-api-key: ' . $claude_key,
        'anthropic-version: 2023-06-01',
        'content-type: application/json'
    ];
    $payload = json_encode([
        'model' => 'claude-3-5-haiku-20241022',
        'max_tokens' => 400,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mime_type,
                            'data' => $img_data
                        ]
                    ],
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ]
                ]
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
}

// --- เรียกใช้ cURL ---
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => $headers
]);
$response = curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ AI: ' . $curl_err]);
    exit();
}

$resp_data = json_decode($response, true);
$ai_raw = '';

if ($provider === 'openai' && !empty($resp_data['choices'][0]['message']['content'])) {
    $ai_raw = trim($resp_data['choices'][0]['message']['content']);
} elseif ($provider === 'gemini' && !empty($resp_data['candidates'][0]['content']['parts'][0]['text'])) {
    $ai_raw = trim($resp_data['candidates'][0]['content']['parts'][0]['text']);
} elseif ($provider === 'claude' && !empty($resp_data['content'][0]['text'])) {
    $ai_raw = trim($resp_data['content'][0]['text']);
}

if (empty($ai_raw)) {
    ob_end_clean();
    $err_msg = 'ไม่ได้รับคำตอบที่สมเหตุสมผลจากผู้ให้บริการ AI';
    if (!empty($resp_data['error']['message'])) {
        $err_msg = $resp_data['error']['message'];
    } elseif (!empty($resp_data['error']['message']['value'])) {
        $err_msg = $resp_data['error']['message']['value'];
    }
    echo json_encode(['status' => 'error', 'message' => $err_msg, 'debug' => $resp_data]);
    exit();
}

// ลบ markdown code block ถ้ามี
$ai_raw = preg_replace('/^```json\s*/i', '', $ai_raw);
$ai_raw = preg_replace('/```$/', '', $ai_raw);
$ai_json = json_decode(trim($ai_raw), true);

if (!$ai_json) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'AI ตอบกลับในรูปแบบที่ไม่ถูกต้อง', 'raw' => $ai_raw]);
    exit();
}

$is_slip    = (bool)($ai_json['is_slip'] ?? false);
$ai_amount  = isset($ai_json['amount']) ? floatval($ai_json['amount']) : null;
$note       = $ai_json['note'] ?? '';
$slip_type  = $ai_json['slip_type'] ?? 'unknown';
$receiver   = $ai_json['receiver'] ?? null;

$receiver_match = isset($ai_json['receiver_match']) ? (bool)$ai_json['receiver_match'] : true;

// ตรวจสอบยอดเงิน (อนุญาตให้ต่างกันได้ ±2 บาท เผื่อค่าธรรมเนียม)
$match = false;
$ai_status = 'pending';

if (!$is_slip) {
    $ai_status = 'invalid';
    $result_msg = '❌ รูปภาพนี้ไม่ใช่สลิปโอนเงิน';
} elseif (!$receiver_match) {
    $ai_status = 'invalid';
    $result_msg = '❌ ชื่อผู้รับเงินโอนไม่ตรงกับชื่อบัญชีของร้านค้า';
} elseif ($ai_amount === null) {
    $ai_status = 'pending';
    $result_msg = '⚠️ เป็นสลิปแต่อ่านยอดเงินไม่ได้';
} else {
    $diff = abs($ai_amount - $expected);
    if ($diff <= 2.00) {
        $match = true;
        $ai_status = 'verified';
        $result_msg = "✅ สลิปยืนยันแล้ว ยอด ฿" . number_format($ai_amount, 2) . " ตรงกัน";
    } else {
        $ai_status = 'mismatch';
        $result_msg = "⚠️ ยอดในสลิป ฿" . number_format($ai_amount, 2) . " ไม่ตรงกับยอดที่คาดหวัง ฿" . number_format($expected, 2);
    }
}

// บันทึกผลลง DB (ถ้ามี order_id)
if ($order_id > 0) {
    $ai_status_esc  = mysqli_real_escape_string($conn, $ai_status);
    $ai_amount_val  = $ai_amount !== null ? "'$ai_amount'" : 'NULL';
    $ai_note_esc    = mysqli_real_escape_string($conn, $note . ($receiver ? " | ผู้รับ: $receiver" : '') . (!$receiver_match ? " | ผู้รับไม่ตรง" : ''));
    mysqli_query($conn, "UPDATE orders SET slip_ai_status='$ai_status_esc', slip_ai_amount=$ai_amount_val, slip_ai_note='$ai_note_esc' WHERE id='$order_id'");
}

ob_end_clean();
echo json_encode([
    'status'          => 'success',
    'is_slip'         => $is_slip,
    'slip_type'       => $slip_type,
    'ai_amount'       => $ai_amount,
    'expected_amount' => $expected,
    'match'           => $match,
    'ai_status'       => $ai_status,
    'receiver'        => $receiver,
    'receiver_match'  => $receiver_match,
    'message'         => $result_msg,
    'note'            => $note
], JSON_UNESCAPED_UNICODE);
exit();
?>
