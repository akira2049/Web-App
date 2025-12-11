<?php
session_start();
if (!isset($_SESSION['cid'])) { 
    header("Location: login.php"); 
    exit; 
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Money</title>

  <!-- Global styles -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">

  <style>
    :root { --primary:#00416A; }

    /* Dashboard-like background */
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 24px 12px;
    }

    .app{
      width:100%;
      max-width:720px;
    }

    /* Card shadow tweak so it pops on gradient */
    .card{
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    .list{
      display:grid;
      gap:12px;
    }
    .item{
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:16px;
    }
    .sub{
      color:var(--muted);
      font-size:14px;
    }
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
            <small>From VISA/Mastercard credit &amp; debit cards</small>
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

        <a class="opt card" style="text-decoration:none" href="add-money-history.php">
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
