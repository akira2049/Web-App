<?php
session_start();
if (!isset($_SESSION['cid'])) { header("Location: login.php"); exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Money</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .list{display:grid; gap:12px}
    .item{display:flex; align-items:center; justify-content:space-between; padding:16px}
    .sub{color:var(--muted); font-size:14px}
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="dashboard.php">← Back</a>
    </div>
    <div class="h1">Add Money</div>

    <div class="card">
      <div class="section list">
        <a class="opt card" style="text-decoration:none" href="add-money-from.php">
          <div>
            <div class="title">VISA/Mastercard</div>
            <small>From VISA/Mastercard credit & debit cards</small>
          </div>
          <div>›</div>
        </a>

        <a class="opt card" style="text-decoration:none" href="add-money-saved.php">
          <div>
            <div class="title">Saved Cards</div>
            <small>See your saved cards</small>
          </div>
          <div>›</div>
        </a>

        <a class="opt card" style="text-decoration:none" href="history.php">
          <div>
            <div class="title">History</div>
            <small>View your recent add-money transactions</small>
          </div>
          <div>›</div>
        </a>
      </div>
    </div>
  </div>
</body>
</html>