<?php
session_start();
/*if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}*/

$billName = $_SESSION['bill_name'] ?? 'N/A';
$billId   = $_SESSION['bill_id'] ?? 'N/A';
$billAmt  = $_SESSION['bill_amt'] ?? 0;
$billFrom = $_SESSION['bill_from'] ?? '---';
$txnId    = $_SESSION['bill_txn_id'] ?? 'BILL-000000';
$paidAt   = $_SESSION['bill_paid_at'] ?? date("Y-m-d H:i:s");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Payment Successful</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .centerCard{
      max-width:720px;
      margin:32px auto;
    }
    .successIcon{
      font-size:48px;
      line-height:1;
    }
    .mainTitle{
      font-size:22px;
      font-weight:900;
      color:var(--primary);
      line-height:1.3;
      margin-top:8px;
    }
    .smallMeta{
      font-size:14px;
      color:var(--muted);
      margin-top:4px;
      line-height:1.4;
    }
    .pairGrid{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:12px;
      margin-top:20px;
    }
    @media(max-width:700px){
      .pairGrid{grid-template-columns:1fr;}
    }
    .boxOutline{
      border:1px solid var(--border);
      border-radius:12px;
      background:#fbfbfc;
      padding:14px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      min-height:100px;
    }
    .kvLabel{
      font-size:12px;
      font-weight:700;
      color:var(--muted);
      margin-bottom:4px;
    }
    .kvMain{
      font-size:20px;
      font-weight:800;
      color:var(--primary);
      line-height:1.3;
      word-break:break-word;
    }
    .kvSub{
      font-size:13px;
      color:var(--muted);
      line-height:1.3;
      margin-top:4px;
    }
    .actionsRow{
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      margin-top:20px;
    }
    .btnPrimary{
      display:inline-flex;
      justify-content:center;
      align-items:center;
      padding:14px 18px;
      border:none;
      border-radius:14px;
      font-weight:800;
      font-size:16px;
      background:var(--primary);
      color:#fff;
      cursor:pointer;
      text-decoration:none;
    }
    .btnGhost{
      display:inline-flex;
      justify-content:center;
      align-items:center;
      padding:14px 18px;
      border:1px solid var(--border);
      border-radius:14px;
      font-weight:700;
      font-size:16px;
      background:#fff;
      color:var(--primary);
      cursor:pointer;
      text-decoration:none;
    }
    @media print{ .no-print{display:none;} }
  </style>
</head>
<body>
  <div class="app centerCard card">
    <div class="section">

      <div class="successIcon">✅</div>
      <div class="mainTitle">Payment Successful</div>
      <div class="smallMeta" id="successTime">
        <?php echo htmlspecialchars($paidAt); ?>
        &nbsp; • Txn: <?php echo htmlspecialchars($txnId); ?>
      </div>

      <div class="pairGrid" style="margin-top:16px;">
        <div class="boxOutline">
          <div class="kvLabel">TOTAL PAID (BDT)</div>
          <div class="kvMain"><?php echo number_format($billAmt, 2); ?></div>
          <div class="kvSub">includes fees: BDT 0.00</div>
        </div>

        <div class="boxOutline">
          <div class="kvLabel">BILLER</div>
          <div class="kvMain"><?php echo htmlspecialchars($billName); ?></div>
          <div class="kvSub">Customer ID : <?php echo htmlspecialchars($billId); ?></div>
        </div>
      </div>

      <div class="pairGrid" style="margin-top:16px;">
        <div class="boxOutline">
          <div class="kvLabel">PAID FROM</div>
          <div class="kvMain">Acc. <?php echo htmlspecialchars($billFrom); ?></div>
          <div class="kvSub">Payment source</div>
        </div>

        <div class="boxOutline">
          <div class="kvLabel">TRANSACTION ID</div>
          <div class="kvMain"><?php echo htmlspecialchars($txnId); ?></div>
          <div class="kvSub">Keep this for reference</div>
        </div>
      </div>

      <div class="actionsRow">
        <a class="btnPrimary" href="bill-receipt-pdf.php" target="_blank">Download PDF receipt</a>
        <a class="btnGhost" href="bill-payments.php">Pay another bill</a>
      </div>

    </div>
  </div>
</body>
</html>
