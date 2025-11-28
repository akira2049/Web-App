<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$host="localhost"; $user="root"; $pass=""; $db="my_bank";

$infoMsg = "";
$demoOtp = "";

/* ---------- Infobip SMS helper (COMMENTED OUT FOR NOW) ---------- */
/*
function sendOtpSms($phone, $otp) {
    $baseUrl = 'https://YOUR_BASE_URL.api.infobip.com';
    $apiKey  = 'App YOUR_API_KEY_HERE';
    $sender  = 'MyBank';

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
            "Authorization: {$apiKey}",
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}
*/

/* ---------- Coming from overview: generate OTP ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_otp'])) {
    $method   = $_POST['v'] ?? 'SMS';
    $fromAcc  = trim($_POST['from_acc'] ?? '');

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

    // Generate 6-digit OTP
    $otp = random_int(100000, 999999);
    $_SESSION['bill_otp']         = (string)$otp;
    $_SESSION['bill_otp_expires'] = time() + 5*60; // 5 minutes

    // Mask phone for display
    $phoneMask = $phone;
    if (strlen($phone) >= 5) {
        $phoneMask = substr($phone, 0, 3) . "*****" . substr($phone, -2);
    }
    $_SESSION['bill_phone_mask'] = $phoneMask;

    // DEV MODE: don't actually send SMS now
    // sendOtpSms($phone, $otp);

    $infoMsg = "Demo OTP generated. Use the 'Fill Demo OTP' button to auto-fill it.";
}

// Handle OTP verification submit
$error   = "";
$demoOtp = $_SESSION['bill_otp'] ?? '';

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

                    // 3) Insert bill_payments record (matches latest schema)
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
  <link rel="stylesheet" href="transfer.css">
  <style>
    .otp-card{
      max-width:480px;
      margin:32px auto;
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
    .btnSecondary{
      width:100%;
      border:1px solid var(--border);
      border-radius:14px;
      font-weight:600;
      font-size:14px;
      padding:10px 14px;
      background:#f9fafb;
      color:#111827;
      cursor:pointer;
      margin-top:10px;
    }
  </style>
</head>
<body data-demo-otp="<?php echo htmlspecialchars($demoOtp, ENT_QUOTES); ?>">
  <div class="app otp-card card">
    <div class="section">

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
          type="text"
          name="otp"
          maxlength="6"
          class="otp-input"
          placeholder="••••••"
          inputmode="numeric"
          autocomplete="one-time-code"
        >
        <?php if ($error): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <button class="btnPrimary" type="submit" name="verify_otp" value="1">
          Verify & Pay
        </button>

        <!-- DEV ONLY: JS helper to auto-fill demo OTP -->
        <button class="btnSecondary" type="button" id="fillDemoOtp">
          Fill Demo OTP (Dev)
        </button>

        <?php if ($demoOtp !== ''): ?>
          <div class="info">Demo OTP: <strong><?php echo htmlspecialchars($demoOtp); ?></strong></div>
        <?php endif; ?>
      </form>

    </div>
  </div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    var demoOtp   = (document.body.dataset.demoOtp || '').trim();
    var btn       = document.getElementById('fillDemoOtp');
    var otpInput  = document.querySelector('input[name="otp"]');

    if (btn && otpInput && demoOtp) {
      btn.addEventListener('click', function(){
        otpInput.value = demoOtp;
        otpInput.focus();
      });
    }
  });
</script>
</body>
</html>
