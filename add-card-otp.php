<?php
session_start();

// must be logged in and have pending card
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['add_card_pending'], $_SESSION['add_card_otp'], $_SESSION['add_card_otp_expires'])) {
    header("Location: add-card.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otpInput = trim($_POST['otp'] ?? "");

    if ($otpInput === "") {
        $error = "Please enter the OTP.";
    } else {
        $now = time();
        if ($now > $_SESSION['add_card_otp_expires']) {
            $error = "OTP has expired. Please start again.";
            // clear old OTP
            unset($_SESSION['add_card_otp'], $_SESSION['add_card_otp_expires'], $_SESSION['add_card_pending']);
        } else if ($otpInput !== $_SESSION['add_card_otp']) {
            $error = "Invalid OTP. Please try again.";
        } else {
            // OTP correct: insert card into DB
            $cid = $_SESSION['cid'];
            $pending = $_SESSION['add_card_pending'];

            $cardNumber = $pending['card_number'];
            $expiry     = $pending['expiry'];   // from <input type="month"> e.g. 2027-05
            $cvc        = $pending['cvc'];

            // ---- DB CONNECTION ----
            $host = "localhost";
            $db   = "my_bank";
            $user = "root";
            $pass = "";

            $conn = new mysqli($host, $user, $pass, $db);
            if ($conn->connect_error) {
                die("DB connection failed: " . $conn->connect_error);
            }

            // Adjust table/column names based on your actual schema
            $stmt = $conn->prepare("
                INSERT INTO cards (CardNumber, ExpiryDate, CVC, CustomerID)
                VALUES (?, ?, ?, ?)
            ");
            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("sssi", $cardNumber, $expiry, $cvc, $cid);
            $stmt->execute();
            $stmt->close();
            $conn->close();

            // clear OTP + pending data
            unset($_SESSION['add_card_otp'], $_SESSION['add_card_otp_expires'], $_SESSION['add_card_pending']);

            // flag success for success page (optional)
            $_SESSION['add_card_success'] = true;

            header("Location: add-card-success.php");
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Card — OTP Verification</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .error-msg{
      margin-bottom:10px;
      padding:8px 10px;
      border-radius:6px;
      background:#ffe4e4;
      color:#b30000;
      font-size:14px;
    }
    .otp-input{
      letter-spacing:4px;
      text-align:center;
      font-size:18px;
    }
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="add-card.php">← Back</a>
      <span class="step">2 / 2</span>
    </div>

    <div class="h1">Verify OTP</div>

    <div class="card">
      <div class="section">
        <div class="kv" style="margin-bottom:18px">
          <b>OTP Verification</b><br>
          <span class="note">
            We sent a One-Time Password (OTP) to your registered
            <?php echo htmlspecialchars($_SESSION['add_card_pending']['otp_method'] ?? 'SMS'); ?>.
          </span>
        </div>

        <?php if ($error !== ""): ?>
          <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="add-card-otp.php">
          <div class="row">
            <div class="label">Enter OTP</div>
            <div>
              <input class="input otp-input" type="text" name="otp" inputmode="numeric" maxlength="6"
                     placeholder="••••••" required>
            </div>
          </div>

          <div class="footerbar">
            <button class="btn" type="submit">Verify &amp; Add Card</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
