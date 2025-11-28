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

// DEMO OTP
if (!isset($_SESSION['am_mock_otp'])) {
    $_SESSION['am_mock_otp'] = "123456";
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $otp = trim($_POST['otp'] ?? '');

    if ($otp === $_SESSION['am_mock_otp']) {

        $cid        = $_SESSION['cid'];
        $toAcc      = $_SESSION['am_to_acc'];
        $amount     = floatval($_SESSION['am_amount']);
        $cardMask   = $_SESSION['am_card_mask']   ?? '';
        $cardHolder = $_SESSION['am_card_holder'] ?? '';
        $sourceType = $_SESSION['am_from']        ?? 'CARD';

        // 🔥 REAL CARD NUMBER
        $cardNo     = $_SESSION['am_card_no']     ?? '';

        $toName     = $_SESSION['am_to_name']     ?? '';
        $veri       = $_SESSION['am_veri']        ?? 'SMS';

        if (!isset($_SESSION['am_when'])) {
            $_SESSION['am_when'] = date("Y-m-d H:i:s");
        }
        $requestedAt = $_SESSION['am_when'];

        $ref = "AM".str_pad(random_int(0,9999999999),10,"0",STR_PAD_LEFT);
        $_SESSION['am_ref'] = $ref;

        $conn = new mysqli("localhost", "root", "", "my_bank");
        if ($conn->connect_error) die("DB failed");

        /* ---------------------------------------
           GET LINKED ACCOUNT OF THIS CARD
        ---------------------------------------- */
        $linkedAccount = null;

        if (!empty($cardNo)) {
            $stmt = $conn->prepare("SELECT linkedAccount FROM cards WHERE cardNo=? LIMIT 1");
            $stmt->bind_param("s",$cardNo);
            $stmt->execute();
            $stmt->bind_result($linkedAccount);
            $stmt->fetch();
            $stmt->close();
        }

        /* ---------------------------------------
           DEDUCT FROM CARD HOLDER ACCOUNT
        ---------------------------------------- */
        if (!empty($linkedAccount)) {
            $stmt = $conn->prepare("UPDATE accounts SET Balance = Balance - ? WHERE AccountNo = ?");
            $stmt->bind_param("ds",$amount,$linkedAccount);
            $stmt->execute();
            $stmt->close();
        }

        /* ---------------------------------------
           ADD TO RECEIVER ACCOUNT
        ---------------------------------------- */
        $stmt = $conn->prepare("UPDATE accounts SET Balance = Balance + ? WHERE AccountNo=? AND CustomerID=?");
        $stmt->bind_param("dsi",$amount,$toAcc,$cid);
        $stmt->execute();
        $stmt->close();

        /* ---------------------------------------
           SAVE TRANSACTION
        ---------------------------------------- */
        $stmt = $conn->prepare("
        INSERT INTO add_money_transactions
        (cid, card_mask, card_holder, source_type, tx_amount, to_account, to_name, verification_method, requested_at)
        VALUES (?,?,?,?,?,?,?,?,?)");

        $stmt->bind_param("isssdssss",
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

        $conn->close();

        header("Location: add-money-success.php");
        exit;

    } else {
        $error = "Invalid code. Try again.";
    }
}

$method   = $_SESSION['am_veri'] ?? "SMS";
$masked   = "+880••• ••• ••12";
$demoCode = $_SESSION['am_mock_otp'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Add Money — OTP</title>
<link rel="stylesheet" href="transfer.css">
<style>
  .otp-wrap{display:flex; gap:10px; justify-content:center; margin-top:12px}
  .otp-wrap input{width:44px; height:56px; text-align:center; font-size:24px; border:1.5px solid var(--border); border-radius:12px}
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

      <div class="kv">We sent a code via <?=htmlspecialchars($method)?> to <?=htmlspecialchars($masked)?></div>

      <?php if($error): ?>
      <div class="err"><?=$error?></div>
      <?php endif; ?>

      <form method="POST" id="otpForm">
        <div class="otp-wrap" id="otpWrap">
          <?php for($i=0;$i<6;$i++): ?>
          <input class="otpbox" maxlength="1" inputmode="numeric">
          <?php endfor; ?>
        </div>

        <input type="hidden" name="otp" id="otpHidden">

        <div class="footerbar">
          <button class="btn" id="verify" disabled>Verify</button>
        </div>
      </form>

      <div class="code-hint">Demo OTP: <?=$demoCode?></div>

    </div>
  </div>
</div>

<script>
const boxes = [...document.querySelectorAll(".otpbox")];
function code(){ return boxes.map(b=>b.value).join(""); }
function update(){ document.getElementById("verify").disabled = code().length !== 6; }

boxes.forEach((b,i)=>{
    b.oninput = ()=>{
        b.value=b.value.replace(/\D/g,'');
        if(b.value && i<5) boxes[i+1].focus();
        update();
    };
    b.onkeydown = e=>{
        if(e.key==="Backspace" && !b.value && i>0) boxes[i-1].focus();
    };
});

document.getElementById("otpForm").onsubmit = ()=>{
    document.getElementById("otpHidden").value = code();
};
boxes[0].focus();
</script>
</body>
</html>
