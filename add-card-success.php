<?php
session_start();

/*if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}*/

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
