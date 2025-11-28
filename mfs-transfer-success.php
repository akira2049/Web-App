<?php
session_start();
if (!isset($_SESSION['cid'])) { 
    header("Location: login.php"); 
    exit; 
}

$tid = $_GET['tid'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>MFS Transfer — Success</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    :root{ --primary:#00416A; }

    .subtitle{
      color:var(--muted);
      font-size:14px;
      margin-top:6px;
      margin-bottom:18px;
    }
    .success-icon{
      font-size:40px;
      margin-bottom:10px;
    }
    .section-center{
      text-align:center;
    }
    .actions{
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

  <div class="h1">MFS Transfer</div>

  <div class="card">
    <div class="section section-center">
      <div class="success-icon">✅</div>
      <div class="h2">Transfer Successful 🎉</div>
      <p class="subtitle">
        Your money has been sent to the mobile wallet.
      </p>

      <?php if (!empty($tid)): ?>
        <p class="kv">
          Transaction ID:<br>
          <b><?= htmlspecialchars($tid) ?></b>
        </p>
      <?php endif; ?>

      <div class="actions">
        <a class="btn" 
           href="mfs-transfer-receipt.php?tid=<?= urlencode($tid) ?>" 
           style="text-decoration:none">
           Download Receipt
        </a>

        <a class="btn" 
           href="mfs-transfer.php" 
           style="text-decoration:none">
           Make Another Transfer
        </a>
      </div>

    </div>
  </div>
</div>
</body>
</html>
