<?php
session_start();

// Must be logged in
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];

// ---- DB CONNECTION ----
$host = "localhost";
$db   = "my_bank";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/* -------------------------------------------------
   1) Load customer's accounts (by phone mapping)
      user.cid -> user.phone -> accounts.Phone
--------------------------------------------------*/
$accounts = [];

$sqlAcc = "
    SELECT a.AccountNo, a.Balance, a.currency
    FROM accounts a
    JOIN user u ON u.phone = a.Phone
    WHERE u.cid = ?
    ORDER BY a.AccountNo ASC
";
$stmtAcc = $conn->prepare($sqlAcc);
$stmtAcc->bind_param("i", $cid);
$stmtAcc->execute();
$resAcc = $stmtAcc->get_result();
while ($row = $resAcc->fetch_assoc()) {
    $accounts[] = $row;
}
$stmtAcc->close();

if (empty($accounts)) {
    $conn->close();
    die("No accounts linked to your profile. Please contact bank support.");
}

/* -------------------------------------------------
   2) Read selected account + date range (GET)
--------------------------------------------------*/
$selectedAcc = isset($_GET['acc']) ? trim($_GET['acc']) : $accounts[0]['AccountNo'];

// Validate selectedAcc belongs to this user
$valid = false;
$currentBalance = 0;
$currentCurrency = 'BDT';

foreach ($accounts as $a) {
    if ($a['AccountNo'] === $selectedAcc) {
        $valid = true;
        $currentBalance = $a['Balance'];
        $currentCurrency = $a['currency'];
        break;
    }
}
if (!$valid) {
    // fallback to first
    $selectedAcc = $accounts[0]['AccountNo'];
    $currentBalance = $accounts[0]['Balance'];
    $currentCurrency = $accounts[0]['currency'];
}

// Default dates: last 30 days
$today = date("Y-m-d");
$defaultFrom = date("Y-m-d", strtotime("-30 days"));

$fromDate = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : $defaultFrom;
$toDate   = isset($_GET['to'])   && $_GET['to']   !== '' ? $_GET['to']   : $today;

// Basic date sanitizing (keep Y-m-d)
function clean_date($d) {
    $ts = strtotime($d);
    if ($ts === false) {
        return date("Y-m-d");
    }
    return date("Y-m-d", $ts);
}
$fromDate = clean_date($fromDate);
$toDate   = clean_date($toDate);

/* -------------------------------------------------
   3) Collect statement rows from all tables
      Unified fields:
      - time
      - source
      - description
      - direction (DEBIT / CREDIT)
      - amount
      - balance_after (if available)
--------------------------------------------------*/
$rows = [];

/* ----- Add Money (CREDIT to this account) ----- */
$sql = "
    SELECT created_at, tx_amount, card_mask, card_holder, verification_method
    FROM add_money_transactions
    WHERE cid = ? 
      AND to_account = ?
      AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $cid, $selectedAcc, $fromDate, $toDate);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'time'          => $r['created_at'],
        'source'        => 'Add Money',
        'description'   => 'Add money from card ' . $r['card_mask'] . ' (' . $r['card_holder'] . ') via ' . $r['verification_method'],
        'direction'     => 'CREDIT',
        'amount'        => (float)$r['tx_amount'],
        'balance_after' => null
    ];
}
$stmt->close();

/* ----- Bank Transfers: Debits (From this account) ----- */
$sql = "
    SELECT created_at, amount, to_acc, note, from_balance_after, transfer_type
    FROM bank_transfers
    WHERE cid = ?
      AND from_acc = ?
      AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $cid, $selectedAcc, $fromDate, $toDate);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'time'          => $r['created_at'],
        'source'        => 'Bank Transfer',
        'description'   => $r['transfer_type'] . ' to ' . $r['to_acc'] . ($r['note'] ? " — " . $r['note'] : ''),
        'direction'     => 'DEBIT',
        'amount'        => (float)$r['amount'],
        'balance_after' => (float)$r['from_balance_after']
    ];
}
$stmt->close();

/* ----- Bank Transfers: Credits (To this account, any sender) ----- */
$sql = "
    SELECT created_at, amount, from_acc, note, to_balance_after, transfer_type
    FROM bank_transfers
    WHERE to_acc = ?
      AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $selectedAcc, $fromDate, $toDate);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'time'          => $r['created_at'],
        'source'        => 'Bank Transfer',
        'description'   => $r['transfer_type'] . ' from ' . $r['from_acc'] . ($r['note'] ? " — " . $r['note'] : ''),
        'direction'     => 'CREDIT',
        'amount'        => (float)$r['amount'],
        'balance_after' => (float)$r['to_balance_after']
    ];
}
$stmt->close();

/* ----- MFS Transfers (DEBIT) ----- */
$sql = "
    SELECT created_at, amount, wallet_type, wallet_number, from_balance_after, note, status
    FROM mfs_transfers
    WHERE cid = ?
      AND from_acc = ?
      AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sql);
// cid is varchar(20) in schema, but numeric value is fine; bind as string for clarity
$cidStr = (string)$cid;
$stmt->bind_param("ssss", $cidStr, $selectedAcc, $fromDate, $toDate);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'time'          => $r['created_at'],
        'source'        => 'MFS Transfer',
        'description'   => $r['wallet_type'] . ' ' . $r['wallet_number'] .
                           ($r['note'] ? " — " . $r['note'] : '') .
                           ' [' . strtoupper($r['status']) . ']',
        'direction'     => 'DEBIT',
        'amount'        => (float)$r['amount'],
        'balance_after' => (float)$r['from_balance_after']
    ];
}
$stmt->close();

/* ----- Mobile Recharges (DEBIT) ----- */
$sql = "
    SELECT created_at, amount, operator, mobile_number, from_balance_after, note, status
    FROM mobile_recharges
    WHERE cid = ?
      AND from_acc = ?
      AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $cidStr, $selectedAcc, $fromDate, $toDate);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'time'          => $r['created_at'],
        'source'        => 'Mobile Recharge',
        'description'   => $r['operator'] . ' ' . $r['mobile_number'] .
                           ($r['note'] ? " — " . $r['note'] : '') . 
                           ' [' . strtoupper($r['status']) . ']',
        'direction'     => 'DEBIT',
        'amount'        => (float)$r['amount'],
        'balance_after' => (float)$r['from_balance_after']
    ];
}
$stmt->close();

/* ----- Bill Payments (DEBIT) ----- */
$sql = "
    SELECT created_at, amount, biller_name, biller_id, from_balance_after, note, status
    FROM bill_payments
    WHERE cid = ?
      AND from_acc = ?
      AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $cidStr, $selectedAcc, $fromDate, $toDate);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'time'          => $r['created_at'],
        'source'        => 'Bill Payment',
        'description'   => $r['biller_name'] . ' (ID: ' . $r['biller_id'] . ')' .
                           ($r['note'] ? " — " . $r['note'] : '') .
                           ' [' . strtoupper($r['status']) . ']',
        'direction'     => 'DEBIT',
        'amount'        => (float)$r['amount'],
        'balance_after' => (float)$r['from_balance_after']
    ];
}
$stmt->close();

$conn->close();

/* -------------------------------------------------
   4) Sort all rows by time DESC (newest first)
--------------------------------------------------*/
usort($rows, function($a, $b) {
    if ($a['time'] == $b['time']) return 0;
    return ($a['time'] < $b['time']) ? 1 : -1; // DESC
});

/* -------------------------------------------------
   Helper: shorten description to first 4 words
--------------------------------------------------*/
function short_desc($text, $wordLimit = 4) {
    $text = trim($text);
    if ($text === '') return '';
    $words = preg_split('/\s+/', $text);
    if (count($words) <= $wordLimit) {
        return $text;
    }
    $short = array_slice($words, 0, $wordLimit);
    return implode(' ', $short) . '...';
}

/* -------------------------------------------------
   5) If "download=1" -> generate PDF and exit
--------------------------------------------------*/
if (isset($_GET['download']) && $_GET['download'] === '1') {
    require 'fpdf186/fpdf.php'; // adjust path if needed

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Account Statement', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 11);
    $pdf->Ln(2);
    $pdf->Cell(0, 6, 'Account: ' . $selectedAcc, 0, 1);
    $pdf->Cell(0, 6, 'Currency: ' . $currentCurrency, 0, 1);
    $pdf->Cell(0, 6, 'Period: ' . $fromDate . ' to ' . $toDate, 0, 1);
    $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1);
    $pdf->Ln(4);

    // Table header
    $pdf->SetFont('Arial', 'B', 9);
    $wTime    = 35;
    $wSource  = 25;
    $wDesc    = 70;
    $wType    = 15;
    $wAmount  = 22;
    $wBalance = 23;

    $pdf->Cell($wTime,    7, 'Date/Time',      1, 0, 'L');
    $pdf->Cell($wSource,  7, 'Source',         1, 0, 'L');
    $pdf->Cell($wDesc,    7, 'Description',    1, 0, 'L');
    $pdf->Cell($wType,    7, 'Type',           1, 0, 'L');
    $pdf->Cell($wAmount,  7, 'Amount',         1, 0, 'R');
    $pdf->Cell($wBalance, 7, 'Balance After',  1, 1, 'R');

    $pdf->SetFont('Arial', '', 8);

    if (empty($rows)) {
        $pdf->Cell(0, 8, 'No transactions for this period.', 1, 1, 'C');
    } else {
        foreach ($rows as $r) {
            $time  = $r['time'];
            $src   = $r['source'];
            // short 3–4 words in PDF too
            $desc  = short_desc($r['description'], 4);
            $type  = $r['direction'];
            $amt   = number_format($r['amount'], 2);
            $bal   = ($r['balance_after'] === null) ? '-' : number_format($r['balance_after'], 2);

            $pdf->Cell($wTime,    6, $time,  1, 0, 'L');
            $pdf->Cell($wSource,  6, $src,   1, 0, 'L');
            $pdf->Cell($wDesc,    6, $desc,  1, 0, 'L');
            $pdf->Cell($wType,    6, $type,  1, 0, 'L');
            $pdf->Cell($wAmount,  6, $amt,   1, 0, 'R');
            $pdf->Cell($wBalance, 6, $bal,   1, 1, 'R');
        }
    }

    $pdf->Output('I', 'statement_' . $selectedAcc . '_' . $fromDate . '_to_' . $toDate . '.pdf');
    exit;
}

/* -------------------------------------------------
   6) Build download URL for HTML button
--------------------------------------------------*/
$downloadUrl = $_SERVER['PHP_SELF']
    . '?acc=' . urlencode($selectedAcc)
    . '&from=' . urlencode($fromDate)
    . '&to='   . urlencode($toDate)
    . '&download=1';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Statement</title>

  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    body{
      margin:0;
      font-family:'Inter',sans-serif;
      background:linear-gradient(135deg,#00416A,#E4E5E6);
      color:#fff;
    }
    .page-wrap{
      width:min(1100px,94vw);
      margin:0 auto;
      padding:24px 0 80px;
    }
    .page-header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:18px;
      gap:12px;
      flex-wrap:wrap;
    }
    .page-title{
      font-size:26px;
      font-weight:800;
      text-shadow:0 2px 6px rgba(0,0,0,0.4);
    }
    .back-btn{
      text-decoration:none;
      padding:8px 14px;
      border-radius:999px;
      background:rgba(255,255,255,0.1);
      color:#fff;
      font-size:14px;
      font-weight:600;
      display:inline-flex;
      align-items:center;
      gap:6px;
      border:1px solid rgba(255,255,255,0.3);
    }
    .summary-card{
      background:#ffffff;
      border-radius:14px;
      padding:16px 18px;
      margin-bottom:18px;
      color:#111827;
      box-shadow:0 4px 14px rgba(0,0,0,0.2);
      display:flex;
      justify-content:space-between;
      align-items:center;
      flex-wrap:wrap;
      gap:10px;
    }
    .summary-main{
      font-weight:700;
      font-size:16px;
    }
    .summary-bal{
      font-size:18px;
      font-weight:800;
      color:#0f766e;
    }
    .filter-form{
      background:#ffffff;
      border-radius:14px;
      padding:14px 16px;
      margin-bottom:10px;
      box-shadow:0 4px 14px rgba(0,0,0,0.16);
      color:#111827;
      display:flex;
      flex-wrap:wrap;
      gap:12px;
      align-items:flex-end;
    }
    .filter-group{
      display:flex;
      flex-direction:column;
      gap:4px;
      min-width:170px;
      flex:1 1 170px;
    }
    .filter-label{
      font-size:12px;
      font-weight:700;
      color:#6b7280;
    }
    .filter-input,
    .filter-select{
      border-radius:10px;
      border:1px solid #d1d5db;
      padding:8px 10px;
      font-size:14px;
      outline:none;
    }
    .filter-btn{
      border:none;
      border-radius:999px;
      padding:10px 18px;
      font-size:14px;
      font-weight:700;
      background:#00416A;
      color:#fff;
      cursor:pointer;
      align-self:flex-start;
      display:inline-flex;
      align-items:center;
      gap:6px;
      text-decoration:none;
    }
    .table-card{
      background:#ffffff;
      border-radius:14px;
      padding:16px 16px 20px;
      box-shadow:0 4px 14px rgba(0,0,0,0.16);
      color:#111827;
    }
    .table-title{
      font-size:16px;
      font-weight:800;
      margin-bottom:10px;
      color:#00416A;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
    }
    .table-scroll{
      overflow-x:auto;
    }
    table{
      width:100%;
      border-collapse:collapse;
      min-width:820px;
    }
    th,td{
      padding:8px 10px;
      border-bottom:1px solid #e5e7eb;
      text-align:left;
      font-size:13px;
      white-space:nowrap;
    }
    th{
      background:#f1f5f9;
      font-weight:700;
      color:#374151;
    }
    .dir-debit{color:#b91c1c;font-weight:700;}
    .dir-credit{color:#166534;font-weight:700;}
    .amount-cell{font-weight:700;}
    .no-rows{
      margin-top:6px;
      font-size:14px;
      color:#6b7280;
    }
  </style>
</head>
<body>
<div class="page-wrap">

  <div class="page-header">
    <div class="page-title">
      <i class="fa-solid fa-calendar-days"></i> Account Statement
    </div>
    <a class="back-btn" href="dashboard.php">
      <i class="fa-solid fa-arrow-left"></i>
      Back to Dashboard
    </a>
  </div>

  <div class="summary-card">
    <div>
      <div class="summary-main">
        Account: <?php echo htmlspecialchars($selectedAcc); ?>
      </div>
      <div style="font-size:13px;color:#6b7280;">
        Currency: <?php echo htmlspecialchars($currentCurrency); ?>
      </div>
    </div>
    <div>
      <div style="font-size:13px;color:#6b7280;">Current Balance</div>
      <div class="summary-bal">
        <?php echo htmlspecialchars($currentCurrency); ?>
        <?php echo number_format($currentBalance, 2); ?>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="filter-form">
    <div class="filter-group">
      <label class="filter-label">Account</label>
      <select name="acc" class="filter-select">
        <?php foreach ($accounts as $a): ?>
          <option value="<?php echo htmlspecialchars($a['AccountNo']); ?>"
            <?php if ($a['AccountNo'] === $selectedAcc) echo 'selected'; ?>>
            <?php echo htmlspecialchars($a['AccountNo']); ?> (<?php echo htmlspecialchars($a['currency']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label class="filter-label">From Date</label>
      <input class="filter-input" type="date" name="from"
             value="<?php echo htmlspecialchars($fromDate); ?>">
    </div>

    <div class="filter-group">
      <label class="filter-label">To Date</label>
      <input class="filter-input" type="date" name="to"
             value="<?php echo htmlspecialchars($toDate); ?>">
    </div>

    <button type="submit" class="filter-btn">
      <i class="fa-solid fa-sliders"></i>
      Apply
    </button>
  </form>

  <!-- Statement Table + Download -->
  <div class="table-card">
    <div class="table-title">
      <span>Transactions (<?php echo htmlspecialchars($fromDate); ?> to <?php echo htmlspecialchars($toDate); ?>)</span>
      <a href="<?php echo htmlspecialchars($downloadUrl); ?>" class="filter-btn">
        <i class="fa-solid fa-download"></i>
        Download PDF
      </a>
    </div>

    <?php if (empty($rows)): ?>
      <div class="no-rows">No transactions found for this period.</div>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>Source</th>
              <th>Description</th>
              <th>Type</th>
              <th>Amount (<?php echo htmlspecialchars($currentCurrency); ?>)</th>
              <th>Balance After</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['time']); ?></td>
              <td><?php echo htmlspecialchars($r['source']); ?></td>
              <td><?php echo htmlspecialchars(short_desc($r['description'], 4)); ?></td>
              <td class="<?php echo $r['direction'] === 'DEBIT' ? 'dir-debit' : 'dir-credit'; ?>">
                <?php echo htmlspecialchars($r['direction']); ?>
              </td>
              <td class="amount-cell">
                <?php echo number_format($r['amount'], 2); ?>
              </td>
              <td>
                <?php
                  if ($r['balance_after'] === null) {
                      echo '-';
                  } else {
                      echo number_format($r['balance_after'], 2);
                  }
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
