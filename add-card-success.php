<?php
session_start();

if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

// Optional: only allow if coming from OTP success
$fromSuccess = isset($_SESSION['add_card_success']) && $_SESSION['add_card_success'] === true;
unset($_SESSION['add_card_success']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Card Added Successfully</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    :root { --primary:#00416A; }

    /* Dashboard-style background + centering */
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 24px 12px;
    }

    .app {
      width: 100%;
      max-width: 720px;
    }

    .card {
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    .h1 {
      color:#ffffff;
      text-shadow:0 1px 2px rgba(0,0,0,0.25);
      margin-bottom: 12px;
    }

    .success-icon{
      font-size:40px;
      margin-bottom:12px;
    }
    .success-msg{
      font-size:16px;
      margin-bottom:8px;
      font-weight:600;
    }
    .success-note{
      font-size:14px;
      color:var(--muted);
      margin-bottom:20px;
    }
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <span class="step">Done</span>
    </div>

    <div class="h1">Add Card</div>

    <div class="card">
      <div class="section" style="text-align:center;">
        <div class="success-icon">✅</div>
        <div class="success-msg">
          <?php echo $fromSuccess ? "Your card has been added successfully!" : "Card status"; ?>
        </div>
        <div class="success-note">
          You can now use this card for your transactions.
        </div>

        <div class="footerbar" style="justify-content:center; margin-top:10px;">
          <a class="btn" href="dashboard.php">Go to Dashboard</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
