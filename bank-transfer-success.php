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
    .btn-outline{
      background: transparent;
      border: 1px solid rgba(255,255,255,0.5);
    }
    .btn-row{
      display:flex;
      gap:10px;
      justify-content:center;
      flex-wrap:wrap;
      margin-top:12px;
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

          <div class="btn-row">
            <!-- PDF receipt button -->
            <a class="btn btn-outline" 
               href="bank-transfer-receipt.php?tx=<?= urlencode($txId) ?>" 
               target="_blank">
              Download PDF Receipt
            </a>

            <a class="btn" href="fund-transfer.php">
              Back to Transfers
            </a>
          </div>

        <?php else: ?>
          <p>Your transfer was completed successfully.</p>

          <div class="btn-row">
            <a class="btn" href="fund-transfer.php">Back to Transfers</a>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

</body>
</html>
