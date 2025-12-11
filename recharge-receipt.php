<?php
// recharge-receipt.php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

if (empty($_GET['tid'])) {
    die("Missing transaction ID.");
}

$cid = $_SESSION['cid'];
$tid = $_GET['tid'];  // tx_id passed as ?tid= from success page

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
  mobile_recharges:
    tx_id, cid, from_acc, operator, mobile_number,
    recharge_type, amount, from_balance_before, from_balance_after,
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
    FROM mobile_recharges t
    LEFT JOIN accounts a
        ON t.from_acc = a.AccountNo
    WHERE t.tx_id = ? AND t.cid = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query prepare failed: " . $conn->error);
}

// tx_id (varchar), cid (varchar)
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
require 'fpdf186/fpdf.php'; // adjust path if needed

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// ---------- HEADER ----------
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Mobile Recharge Receipt', 0, 1, 'C');

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, 'Shanto Bank', 0, 1, 'C');
$pdf->Ln(4);

// ---------- BASIC INFO ----------
$pdf->SetFont('Arial', '', 10);

$accountName   = $tx['account_name']   ?? '';
$accountEmail  = $tx['account_email']  ?? '';
$accountPhone  = $tx['account_phone']  ?? '';
$createdAt     = $tx['created_at']     ?? '';

$customerLine = $accountName !== ''
    ? $accountName . " (CID: " . $cid . ")"
    : "Customer ID: " . $cid;

$createdAtStr = $createdAt
    ? date('d M Y, h:i A', strtotime($createdAt))
    : 'N/A';

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

// ---------- RECHARGE DETAILS ----------
$fromAcc      = $tx['from_acc']          ?? 'N/A';
$operator     = $tx['operator']          ?? 'N/A';
$mobileNumber = $tx['mobile_number']     ?? 'N/A';
$rechType     = $tx['recharge_type']     ?? 'N/A';
$amount       = isset($tx['amount']) ? floatval($tx['amount']) : 0.0;

$fromBefore   = isset($tx['from_balance_before']) ? floatval($tx['from_balance_before']) : 0.0;
$fromAfter    = isset($tx['from_balance_after'])  ? floatval($tx['from_balance_after'])  : 0.0;

// No fee column in table → assume 0
$fee          = 0.00;
$totalDebit   = $amount + $fee;
$note         = trim($tx['note'] ?? '');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, "Recharge Details", 0, 1);
$pdf->SetFont('Arial', '', 10);

// helper for key-value rows
function kvRow($pdf, $label, $value) {
    $pdf->Cell(45, 6, $label, 0, 0);
    $pdf->Cell(0, 6, ": " . $value, 0, 1);
}

kvRow($pdf, "From Account",     $fromAcc);
kvRow($pdf, "Mobile Number",    $mobileNumber);
kvRow($pdf, "Operator",         $operator);
kvRow($pdf, "Recharge Type",    $rechType);
kvRow($pdf, "Amount",           number_format($amount, 2) . " BDT");
kvRow($pdf, "Fee",              number_format($fee, 2) . " BDT");
kvRow($pdf, "Total Debited",    number_format($totalDebit, 2) . " BDT");
kvRow($pdf, "Balance Before",   number_format($fromBefore, 2) . " BDT");
kvRow($pdf, "Balance After",    number_format($fromAfter, 2) . " BDT");

if ($note !== "") {
    kvRow($pdf, "Note", $note);
}

$pdf->Ln(10);

// ---------- FOOTER ----------
$pdf->SetFont('Arial', 'I', 9);
$pdf->MultiCell(0, 5,
    "This is a system generated receipt for your mobile recharge.\n" .
    "Please keep it for your records."
);

$filename = "Recharge-Receipt-" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $tid) . ".pdf";
$pdf->Output('D', $filename);
exit;
