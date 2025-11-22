<?php
session_start();
if (!isset($_SESSION['cid'])) { header("Location: login.php"); exit; }

$tid = $_GET['tid'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>MFS Transfer Success</title>
  <link rel="stylesheet" href="transfer.css">
  <style>:root{ --primary:#00416A; }</style>
</head>
<body>
<div class="app">
  <div class="h1">Transfer Successful 🎉</div>
  <div class="card">
    <div class="section">
      <p class="kv">Transaction ID: <b><?= htmlspecialchars($tid) ?></b></p>
      <div style="margin-top:14px">
        <a class="btn" href="history.html" style="text-decoration:none">Go to History</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
