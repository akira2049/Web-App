<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: info-update.php");
    exit;
}

$info_type      = $_POST['info_type'] ?? '';
$target_type    = $_POST['target_type'] ?? '';
$target_account = $_POST['target_account'] ?? '';

if (!$info_type || !$target_type || !$target_account) {
    die("Missing required fields");
}

// Pass to next page
if ($info_type === "email") {
    header("Location: info-update-email.php");
    exit;
} 
else if ($info_type === "mobile") {
    header("Location: info-update-mobile.php");
    exit;
}
else {
    die("Invalid info type.");
}
