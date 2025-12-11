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
$conn = new mysqli("localhost", "root", "", "my_bank");
if ($conn->connect_error) die("DB failed: " . $conn->connect_error);

// ---------- LOAD ACCOUNT DATA ----------
$accParam = $_GET['acc'] ?? '';

if ($accParam !== "") {
    $sql = "SELECT AccountNo, account_name, account_type, Balance 
            FROM accounts 
            WHERE CustomerID = ? AND AccountNo = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $cid, $accParam);
} else {
    $sql = "SELECT AccountNo, account_name, account_type, Balance 
            FROM accounts 
            WHERE CustomerID = ?
            ORDER BY AccountNo
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cid);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $accountNo   = $row['AccountNo'];
    $accountName = $row['account_name'];
    $accountType = $row['account_type'] ?? "Account";
    $balance     = $row['Balance'];
} else {
    $accountNo = "N/A";
    $accountName = "Unknown";
    $accountType = "Account";
    $balance = 0;
}

$stmt->close();
$conn->close();

// Display info
$issueDate  = date("F j, Y");
$branchName = "Main Branch, Astra Bank";
$pdfUrl     = "certificate-pdf.php?acc=" . urlencode($accountNo);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Account Certificate</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="transfer.css">

<style>
:root { --primary:#00416A; }

/* Dashboard theme background */
body {
    margin: 0;
    font-family: 'Inter', system-ui, Arial;
    background: linear-gradient(135deg, #00416A, #E4E5E6);
    min-height: 100vh;
    padding: 24px 12px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

.app {
    width: 100%;
    max-width: 900px;
}

/* Card theme */
.card {
    background:#ffffff;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
}

.h1 {
    color:#ffffff;
    text-shadow:0 1px 2px rgba(0,0,0,0.25);
}

/* Certificate styles */
.cert-card-inner {
    border: 3px solid #f7e992;
    border-radius: 16px;
    padding: 26px;
}

.cert-title {
    font-size: 22px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.cert-subtitle {
    font-size: 13px;
    text-transform: uppercase;
    color: #666;
    letter-spacing: 0.15em;
}

.cert-body {
    margin-top: 18px;
    font-size: 15px;
    line-height: 1.6;
}

.cert-kv {
    margin-top: 14px;
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.cert-kv th {
    text-align: left;
    padding: 6px 0;
    width: 40%;
    color: #666;
}

.cert-kv td {
    padding: 6px 0;
    font-weight: 600;
}

.cert-footer {
    margin-top: 24px;
    display: flex;
    justify-content: space-between;
}

.cert-sign-line {
    margin-top: 30px;
    width: 180px;
    border-top: 1px solid #777;
}

.cert-seal {
    width: 100px;
    height: 100px;
    border: 2px dashed #999;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
}

/* Action buttons */
.cert-actions {
    margin-top: 20px;
    display: flex;
    gap: 12px;
}

.btn-ghost {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary);
    padding: 8px 14px;
    border-radius: 999px;
    cursor: pointer;
}

.btn-link {
    background: var(--primary);
    color: white;
    padding: 8px 14px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 700;
}

/* Print mode */
@media print {
    body {
        background:white !important;
    }
    .topbar, .cert-actions {
        display:none !important;
    }
    .card {
        box-shadow:none !important;
    }
    .cert-card-inner {
        border-color:#000;
    }
}
</style>
</head>

<body>
<div class="app">

    <div class="topbar">
        <a class="linkish" href="dashboard.php">← Back</a>
        <span style="margin-left:auto;font-weight:600;">Account Certificate</span>
    </div>

    <h1 class="h1">Account Certificate</h1>

    <div class="card">
        <div class="section cert-card-inner">

            <div style="text-align:center;">
                <div class="cert-title">Astra Bank</div>
                <div class="cert-subtitle">Official Account Confirmation</div>
                <div style="margin-top:6px;">
                    Issue Date: <strong><?= htmlspecialchars($issueDate) ?></strong>
                </div>
            </div>

            <div class="cert-body">

                <p>
                    This is to certify that <strong><?= htmlspecialchars($accountName) ?></strong>,
                    Customer ID <strong><?= htmlspecialchars($cid) ?></strong>,
                    maintains a <strong><?= htmlspecialchars($accountType) ?></strong> with
                    <strong>Astra Bank</strong>.
                </p>

                <p>The following details are verified as of the certificate issue date:</p>

                <table class="cert-kv">
                    <tr><th>Account Holder</th><td><?= htmlspecialchars($accountName) ?></td></tr>
                    <tr><th>CID</th><td><?= htmlspecialchars($cid) ?></td></tr>
                    <tr><th>Account Number</th><td><?= htmlspecialchars($accountNo) ?></td></tr>
                    <tr><th>Type</th><td><?= htmlspecialchars($accountType) ?></td></tr>
                    <tr><th>Balance</th><td><?= number_format((float)$balance,2) ?> BDT</td></tr>
                    <tr><th>Branch</th><td><?= htmlspecialchars($branchName) ?></td></tr>
                </table>

                <div class="cert-footer">
                    <div>
                        <div class="cert-sign-line"></div>
                        <div>Authorized Officer</div>
                        <div>Astra Bank</div>
                    </div>

                    <div class="cert-seal">Astra Bank<br>Verified</div>
                </div>

            </div>

            <div class="cert-actions">
                <button class="btn-ghost" onclick="window.history.back()">Back</button>
                <button class="btn" onclick="window.print()">Print</button>
                <a class="btn-link" href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank">
                    Download
                </a>
            </div>

        </div>
    </div>

</div>
</body>
</html>
