<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];
$tid = $_GET['tid'] ?? '';

if ($tid == '') die("Missing transaction ID.");

/* ---------- DB CONNECTION ---------- */
$host="localhost"; 
$user="root"; 
$password="";
$database="my_bank";
$conn = new mysqli($host,$user,$password,$database);
if ($conn->connect_error) die("DB failed: ".$conn->connect_error);

/*
   ONLY use bank_transfers table – guaranteed to exist.
*/
$sql = "
SELECT *
FROM bank_transfers
WHERE ref_id = ? AND cid = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL ERROR: " . $conn->error . "<br>QUERY:<br>" . $sql);
}

$stmt->bind_param("si", $tid, $cid);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$tx) die("Transaction not found.");

/* ---------- PDF ---------- */
require "fpdf186/fpdf.php";
$pdf = new FPDF('P','mm','A4');
$pdf->AddPage();

/* HEADER */
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Bank Transfer Receipt',0,1,'C');
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,6,'Astra Bank',0,1,'C');
$pdf->Ln(4);

/* BASIC INFO (TOP, SIMPLE LINES) */
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,"Customer CID: ".$cid,0,1);
$pdf->Cell(0,6,"Transaction ID: ".$tid,0,1);
$pdf->Cell(0,6,"Date & Time: ".date('d M Y, h:i A', strtotime($tx['created_at'])),0,1);
$pdf->Ln(4);

/* TABLE STYLE DETAILS (LIKE RECHARGE RECEIPT) */
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,7,"Transfer Details",0,1);
$pdf->Ln(1);

/* Table header */
$col1 = 60;  // Field column width
$col2 = 130; // Details column width

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(230,230,230); // light gray header background
$pdf->Cell($col1,8,'Field',1,0,'L',true);
$pdf->Cell($col2,8,'Details',1,1,'L',true);

$pdf->SetFont('Arial','',10);

/* helper for table row */
function tableRow($pdf, $label, $value, $col1, $col2) {
    $pdf->Cell($col1,8,$label,1,0,'L');
    $pdf->Cell($col2,8,$value,1,1,'L');
}

/* Add rows */
tableRow($pdf, "From Account",        $tx['from_acc'],                                   $col1, $col2);
tableRow($pdf, "To Account",          $tx['to_acc'],                                     $col1, $col2);
tableRow($pdf, "Amount",              number_format($tx['amount'],2)." BDT",            $col1, $col2);
tableRow($pdf, "Sender Balance Before",  number_format($tx['from_balance_before'],2)." BDT", $col1, $col2);
tableRow($pdf, "Sender Balance After",   number_format($tx['from_balance_after'],2)." BDT",  $col1, $col2);
tableRow($pdf, "Receiver Balance Before",number_format($tx['to_balance_before'],2)." BDT",   $col1, $col2);
tableRow($pdf, "Receiver Balance After", number_format($tx['to_balance_after'],2)." BDT",    $col1, $col2);

if (!empty($tx['note'])) {
    tableRow($pdf, "Note", $tx['note'], $col1, $col2);
}

$pdf->Ln(10);

/* FOOTER */
$pdf->SetFont('Arial','I',9);
$pdf->MultiCell(0,5,
    "This is a system-generated receipt for your bank transfer.\n".
    "Please keep it for your records."
);

$pdf->Output("D", "Bank-Receipt-$tid.pdf");
exit;
