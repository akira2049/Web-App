<?php
session_start();

// must be logged in and have pending card
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['add_card_pending'])) {
    header("Location: add-card.php");
    exit;
}

$cid     = $_SESSION['cid'];
$error   = "";
$success = "";

/* ---------- DB CONNECTION ---------- */
$host = "localhost";
$db   = "my_bank";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/* ---------- Fetch registered phone number ---------- */
$phone = "";
$stmt = $conn->prepare("SELECT phone FROM user WHERE cid = ?");
$stmt->bind_param("i", $cid); // adjust to "s" if your cid is varchar
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $phone = $row['phone'];
}
$stmt->close();

if ($phone === "" || $phone === null) {
    die("Phone number not found for this account.");
}

/* ---------- Helpers ---------- */
function mask_phone($n) {
    $digits = preg_replace('/\D+/', '', $n);
    if ($digits === "") return "**********";
    $last2  = substr($digits, -2);
    $prefix = substr($n, 0, 3);
    if ($prefix === "") $prefix = "***";
    return $prefix . "******" . $last2;
}

/* Infobip SMS sender (DISABLED FOR DEMO) */
/*
function send_infobip_otp($to, $otp) {
    // TODO: replace {your-base-url} and YOUR_API_KEY_HERE
    $baseUrl = "vy69ep.api.infobip.com"; // e.g. https://xyz123.api.infobip.com
    $apiKey  = "a1d651fc0c0ed790d6799fc83b304b13-d3308d15-ffe1-4a74-a95c-c034f22507ea"; // only the key, NOT including "App "

    // Normalize phone for BD if needed
    $clean = preg_replace('/\D+/', '', $to);
    if (substr($clean, 0, 2) === "01") {
        $clean = "+880" . substr($clean, 1); // 01XXXXXXXXX -> +8801XXXXXXXXX
    } elseif (substr($clean, 0, 1) !== "+") {
        $clean = "+" . $clean;
    }

    $payload = [
        "messages" => [
            [
                "destinations" => [["to" => $clean]],
                "from"         => "MyBank",
                "text"         => "Your MyBank add-card OTP code is: {$otp}"
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $baseUrl . "/sms/2/text/advanced",
        CURLOPT_HTTPHEADER     => [
            "Authorization: App {$apiKey}",
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        // For production, log curl_error($ch)
        // error_log("Infobip error: " . curl_error($ch));
    }
    curl_close($ch);
}
*/

/* DEMO MODE: fixed OTP 123456, no SMS */
if (!isset($_SESSION['add_card_otp'], $_SESSION['add_card_otp_expires']) || isset($_GET['resend'])) {
    $_SESSION['add_card_otp']         = '123456';
    $_SESSION['add_card_otp_expires'] = time() + 300; // 5 minutes
    $success = "An OTP has been sent to your registered mobile number.";
}

/* ---------- Handle OTP submit ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otpInput = trim($_POST['otp'] ?? "");

    if ($otpInput === "") {
        $error = "Please enter the OTP.";
    } else {
        $now = time();
        $exp = $_SESSION['add_card_otp_expires'] ?? 0;

        if (!isset($_SESSION['add_card_otp']) || $exp === 0) {
            $error = "OTP not found. Please start again.";
        } elseif ($now > $exp) {
            $error = "OTP has expired. Please start again.";
            unset($_SESSION['add_card_otp'], $_SESSION['add_card_otp_expires'], $_SESSION['add_card_pending']);
        } elseif ($otpInput !== $_SESSION['add_card_otp']) {
            $error = "Invalid OTP. Please try again.";
        } else {
            // OTP correct: insert card into DB
            $cid     = $_SESSION['cid'];
            $pending = $_SESSION['add_card_pending'];

            $cardNumber = $pending['card_number'];
            $expiry     = $pending['expiry'];   // from <input type="month"> e.g. 2027-05
            $cvc        = $pending['cvc'];

            $stmt = $conn->prepare("
                INSERT INTO cards (CardNo, expiryDate, cvc, customer_id)
                VALUES (?, ?, ?, ?)
            ");
            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("sssi", $cardNumber, $expiry, $cvc, $cid);
            $stmt->execute();
            $stmt->close();

            // clear OTP + pending data
            unset($_SESSION['add_card_otp'], $_SESSION['add_card_otp_expires'], $_SESSION['add_card_pending']);

            // flag success for success page (optional)
            $_SESSION['add_card_success'] = true;

            $conn->close();
            header("Location: add-card-success.php");
            exit;
        }
    }
}

$maskedPhone = mask_phone($phone);
$otpMethod   = $_SESSION['add_card_pending']['otp_method'] ?? 'SMS';

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Card — OTP Verification</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    :root { --primary:#00416A; }

    /* Dashboard-style background + centering */
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 24px 12px;
    }

    .app {
      width: 100%;
      max-width: 720px;
    }

    .card {
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    .h1 {
      color:#ffffff;
      text-shadow:0 1px 2px rgba(0,0,0,0.25);
      margin-bottom: 12px;
    }

    .error-msg{
      margin-bottom:10px;
      padding:8px 10px;
      border-radius:6px;
      background:#ffe4e4;
      color:#b30000;
      font-size:14px;
    }
    .success-msg{
      margin-bottom:10px;
      padding:8px 10px;
      border-radius:6px;
      background:#e0ffe7;
      color:#0a7f3f;
      font-size:14px;
    }
    .otp-input{
      letter-spacing:4px;
      text-align:center;
      font-size:18px;
    }
    .note{
      font-size:13px;
      color:#555;
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
            We sent a code via <?=htmlspecialchars($otpMethod)?> to
            <?=htmlspecialchars($maskedPhone)?>.
          </span>
        </div>

        <?php if ($success !== ""): ?>
          <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
          <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="add-card-otp.php">
          <div class="row">
            <div class="label">Enter OTP</div>
            <div>
              <input
                class="input otp-input"
                type="password"
                name="otp"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                placeholder="••••••"
                required
              >
            </div>
          </div>

          <div class="footerbar" style="flex-direction:column;align-items:stretch;gap:8px;">
            <button class="btn" type="submit">Verify &amp; Add Card</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
  // You can add multi-box OTP UI later if you want it consistent with recharge.
</script>
</body>
</html>
