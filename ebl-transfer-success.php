<?php
session_start();
if (!isset($_SESSION['cid'])) { 
    header("Location: login.php"); 
    exit; 
}

$txId = $_GET['tx'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Transfer Success</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">
  <style>
    .app{
      max-width:720px;
      margin:0 auto;
      padding:24px;
      text-align:center;
    }
    .success-card{
      max-width:520px;
      margin:0 auto;
    }
  </style>
</head>
<body>

  <div class="app">
    <div class="card success-card">
      <div class="section">
        <h2 style="margin-bottom:8px;">✅ Transfer Successful</h2>

        <?php if($txId): ?>
          <p>Transaction ID: <b><?= htmlspecialchars($txId) ?></b></p>
        <?php else: ?>
          <p>Your transfer was completed successfully.</p>
        <?php endif; ?>

        <a class="btn" href="fund-transfer.php" style="display:inline-block;margin-top:12px;">
          Back to Transfers
        </a>
      </div>
    </div>
  </div>

</body>
</html>
