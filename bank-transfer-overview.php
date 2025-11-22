<?php
session_start();
if (!isset($_SESSION['cid'])) { header("Location: login.php"); exit; }

// pull values from Step-1 POST
$from_acc = $_POST['from_acc'] ?? '';
$to_acc   = $_POST['to_acc'] ?? '';
$amount   = $_POST['amount'] ?? '';
$note     = $_POST['note'] ?? '';
$holder   = $_POST['holder_name'] ?? '';

if ($from_acc=='' || $to_acc=='' || $amount=='') {
    header("Location: bank-transfer.php");
    exit;
}
// Save transfer info into session so OTP + process can use it
$_SESSION['ebl_pending_transfer'] = [
    'from_acc' => $from_acc,
    'to_acc'   => $to_acc,
    'amount'   => $amount,
    'note'     => $note,
    'holder'   => $holder
];

// clear old otp / verify flag
unset($_SESSION['otp_code'], $_SESSION['otp_exp'], $_SESSION['ebl_transfer_verified']);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bank Transfer — Overview</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    .admin-content{width:100%;padding:24px;}
    .overview-card{max-width:520px;margin:0 auto;}
    .overview-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px dashed rgba(255,255,255,0.15);}
    .overview-row:last-child{border-bottom:none;}
    .overview-label{opacity:.8;}
    .overview-value{font-weight:600;}
  </style>
</head>
<body>

<div class="admin-layout">
  <aside class="sidebar">
    <div class="sidebar-top">
      <div class="logo"><i class="fa-solid fa-building-columns"></i> Shanto Bank</div>
    </div>

    <!-- Sidebar buttons removed -->
    <nav class="side-nav"></nav>
  </aside>

  <main class="admin-content">

    <div class="topbar">
      <a class="linkish" href="bank-transfer.php">← Back</a>
      <span class="step">2 / 2</span>
    </div>

    <div class="h1">Transfer Overview</div>

    <div class="card overview-card">
      <div class="section">

        <div class="overview-row">
          <div class="overview-label">From Account</div>
          <div class="overview-value"><?= htmlspecialchars($from_acc) ?></div>
        </div>

        <div class="overview-row">
          <div class="overview-label">To Account</div>
          <div class="overview-value"><?= htmlspecialchars($to_acc) ?></div>
        </div>

        <div class="overview-row">
          <div class="overview-label">Beneficiary Name</div>
          <div class="overview-value"><?= htmlspecialchars($holder ?: "—") ?></div>
        </div>

        <div class="overview-row">
          <div class="overview-label">Amount</div>
          <div class="overview-value">৳ <?= htmlspecialchars($amount) ?></div>
        </div>

        <div class="overview-row">
          <div class="overview-label">Note</div>
          <div class="overview-value"><?= htmlspecialchars($note ?: "—") ?></div>
        </div>

        <form method="POST" action="bank-transfer-otp.php">
          <div class="footerbar">
            <button class="btn" type="submit">Confirm Transfer</button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

</body>
</html>
