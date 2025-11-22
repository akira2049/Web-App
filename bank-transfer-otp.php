<?php
session_start();

if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['ebl_pending_transfer'])) {
    header("Location: bank-transfer.php");
    exit;
}

/* ---------- DB CONNECTION ---------- */
$host="localhost"; $user="root"; $password=""; $database="my_bank";
$conn = new mysqli($host,$user,$password,$database);
if ($conn->connect_error) die("DB failed: ".$conn->connect_error);

$cid = $_SESSION['cid'];

/* ---------- Fetch phone number of logged in user ---------- */
$stmt = $conn->prepare("SELECT Phone FROM user WHERE cid = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$result = $stmt->get_result();
$userPhone = ($row = $result->fetch_assoc()) ? $row['Phone'] : "";
$stmt->close();

if ($userPhone == "") die("Phone number not found.");

/* Convert to +880 format */
function phoneE164($n){
    $n = preg_replace('/\D+/', '', $n);
    if (strpos($n, '880') === 0) return '+'.$n;
    if ($n[0] == '0') return '+88'.$n;
    return '+88'.$n;
}
$toPhone = phoneE164($userPhone);

/* ---------- INFobip SMS CONFIG ---------- */
$BASE_URL = "4ed4v6.api.infobip.com"; // example: xxxx.api.infobip.com
$API_KEY  = "24817f74a2afaa722d8748347118e2a1-87b1545b-3fb1-4a53-aa43-37152f9f40ac";

/* ---------- Generate OTP & send once ---------- */
$error = "";
$success = "";

if (!isset($_SESSION['otp_code'])) {

    $otp = rand(100000, 999999);
    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_exp']  = time() + 180; // 3 mins

    $smsText = "Your EBL transfer OTP is: $otp";

    $payload = [
        "messages" => [
            [
                "destinations" => [
                    ["to" => $toPhone]
                ],
                "from" => "EBLBank",     // Your sender name
                "text" => $smsText
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://{$BASE_URL}/sms/2/text/advanced");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: App {$API_KEY}",
        "Content-Type: application/json",
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http >= 200 && $http < 300) {
        $success = "OTP has been sent to your phone.";
    } else {
        $error = "Failed to send OTP. Please try again.";
    }
}

/* ---------- Verify OTP ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['otp'] ?? "");

    if ($entered == "") {
        $error = "Enter OTP.";
    } else if (time() > ($_SESSION['otp_exp'] ?? 0)) {
        $error = "OTP expired. Go back and try again.";
        unset($_SESSION['otp_code'], $_SESSION['otp_exp']);
    } else if ($entered == $_SESSION['otp_code']) {

        unset($_SESSION['otp_code'], $_SESSION['otp_exp']);
        $_SESSION['ebl_transfer_verified'] = true;

        header("Location: bank-transfer-process.php");
        exit;
    } else {
        $error = "Invalid OTP.";
    }
}

$pending = $_SESSION['ebl_pending_transfer'];
?>
<!DOCTYPE html>
<html>
<head>
<title>EBL Transfer — OTP Verification</title>
<link rel="stylesheet" href="transfer.css">
<link rel="stylesheet" href="dashboard.css">

<style>
.otp-box{max-width:500px;margin:0 auto;}
.otp-input{
  font-size:22px;text-align:center;font-weight:700;letter-spacing:6px;
}
.error{
  background:#ffdede;border:1px solid #ffb8b8;padding:10px;margin-bottom:15px;border-radius:8px;
}
.success{
  background:#e0ffe7;border:1px solid #89e6a8;padding:10px;margin-bottom:15px;border-radius:8px;
}
</style>
</head>
<body>

<div class="app otp-box">

  <div class="topbar">
    <a class="linkish" href="bank-transfer-overview.php">← Back</a>
    <span class="step">2 / 3</span>
  </div>

  <div class="h1">OTP Verification</div>

  <div class="card">
    <div class="section">

      <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
      <?php endif; ?>

      <p>Enter the 6-digit OTP sent to your phone number.</p>

      <form method="post">
        <div class="row">
          <div class="label">OTP</div>
          <input type="text" name="otp" maxlength="6" class="input otp-input" required>
        </div>

        <div class="footerbar">
          <button class="btn">Verify & Transfer</button>
        </div>
      </form>

    </div>
  </div>

</div>

</body>
</html>
