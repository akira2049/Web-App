<?php
// certificate.php
session_start();

// Must be logged in
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = (string)$_SESSION['cid'];

// ---------- DB CONNECTION ----------
$host     = "localhost";
$user     = "root";
$password = "";
$database = "my_bank";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ---------- LOAD ACCOUNT DATA ----------
//
// We expect ?acc=ACCOUNTNO from dashboard.
// If not provided, we just pick the first account of this CID.

$accountNoParam = $_GET['acc'] ?? '';

if ($accountNoParam !== '') {
    $sql = "SELECT AccountNo, account_name, account_type, Balance 
            FROM accounts 
            WHERE CustomerID = ? AND AccountNo = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("SQL prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $cid, $accountNoParam);
} else {
    // No account passed → take first account for this customer
    $sql = "SELECT AccountNo, account_name, account_type, Balance 
            FROM accounts 
            WHERE CustomerID = ?
            ORDER BY AccountNo
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("SQL prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $cid);
}

$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows === 1) {
    $row = $res->fetch_assoc();
    $accountNo   = $row['AccountNo'];
    $accountName = $row['account_name'];   // holder name / account name
    $accountType = $row['account_type'] ?? 'Account';
    $balance     = $row['Balance'];
} else {
    // No account found for this CID (or for the given AccountNo)
    $accountNo   = "N/A";
    $accountName = "Unknown";
    $accountType = "Account";
    $balance     = 0;
}

$stmt->close();
$conn->close();

// Other certificate info
$issueDate  = date('F j, Y');
$branchName = "Main Branch, My Bank"; // change if you have branches

// URL for PDF download (you will create certificate-pdf.php)
$downloadUrl = "certificate-pdf.php?acc=" . urlencode($accountNo);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Account Certificate</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Dash / app style -->
  <link rel="stylesheet" href="transfer.css">

  <style>
    /* Extra certificate styling on top of transfer.css */

    .cert-card-inner {
      border: 3px solid #f7e992;
      border-radius: 16px;
      padding: 24px 24px 28px;
    }
    .cert-heading {
      text-align: center;
      margin-bottom: 16px;
    }
    .cert-title {
      font-size: 22px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .12em;
    }
    .cert-subtitle {
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: .16em;
      margin-top: 4px;
      color: #666;
    }
    .cert-body {
      margin-top: 18px;
      font-size: 15px;
      line-height: 1.6;
    }
    .cert-kv {
      margin-top: 16px;
      font-size: 14px;
      width: 100%;
      border-collapse: collapse;
    }
    .cert-kv th,
    .cert-kv td {
      padding: 6px 2px;
    }
    .cert-kv th {
      text-align: left;
      color: #666;
      width: 40%;
      font-weight: 600;
    }
    .cert-kv td {
      font-weight: 600;
    }
    .cert-footer {
      margin-top: 22px;
      display: flex;
      justify-content: space-between;
      gap: 16px;
      font-size: 13px;
    }
    .cert-sign-line {
      margin-top: 32px;
      width: 180px;
      border-top: 1px solid #999;
    }
    .cert-seal {
      border: 2px dashed #bbb;
      border-radius: 50%;
      width: 100px;
      height: 100px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      text-transform: uppercase;
      text-align: center;
      font-weight: 600;
    }
    .cert-actions {
      margin-top: 18px;
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }
    .btn-ghost {
      background: transparent;
      border: 1px solid var(--primary);
      color: var(--primary);
      border-radius: 999px;
      padding: 8px 14px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-link {
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 8px 14px;
      font-size: 14px;
      font-weight: 600;
      border: none;
    }

    @media print {
      body {
        background: #fff;
      }
      .topbar,
      .cert-actions {
        display: none !important;
      }
      .app {
        padding-top: 0;
      }
      .card {
        box-shadow: none !important;
      }
      .cert-card-inner {
        border-color: #000;
      }
    }
  </style>
</head>
<body>
<div class="app">

  <!-- Top bar like other dashboard pages -->
  <div class="topbar">
    <a class="linkish" href="dashboard.php">← Back to Dashboard</a>
    <div style="margin-left:auto;font-weight:600;">Account Certificate</div>
  </div>

  <div class="h1">Account Opening Certificate</div>

  <div class="card">
    <div class="section cert-card-inner">

      <div class="cert-heading">
        <div class="cert-title">My Bank</div>
        <div class="cert-subtitle">Official account confirmation</div>
        <div style="margin-top:6px;font-size:13px;">
          Issue Date: <strong><?= htmlspecialchars($issueDate) ?></strong>
        </div>
      </div>

      <div class="cert-body">
        <p>
          This is to certify that
          <strong><?= htmlspecialchars($accountName) ?></strong>,
          Customer ID <strong><?= htmlspecialchars($cid) ?></strong>,
          maintains a <strong><?= htmlspecialchars($accountType) ?></strong>
          with <strong>My Bank</strong> at
          <strong><?= htmlspecialchars($branchName) ?></strong>.
        </p>

        <p>
          The account details and current balance as of the issue date are given below:
        </p>

        <table class="cert-kv">
          <tr>
            <th>Account Holder Name</th>
            <td><?= htmlspecialchars($accountName) ?></td>
          </tr>
          <tr>
            <th>Customer ID (CID)</th>
            <td><?= htmlspecialchars($cid) ?></td>
          </tr>
          <tr>
            <th>Account Number</th>
            <td><?= htmlspecialchars($accountNo) ?></td>
          </tr>
          <tr>
            <th>Account Type</th>
            <td><?= htmlspecialchars($accountType) ?></td>
          </tr>
          <tr>
            <th>Current Balance</th>
            <td><?= number_format((float)$balance, 2) ?> BDT</td>
          </tr>
          <tr>
            <th>Branch</th>
            <td><?= htmlspecialchars($branchName) ?></td>
          </tr>
        </table>

        <div class="cert-footer">
          <div>
            <div class="cert-sign-line"></div>
            <div>Authorized Officer</div>
            <div>My Bank</div>
          </div>
          <div style="text-align:right;">
            <div class="cert-seal">
              My Bank<br>Verified
            </div>
          </div>
        </div>
      </div>

      <div class="cert-actions">
        <button type="button" class="btn-ghost" onclick="window.history.back()">Back</button>
        <button type="button" class="btn" onclick="window.print()">Print</button>

        <!-- 🔽 Download link for PDF certificate -->
        <a href="<?= htmlspecialchars($downloadUrl) ?>"
           class="btn btn-link"
           target="_blank">
          Download Certificate
        </a>
      </div>
    </div>
  </div>

</div>
</body>
</html>
