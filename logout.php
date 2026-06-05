<?php
session_start();
// Security: ล้างข้อมูล session และ cookie ก่อน destroy เพื่อป้องกัน Session Fixation
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
header("Location: index.php");
exit();
?>
<div id="loader" style="position:fixed; width:100%; height:100%; background:white; z-index:9999; display:flex; align-items:center; justify-content:center;">
    <div class="spinner-border text-blue" role="status" style="width: 3rem; height: 3rem; color: #AEE2FF;"></div>
</div>

<script>
    // บัเนŠกFix: ถ้าจอขาวนานแิน 1 วินาที ให้บังคับโชว์เนื้อหาทันที
    setTimeout(function() {
        document.body.style.opacity = '1';
        const loader = document.getElementById('loader');
        if(loader) loader.style.display = 'none';
    }, 1000);
</script>


