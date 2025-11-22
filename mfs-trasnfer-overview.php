<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];

// get POST from step1
$fromAcc     = $_POST['fromAcc'] ?? '';
$walletType  = $_POST['walletType'] ?? '';
$walletNo    = $_POST['walletNo'] ?? '';
$receiver    = $_POST['receiverName'] ?? '';
$amount      = $_POST['amount'] ?? '';
$note        = $_POST['note'] ?? '';

if($fromAcc=='' || $walletType=='' || $walletNo=='' || $receiver=='' || $amount==''){
    header("Location: mfs-transfer.php?err=1");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>MFS Transfer — Overview</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    :root{ --primary:#00416A; --primary-600:#005a91; }
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="mfs-transfer.php">← Back</a>
      <span class="step">2 / 2</span>
    </div>

    <div class="h1">Transfer Overview</div>

    <div class="card">
      <div class="section">

        <div class="row">
          <div class="label">Transfer From</div>
          <div class="kv"><b>Acc. <?= htmlspecialchars($fromAcc) ?></b></div>
        </div>

        <div class="row">
          <div class="label">Total Amount</div>
          <div class="total">
            <div>
              <div class="kv">BDT</div>
              <div style="font-size:24px;font-weight:900;color:var(--primary)">
                <?= number_format((float)$amount,2) ?>
              </div>
              <div class="note">BDT <?= number_format((float)$amount,2) ?> + BDT 0.00</div>
            </div>
            <div class="kv">View Breakdown ⌄</div>
          </div>
        </div>

        <div class="row">
          <div class="label">Transfer To</div>
          <div class="kv">
            <b><?= htmlspecialchars($receiver) ?></b><br>
            <?= htmlspecialchars($walletType) ?> • <span class="mask"><?= htmlspecialchars($walletNo) ?></span>
          </div>
        </div>

        <form action="mfs-transfer-process.php" method="post">
          <!-- pass data forward -->
          <input type="hidden" name="fromAcc" value="<?= htmlspecialchars($fromAcc) ?>">
          <input type="hidden" name="walletType" value="<?= htmlspecialchars($walletType) ?>">
          <input type="hidden" name="walletNo" value="<?= htmlspecialchars($walletNo) ?>">
          <input type="hidden" name="receiverName" value="<?= htmlspecialchars($receiver) ?>">
          <input type="hidden" name="amount" value="<?= htmlspecialchars($amount) ?>">

          <div class="row">
            <div class="label">Notes</div>
            <input class="input" name="note" value="<?= htmlspecialchars($note) ?>">
          </div>

          <div class="row">
            <div class="label">Verification</div>
            <div class="veri-grid" id="veri">
              <label class="vcard"><input type="radio" name="veri" value="EMAIL" required> Email</label>
              <label class="vcard"><input type="radio" name="veri" value="SMS" required> SMS</label>
            </div>
          </div>

          <div class="footerbar">
            <button class="btn" type="submit">Confirm</button>
          </div>
        </form>

      </div>
    </div>
  </div>

<script>
  // active border on verification cards
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
