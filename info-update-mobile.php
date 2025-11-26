<?php
session_start();

/* if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
} */

$cid = $_SESSION['cid'] ?? null;

// Must come from previous step
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: info-update.php");
    exit;
}

$info_type      = trim($_POST['info_type']      ?? '');
$target_type    = trim($_POST['target_type']    ?? '');
$target_account = trim($_POST['target_account'] ?? '');

if (!$cid || !$info_type || !$target_type || !$target_account) {
    die("Missing required data from previous step.");
}

// ---- DB CONNECTION ----
$host="localhost"; $user="root"; $pass=""; $db="my_bank";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/* ------------------------
   LOAD USER INFO
-------------------------*/
$stmt = $conn->prepare("SELECT user_name, email, phone FROM user WHERE cid = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
$userRow = $res->fetch_assoc();
$stmt->close();

if (!$userRow) {
    die("User not found.");
}

$userName   = $userRow['user_name'];
$userEmail  = $userRow['email'];
$userMobile = $userRow['phone'] ?? '';

/* ------------------------
   LOAD ACCOUNT/CARD DETAILS
-------------------------*/
$accNumber = "";
$accLabel  = "";

if ($target_type === "Account") {

    $stmt = $conn->prepare("SELECT AccountNo FROM accounts WHERE AccountNo = ? AND CustomerID = ?");
    $stmt->bind_param("si", $target_account, $cid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$r) die("Account not found.");

    $accNumber = $r['AccountNo'];
    $accLabel  = "Account Number";

} else if ($target_type === "Card") {

    $stmt = $conn->prepare("SELECT CardNo FROM cards WHERE CardNo = ? AND CustomerID = ?");
    $stmt->bind_param("si", $target_account, $cid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$r) die("Card not found.");

    $accNumber = $r['CardNo'];
    $accLabel  = "Card Number";
}

$conn->close();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Info Update — New Mobile</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .subtitle{ color:var(--muted); font-size:14px; margin-bottom:18px; }
    .steps-caption{ font-size:12px; text-transform:uppercase; letter-spacing:.12em; color:var(--muted); margin-bottom:4px; }

    .holder-title{ font-size:12px; font-weight:700; letter-spacing:.12em; color:var(--muted); margin-bottom:8px; }
    .holder{ display:flex; gap:12px; align-items:center; margin-bottom:14px; }
    .holder-avatar{
      width:56px; height:56px; border-radius:999px; border:1px solid var(--border);
      display:flex; align-items:center; justify-content:center;
      font-size:26px; color:var(--muted); background:#fbfbfc;
    }
    .holder-main{ display:flex; flex-direction:column; gap:3px; }
    .holder-name{ font-weight:700; }
    .holder-acc{ font-size:13px; color:var(--muted); }
    .holder-meta{ font-size:13px; margin-top:6px; }

    .otp-row-label{ margin-top:16px; margin-bottom:6px; font-size:13px; font-weight:600; color:var(--muted); }
    .otp-options{ display:flex; gap:10px; flex-wrap:wrap; }
    .otp-opt{
      flex:1 1 120px; padding:10px 14px; border-radius:12px; border:1px solid var(--border);
      background:#fff; display:flex; align-items:center; gap:8px; font-size:14px;
      cursor:pointer; justify-content:center;
    }
    .otp-opt .otp-dot{ width:16px; height:16px; border-radius:999px; border:2px solid var(--border); }
    .otp-opt.active{ border-color:#3bb54a; background:#f5fff6; }
    .otp-opt.active .otp-dot{ border-color:#3bb54a; background:#3bb54a; }

    .btn-primary{ width:100%; text-align:center; }
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="info-update.php">← Back</a>
      <span class="step">3 / 4</span>
    </div>
    <div class="h1">Info Update</div>

    <div class="card">
      <div class="section">

        <div class="steps-caption">Steps 3 / 4</div>
        <div class="subtitle">
          Update Mobile Number<br>
          <span class="note">Please enter the following details.</span>
        </div>

        <div class="holder-title">ACCOUNT HOLDER DETAILS :</div>
        <div class="holder">
          <div class="holder-avatar">👤</div>
          <div class="holder-main">
            <div class="holder-name"><?= htmlspecialchars($userName) ?></div>
            <div class="holder-acc"><?= htmlspecialchars($accNumber) ?></div>
          </div>
        </div>

        <div class="holder-meta">
          <b>Existing Mobile</b><br>
          <?= htmlspecialchars($userMobile) ?>
        </div>

        <form method="post" action="info-update-process.php">
          <input type="hidden" name="info_type" value="<?= htmlspecialchars($info_type) ?>">
          <input type="hidden" name="target_type" value="<?= htmlspecialchars($target_type) ?>">
          <input type="hidden" name="target_account" value="<?= htmlspecialchars($accNumber) ?>">
          <input type="hidden" name="auth_mode" value="password"> <!-- For now -->

          <div class="row" style="margin-top:16px;">
            <div class="label">New Mobile Number</div>
            <div class="input-wrap">
              <input class="input" name="new_mobile" id="newMobile" type="tel"
                     placeholder="Enter new mobile number" required>
            </div>
          </div>

          <div class="otp-row-label">Receive OTP Via :</div>
          <div class="otp-options">
            <button type="button" class="otp-opt active" data-otp="SMS">
              <span class="otp-dot"></span>
              <span>SMS</span>
            </button>
            <button type="button" class="otp-opt" data-otp="Email">
              <span class="otp-dot"></span>
              <span>Email</span>
            </button>
          </div>

          <input type="hidden" name="otp_method" id="otpMethod" value="SMS">

          <div class="footerbar">
            <button class="btn btn-primary" type="submit">Next</button>
          </div>
        </form>

      </div>
    </div>
  </div>

<script>
  let selectedOtp = 'SMS';
  const otpButtons = document.querySelectorAll('.otp-opt');
  const otpField   = document.getElementById('otpMethod');

  otpButtons.forEach(b => {
    b.addEventListener('click', () => {
      otpButtons.forEach(x => x.classList.remove('active'));
      b.classList.add('active');
      selectedOtp = b.dataset.otp;
      otpField.value = selectedOtp;
    });
  });
</script>
</body>
</html>
