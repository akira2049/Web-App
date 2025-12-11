<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

if (empty($_GET['tid'])) {
    die("Missing transaction ID.");
}

$cid = $_SESSION['cid'];
$tid = $_GET['tid'];  // we pass ?tid=... from success page

// -------------------------
// DB CONNECTION
// -------------------------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/*
  mfs_transfers:
    tx_id, cid, from_acc, wallet_type, wallet_number,
    amount, from_balance_before, from_balance_after,
    note, status, created_at

  accounts:
    AccountNo, account_name, Email, Phone, ...
*/

$sql = "
    SELECT 
        t.*,
        a.account_name,
        a.Email  AS account_email,
        a.Phone  AS account_phone
    FROM mfs_transfers t
    LEFT JOIN accounts a
        ON t.from_acc = a.AccountNo
    WHERE t.tx_id = ? AND t.cid = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query prepare failed: " . $conn->error);
}

// tx_id (varchar), cid (varchar in mfs_transfers)
$stmt->bind_param("ss", $tid, $cid);
$stmt->execute();
$res = $stmt->get_result();
$tx  = $res->fetch_assoc();
$stmt->close();
$conn->close();

if (!$tx) {
    die("Transaction not found.");
}

// -------------------------
// FPDF SETUP
// -------------------------
require 'fpdf186/fpdf.php'; // adjust path to your FPDF file if needed

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// ---------- HEADER ----------
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'MFS Transfer Receipt', 0, 1, 'C');

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, 'My Bank', 0, 1, 'C');
$pdf->Ln(4);

// ---------- BASIC INFO ----------
$pdf->SetFont('Arial', '', 10);

$accountName   = $tx['account_name'] ?? '';
$accountEmail  = $tx['account_email'] ?? '';
$accountPhone  = $tx['account_phone'] ?? '';

$customerLine = $accountName !== ''
    ? $accountName . " (CID: " . $cid . ")"
    : "Customer ID: " . $cid;

$createdAt    = $tx['created_at'] ?? '';
$createdAtStr = $createdAt ? date('d M Y, h:i A', strtotime($createdAt)) : 'N/A';

$pdf->Cell(0, 6, "Customer: " . $customerLine, 0, 1);
if ($accountEmail !== '') {
    $pdf->Cell(0, 6, "Email: " . $accountEmail, 0, 1);
}
if ($accountPhone !== '') {
    $pdf->Cell(0, 6, "Phone: " . $accountPhone, 0, 1);
}
$pdf->Cell(0, 6, "Transaction ID: " . $tx['tx_id'], 0, 1);
$pdf->Cell(0, 6, "Date & Time: " . $createdAtStr, 0, 1);
$pdf->Ln(4);

// ---------- TRANSFER DETAILS (TABLE STYLE) ----------
$fromAcc    = $tx['from_acc']        ?? 'N/A';
$walletType = $tx['wallet_type']     ?? 'N/A';
$walletNo   = $tx['wallet_number']   ?? 'N/A';
$amount     = isset($tx['amount']) ? floatval($tx['amount']) : 0.0;

$fromBefore = isset($tx['from_balance_before']) ? floatval($tx['from_balance_before']) : 0.0;
$fromAfter  = isset($tx['from_balance_after'])  ? floatval($tx['from_balance_after'])  : 0.0;

// No fee column in table, so default to 0
$fee        = 0.00;
$totalDebit = $amount + $fee;
$note       = trim($tx['note'] ?? '');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, "Transfer Details", 0, 1);
$pdf->Ln(1);

/* Table column widths */
$col1 = 60;   // Field
$col2 = 130;  // Details

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell($col1, 8, 'Field',   1, 0, 'L', true);
$pdf->Cell($col2, 8, 'Details', 1, 1, 'L', true);

// Table body
$pdf->SetFont('Arial', '', 10);

function mfsTableRow($pdf, $label, $value, $col1, $col2) {
    $pdf->Cell($col1, 8, $label, 1, 0, 'L');
    $pdf->Cell($col2, 8, $value, 1, 1, 'L');
}

mfsTableRow($pdf, "From Account",    $fromAcc,                                    $col1, $col2);
mfsTableRow($pdf, "Wallet Type",     $walletType,                                 $col1, $col2);
mfsTableRow($pdf, "Wallet Number",   $walletNo,                                   $col1, $col2);
mfsTableRow($pdf, "Amount",          number_format($amount, 2) . " BDT",         $col1, $col2);
mfsTableRow($pdf, "Fee",             number_format($fee, 2) . " BDT",            $col1, $col2);
mfsTableRow($pdf, "Total Debited",   number_format($totalDebit, 2) . " BDT",     $col1, $col2);
mfsTableRow($pdf, "Balance Before",  number_format($fromBefore, 2) . " BDT",     $col1, $col2);
mfsTableRow($pdf, "Balance After",   number_format($fromAfter, 2) . " BDT",      $col1, $col2);

if ($note !== "") {
    mfsTableRow($pdf, "Note", $note, $col1, $col2);
}

$pdf->Ln(10);

// ---------- FOOTER ----------
$pdf->SetFont('Arial', 'I', 9);
$pdf->MultiCell(0, 5,
    "This is a system generated receipt for your mobile financial service (MFS) transfer.\n" .
    "Please keep it for your records."
);

$filename = "MFS-Receipt-" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $tid) . ".pdf";
$pdf->Output('D', $filename);
exit;
