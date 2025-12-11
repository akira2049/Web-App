<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$infoMsg = "";
$demoOtp = "";

/* ---------- Helper: convert phone to E.164 (example for Bangladesh) ---------- */
function phoneE164($n) {
    // Keep digits only
    $n = preg_replace('/\D+/', '', $n);

    // If stored as 11-digit local starting with 0 (e.g., 017xxxxxxxx)
    if (strlen($n) == 11 && strpos($n, '0') === 0) {
        // 0XXXXXXXXXX -> +880XXXXXXXXXX
        return '+88' . $n;
    }

    // If stored as 13-digit with 880 prefix (e.g., 88017xxxxxxxx)
    if (strlen($n) == 13 && strpos($n, '880') === 0) {
        return '+' . $n;
    }

    // Fallback: just prefix +
    return '+' . $n;
}

/* ---------- Infobip SMS helper (DISABLED IN DEMO) ----------
function sendOtpSms($phone, $otp) {
    // TODO: move these into a secure config / .env file
    $baseUrl = 'https://pdy24l.api.infobip.com';       // MUST include https://
    $apiKey  = 'Ye59439c63b2b9f2063cb024955556423-1e045f23-a5ff-4ebd-aa0f-39248273daa9'; // Replace with your real key
    $sender  = 'MyBank';                               // Make sure this is allowed in Infobip

    $url = $baseUrl . '/sms/2/text/advanced';

    $payload = [
        "messages" => [
            [
                "from" => $sender,
                "destinations" => [
                    ["to" => $phone]
                ],
                "text" => "Your bill payment OTP is {$otp}. It will expire in 5 minutes."
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: App {$apiKey}",
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // DEBUG: log for now; remove or reduce in production
    if ($err) {
        error_log("Infobip SMS error: $err");
    } else {
        error_log("Infobip SMS status {$status}, response: {$response}");
    }

    return [$status, $response, $err];
}
*/

/* ---------- Coming from overview: generate OTP ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_otp'])) {
    $method  = $_POST['v'] ?? 'SMS';
    $fromAcc = trim($_POST['from_acc'] ?? '');

    if ($fromAcc === '') {
        die("No account selected. Please go back.");
    }

    $_SESSION['bill_veri_method'] = $method;

    $cid = $_SESSION['cid'];

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("DB connection failed: " . $conn->connect_error);
    }

    // Make sure account belongs to this customer + get phone
    $stmt = $conn->prepare("
        SELECT a.AccountNo, a.Balance, u.phone
        FROM accounts a
        JOIN user u ON u.cid = a.CustomerID
        WHERE a.AccountNo = ? AND a.CustomerID = ?
        LIMIT 1
    ");
    // AccountNo = string, CustomerID = INT
    $cidInt = (int)$cid;
    $stmt->bind_param("si", $fromAcc, $cidInt);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!($row = $res->fetch_assoc())) {
        $stmt->close();
        $conn->close();
        die("Invalid account selection.");
    }

    $phone = $row['phone'];
    $stmt->close();
    $conn->close();

    $_SESSION['bill_from'] = $fromAcc;

    // DEMO: Fixed 6-digit OTP (123456)
    $otp = 123456;
    $_SESSION['bill_otp']         = (string)$otp;
    $_SESSION['bill_otp_expires'] = time() + 5 * 60; // 5 minutes

    // Mask phone for display (use original stored format)
    $phoneMask = $phone;
    if (strlen($phoneMask) >= 5) {
        $phoneMask = substr($phoneMask, 0, 3) . "*****" . substr($phoneMask, -2);
    }
    $_SESSION['bill_phone_mask'] = $phoneMask;

    // DEMO: Do NOT send SMS in this mode
    // $phoneForSms = phoneE164($phone);
    // sendOtpSms($phoneForSms, $otp);

    // Info message for user (generic, no OTP shown)
    $infoMsg = "A 6-digit OTP has been sent to your registered phone number.";
}

// Handle OTP verification submit
$error = "";
// DEMO: no longer exposing OTP to screen
// $demoOtp = $_SESSION['bill_otp'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered = trim($_POST['otp'] ?? '');

    if ($entered === "") {
        $error = "Please enter the OTP.";
    } elseif (!isset($_SESSION['bill_otp'], $_SESSION['bill_otp_expires'])) {
        $error = "OTP not generated. Please go back and try again.";
    } elseif (time() > $_SESSION['bill_otp_expires']) {
        $error = "OTP expired. Please go back and start again.";
    } elseif ($entered !== $_SESSION['bill_otp']) {
        $error = "Incorrect OTP. Please try again.";
    } else {
        // OTP OK → do DB operations: debit account + insert bill_payments
        if (!isset(
            $_SESSION['bill_amt'],
            $_SESSION['bill_from'],
            $_SESSION['bill_name'],
            $_SESSION['bill_id'],
            $_SESSION['cid']
        )) {
            $error = "Session data missing. Please restart payment.";
        } else {
            $billAmt  = floatval($_SESSION['bill_amt']);
            $billFrom = $_SESSION['bill_from'];
            $biller   = $_SESSION['bill_name'];
            $custId   = $_SESSION['bill_id'];
            $cid      = $_SESSION['cid'];
            $channel  = $_SESSION['bill_veri_method'] ?? 'SMS';

            $conn = new mysqli($host, $user, $pass, $db);
            if ($conn->connect_error) {
                $error = "Database connection failed.";
            } else {
                $conn->begin_transaction();
                try {
                    $cidInt = (int)$cid;

                    // 1) Lock account row and get current balance
                    $stmt = $conn->prepare("
                        SELECT Balance
                        FROM accounts
                        WHERE AccountNo = ?
                          AND CustomerID = ?
                        FOR UPDATE
                    ");
                    if (!$stmt) {
                        throw new Exception("SQL error (select balance): " . $conn->error);
                    }
                    $stmt->bind_param("si", $billFrom, $cidInt);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res->fetch_assoc();
                    $stmt->close();

                    if (!$row) {
                        throw new Exception("Account not found.");
                    }

                    $fromBalanceBefore = (float)$row['Balance'];

                    if ($fromBalanceBefore < $billAmt) {
                        throw new Exception("Insufficient balance.");
                    }

                    $fromBalanceAfter = $fromBalanceBefore - $billAmt;

                    // 2) Debit account
                    $stmt = $conn->prepare("
                        UPDATE accounts
                        SET Balance = ?
                        WHERE AccountNo = ?
                          AND CustomerID = ?
                    ");
                    if (!$stmt) {
                        throw new Exception("SQL error (update balance): " . $conn->error);
                    }
                    $stmt->bind_param("dsi", $fromBalanceAfter, $billFrom, $cidInt);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        $stmt->close();
                        throw new Exception("Failed to update balance.");
                    }
                    $stmt->close();

                    // 3) Insert bill_payments record
                    // bill_payments(tx_id, cid, from_acc, biller_name, biller_id,
                    //               amount, from_balance_before, from_balance_after, note, status)
                    $txnId  = "BILL-" . date("YmdHis") . "-" . random_int(1000, 9999);
                    $status = 'SUCCESS';
                    $note   = $biller . " bill payment";

                    $stmt2 = $conn->prepare("
                        INSERT INTO bill_payments
                            (tx_id, cid, from_acc, biller_name, biller_id,
                             amount, from_balance_before, from_balance_after, note, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if (!$stmt2) {
                        throw new Exception("SQL error (bill_payments prepare): " . $conn->error);
                    }

                    $cidStr = (string)$cid;

                    $stmt2->bind_param(
                        "sssssdddss",
                        $txnId,
                        $cidStr,
                        $billFrom,
                        $biller,
                        $custId,
                        $billAmt,
                        $fromBalanceBefore,
                        $fromBalanceAfter,
                        $note,
                        $status
                    );
                    $stmt2->execute();
                    $stmt2->close();

                    $conn->commit();

                    // store for success + PDF
                    $_SESSION['bill_txn_id']  = $txnId;
                    $_SESSION['bill_paid_at'] = date("Y-m-d H:i:s");

                    // clear OTP from session
                    unset($_SESSION['bill_otp'], $_SESSION['bill_otp_expires']);

                    $conn->close();

                    header("Location: bill-success.php");
                    exit;

                } catch (Exception $ex) {
                    $conn->rollback();
                    $conn->close();
                    $error = $ex->getMessage();
                }
            }
        }
    }
}

// For display
$phoneMask = $_SESSION['bill_phone_mask'] ?? 'your registered phone';
$method    = $_SESSION['bill_veri_method'] ?? 'SMS';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bill Payment — OTP Verification</title>

  <!-- Global styles -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">

  <style>
    :root{ --primary:#00416A; }

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
      max-width: 480px;
    }

    .card {
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    .otp-card{
      margin:0 auto;
    }
    .otp-title{
      font-size:20px;
      font-weight:800;
      color:var(--primary);
      margin-bottom:8px;
    }
    .otp-sub{
      font-size:14px;
      color:var(--muted);
      margin-bottom:16px;
      line-height:1.4;
    }
    .otp-input{
      width:100%;
      font-size:24px;
      padding:12px 14px;
      border-radius:12px;
      border:1px solid var(--border);
      text-align:center;
      letter-spacing:4px;
      box-sizing:border-box;
    }
    .error{
      margin-top:10px;
      color:#b91c1c;
      font-size:14px;
    }
    .info{
      margin-top:8px;
      color:#047857;
      font-size:13px;
    }
    .btnPrimary{
      width:100%;
      border:none;
      border-radius:14px;
      font-weight:800;
      font-size:16px;
      padding:14px 18px;
      background:var(--primary);
      color:#fff;
      cursor:pointer;
      margin-top:18px;
    }
    .topbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:16px;
    }
    .linkish{
      color:var(--primary);
      font-size:14px;
      text-decoration:none;
      font-weight:600;
    }
  </style>
</head>
<body>
  <div class="app">
    <div class="otp-card card">
      <div class="section">

        <div class="topbar">
          <a class="linkish" href="bill-payment-overview.php">← Back</a>
        </div>

        <div class="otp-title">Enter OTP</div>
        <div class="otp-sub">
          We sent a 6-digit code to <?php echo htmlspecialchars($phoneMask); ?> via
          <?php echo htmlspecialchars($method); ?>.
        </div>

        <?php if ($infoMsg): ?>
          <div class="info"><?php echo htmlspecialchars($infoMsg); ?></div>
        <?php endif; ?>

        <form method="post">
          <input
            type="password"
            name="otp"
            maxlength="6"
            class="otp-input"
            placeholder="••••••"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="one-time-code"
          >
          <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <button class="btnPrimary" type="submit" name="verify_otp" value="1">
            Verify & Pay
          </button>
        </form>

      </div>
    </div>
  </div>
</body>
</html>
