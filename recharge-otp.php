<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid   = $_SESSION['cid'];
$error = "";
$success = "";
$demoOtp = "";

/* ---------- DB CONNECTION ---------- */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/* ---------- Get registered phone number ---------- */
$phone = "";
$stmt = $conn->prepare("SELECT phone FROM user WHERE cid = ?");
$stmt->bind_param("s", $cid); // cid is varchar in your schema
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $phone = $row['phone'];
}
$stmt->close();

/* Mask phone for UI */
function mask_phone($n) {
    $digits = preg_replace('/\D+/', '', $n);
    $last2  = substr($digits, -2);
    $prefix = substr($n, 0, 3);
    if ($prefix === "") $prefix = "***";
    return $prefix . "******" . $last2;
}

/* ---------- Infobip SMS sender (COMMENTED OUT FOR NOW) ---------- */
/*
function send_infobip_otp($to, $otp) {
    // TODO: replace with your real Infobip credentials
    $baseUrl = "https://{your-base-url}.api.infobip.com";
    $apiKey  = "YOUR_API_KEY_HERE";

    $payload = [
        "messages" => [
            [
                "destinations" => [["to" => $to]],
                "from"         => "MyBank",
                "text"         => "Your MyBank mobile recharge OTP code is: {$otp}"
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
        // You can log curl_error($ch) if needed
    }
    curl_close($ch);
}
*/

/* ---------- Generate DEMO OTP (NO SMS) ---------- */
function generate_demo_otp() {
    // 6-digit random OTP
    $otp = str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);

    $_SESSION['recharge_otp']         = $otp;
    $_SESSION['recharge_otp_expires'] = time() + 300; // valid for 5 minutes
}

// If first time here or resend requested, generate a new demo OTP
if (!isset($_SESSION['recharge_otp']) || isset($_GET['resend'])) {
    generate_demo_otp();
    $success = "Demo OTP generated. Use the 'Fill Demo OTP' helper to auto-fill it.";
}

// Expose the OTP for JS (demo only)
$demoOtp = isset($_SESSION['recharge_otp']) ? $_SESSION['recharge_otp'] : "";

/* ---------- Operator detection from mobile number (BD prefixes) ---------- */
function detect_operator_from_msisdn($msisdn) {
    $d = preg_replace('/\D+/', '', $msisdn);
    if (strlen($d) >= 11) {
        // take last 11 digits (01XXXXXXXXX)
        $core = substr($d, -11);
        $prefix3 = substr($core, 0, 3); // 01X

        switch ($prefix3) {
            case '017':
            case '013':
                return 'GRAMEENPHONE';
            case '018':
            case '016':
                return 'ROBI';
            case '019':
            case '014':
                return 'BANGLALINK';
            case '015':
                return 'TELETALK';
            default:
                return 'UNKNOWN';
        }
    }
    return 'UNKNOWN';
}

/* ---------- Handle OTP submit ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otpInput = trim($_POST['otp'] ?? '');

    if ($otpInput === "") {
        $error = "Enter the 6-digit OTP.";
    } elseif (!isset($_SESSION['recharge_otp'], $_SESSION['recharge_otp_expires'])) {
        $error = "OTP not found. Please request a new code.";
    } elseif (time() > $_SESSION['recharge_otp_expires']) {
        $error = "OTP expired. Please request a new code.";
    } elseif ($otpInput !== $_SESSION['recharge_otp']) {
        $error = "Incorrect OTP. Please try again.";
    } else {
        // OTP OK
        $_SESSION['recharge_verified'] = true;

        // --------- Get recharge data from hidden inputs (filled from localStorage via JS) ----------
        $fromAcc      = trim($_POST['from_acc'] ?? '');
        $mobileNumber = trim($_POST['mobile_number'] ?? '');
        $amountRaw    = trim($_POST['amount'] ?? '0');
        $amount       = floatval($amountRaw);
        $rechargeType = trim($_POST['recharge_type'] ?? 'PREPAID');
        $note         = trim($_POST['note'] ?? 'Mobile Recharge');

        if ($fromAcc === '' || $mobileNumber === '' || $amount <= 0) {
            $error = "Invalid recharge data. Please try again.";
        } else {
            // Determine operator from mobile number
            $operator = detect_operator_from_msisdn($mobileNumber);

            // Default balances
            $fromBalBefore = 0.00;
            $fromBalAfter  = 0.00;
            $cidStr        = (string)$cid;

            // --- 1) Get current balance and compute new balance ---
            $stmt = $conn->prepare("SELECT Balance FROM accounts WHERE AccountNo = ? AND CustomerID = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $fromAcc, $cidStr);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $fromBalBefore = (float)$row['Balance'];
                    $fromBalAfter  = $fromBalBefore - $amount;
                } else {
                    $error = "Account not found.";
                }
                $stmt->close();
            } else {
                $error = "Failed to prepare balance query.";
            }

            if ($error === "") {
                if ($fromBalAfter < 0) {
                    $error = "Insufficient balance for this recharge.";
                } else {
                    // --- 2) Update user's account balance ---
                    $stmt = $conn->prepare("UPDATE accounts SET Balance = ? WHERE AccountNo = ? AND CustomerID = ?");
                    if ($stmt) {
                        $stmt->bind_param("dss", $fromBalAfter, $fromAcc, $cidStr);
                        $stmt->execute();
                        if ($stmt->affected_rows === 0) {
                            $error = "Failed to update account balance.";
                        }
                        $stmt->close();
                    } else {
                        $error = "Failed to prepare balance update.";
                    }
                }
            }

            // --- 3) Insert into mobile_recharges history (only if no error) ---
            if ($error === "") {
                $status = 'SUCCESS';

                // Generate TX ID (same as before)
                $txId = "MR-" . date("Ymd-His") . "-" . strtoupper(substr(md5(uniqid("", true)), 0, 6));

                $stmt = $conn->prepare("
                    INSERT INTO mobile_recharges
                        (tx_id, cid, from_acc, operator, mobile_number,
                        recharge_type, amount, from_balance_before,
                        from_balance_after, note, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        "sssssssddss",
                        $txId,
                        $cidStr,
                        $fromAcc,
                        $operator,
                        $mobileNumber,
                        $rechargeType,
                        $amount,
                        $fromBalBefore,
                        $fromBalAfter,
                        $note,
                        $status
                    );
                    $stmt->execute();
                    $stmt->close();

                    // ✅ save tx_id for success page
                    $_SESSION['last_recharge_txid'] = $txId;
                }
            }

            if ($error === "") {
                // clear OTP
                unset($_SESSION['recharge_otp'], $_SESSION['recharge_otp_expires']);

                // continue to recharge success page
                header("Location: recharge-success.php");
                exit;
            }

        }
    }
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>OTP Verification</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .center{max-width:520px;margin:48px auto}
    .otp-box{display:flex;gap:10px;justify-content:center;margin:16px 0 8px}
    .otp-input{width:56px;height:56px;text-align:center;font-size:22px;border:1.5px solid var(--border);border-radius:12px}
    .small{color:var(--muted);text-align:center}
    .row-aux{display:flex;justify-content:space-between;align-items:center;margin:8px 0 18px}
    .timer{color:var(--muted)}
    .btn.full{width:100%}
    .linkish{cursor:pointer}
    .error-msg{color:#d00;font-size:14px;text-align:center;margin-top:8px}
    .success-msg{
      color:#0a7f3f;
      font-size:14px;
      text-align:center;
      margin-top:8px;
      margin-bottom:4px;
    }
  </style>
</head>
<body data-demo-otp="<?php echo htmlspecialchars($demoOtp, ENT_QUOTES); ?>">
  <div class="app center card">
    <div class="section">
      <div class="h1" style="margin-bottom:6px">OTP Verification</div>
      <div class="small">
        Enter the verification code sent to your registered phone number
        <span id="msk"><?php echo $phone ? htmlspecialchars(mask_phone($phone)) : "**********"; ?></span>
      </div>

      <?php if ($success): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" id="otpForm">
        <div class="otp-box" id="box">
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
        </div>

        <input type="hidden" name="otp" id="otpHidden">

        <!-- Hidden fields that will be filled from localStorage (recharge data) -->
        <input type="hidden" name="from_acc" id="fromAccHidden">
        <input type="hidden" name="mobile_number" id="msisdnHidden">
        <input type="hidden" name="amount" id="amountHidden">
        <input type="hidden" name="recharge_type" id="ctypeHidden">
        <input type="hidden" name="note" id="noteHidden">

        <div class="row-aux">
          <span class="linkish" id="resend">Resend code</span>
          <span>
            <span class="timer" id="count">01:30</span>
            <span class="linkish" id="fillDemo" style="margin-left:10px;font-size:13px;">Fill Demo OTP</span>
          </span>
        </div>

        <button type="submit" class="btn full" id="submit" disabled>Submit</button>

        <!-- For development you can show the OTP, remove in production -->
        <?php if (isset($_SESSION['recharge_otp'])): ?>
          <div class="small" style="margin-top:8px">Demo code: <b><?php echo htmlspecialchars($_SESSION['recharge_otp']); ?></b></div>
        <?php endif; ?>
      </form>
    </div>
  </div>

<script>
  const inputs    = [...document.querySelectorAll('.otp-input')];
  const submit    = document.getElementById('submit');
  const count     = document.getElementById('count');
  const resend    = document.getElementById('resend');
  const form      = document.getElementById('otpForm');
  const otpHidden = document.getElementById('otpHidden');
  const fillDemo  = document.getElementById('fillDemo');
  const demoOtp   = (document.body.dataset.demoOtp || "").trim();

  const fromAccHidden  = document.getElementById('fromAccHidden');
  const msisdnHidden   = document.getElementById('msisdnHidden');
  const amountHidden   = document.getElementById('amountHidden');
  const ctypeHidden    = document.getElementById('ctypeHidden');
  const noteHidden     = document.getElementById('noteHidden');

  // Move focus + numeric only
  inputs.forEach((el, i)=>{
    el.addEventListener('input', ()=>{
      el.value = el.value.replace(/\D/g,'');
      if (el.value && i < inputs.length - 1) {
        inputs[i+1].focus();
      }
      validate();
    });
    el.addEventListener('keydown', (e)=>{
      if (e.key === 'Backspace' && !el.value && i > 0) {
        inputs[i-1].focus();
      }
    });
  });

  function validate(){
    const code = inputs.map(x=>x.value).join('');
    submit.disabled = code.length !== 6;
  }

  function timer(sec){
    let t = sec;
    const tick = ()=>{
      const m = String(Math.floor(t/60)).padStart(2,'0');
      const s = String(t%60).padStart(2,'0');
      count.textContent = `${m}:${s}`;
      if(t>0){
        t--;
        setTimeout(tick, 1000);
      }
    };
    tick();
  }
  timer(90);

  // Hydrate hidden fields from localStorage (set in recharge.php + recharge-overview.php)
  (function(){
    const msisdn = localStorage.getItem('re_msisdn') || '';
    const amt    = localStorage.getItem('re_amt') || '0';
    const acct   = localStorage.getItem('re_from') || '';
    const ctype  = localStorage.getItem('re_ctype') || 'PREPAID';
    const note   = localStorage.getItem('re_note') || 'Mobile Recharge';

    if (fromAccHidden)  fromAccHidden.value  = acct;
    if (msisdnHidden)   msisdnHidden.value   = msisdn;
    if (amountHidden)   amountHidden.value   = amt;
    if (ctypeHidden)    ctypeHidden.value    = ctype;
    if (noteHidden)     noteHidden.value     = note;
  })();

  // Resend: reload page with ?resend=1 so PHP regenerates a new demo OTP
  resend.addEventListener('click', ()=>{
    window.location.href = 'recharge-otp.php?resend=1';
  });

  // On submit: put combined code into hidden input
  form.addEventListener('submit', (e)=>{
    const code = inputs.map(x=>x.value).join('');
    if (code.length !== 6) {
      e.preventDefault();
      return;
    }
    otpHidden.value = code;
  });

  // Fill Demo OTP helper (same idea as bank transfer)
  if (fillDemo && demoOtp && demoOtp.length === 6 && inputs.length === 6) {
    fillDemo.addEventListener('click', ()=>{
      for (let i = 0; i < 6; i++) {
        inputs[i].value = demoOtp[i] || '';
      }
      if (inputs[5]) inputs[5].focus();
      validate();
    });
  }

  // focus first box
  if (inputs[0]) inputs[0].focus();
</script>
</body>
</html>
