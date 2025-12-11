<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];

// Make sure user just completed OTP for recharge
if (empty($_SESSION['recharge_verified'])) {
    header("Location: recharge.php");
    exit;
}

// Get tx_id from session (set in OTP page)
$txId = $_SESSION['last_recharge_txid'] ?? '';

// If no tx_id, something went wrong or page was refreshed oddly
if ($txId === '') {
    header("Location: recharge.php");
    exit;
}

// ---- DB: load transaction details for this tx_id & cid ----
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$sql = "
    SELECT *
    FROM mobile_recharges
    WHERE tx_id = ? AND cid = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $txId, $cid);
$stmt->execute();
$res = $stmt->get_result();
$tx  = $res->fetch_assoc();
$stmt->close();
$conn->close();

if (!$tx) {
    // If somehow not found, go back safely
    header("Location: recharge.php");
    exit;
}

// Extract values for display
$amount   = isset($tx['amount']) ? floatval($tx['amount']) : 0.0;
$msisdn   = $tx['mobile_number']    ?? '';
$ctype    = $tx['recharge_type']    ?? 'Prepaid';
$fromAcc  = $tx['from_acc']         ?? '---';
$created  = $tx['created_at']       ?? date("Y-m-d H:i:s");

// Optionally clear flags so refresh doesn’t reuse same verification
unset($_SESSION['recharge_verified'], $_SESSION['last_recharge_txid']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Recharge Successful</title>

  <!-- Global styles -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">

  <style>
    :root{ --primary:#00416A; }

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

    .app {
      width: 100%;
      max-width: 720px;
    }

    .card {
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    .center{margin:0 auto;}
    .big{font-size:22px;font-weight:900;color:var(--primary)}
    .ok{font-size:48px;line-height:1}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width:700px){.grid{grid-template-columns:1fr}}
    .kv{font-size:14px}

    .total{
      display:flex;
      justify-content:space-between;
      gap:10px;
      padding:14px;
      border:1px solid var(--border);
      border-radius:12px;
      background:#fbfbfc;
    }
    .note{color:var(--muted);font-size:13px}

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:12px;
    }
    .ghost{
      background:#fff;
      color:var(--primary);
      border:1px solid var(--border);
      text-decoration:none;
    }
  </style>
</head>
<body>
  <div class="app center card">
    <div class="section">
      <div class="ok">✅</div>
      <div class="big">Recharge Successful</div>
      <div class="kv">
        <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($created))); ?>
        &nbsp; • Txn: <?php echo htmlspecialchars($txId); ?>
      </div>

      <div class="grid" style="margin-top:12px">
        <div class="total">
          <div>
            <div class="kv">BDT</div>
            <div style="font-size:24px;font-weight:900;color:var(--primary)">
              <?php echo number_format($amount, 2); ?>
            </div>
            <div class="note">includes fees: BDT 0.00</div>
          </div>
          <div class="kv">Paid</div>
        </div>
        <div class="total">
          <div class="kv">
            <b><?php echo htmlspecialchars($msisdn ?: '+8801XXXXXXXXX'); ?></b><br>
            Connection: <span><?php echo htmlspecialchars($ctype); ?></span>
          </div>
          <div class="kv">Recipient</div>
        </div>
      </div>

      <div class="grid" style="margin-top:12px">
        <div class="total">
          <div class="kv">From</div>
          <div class="kv">
            <b>Acc. <?php echo htmlspecialchars($fromAcc); ?></b>
          </div>
        </div>
        <div class="total">
          <div class="kv">Transaction ID</div>
          <div class="kv">
            <b><?php echo htmlspecialchars($txId); ?></b>
          </div>
        </div>
      </div>

      <div class="actions">
        <!-- ✅ Link to FPDF receipt -->
        <a class="btn" href="recharge-receipt.php?tid=<?php echo urlencode($txId); ?>">
          Download PDF receipt
        </a>
        <a class="btn ghost" href="recharge.php">Make another recharge</a>
      </div>
    </div>
  </div>
</body>
</html>
