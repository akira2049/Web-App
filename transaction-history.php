<?php
session_start();

// User must be logged in
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

// ---------- DATABASE CONNECTION ----------
$host     = "localhost";
$user     = "root";
$password = "";
$database = "my_bank";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$cid = $_SESSION['cid'];

// ---------- FETCH TRANSACTIONS ----------

// Add Money transactions
$addMoney = [];
$sql = "SELECT * FROM add_money_transactions WHERE cid=? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $addMoney[] = $r;
$stmt->close();

// Bank transfers
$bankTransfers = [];
$sql = "SELECT * FROM bank_transfers WHERE cid = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $bankTransfers[] = $r;
}
$stmt->close();

// MFS transfers
$mfsTransfers = [];
$sql = "SELECT * FROM mfs_transfers WHERE cid=? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $mfsTransfers[] = $r;
$stmt->close();

// Bill payments
$billPayments = [];
$sql = "SELECT * FROM bill_payments WHERE cid=? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $billPayments[] = $r;
$stmt->close();

// Mobile recharges (cid is VARCHAR(20) in table, so bind as string) :contentReference[oaicite:1]{index=1}
$mobileRecharges = [];
$sql = "SELECT * FROM mobile_recharges WHERE cid=? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$cidStr = (string)$cid;
$stmt->bind_param("s", $cidStr);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $mobileRecharges[] = $r;
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transaction History</title>

  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    body {
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      margin: 0;
      font-family: 'Inter', sans-serif;
      color: #fff;
    }

    .page-wrap {
      width: min(1100px, 94vw);
      margin: 0 auto;
      padding: 30px 0 80px;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }

    .page-title {
      font-size: 28px;
      font-weight: 800;
      color: #ffffff;
      text-shadow: 0 2px 6px rgba(0,0,0,0.4);
      margin: 0;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(255,255,255,0.12);
      color: #fff;
      font-size: 14px;
      text-decoration: none;
      border: 1px solid rgba(255,255,255,0.4);
      backdrop-filter: blur(6px);
      transition: background 0.15s ease, transform 0.1s ease;
    }
    .back-btn i {
      font-size: 14px;
    }
    .back-btn:hover {
      background: rgba(255,255,255,0.22);
      transform: translateY(-1px);
    }

    .card-section {
      background: #ffffff;
      color: #000;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 26px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.20);
    }

    .card-section h2 {
      margin: 0 0 8px;
      color: #00416A;
      font-size: 20px;
      font-weight: 800;
    }

    .subtitle {
      color: #505050;
      margin-bottom: 12px;
      font-size: 14px;
    }

    .table-scroll {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 800px;
    }

    th, td {
      padding: 8px 10px;
      border-bottom: 1px solid #ddd;
      text-align: center;
      font-size: 13px;
      white-space: nowrap;
    }

    th {
      background: #f1f5f9;
      font-weight: 700;
      color: #333;
    }

    .badge {
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
    }
    .badge-green { background:#dcfce7; color:#166534; }
    .badge-red   { background:#fee2e2; color:#b91c1c; }
    .badge-yellow{ background:#fef9c3; color:#854d0e; }

  </style>
</head>
<body>

<div class="page-wrap">

  <div class="page-header">
    <h1 class="page-title">Transaction History</h1>
    <a href="dashboard.php" class="back-btn">
      <i class="fa-solid fa-arrow-left"></i>
      Back to Dashboard
    </a>
  </div>

  <!-- ADD MONEY -->
  <section class="card-section">
    <h2><i class="fa-solid fa-circle-plus"></i> Add Money</h2>
    <p class="subtitle">Card → Account transactions</p>

    <?php if (empty($addMoney)): ?>
      <p class="subtitle">No add money history found.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Card</th>
              <th>Holder</th>
              <th>Amount</th>
              <th>To Account</th>
              <th>Verified By</th>
              <th>Requested</th>
              <th>Completed</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($addMoney as $t): ?>
            <tr>
              <td><?= $t['id'] ?></td>
              <td><?= htmlspecialchars($t['card_mask']) ?></td>
              <td><?= htmlspecialchars($t['card_holder']) ?></td>
              <td><?= number_format($t['tx_amount'], 2) ?></td>
              <td><?= htmlspecialchars($t['to_account']) ?></td>
              <td><?= htmlspecialchars($t['verification_method']) ?></td>
              <td><?= $t['requested_at'] ?></td>
              <td><?= $t['created_at'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- BANK TRANSFERS -->
  <section class="card-section">
    <h2><i class="fa-solid fa-arrow-right-arrow-left"></i> Bank Transfers</h2>
    <p class="subtitle">Your bank-to-bank transfers</p>

    <?php if (empty($bankTransfers)): ?>
      <p class="subtitle">No bank transfers found.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>CID</th>
              <th>Type</th>
              <th>From</th>
              <th>To</th>
              <th>Amount</th>
              <th>Note</th>
              <th>Status</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($bankTransfers as $t): ?>
            <tr>
              <td><?= $t['cid'] ?></td>
              <td><?= htmlspecialchars($t['transfer_type']) ?></td>
              <td><?= htmlspecialchars($t['from_acc']) ?></td>
              <td><?= htmlspecialchars($t['to_acc']) ?></td>
              <td><?= number_format($t['amount'], 2) ?></td>
              <td><?= htmlspecialchars($t['note']) ?></td>
              <td>
                <?php
                  $status = strtoupper($t['status']);
                  $cls = "badge ";
                  if ($status == "SUCCESS") $cls .= "badge-green";
                  elseif ($status == "FAILED") $cls .= "badge-red";
                  else $cls .= "badge-yellow";
                ?>
                <span class="<?= $cls ?>"><?= $status ?></span>
              </td>
              <td><?= $t['created_at'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- MFS TRANSFERS -->
  <section class="card-section">
    <h2><i class="fa-solid fa-mobile-screen"></i> MFS Transfers</h2>
    <p class="subtitle">bKash, Nagad, Rocket</p>

    <?php if (empty($mfsTransfers)): ?>
      <p class="subtitle">No MFS transfers found.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Tx ID</th>
              <th>From</th>
              <th>Wallet</th>
              <th>Number</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($mfsTransfers as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['tx_id']) ?></td>
              <td><?= htmlspecialchars($t['from_acc']) ?></td>
              <td><?= htmlspecialchars($t['wallet_type']) ?></td>
              <td><?= htmlspecialchars($t['wallet_number']) ?></td>
              <td><?= number_format($t['amount'], 2) ?></td>
              <td>
                <?php
                  $status = strtoupper($t['status']);
                  $cls = "badge ";
                  if ($status == "SUCCESS") $cls .= "badge-green";
                  elseif ($status == "FAILED") $cls .= "badge-red";
                  else $cls .= "badge-yellow";
                ?>
                <span class="<?= $cls ?>"><?= $status ?></span>
              </td>
              <td><?= $t['created_at'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- MOBILE RECHARGES -->
  <section class="card-section">
    <h2><i class="fa-solid fa-signal"></i> Mobile Recharges</h2>
    <p class="subtitle">All your mobile top-ups</p>

    <?php if (empty($mobileRecharges)): ?>
      <p class="subtitle">No mobile recharge history found.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Tx ID</th>
              <th>From Account</th>
              <th>Operator</th>
              <th>Mobile Number</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Balance Before</th>
              <th>Balance After</th>
              <th>Status</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($mobileRecharges as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['tx_id']) ?></td>
              <td><?= htmlspecialchars($t['from_acc']) ?></td>
              <td><?= htmlspecialchars($t['operator']) ?></td>
              <td><?= htmlspecialchars($t['mobile_number']) ?></td>
              <td><?= htmlspecialchars($t['recharge_type']) ?></td>
              <td><?= number_format($t['amount'], 2) ?></td>
              <td><?= number_format($t['from_balance_before'], 2) ?></td>
              <td><?= number_format($t['from_balance_after'], 2) ?></td>
              <td>
                <?php
                  $status = strtoupper($t['status']);
                  $cls = "badge ";
                  if ($status == "SUCCESS") $cls .= "badge-green";
                  elseif ($status == "FAILED") $cls .= "badge-red";
                  else $cls .= "badge-yellow";
                ?>
                <span class="<?= $cls ?>"><?= $status ?></span>
              </td>
              <td><?= $t['created_at'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- BILL PAYMENTS -->
  <section class="card-section">
    <h2><i class="fa-solid fa-file-invoice-dollar"></i> Bill Payments</h2>
    <p class="subtitle">Utility & other bills</p>

    <?php if (empty($billPayments)): ?>
      <p class="subtitle">No bill payment history found.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Tx ID</th>
              <th>Biller</th>
              <th>Customer Ref ID</th>
              <th>From Account</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($billPayments as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['tx_id'] ?? ($t['id'] ?? '')) ?></td>
              <td><?= htmlspecialchars($t['biller_name']) ?></td>
              <td><?= htmlspecialchars($t['biller_id']) ?></td>
              <td><?= htmlspecialchars($t['from_acc'] ?? $t['account_no'] ?? '') ?></td>
              <td><?= number_format($t['amount'], 2) ?></td>
              <td>
                <?php
                  $status = strtoupper($t['status']);
                  $cls = "badge ";
                  if ($status == "SUCCESS") $cls .= "badge-green";
                  elseif ($status == "FAILED") $cls .= "badge-red";
                  else $cls .= "badge-yellow";
                ?>
                <span class="<?= $cls ?>"><?= $status ?></span>
              </td>
              <td><?= $t['created_at'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</div>

</body>
</html>
<?php $conn->close(); ?>
