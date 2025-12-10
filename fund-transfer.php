<?php
session_start();

// If user is not logged in, redirect to login page
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Fund Transfer</title>
  <link rel="stylesheet" href="transfer.css">
</head>
<body>
  <div class="app">

    <!-- TOP BAR -->
    <div class="topbar">
      <span class="kv"><b>Fund Transfer</b></span>
    </div>

    <div class="h1">Select transfer options</div>

    <div class="card">
      <div class="section">
        <div class="grid">

          <!-- OBA TRANSFER -->
          <a class="opt card" href="bank-transfer.php" style="text-decoration:none">
            <div>
              <div class="title">OBA</div>
              <small>To Own Bank Account</small>
            </div>
            <div>›</div>
          </a>

          <!-- OTHER BANK -->
          <div class="opt card">
            <div>
              <div class="title">Other Bank</div>
              <small>To Other Bank Account</small>
            </div>
            <div>…</div>
          </div>

          <!-- MFS TRANSFER -->
          <a class="opt card" href="mfs-transfer.php" style="text-decoration:none">
            <div>
              <div class="title">MFS</div>
              <small>To Mobile Wallets</small>
            </div>
            <div>›</div>
          </a>

        </div>

        <!-- BACK LINK -->
        <div class="linkrow" style="margin-top:16px">
          <a class="linkish" href="dashboard.php">Dashboard →</a>
        </div>

      </div>
    </div>

  </div>
</body>
</html>
