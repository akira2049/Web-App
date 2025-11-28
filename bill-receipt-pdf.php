<?php
session_start();
if (!isset($_SESSION['cid'])) {
    die("Not authorized.");
}

// Load FPDF
require 'fpdf186/fpdf.php';

$billName = $_SESSION['bill_name'] ?? 'N/A';
$billId   = $_SESSION['bill_id'] ?? 'N/A';
$billAmt  = $_SESSION['bill_amt'] ?? 0;
$billFrom = $_SESSION['bill_from'] ?? '---';
$txnId    = $_SESSION['bill_txn_id'] ?? 'BILL-000000';
$paidAt   = $_SESSION['bill_paid_at'] ?? date("Y-m-d H:i:s");
$cid      = $_SESSION['cid'];

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Bill Payment Receipt',0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->Ln(4);
$pdf->Cell(0,8,"Transaction ID: $txnId",0,1);
$pdf->Cell(0,8,"Date: $paidAt",0,1);
$pdf->Cell(0,8,"Customer ID (CID): $cid",0,1);
$pdf->Ln(4);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,'Details',0,1);

$pdf->SetFont('Arial','',12);
$pdf->Cell(60,8,'Biller Name',1);
$pdf->Cell(0,8,$billName,1,1);

$pdf->Cell(60,8,'Customer ID',1);
$pdf->Cell(0,8,$billId,1,1);

$pdf->Cell(60,8,'Paid From Account',1);
$pdf->Cell(0,8,$billFrom,1,1);

$pdf->Cell(60,8,'Amount (BDT)',1);
$pdf->Cell(0,8,number_format($billAmt,2),1,1);

$pdf->Cell(60,8,'Fee (BDT)',1);
$pdf->Cell(0,8,'0.00',1,1);

$pdf->Cell(60,8,'Total (BDT)',1);
$pdf->Cell(0,8,number_format($billAmt,2),1,1);

$pdf->Ln(6);
$pdf->MultiCell(0,7,"Payment to $billName (Customer ID $billId) was successful. Please keep this receipt for your records.");

$filename = "bill-receipt-$txnId.pdf";
$pdf->Output('D', $filename);
exit;
