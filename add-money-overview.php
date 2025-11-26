<?php
session_start();
if (!isset($_SESSION['cid'])) { header("Location: login.php"); exit; }

// make sure all needed session values exist
foreach(['am_card_mask','am_amount','am_to_acc'] as $k){
  if(!isset($_SESSION[$k])) { header("Location: add-money.php"); exit; }
}

$mask   = $_SESSION['am_card_mask'];
$holder = $_SESSION['am_card_holder'] ?? "NA";
$amount = floatval($_SESSION['am_amount']);
$toAcc  = $_SESSION['am_to_acc'];
$toName = $_SESSION['am_to_name'] ?? "Account Holder";
$from   = $_SESSION['am_from'] ?? "Card";   // e.g. "VISA/Mastercard", "Saved Card"

// handle verification choice
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $veri = $_POST['veri'] ?? 'SMS';
    $_SESSION['am_veri'] = $veri;
    $_SESSION['am_when'] = date("Y-m-d H:i:s");
    header("Location: add-money-otp.php");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Money — Overview</title>
  <link rel="stylesheet" href="transfer.css">
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="add-money-to.php">← Back</a>
      <span class="step">4 / 4</span>
    </div>
    <div class="h1">Overview</div>

    <div class="card">
      <div class="section">

        <!-- CARD ROW -->
        <div class="row">
          <div class="label">Card</div>
          <div class="kv">
            <b><?= htmlspecialchars($mask) ?></b><br>
            Card Holder: <?= htmlspecialchars($holder) ?>
          </div>
        </div>

        <!-- ADD FROM ROW (SOURCE TYPE) -->
        <div class="row">
          <div class="label">Add From</div>
          <div class="kv">
            <?= htmlspecialchars($from) ?>
          </div>
        </div>

        <!-- TOTAL AMOUNT -->
        <div class="row">
          <div class="label">Total Amount</div>
          <div class="total">
            <div>
              <div class="kv">BDT</div>
              <div style="font-size:24px; font-weight:900; color:var(--primary)">
                <?= number_format($amount,2) ?>
              </div>
              <div class="note">BDT <?= number_format($amount,2) ?> + BDT 0.00</div>
            </div>
            <div class="kv">View Breakdown ⌄</div>
          </div>
        </div>

        <!-- ADD TO ROW -->
        <div class="row">
          <div class="label">Add To</div>
          <div class="kv">
            <b><?= htmlspecialchars($toName) ?></b><br>
            Acc ** <span class="mask"><?= htmlspecialchars(substr($toAcc,-4)) ?></span>
          </div>
        </div>

        <!-- VERIFICATION -->
        <form method="POST">
          <div class="row">
            <div class="label">Verification</div>
            <div class="veri-grid" id="veri">
              <label class="vcard">
                <input type="radio" name="veri" value="Email"> Email
              </label>
              <label class="vcard active">
                <input type="radio" name="veri" value="SMS" checked> SMS
              </label>
            </div>
          </div>

          <div class="footerbar">
            <button class="btn" id="confirm">Confirm & Add</button>
          </div>
        </form>

      </div>
    </div>
  </div>

<script>
  // toggle active style on verification cards
  const cards = document.querySelectorAll('.vcard');
  cards.forEach(c=>{
    c.addEventListener('click', ()=>{
      cards.forEach(x=>x.classList.remove('active'));
      c.classList.add('active');
      c.querySelector('input').checked = true;
    });
  });
</script>
</body>
</html>
