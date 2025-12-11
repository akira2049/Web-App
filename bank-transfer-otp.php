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

/* ---------- DEMO MODE: FIXED OTP (HIDDEN FROM USER) ---------- */
$error = "";
$success = "";

/* Generate OTP only once */
if (!isset($_SESSION['otp_code'])) {

    // DEMO FIXED OTP
    $otp = 123456;

    $_SESSION['otp_code'] = (string)$otp;
    $_SESSION['otp_exp']  = time() + 180; // 3 minutes

    // Do NOT show OTP to user
    $success = "An OTP has been sent to your phone.";  // demo message
}

/* ---------- OTP Verification ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $entered = trim($_POST['otp'] ?? "");

    if ($entered === "") {
        $error = "Enter OTP.";
    } 
    else if (time() > ($_SESSION['otp_exp'] ?? 0)) {
        $error = "OTP expired. Please try again.";
        unset($_SESSION['otp_code'], $_SESSION['otp_exp']);
    } 
    else if ($entered === $_SESSION['otp_code']) {

        unset($_SESSION['otp_code'], $_SESSION['otp_exp']);
        $_SESSION['ebl_transfer_verified'] = true;

        header("Location: bank-transfer-process.php");
        exit;
    } 
    else {
        $error = "Invalid OTP.";
    }
}

$pending = $_SESSION['ebl_pending_transfer'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>EBL Transfer — OTP Verification</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="transfer.css">

<style>
  body {
    margin: 0;
    font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
    background: linear-gradient(135deg, #00416A, #E4E5E6);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .app {
    max-width: 500px;
    margin: 0 auto;
    padding: 24px;
  }

  .card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }

  .otp-box { max-width: 500px; margin: 0 auto; }

  .otp-input {
    font-size: 22px;
    text-align: center;
    font-weight: 700;
    letter-spacing: 6px;
  }

  .error {
    background:#ffdede;
    border:1px solid #ffb8b8;
    padding:10px;
    margin-bottom:15px;
    border-radius:8px;
  }

  .success {
    background:#e0ffe7;
    border:1px solid #89e6a8;
    padding:10px;
    margin-bottom:15px;
    border-radius:8px;
  }
</style>
</head>

<body>

<div class="app otp-box">

  <div class="topbar">
    <a class="linkish" href="bank-transfer-overview.php">← Back</a>
    <span class="step">3 / 3</span>
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

      <p>Enter the 6-digit OTP sent to your phone.</p>

      <form method="post">
      <div class="row">
        <div class="label">OTP</div>

        <!-- MASKED OTP INPUT -->
        <input
          type="password"
          name="otp"
          maxlength="6"
          class="input otp-input"
          inputmode="numeric"
          pattern="[0-9]*"
          required
        >
      </div>

      <div class="footerbar">
        <button class="btn">Verify &amp; Transfer</button>
      </div>
    </form>

    </div>
  </div>

</div>

</body>
</html>
