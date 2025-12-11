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

$cid     = $_SESSION['cid'];
$error   = "";
$success = "";

/* ---------- DB CONNECTION ---------- */
$conn = new mysqli("localhost", "root", "", "my_bank");
if ($conn->connect_error) die("DB failed: " . $conn->connect_error);

/* ---------- Get registered phone number ---------- */
$phone = "";
$stmt = $conn->prepare("SELECT phone FROM user WHERE cid = ?");
$stmt->bind_param("i", $cid); // cid is INT in most of your code
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $phone = $row['phone'];
}
$stmt->close();

if ($phone === "") {
    die("Phone number not found for this account.");
}

/* ---------- Mask phone for UI ---------- */
function mask_phone($n) {
    $digits = preg_replace('/\D+/', '', $n);
    if ($digits === "") return "**********";
    $last2  = substr($digits, -2);
    // rough prefix from original string
    $prefix = substr($n, 0, 3);
    if ($prefix === "") $prefix = "***";
    return $prefix . "******" . $last2;
}

/* ---------- Infobip SMS sender (DISABLED FOR DEMO) ----------
function send_infobip_otp($to, $otp) {
    // TODO: replace with your real Infobip credentials
    $baseUrl = "558evd.api.infobip.com"; // e.g. "https://xyz123.api.infobip.com"
    $apiKey  = "1aa44ec44a7017971dee6c727515f690-612fafcd-651b-4430-bd2d-29f970e6ad2d"; // only the key, NOT including "App "

    // Optional: normalize phone if needed (e.g. +8801XXXXXXXXX)
    $to = preg_replace('/\D+/', '', $to);
    if (substr($to, 0, 2) === "01") {
        $to = "+880" . substr($to, 1); // 01XXXXXXXXX -> +8801XXXXXXXXX
    } elseif (substr($to, 0, 3) !== "+88" && substr($to, 0, 1) !== "+") {
        // if you already store +880..., skip this or adjust as needed
        $to = "+" . $to;
    }

    $payload = [
        "messages" => [
            [
                "destinations" => [["to" => $to]],
                "from"         => "MyBank",
                "text"         => "Your Astra Bank add-money OTP code is: {$otp}"
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
        // In production, log curl_error($ch) to a file
        // error_log("Infobip error: " . curl_error($ch));
    }
    curl_close($ch);
}
*/

/* ---------- DEMO OTP: FIXED 123456, NO SMS ---------- */
// If first time here (or if in future you add ?resend=1), generate demo OTP
if (!isset($_SESSION['am_otp']) || isset($_GET['resend'])) {
    $_SESSION['am_otp']         = '123456';              // DEMO OTP
    $_SESSION['am_otp_expires'] = time() + 300;          // valid for 5 minutes
    $success = "An OTP has been sent to your registered mobile number.";
}

/* ---------- Handle OTP submit ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $otp = trim($_POST['otp'] ?? '');

    if ($otp === "") {
        $error = "Enter the 6-digit OTP.";
    } elseif (!isset($_SESSION['am_otp'], $_SESSION['am_otp_expires'])) {
        $error = "OTP not found. Please request a new code.";
    } elseif (time() > $_SESSION['am_otp_expires']) {
        $error = "OTP expired. Please try again.";
    } elseif ($otp !== $_SESSION['am_otp']) {
        $error = "Invalid code. Try again.";
    } else {

        // ✅ OTP correct
        $cid        = $_SESSION['cid'];
        $toAcc      = $_SESSION['am_to_acc'];
        $amount     = floatval($_SESSION['am_amount']);
        $cardMask   = $_SESSION['am_card_mask']   ?? '';
        $cardHolder = $_SESSION['am_card_holder'] ?? '';
        $sourceType = $_SESSION['am_from']        ?? 'CARD';

        // REAL CARD NUMBER
        $cardNo     = $_SESSION['am_card_no']     ?? '';

        $toName     = $_SESSION['am_to_name']     ?? '';
        $veri       = $_SESSION['am_veri']        ?? 'SMS';

        if (!isset($_SESSION['am_when'])) {
            $_SESSION['am_when'] = date("Y-m-d H:i:s");
        }
        $requestedAt = $_SESSION['am_when'];

        $ref = "AM" . str_pad(random_int(0, 9999999999), 10, "0", STR_PAD_LEFT);
        $_SESSION['am_ref'] = $ref;

        /* ---------------------------------------
           GET LINKED ACCOUNT OF THIS CARD
        ---------------------------------------- */
        $linkedAccount = null;

        if (!empty($cardNo)) {
            $stmt = $conn->prepare("SELECT linkedAccount FROM cards WHERE cardNo=? LIMIT 1");
            $stmt->bind_param("s", $cardNo);
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
            $stmt->bind_param("ds", $amount, $linkedAccount);
            $stmt->execute();
            $stmt->close();
        }

        /* ---------------------------------------
           ADD TO RECEIVER ACCOUNT
        ---------------------------------------- */
        $stmt = $conn->prepare("UPDATE accounts SET Balance = Balance + ? WHERE AccountNo=? AND CustomerID=?");
        $stmt->bind_param("dsi", $amount, $toAcc, $cid);
        $stmt->execute();
        $stmt->close();

        /* ---------------------------------------
           SAVE TRANSACTION
        ---------------------------------------- */
        $stmt = $conn->prepare("
            INSERT INTO add_money_transactions
            (cid, card_mask, card_holder, source_type, tx_amount, to_account, to_name, verification_method, requested_at)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");

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

        // clear OTP from session
        unset($_SESSION['am_otp'], $_SESSION['am_otp_expires']);

        $conn->close();

        header("Location: add-money-success.php");
        exit;
    }
}

$method = $_SESSION['am_veri'] ?? "SMS";
$masked = mask_phone($phone);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Add Money — OTP</title>
<link rel="stylesheet" href="transfer.css">
<style>
  :root{ --primary:#00416A; }

  /* Dashboard-style gradient background + centered card */
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

  .otp-wrap{
    display:flex; 
    gap:10px; 
    justify-content:center; 
    margin-top:12px
  }
  .otp-wrap input{
    width:44px; 
    height:56px; 
    text-align:center; 
    font-size:24px; 
    border:1.5px solid var(--border); 
    border-radius:12px
  }
  .err{
    color:#b00020;
    font-weight:700;
    text-align:center;
    margin-top:8px;
  }
  .success{
    color:#0a7f3f;
    font-weight:600;
    text-align:center;
    margin-top:8px;
  }
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

      <div class="kv">
        We sent a code via <?=htmlspecialchars($method)?> to
        <?=htmlspecialchars($masked)?>
      </div>

      <?php if($success): ?>
      <div class="success"><?=htmlspecialchars($success)?></div>
      <?php endif; ?>

      <?php if($error): ?>
      <div class="err"><?=htmlspecialchars($error)?></div>
      <?php endif; ?>

      <form method="POST" id="otpForm">
        <div class="otp-wrap" id="otpWrap">
          <?php for($i=0;$i<6;$i++): ?>
          <input class="otpbox" type="password" maxlength="1" inputmode="numeric">
          <?php endfor; ?>
        </div>

        <input type="hidden" name="otp" id="otpHidden">

        <div class="footerbar">
          <button class="btn" id="verify" disabled>Verify</button>
        </div>
      </form>

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
