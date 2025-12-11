<?php
session_start();
if (!isset($_SESSION['cid'])) { 
    header("Location: login.php"); 
    exit; 
}

$error = "";

/* ------------------ HANDLE NEW CARD SUBMIT ------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_card') {
    $name = trim($_POST['card_name'] ?? '');
    $no   = preg_replace('/\D+/', '', $_POST['card_no'] ?? '');
    $exp  = trim($_POST['card_exp'] ?? '');
    $cvv  = trim($_POST['card_cvv'] ?? '');
    $save = isset($_POST['saveCard']) ? 1 : 0;

    if ($name === '' || $no === '' || $exp === '' || $cvv === '') {
        $error = "Please fill all card fields.";
    } else {
        $last4 = substr($no, -4);

        // SAVE FOR OTP
        $_SESSION['am_card_holder'] = $name;
        $_SESSION['am_card_mask']   = "•••• •••• •••• $last4";
        $_SESSION['am_from']        = "CARD";
        $_SESSION['am_save_card']   = $save;

        // SAVE REAL CARD NUMBER
        $_SESSION['am_card_no']     = $no;

        header("Location: add-money-amount.php");
        exit;
    }
}

/* ------------------ HANDLE SAVED CARD CLICK ------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'use_saved') {
    $card = $_POST['saved_card'] ?? '';

    if ($card !== '') {
        $digits = preg_replace('/\D+/', '', $card);

        $_SESSION['am_card_holder'] = "Saved Card";
        $_SESSION['am_card_mask']   = "•••• •••• •••• " . substr($digits, -4);
        $_SESSION['am_from']        = "SAVED_CARD";

        // SAVE REAL CARD NUMBER
        $_SESSION['am_card_no']     = $digits;

        header("Location: add-money-amount.php");
        exit;
    }
}

/* ------------------ LOAD SAVED CARDS FROM accounts TABLE ------------------ */

$host = "localhost"; 
$user = "root"; 
$pass = ""; 
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$cid = $_SESSION['cid'];
$savedCards = [];

$stmt = $conn->prepare("
    SELECT Card 
    FROM accounts
    WHERE CustomerID = ?
      AND Card IS NOT NULL
      AND Card <> ''
");
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $savedCards[] = $row['Card'];
}

$stmt->close();
$conn->close();

function maskCard($number) {
    $digits = preg_replace('/\D/', '', $number);
    if ($digits === '') return '';
    return "•••• •••• •••• " . substr($digits, -4);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Add From — Card</title>
<link rel="stylesheet" href="transfer.css">

<style>
/* ⬇⬇⬇ DASHBOARD BACKGROUND THEME ⬇⬇⬇ */
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
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* ---------------- EXISTING STYLES ---------------- */
.tabs{display:flex; gap:14px; margin:0 0 8px 0}
.tab{padding:10px 14px; border:1px solid var(--border); border-radius:999px; cursor:pointer; font-weight:700}
.tab[aria-selected="true"]{background:var(--primary); color:#fff; border-color:var(--primary)}
.empty{padding:16px; color:var(--muted)}
.err{color:#b00020; font-weight:700; margin:8px 0;}
.saved-list{display:flex; flex-direction:column; gap:10px; margin-top:10px;}
.saved-card-item{
    padding:12px 14px;
    border-radius:12px;
    border:1px solid var(--border);
    background:#fafafa;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:14px;
    cursor:pointer;
    width:100%;
}
</style>
</head>

<body>
<div class="app">
  <div class="topbar">
    <a class="linkish" href="add-money.php">← Back</a>
    <span class="step">1 / 4</span>
  </div>

  <div class="h1">Add From</div>

  <div class="card">
    <div class="section">

      <div class="tabs" role="tablist">
        <button id="t1" class="tab" aria-selected="true">Saved Card</button>
        <button id="t2" class="tab" aria-selected="false">New Card</button>
      </div>

      <!-- SAVED CARDS -->
      <div id="panel-saved">
        <?php if(empty($savedCards)): ?>
          <div class="empty">You have no saved cards.</div>
        <?php else: ?>
          <div class="saved-list">
            <?php foreach($savedCards as $card): ?>
              <form method="POST">
                <input type="hidden" name="action" value="use_saved">
                <input type="hidden" name="saved_card" value="<?= htmlspecialchars($card) ?>">
                <button class="saved-card-item">
                  <span>Saved Card</span>
                  <span><?= maskCard($card) ?></span>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if($error): ?>
        <div class="err"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- NEW CARD -->
      <form id="cardForm" method="POST" hidden>
        <input type="hidden" name="action" value="new_card">

        <div class="row">
          <div class="label">Cardholder Name</div>
          <input class="input" name="card_name" required>
        </div>

        <div class="row">
          <div class="label">Card Number</div>
          <input class="input" name="card_no" required inputmode="numeric" maxlength="19">
        </div>

        <div class="row">
          <div class="label">Expiry Date</div>
          <input type="month" class="input" name="card_exp" required>
        </div>

        <div class="row">
          <div class="label">CVV</div>
          <input class="input" name="card_cvv" required inputmode="numeric" maxlength="4">
        </div>

        <div class="row">
          <label class="kv"><input type="checkbox" name="saveCard" checked> Save this card</label>
        </div>

        <div class="footerbar">
          <button class="btn">Next</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
const t1=document.getElementById('t1');
const t2=document.getElementById('t2');
const saved=document.getElementById('panel-saved');
const form=document.getElementById('cardForm');

t1.onclick=()=>{
  t1.setAttribute("aria-selected","true");
  t2.setAttribute("aria-selected","false");
  saved.hidden=false;
  form.hidden=true;
};

t2.onclick=()=>{
  t2.setAttribute("aria-selected","true");
  t1.setAttribute("aria-selected","false");
  saved.hidden=true;
  form.hidden=false;
};
</script>

</body>
</html>
