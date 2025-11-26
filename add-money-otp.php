<?php
session_start();

if (!isset($_SESSION['cid'])) { 
    header("Location: login.php"); 
    exit; 
}
if (!isset($_SESSION['am_amount'], $_SESSION['am_to_acc'])) { 
    header("Location: add-money.php"); 
    exit; 
}

// Demo OTP (replace later with Infobip)
if (!isset($_SESSION['am_mock_otp'])) {
    $_SESSION['am_mock_otp'] = "123456";
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if ($otp === ($_SESSION['am_mock_otp'] ?? '')) {

        // -----------------------------
        // 1) Collect data from session
        // -----------------------------
        $cid        = $_SESSION['cid'];
        $toAcc      = $_SESSION['am_to_acc'];
        $amount     = floatval($_SESSION['am_amount']);

        $cardMask   = $_SESSION['am_card_mask']   ?? '';
        $cardHolder = $_SESSION['am_card_holder'] ?? '';
        $sourceType = $_SESSION['am_from']        ?? 'VISA/Mastercard';
        $cardNo     = $_SESSION['am_card_no']     ?? '';  // full card number

        $toName     = $_SESSION['am_to_name']     ?? '';
        $veri       = $_SESSION['am_veri']        ?? 'SMS';

        if (!isset($_SESSION['am_when'])) {
            $_SESSION['am_when'] = date("Y-m-d H:i:s");
        }
        $requestedAt = $_SESSION['am_when'];

        // Optional reference for success screen (not stored in table)
        $ref = "AM" . str_pad(strval(random_int(0, 9999999999)), 10, "0", STR_PAD_LEFT);
        $_SESSION['am_ref'] = $ref;

        // flag for insufficient card balance
        $insufficient = false;

        // -----------------------------
        // 2) DB connection
        // -----------------------------
        $host = "localhost"; 
        $user = "root"; 
        $pass = ""; 
        $db   = "my_bank";

        $conn = new mysqli($host, $user, $pass, $db);
        if ($conn->connect_error) {
            die("DB connection failed: " . $conn->connect_error);
        }

        // -----------------------------
        // 3) Deduct from CARD balance
        //    Use cards table: cardNo + customer_id
        //    and ensure balance >= amount
        // -----------------------------
        if (in_array($sourceType, ['VISA/Mastercard', 'SAVED_CARD'], true) && $cardNo !== '') {

            $stmt = $conn->prepare("
                UPDATE cards
                SET balance = balance - ?
                WHERE cardNo = ?
                  AND customer_id = ?
                  AND balance >= ?
            ");
            if ($stmt) {
                $cidStr = (string)$cid; // customer_id is varchar
                $stmt->bind_param("dssd", $amount, $cardNo, $cidStr, $amount);
                $stmt->execute();

                if ($stmt->affected_rows === 0) {
                    // either card not found or not enough balance
                    $insufficient = true;
                }

                $stmt->close();
            }
        }

        // If card didn't have enough balance, DO NOT credit account
        if ($insufficient) {
            $error = "Insufficient card balance.";
            $conn->close();
        } else {

            // -----------------------------
            // 4) Update destination account balance
            // -----------------------------
            $stmt = $conn->prepare("
                UPDATE accounts 
                SET Balance = Balance + ? 
                WHERE AccountNo = ? AND CustomerID = ?
            ");
            if ($stmt) {
                $stmt->bind_param("dsi", $amount, $toAcc, $cid);
                $stmt->execute();
                $stmt->close();
            }

            // -----------------------------
            // 5) Insert into add_money_transactions
            // -----------------------------
            $stmt = $conn->prepare("
                INSERT INTO add_money_transactions
                    (cid, card_mask, card_holder, source_type, tx_amount,
                     to_account, to_name, verification_method, requested_at)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "isssdssss",
                    $cid,
                    $cardMask,
                    $cardHolder,
                    $sourceType,
                    $amount,
                    $toAcc,
                    $toName,
                    $veri,
                    $requestedAt
                );
                $stmt->execute();
                $stmt->close();
            }

            $conn->close();

            // -----------------------------
            // 6) Go to success page
            // -----------------------------
            header("Location: add-money-success.php");
            exit;
        }

    } else {
        $error = "Invalid code. Please try again.";
    }
}

$method   = $_SESSION['am_veri'] ?? "SMS";
$masked   = ($method === "Email") ? "your@email.com"
                                  : "+880••• ••• ••12";
$demoCode = $_SESSION['am_mock_otp'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Money — OTP Verification</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .otp-wrap{display:flex; gap:10px; justify-content:center; margin-top:12px}
    .otp-wrap input{width:44px; height:56px; text-align:center; font-size:24px; border:1.5px solid var(--border); border-radius:12px}
    .otp-wrap input:focus{outline:none; border-color:var(--primary)}
    .help{color:var(--muted); text-align:center; margin-top:8px}
    .timer{font-weight:700}
    .resend{color:var(--primary); cursor:pointer; font-weight:700}
    .code-hint{font-size:12px; color:var(--muted); text-align:center; margin-top:6px}
    .err{color:#b00020;font-weight:700;text-align:center;margin-top:8px;}
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="add-money-overview.php">← Back</a>
      <span class="step">OTP</span>
    </div>
    <div class="h1">Verify it's you</div>

    <div class="card">
      <div class="section">
        <div class="kv" id="via">
          We sent a 6-digit code via <?= htmlspecialchars($method) ?> to <?= htmlspecialchars($masked) ?>.
        </div>

        <?php if($error): ?>
          <div class="err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="otpForm">
          <div class="otp-wrap" id="otpWrap">
            <?php for($i=0;$i<6;$i++): ?>
              <input class="otpbox" maxlength="1" inputmode="numeric">
            <?php endfor; ?>
          </div>

          <input type="hidden" name="otp" id="otpHidden">

          <div class="help">
            Enter the code before <span class="timer" id="timer">00:59</span>.
          </div>
          <div class="help">
            Didn't get a code? <span class="resend" id="resend">Resend</span>
          </div>
          <div class="code-hint">
            Demo OTP: <?= htmlspecialchars($demoCode) ?>
          </div>

          <div class="footerbar">
            <button class="btn" id="verify" disabled>Verify & Continue</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
  let left = 59;
  const t = setInterval(()=>{
    const m = String(Math.floor(left/60)).padStart(2,'0');
    const s = String(left%60).padStart(2,'0');
    document.getElementById('timer').textContent = m + ':' + s;
    left--;
    if(left < 0){ clearInterval(t); }
  }, 1000);

  document.getElementById('resend').addEventListener('click', ()=>{
    left = 59;
  });

  const boxes = Array.from(document.querySelectorAll('.otpbox'));
  function codeValue(){ return boxes.map(b=>b.value||'').join(''); }
  function checkReady(){
    document.getElementById('verify').disabled = (codeValue().length !== 6);
  }

  boxes.forEach((box,idx)=>{
    box.addEventListener('input', ()=>{
      box.value = box.value.replace(/\D/g,'');
      if(box.value && idx < boxes.length-1){ boxes[idx+1].focus(); }
      checkReady();
    });
    box.addEventListener('keydown', (e)=>{
      if(e.key === 'Backspace' && !box.value && idx > 0){ boxes[idx-1].focus(); }
    });
    box.addEventListener('paste', (e)=>{
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
      if(text){
        for(let i=0;i<boxes.length;i++){ boxes[i].value = text[i] || ''; }
        checkReady();
      }
    });
  });

  document.getElementById('otpForm').addEventListener('submit', ()=>{
    document.getElementById('otpHidden').value = codeValue();
  });

  boxes[0].focus();
</script>
</body>
</html>
