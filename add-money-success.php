<?php
session_start();
if (!isset($_SESSION['cid'])) { header("Location: login.php"); exit; }
if (!isset($_SESSION['am_amount'], $_SESSION['am_to_acc'])) { header("Location: add-money.php"); exit; }

$amount = floatval($_SESSION['am_amount']);
$from   = $_SESSION['am_card_mask'] ?? "Card";
$holder = $_SESSION['am_card_holder'] ?? "NA";
$toAcc  = $_SESSION['am_to_acc'];
$toName = $_SESSION['am_to_name'] ?? "Account Holder";
$when   = $_SESSION['am_when'] ?? date("Y-m-d H:i:s");
$ref    = $_SESSION['am_ref'] ?? ("AM".str_pad(strval(random_int(0,9999999999)),10,"0",STR_PAD_LEFT));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Money — Success</title>
  <link rel="stylesheet" href="transfer.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <style>
    .center{ text-align:center; padding:30px }
    .receipt{ border:1px dashed var(--border); border-radius:12px; padding:16px; margin-top:12px; text-align:left }
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="dashboard.php">← Back to Dashboard</a>
    </div>
    <div class="h1">Money Added Successfully</div>

    <div class="card">
      <div class="section center">
        <div style="font-size:48px">✅</div>
        <p class="kv">Your funds have been added to the account.</p>

        <div class="receipt" id="rcpt">
          <div class="row">
            <div class="label">Amount</div>
            <div><b id="r_amt">BDT <?= number_format($amount,2) ?></b></div>
          </div>
          <div class="row">
            <div class="label">From</div>
            <div id="r_from"><?= htmlspecialchars($from) ?> — <?= htmlspecialchars($holder) ?></div>
          </div>
          <div class="row">
            <div class="label">To</div>
            <div id="r_to"><?= htmlspecialchars($toName) ?> (Acc ** <?= htmlspecialchars(substr($toAcc,-4)) ?>)</div>
          </div>
          <div class="row">
            <div class="label">Date</div>
            <div id="r_when"><?= htmlspecialchars($when) ?></div>
          </div>
          <div class="row">
            <div class="label">Reference</div>
            <div id="r_ref"><?= htmlspecialchars($ref) ?></div>
          </div>
        </div>

        <div class="footerbar">
          <button class="btn" id="dl">Download PDF Receipt</button>
        </div>
      </div>
    </div>
  </div>

<script>
  document.getElementById('dl').addEventListener('click', async ()=>{
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFont("helvetica","bold");
    doc.setFontSize(18);
    doc.text("Add Money Receipt", 20, 20);

    doc.setFontSize(12);
    doc.setFont("helvetica","normal");
    doc.text("Transaction Reference: " + <?= json_encode($ref) ?>, 20, 35);
    doc.text("Date & Time: " + <?= json_encode($when) ?>, 20, 45);

    doc.text("Amount: BDT " + <?= json_encode(number_format($amount,2)) ?>, 20, 60);
    doc.text("From: " + <?= json_encode($from . " — " . $holder) ?>, 20, 70);
    doc.text("To: " + <?= json_encode($toName . " (Acc ** " . substr($toAcc,-4) . ")") ?>, 20, 80);

    doc.text("Status: SUCCESSFUL ✅", 20, 95);
    doc.line(20, 100, 190, 100);
    doc.text("Thank you for using our Add Money service.", 20, 115);

    doc.save("AddMoney_Receipt.pdf");
  });
</script>
</body>
</html>
