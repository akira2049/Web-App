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

  <style>
    /* Match EXACT Dashboard Background */
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: linear-gradient(135deg, #00416A, #E4E5E6); /* Dashboard gradient */
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .app {
      min-height: 100vh;
    }

    /* Optional: Make cards pop more on gradient */
    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    .opt.card:hover {
      transform: scale(1.02);
      transition: 0.2s ease-in-out;
    }
  </style>

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
              <div class="title">Bank Transfer</div>
              <small>To Bank Account</small>
            </div>
            <div>›</div>
          </a>

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
