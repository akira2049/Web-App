<?php
session_start();
/*if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['biller_id'])) {
    // Coming from bill-payments.php / bill-billers.php
    $billerId   = trim($_POST['biller_id'] ?? '');      // this is biller_code (varchar, e.g. 2077)
    $billerName = $_POST['biller_name'] ?? '';
    $billerLogo = $_POST['biller_logo'] ?? '';
    // hidden field name in bill-payments.php is "biller_type"
    $category   = $_POST['biller_type'] ?? '';

    if ($billerId === '' || $billerName === '') {
        header("Location: bill-payments.php");
        exit;
    }

    // Save biller info into session
    $_SESSION['bill_biller_code'] = $billerId;   // <-- biller_code from billers table
    $_SESSION['bill_biller_name'] = $billerName;
    $_SESSION['bill_biller_logo'] = $billerLogo;
    $_SESSION['bill_category']    = $category;

} else {
    // If user directly hits this page without biller info
    if (!isset($_SESSION['bill_biller_name'])) {
        header("Location: bill-payments.php");
        exit;
    }
    $billerName = $_SESSION['bill_biller_name'];
    $billerLogo = $_SESSION['bill_biller_logo'] ?? '';
    $category   = $_SESSION['bill_category'] ?? '';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Enter Amount — <?php echo htmlspecialchars($billerName); ?></title>

  <!-- Global styles -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">

  <style>
    :root{ --primary:#00416A; }

    /* Match dashboard background + centered card layout */
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .app {
      max-width: 540px;
      margin: 0 auto;
      padding: 24px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .card {
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    .backlink{
      text-decoration:none;
      color:var(--primary);
      font-weight:600;
      display:flex;
      align-items:center;
      gap:6px;
      font-size:15px;
    }

    .amount-card{
      width:100%;
    }

    .billHead{
      display:flex;
      align-items:flex-start;
      gap:12px;
      margin-bottom:16px;
    }
    .billLogo{
      width:40px;
      height:40px;
      border-radius:8px;
      border:1px solid var(--border);
      background:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:12px;
      font-weight:700;
      color:var(--primary);
    }
    .billName{
      font-weight:800;
      color:var(--primary);
      font-size:16px;
      line-height:1.3;
    }
    .billSub{
      color:var(--muted);
      font-size:14px;
      line-height:1.3;
    }

    .field-label{
      font-size:12px;
      font-weight:700;
      color:var(--muted);
      margin-bottom:4px;
      margin-top:12px;
    }
    .text-input{
      width:100%;
      border-radius:12px;
      border:1px solid var(--border);
      padding:10px 12px;
      font-size:15px;
      color:var(--primary);
      box-sizing:border-box;
      outline:none;
      background:#fff;
    }
    .text-input::placeholder{
      color:var(--muted);
    }

    .amount-input{
      font-size:22px;
      font-weight:800;
      text-align:left;
    }

    .footercta{
      margin-top:24px;
    }
    .nextBtn{
      width:100%;
      border:none;
      border-radius:14px;
      font-weight:800;
      font-size:16px;
      padding:14px 18px;
      background:var(--primary);
      color:#fff;
      cursor:pointer;
    }
  </style>
</head>
<body>
  <div class="app">
    <div class="amount-card card">
      <div class="section">

        <div class="topbar" style="margin-bottom:10px;">
          <a class="backlink" href="bill-billers.php">← Back</a>
        </div>

        <div class="billHead">
          <div class="billLogo">
            <?php echo htmlspecialchars($billerLogo ?: substr($billerName,0,3)); ?>
          </div>
          <div>
            <div class="billName"><?php echo htmlspecialchars($billerName); ?></div>
            <?php if ($category): ?>
              <div class="billSub"><?php echo htmlspecialchars($category); ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- This form goes to the OVERVIEW page -->
        <form method="post" action="bill-payment-overview.php">

          <!-- biller info for next step -->
          <input type="hidden" name="bill_name" value="<?php echo htmlspecialchars($billerName); ?>">
          <input type="hidden" name="bill_logo" value="<?php echo htmlspecialchars($billerLogo); ?>">
          <input type="hidden" name="bill_type" value="<?php echo htmlspecialchars($category); ?>">

          <div class="field-label">Customer ID</div>
          <input
            type="text"
            name="bill_id"
            class="text-input"
            placeholder="Connection ID"
            required
          >

          <div class="field-label">Amount (BDT)</div>
          <input
            type="number"
            name="bill_amt"
            class="text-input amount-input"
            step="0.01"
            min="1"
            placeholder="0.00"
            required
          >

          <div class="footercta">
            <button type="submit" class="nextBtn">Next</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</body>
</html>
