// ---- FPDF ----
require 'fpdf.php';  // adjust path if needed (e.g. 'fpdf186/fpdf.php')

class PDF extends FPDF {
    function Header() {
        // You can customize header if needed
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF('L','mm','A4'); // Landscape
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);

// Title
$pdf->Cell(0,8,'Account Statement',0,1,'C');
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,'Account: '.$selectedAcc.'   Period: '.$from.' to '.$to,0,1,'C');
$pdf->Ln(4);

// Table Header
$pdf->SetFont('Arial','B',9);
$pdf->Cell(50,7,'Date & Time',1,0,'L');
$pdf->Cell(40,7,'Source',1,0,'L');
$pdf->Cell(150,7,'Description',1,0,'L');
$pdf->Cell(20,7,'Type',1,0,'C');
$pdf->Cell(30,7,'Amount ('.$currentCurrency.')',1,1,'R');

$pdf->SetFont('Arial','',8);

// Table Rows
foreach ($rows as $r) {
    $pdf->Cell(50,6,$r['time'],1,0,'L');
    $pdf->Cell(40,6,$r['source'],1,0,'L');

    // Description (may need truncation)
    $desc = $r['desc'];
    if (strlen($desc) > 60) {
        $desc = substr($desc,0,57).'...';
    }
    $pdf->Cell(150,6,$desc,1,0,'L');

    $pdf->Cell(20,6,$r['direction'],1,0,'C');
    $pdf->Cell(30,6,number_format($r['amount'],2),1,1,'R');
}

if (empty($rows)) {
    $pdf->Ln(4);
    $pdf->SetFont('Arial','I',9);
    $pdf->Cell(0,6,'No transactions found for this period.',0,1,'L');
}

// Output
$filename = 'statement_'.$selectedAcc.'_'.$from.'_to_'.$to.'.pdf';
$pdf->Output('I', $filename);
exit;