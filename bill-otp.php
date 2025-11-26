<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$host="localhost"; $user="root"; $pass=""; $db="my_bank";

/* ---------- Infobip SMS helper ---------- */
/*
   Fill in:
   - $baseUrl with your Infobip base URL
   - $apiKey with "App {your_api_key}"
   - $sender with your approved sender ID (this changes the message sender from 'InfoSMS' to your name).
*/
function sendOtpSms($phone, $otp) {
    $baseUrl = 'https://YOUR_BASE_URL.api.infobip.com';   // e.g. https://xyz1234c3f3c4.api.infobip.com
    $apiKey  = 'App YOUR_API_KEY_HERE';
    $sender  = 'MyBank';  // change to your preferred / approved sender name

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

    // You can log $response / $err / $status if you want
    // For now we won't stop the flow if SMS fails; user still sees OTP on screen in dev mode (if you choose to).
}

/* ---------- Coming from overview: generate OTP ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_otp'])) {
    $method   = $_POST['v'] ?? 'SMS';
    $fromAcc  = trim($_POST['from_acc'] ?? '');

    if ($fromAcc === '') {
        die("No account selected. Please go back.");
    }

    $_SESSION['bill_veri_method'] = $method;

    // validate that fromAcc belongs to this cid
    $cid = $_SESSION['cid'];

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("DB connection failed: " . $conn->connect_error);
    }

    /*
      Adjust CustomerID/cid depending on your schema.
    */
    $stmt = $conn->prepare("
        SELECT a.AccountNo, a.Balance, u.phone
        FROM accounts a
        JOIN user u ON u.cid = a.CustomerID
        WHERE a.AccountNo = ? AND a.CustomerID = ?
        LIMIT 1
    ");
    $stmt->bind_param("si", $fromAcc, $cid);
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

    // Send via Infobip (remove this in dev if you don't want SMS cost)
    sendOtpSms($phone, $otp);
}

// Handle OTP verification submit
$error = "";
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
                    // 1) Debit account (ensure enough balance)
                    $stmt = $conn->prepare("
                        UPDATE accounts
                        SET Balance = Balance - ?
                        WHERE AccountNo = ?
                          AND Balance >= ?
                    ");
                    $stmt->bind_param("dss", $billAmt, $billFrom, $billAmt);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        throw new Exception("Insufficient balance or invalid account.");
                    }
                    $stmt->close();

                    // 2) Insert bill_payments record
                    $txnId = "BILL-" . date("YmdHis") . "-" . random_int(1000, 9999);
                    $stmt2 = $conn->prepare("
                      INSERT INTO bill_payments
                        (cid, biller_name, customer_id, amount, account_no, txn_id, channel)
                      VALUES (?,?,?,?,?,?,?)
                    ");
                    $stmt2->bind_param(
                        "issdsss",
                        $cid, $biller, $custId, $billAmt, $billFrom, $txnId, $channel
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
  </style>
</head>
<body>
  <div class="app otp-card card">
    <div class="section">

      <div class="otp-title">Enter OTP</div>
      <div class="otp-sub">
        We sent a 6-digit code to <?php echo htmlspecialchars($phoneMask); ?> via
        <?php echo htmlspecialchars($method); ?>.
      </div>

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
        <button class="btnPrimary" type="submit" name="verify_otp" value="1">Verify & Pay</button>
      </form>

    </div>
  </div>
</body>
</html>
