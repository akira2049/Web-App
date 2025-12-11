<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];
$tid = $_GET['tid'] ?? '';

if ($tid == '') {
    die("Missing transaction ID.");
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bank Transfer — Success</title>

  <!-- Global styles -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">

  <style>
    /* Match dashboard background + layout */
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .app {
      max-width: 520px;
      margin: 0 auto;
      padding: 24px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .card {
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .subtitle {
      color:#666;
      font-size:14px;
      margin-top:6px;
      margin-bottom:18px;
    }

    .success-icon {
      font-size:40px;
      margin-bottom:10px;
    }

    .section-center {
      text-align:center;
    }

    .actions {
      margin-top:18px;
      display:flex;
      justify-content:center;
      gap:10px;
      flex-wrap:wrap;
    }
  </style>
</head>
<body>
<div class="app">
  <div class="topbar">
    <a class="linkish" href="dashboard.php">← Back to Dashboard</a>
  </div>

  <div class="h1">Bank Transfer</div>

  <div class="card">
    <div class="section section-center">

      <div class="success-icon">✅</div>
      <div class="h2">Transfer Successful 🎉</div>

      <p class="subtitle">Your bank transfer has been completed.</p>

      <p class="kv">
        Transaction ID:<br>
        <b><?= htmlspecialchars($tid) ?></b>
      </p>

      <div class="actions">
        <a class="btn" href="bank-transfer-receipt.php?tid=<?= urlencode($tid) ?>">Download Receipt</a>
        <a class="btn" href="bank-transfer.php">Make Another Transfer</a>
      </div>

    </div>
  </div>
</div>
</body>
</html>
