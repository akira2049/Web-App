<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];

/*
  Step 1: comes from bill-category-internet.php via POST
  We store bill details in session once.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bill_name'])) {
    $_SESSION['bill_name'] = $_POST['bill_name'];
    $_SESSION['bill_id']   = $_POST['bill_id'];
    $_SESSION['bill_logo'] = $_POST['bill_logo'];
    $_SESSION['bill_amt']  = floatval($_POST['bill_amt']);
    $_SESSION['bill_flow'] = $_POST['bill_flow'] ?? 'internet';
}

// Safety check
if (!isset($_SESSION['bill_name'], $_SESSION['bill_amt'])) {
    header("Location: bill-payments.php");
    exit;
}

$billName = $_SESSION['bill_name'];
$billId   = $_SESSION['bill_id'];
$billLogo = $_SESSION['bill_logo'];
$billAmt  = $_SESSION['bill_amt'];

// -------- DB: load all accounts of this customer --------
$host="localhost"; $user="root"; $pass=""; $db="my_bank";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$accounts = [];
/*
   If your schema is accounts(AccountNo, Balance, CustomerID)
   keep the query as is.
   If it's accounts(AccountNo, Balance, cid) then change to WHERE cid = ?
*/
$stmt = $conn->prepare("SELECT AccountNo, Balance FROM accounts WHERE CustomerID = ? ORDER BY AccountNo ASC");
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $accounts[] = $row;
}
$stmt->close();
$conn->close();

if (empty($accounts)) {
    die("No accounts linked to your profile. Please contact bank support.");
}

// choose first account as default
$defaultFrom = $accounts[0]['AccountNo'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bill Payment — Overview</title>

  <!-- Global styles -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">

  <style>
    :root { --primary:#00416A; }

    /* Match dashboard background + centered layout */
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .app {
      max-width: 720px;
      margin: 0 auto;
      padding: 24px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .card {
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    .billHead{
      display:flex;
      align-items:flex-start;
      gap:12px;
      margin-bottom:16px;
    }
    .billLogo{
      width:40px;
      height:40px;
      border-radius:8px;
      border:1px solid var(--border);
      background:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:12px;
      font-weight:700;
      color:var(--primary);
    }
    .billName{
      font-weight:800;
      color:var(--primary);
      font-size:16px;
      line-height:1.3;
    }
    .billSub{
      color:var(--muted);
      font-size:14px;
      line-height:1.3;
    }

    .rowTitle{
      font-size:12px;
      font-weight:700;
      color:var(--muted);
      margin-bottom:4px;
    }
    .rowValue{
      font-size:16px;
      font-weight:700;
      color:var(--primary);
      line-height:1.4;
      display:flex;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
    }

    .accSelect{
      width:100%;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid var(--border);
      background:#fff;
      font-size:14px;
      font-weight:600;
      color:var(--primary);
      outline:none;
    }

    .amountBox{
      margin-top:16px;
      margin-bottom:16px;
    }
    .amountLabel{
      font-size:12px;
      font-weight:700;
      color:var(--muted);
      margin-bottom:4px;
    }
    .amountMain{
      font-size:20px;
      font-weight:800;
      color:var(--primary);
      line-height:1.3;
    }
    .amountSub{
      font-size:14px;
      color:var(--muted);
      line-height:1.3;
      margin-top:4px;
    }

    .notesWrap{
      margin-top:16px;
    }
    .notesLabel{
      font-size:12px;
      font-weight:700;
      color:var(--muted);
      margin-bottom:4px;
    }
    .notesBox{
      font-size:16px;
      color:var(--primary);
      border:1px solid var(--border);
      border-radius:12px;
      padding:14px 12px;
      background:#fff;
      min-height:48px;
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      flex-wrap:wrap;
    }

    .confirmWrap{
      margin-top:24px;
      font-size:14px;
      font-weight:600;
      color:var(--primary);
    }
    .confirmLabel{
      margin-bottom:12px;
    }

    .veri-grid-3{
      display:grid;
      gap:12px;
      grid-template-columns:repeat(3,minmax(0,1fr));
    }
    .vcard-sm{
      padding:16px;
      border:1.5px solid var(--border);
      border-radius:14px;
      cursor:pointer;
      text-align:center;
      background:#fff;
      font-weight:600;
      color:var(--primary);
      min-height:80px;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      gap:8px;
    }
    .vcard-sm input{
      accent-color:var(--primary);
    }
    .vcard-sm.active{
      border-color:var(--primary);
      background:#e6f1fb;
    }

    .footercta{
      margin-top:24px;
    }
    .payBtn{
      width:100%;
      border:none;
      border-radius:14px;
      font-weight:800;
      font-size:16px;
      padding:14px 18px;
      background:var(--primary);
      color:#fff;
      cursor:pointer;
    }
  </style>
</head>
<body>
  <div class="app">

    <div class="topbar">
      <a class="linkish" href="bill-amount.php">← Back</a>
      <span class="step">2 / 2</span>
    </div>

    <div class="card">
      <div class="section">

        <div class="billHead">
          <div class="billLogo"><?php echo htmlspecialchars($billLogo); ?></div>
          <div>
            <div class="billName"><?php echo htmlspecialchars($billName); ?></div>
            <div class="billSub">Customer ID : <?php echo htmlspecialchars($billId); ?></div>
          </div>
        </div>

        <!-- Wrap everything in ONE form so from_acc and method go together -->
        <form method="post" action="bill-otp.php">

          <div>
            <div class="rowTitle">PAY FROM</div>
            <div class="rowValue">
              <select name="from_acc" class="accSelect" required>
                <?php foreach ($accounts as $acc): ?>
                  <?php
                    $accNo = $acc['AccountNo'];
                    $bal  = $acc['Balance'];
                  ?>
                  <option value="<?php echo htmlspecialchars($accNo); ?>"
                    <?php if ($accNo === $defaultFrom) echo 'selected'; ?>>
                    Acc. <?php echo htmlspecialchars($accNo); ?> — Balance: BDT <?php echo number_format($bal,2); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="amountBox">
            <div class="amountLabel">TOTAL AMOUNT</div>
            <div class="amountMain">BDT <?php echo number_format($billAmt,2); ?></div>
            <div class="amountSub">
              BDT <?php echo number_format($billAmt,2); ?> + BDT 0.00
            </div>
          </div>

          <div class="notesWrap">
            <div class="notesLabel">NOTES</div>
            <div class="notesBox">
              <span><?php echo htmlspecialchars($billName); ?> payment</span>
              <span style="font-size:18px;line-height:1;">✏️</span>
            </div>
          </div>

          <div class="confirmWrap">
            <div class="confirmLabel">Complete payment confirmation via :</div>
            <div class="veri-grid-3" id="veriOptions">
              <label class="vcard-sm">
                <input type="radio" name="v" value="Email" required> Email
              </label>
              <label class="vcard-sm">
                <input type="radio" name="v" value="SMS" required> SMS
              </label>
            </div>
          </div>

          <div class="footercta">
            <button class="payBtn" type="submit" name="start_otp" value="1">Pay Now</button>
          </div>

        </form>

      </div>
    </div>
  </div>

<script>
  // Make verification cards visually selectable
  const vCards = document.querySelectorAll('.vcard-sm');
  vCards.forEach(card => {
    card.addEventListener('click', () => {
      vCards.forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      const input = card.querySelector('input[type="radio"]');
      if (input) input.checked = true;
    });
  });
</script>
</body>
</html>
