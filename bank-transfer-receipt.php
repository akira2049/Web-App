<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$txId = $_GET['tx'] ?? '';
if ($txId == '') die("Transaction ID missing.");

/* ---------- DB CONNECTION ---------- */
$host="localhost"; $user="root"; $password=""; $database="my_bank";
$conn = new mysqli($host,$user,$password,$database);
if ($conn->connect_error) die("DB failed: ".$conn->connect_error);

/* ---------- Fetch transaction ---------- */
$stmt = $conn->prepare("
    SELECT id, transfer_type, from_acc, to_acc, amount, note, created_at
    FROM bank_transfers
    WHERE id = ?
");
$stmt->bind_param("i", $txId);
$stmt->execute();
$res = $stmt->get_result();
$tx = $res->fetch_assoc();
$stmt->close();

if (!$tx) die("Transaction not found.");

/* ---------- Fetch sender name ---------- */
$senderName = "Unknown";
$stmt = $conn->prepare("
    SELECT u.user_name 
    FROM accounts a
    JOIN user u ON a.CustomerID = u.cid
    WHERE a.AccountNo = ?
");
$stmt->bind_param("s", $tx['from_acc']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if ($r) $senderName = $r['user_name'];
$stmt->close();

/* ---------- Fetch receiver name ---------- */
$receiverName = "Unknown";
$stmt = $conn->prepare("
    SELECT u.user_name 
    FROM accounts a
    JOIN user u ON a.CustomerID = u.cid
    WHERE a.AccountNo = ?
");
$stmt->bind_param("s", $tx['to_acc']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if ($r) $receiverName = $r['user_name'];
$stmt->close();

$conn->close();

/* ---------- PDF LIBRARY ---------- */
require_once "fpdf186/fpdf.php";

/* ---------- Build PDF ---------- */
$pdf = new FPDF();
$pdf->AddPage();

// Title
$pdf->SetFont("Arial","B",16);
$pdf->Cell(0,10,"My Bank - Transfer Receipt",0,1,"C");
$pdf->Ln(4);

// Body font
$pdf->SetFont("Arial","",12);

$pdf->Cell(60,8,"Transaction ID:",0,0);
$pdf->Cell(0,8,$tx['id'],0,1);

$pdf->Cell(60,8,"Transfer Type:",0,0);
$pdf->Cell(0,8,$tx['transfer_type'],0,1);

$pdf->Cell(60,8,"Sender Account:",0,0);
$pdf->Cell(0,8,$tx['from_acc']." (".$senderName.")",0,1);

$pdf->Cell(60,8,"Receiver Account:",0,0);
$pdf->Cell(0,8,$tx['to_acc']." (".$receiverName.")",0,1);

$pdf->Cell(60,8,"Amount:",0,0);
$pdf->Cell(0,8,"BDT ".$tx['amount'],0,1);

$pdf->Cell(60,8,"Note:",0,0);
$pdf->MultiCell(0,8,$tx['note'] ?: "—");

$pdf->Ln(2);
$pdf->Cell(60,8,"Date & Time:",0,0);
$pdf->Cell(0,8,$tx['created_at'] ?? date("Y-m-d H:i:s"),0,1);

$pdf->Ln(6);
$pdf->SetFont("Arial","I",10);
$pdf->Cell(0,8,"Thank you for banking with us.",0,1,"C");

// Output as download
$filename = "OBT_Receipt_TX_".$tx['id'].".pdf";
$pdf->Output("D", $filename);
exit;
