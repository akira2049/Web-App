<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];

// ---- DB CONNECTION ----
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/*
   Table: saved_cards
   Columns: cid, card_number, cvc, expirey_date (DATE), cardholder_name
*/

$saved = [];
$stmt = $conn->prepare("
    SELECT card_number, cvc, expirey_date, cardholder_name
    FROM saved_cards
    WHERE cid = ?
    ORDER BY expirey_date DESC
");
$stmt->bind_param("s", $cid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $saved[] = $row;
}
$stmt->close();
$conn->close();

/* Helper: mask card number (show last 4) */
function mask_card($num) {
    $digits = preg_replace('/\D+/', '', $num);
    $last4 = substr($digits, -4);
    return '**** **** **** ' . $last4;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Saved Cards — Add Money</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .center{max-width:720px;margin:24px auto;}
    .card-list{display:flex;flex-direction:column;gap:12px;margin-top:12px;}
    .saved-item{
      border:1px solid var(--border);
      border-radius:14px;
      padding:12px 14px;
      background:#fff;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
    }
    .saved-left{
      display:flex;
      flex-direction:column;
      gap:4px;
    }
    .saved-name{font-weight:700;font-size:15px;color:var(--primary);}
    .saved-num{font-size:14px;}
    .saved-meta{font-size:13px;color:var(--muted);}
    .saved-btn{
      padding:8px 12px;
      border-radius:999px;
      border:none;
      background:var(--primary);
      color:#fff;
      font-size:13px;
      font-weight:600;
      cursor:pointer;
      white-space:nowrap;
    }
    .empty{font-size:14px;color:var(--muted);margin-top:8px;}
  </style>
</head>
<body>
  <div class="app center">
    <div class="topbar">
      <a class="linkish" href="add-money-from.php">← Back</a>
    </div>

    <div class="h1">Saved Cards</div>
    <div class="kv" style="margin-top:-6px;">Choose a saved card for Add Money</div>

    <div class="card">
      <div class="section">
        <?php if (empty($saved)): ?>
          <div class="empty">You have no saved cards yet.</div>
        <?php else: ?>
          <div class="card-list">
            <?php foreach ($saved as $c): ?>
              <?php
                $masked = mask_card($c['card_number']);
                $exp    = date('m / Y', strtotime($c['expirey_date']));
              ?>
              <div class="saved-item">
                <div class="saved-left">
                  <div class="saved-name">
                    <?php echo htmlspecialchars($c['cardholder_name']); ?>
                  </div>
                  <div class="saved-num">
                    <?php echo htmlspecialchars($masked); ?>
                  </div>
                  <div class="saved-meta">
                    Expiry: <?php echo htmlspecialchars($exp); ?>
                  </div>
                </div>
                <form method="post" action="add-money-from.php">
                  <input type="hidden" name="saved_card_number"
                         value="<?php echo htmlspecialchars($c['card_number']); ?>">
                  <input type="hidden" name="saved_cvc"
                         value="<?php echo htmlspecialchars($c['cvc']); ?>">
                  <input type="hidden" name="saved_expirey"
                         value="<?php echo htmlspecialchars($c['expirey_date']); ?>">
                  <input type="hidden" name="saved_holder"
                         value="<?php echo htmlspecialchars($c['cardholder_name']); ?>">
                  <button type="submit" class="saved-btn">Use this card</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
