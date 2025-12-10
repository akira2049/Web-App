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
   Load user's cards
--------------------------------------------------*/
$cards = [];
$sql = "SELECT cardNo, cardHolderName, cardType, expiryDate, cvc
        FROM cards
        WHERE customer_id = ?
        ORDER BY cardNo ASC";
$stmt = $conn->prepare($sql);
$cidStr = (string)$cid;
$stmt->bind_param("s", $cidStr);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $cards[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Cards</title>

  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    body{
      margin:0;
      font-family:'Inter',sans-serif;
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      color:#fff;
      min-height:100vh;
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
      margin-bottom:10px;
      flex-wrap:wrap;
      gap:12px;
    }

    .page-title{
      font-size:26px;
      font-weight:800;
      display:flex;
      align-items:center;
      gap:10px;
      color:white;
      text-shadow:0 2px 6px rgba(0,0,0,0.4);
    }

    .back-btn{
      text-decoration:none;
      padding:8px 14px;
      border-radius:999px;
      background:rgba(255,255,255,0.15);
      color:white;
      font-size:14px;
      font-weight:600;
      display:inline-flex;
      align-items:center;
      gap:6px;
      border:1px solid rgba(255,255,255,0.3);
    }
    .back-btn:hover{
      background:rgba(255,255,255,0.25);
    }

    .hint{
      font-size:12px;
      opacity:0.9;
      margin-bottom:18px;
    }

    .cards-grid{
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
      gap:22px;
    }

    .card-shell{
      position:relative;
      width:100%;
      min-height:210px;
      perspective:1000px;
    }

    .card-inner{
      position:relative;
      width:100%;
      height:100%;
      transform-style:preserve-3d;
      transition:transform 0.6s;
    }

    .card-shell:hover .card-inner{
      transform:rotateY(180deg);
    }

    .card-face{
      position:absolute;
      inset:0;
      border-radius:18px;
      padding:16px 18px;
      color:white;
      box-shadow:0 6px 16px rgba(0,0,0,0.25);
      backface-visibility:hidden;
      box-sizing:border-box;
    }

    .card-visa{
      background:linear-gradient(135deg,#38bdf8,#0ea5e9,#0369a1);
    }
    .card-mastercard{
      background:linear-gradient(135deg,#ef4444,#dc2626,#7f1d1d);
    }
    .card-default{
      background:linear-gradient(135deg,#22c55e,#15803d,#064e3b);
    }

    .card-front{
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }

    .card-front-top{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      margin-bottom:10px;
    }

    .chip{
      width:40px;
      height:28px;
      background:linear-gradient(135deg,#facc15,#f59e0b);
      border-radius:8px;
    }

    .card-brand{
      font-size:20px;
      font-weight:900;
      text-shadow:0 2px 6px rgba(0,0,0,0.35);
    }

    .card-number{
      font-size:18px;
      letter-spacing:0.15em;
      font-weight:700;
      margin-bottom:10px;
      word-spacing:0.35em;
    }

    .card-front-bottom{
      display:flex;
      justify-content:space-between;
      align-items:flex-end;
      gap:16px;
    }

    .label{
      font-size:10px;
      opacity:0.9;
    }

    .holder-name, .expiry{
      font-size:13px;
      font-weight:600;
      letter-spacing:0.08em;
      text-transform:uppercase;
      margin-top:3px;
    }

    /* ------------ BACK SIDE ------------ */
    .card-back{
      transform:rotateY(180deg);
      display:flex;
      flex-direction:column;
      justify-content:center;
      gap:14px;
    }

    .magstripe{
      height:32px;
      background:rgba(15,23,42,0.9);
      border-radius:6px;
      margin-bottom:10px;
    }

    .cvc-row-wrap{
      max-width:240px;
    }

    .cvc-label{
      font-weight:600;
      text-transform:uppercase;
      letter-spacing:0.12em;
      font-size:10px;
      color:#e5e7eb;
      margin-bottom:4px;
    }

    .cvc-row{
      background:rgba(255,255,255,0.9);
      border-radius:8px;
      padding:8px 10px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      color:#111827;
      font-size:13px;
    }

    .cvc-value{
      font-weight:700;
      font-size:16px;
      letter-spacing:0.25em;
    }

    .back-note{
      font-size:10px;
      opacity:0.85;
      max-width:220px;
    }
  </style>
</head>
<body>

<div class="page-wrap">

  <div class="page-header">
    <div class="page-title">
      <i class="fa-regular fa-credit-card"></i> My Cards
    </div>

    <a class="back-btn" href="dashboard.php">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
  </div>

  <div class="hint">
    Hover or tap a card to flip and view the CVC.
  </div>

  <?php if (empty($cards)): ?>
    <div class="no-cards">You do not have any cards yet.</div>
  <?php else: ?>
    <div class="cards-grid">
      <?php foreach ($cards as $c): ?>

        <?php
          $type = strtoupper($c['cardType']);

          if ($type === "VISA") {
              $cls = "card-visa";
              $brandText = "VISA";
          } elseif ($type === "MASTERCARD") {
              $cls = "card-mastercard";
              $brandText = "MasterCard";
          } else {
              $cls = "card-default";
              $brandText = $type;
          }

          $display = trim(chunk_split($c['cardNo'], 4, ' '));

          // Expiry
          $exp = "--/--";
          if (!empty($c['expiryDate']) && $c['expiryDate'] !== "0000-00-00") {
              $p = explode("-", $c['expiryDate']);
              $exp = $p[1] . "/" . substr($p[0], -2);
          }

          $cvc = $c['cvc'];
        ?>

        <div class="card-shell">
          <div class="card-inner">

            <!-- FRONT -->
            <div class="card-face card-front <?php echo $cls; ?>">

              <div class="card-front-top">
                <div class="chip"></div>
                <div class="card-brand"><?php echo $brandText; ?></div>
              </div>

              <div class="card-number"><?php echo htmlspecialchars($display); ?></div>

              <div class="card-front-bottom">
                <div>
                  <div class="label">Card Holder</div>
                  <div class="holder-name"><?php echo htmlspecialchars($c['cardHolderName']); ?></div>
                </div>

                <div style="text-align:right;">
                  <div class="label">Expires</div>
                  <div class="expiry"><?php echo htmlspecialchars($exp); ?></div>
                </div>
              </div>

            </div>

            <!-- BACK -->
            <div class="card-face card-back <?php echo $cls; ?>">
              <div class="magstripe"></div>

              <div class="cvc-row-wrap">
                <div class="cvc-label">Security Code (CVC)</div>
                <div class="cvc-row">
                  <span>Signature</span>
                  <span class="cvc-value"><?php echo htmlspecialchars($cvc); ?></span>
                </div>
              </div>

              <div class="back-note">
                Never share your CVC code with anyone.
              </div>
            </div>

          </div>
        </div>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

</body>
</html>
