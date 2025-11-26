<?php
session_start();
if (!isset($_SESSION['cid'])) { header("Location: login.php"); exit; }
if (!isset($_SESSION['am_card_mask'])) { header("Location: add-money-from.php"); exit; }

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amt = floatval($_POST['amount'] ?? 0);
    if ($amt <= 0) $error = "Please enter a valid amount.";
    else {
        $_SESSION['am_amount'] = $amt;
        header("Location: add-money-to.php");
        exit;
    }
}
$mask   = $_SESSION['am_card_mask'] ?? "Select a card";
$holder = $_SESSION['am_card_holder'] ?? "NA";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add From — Amount</title>
  <link rel="stylesheet" href="transfer.css">
  <style>.err{color:#b00020;font-weight:700;margin:8px 0;}</style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="add-money-from.php">← Back</a>
      <span class="step">2 / 4</span>
    </div>
    <div class="h1">Add From</div>

    <div class="card">
      <div class="section">
        <div class="row">
          <div class="label">From</div>
          <div class="kv"><b id="fromSel"><?= htmlspecialchars($mask) ?></b><br>
            Card Holder: <span id="ch"><?= htmlspecialchars($holder) ?></span>
          </div>
        </div>

        <?php if($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" id="amtForm">
          <div class="row">
            <div class="label">Amount</div>
            <div>
              <input class="input" id="amount" name="amount" type="number" inputmode="decimal" placeholder="৳ Enter amount here" required>
              <div class="amounts" style="margin-top:10px">
                <button class="chip" type="button" data-a="500">৳ 500</button>
                <button class="chip" type="button" data-a="5000">৳ 5000</button>
                <button class="chip" type="button" data-a="10000">৳ 10000</button>
                <button class="chip" type="button" data-a="20000">৳ 20000</button>
              </div>
            </div>
          </div>

          <div class="footerbar">
            <button class="btn" id="next">Next</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
  document.querySelectorAll('.chip').forEach(c=>{
    c.addEventListener('click', ()=>{ document.getElementById('amount').value = c.dataset.a; });
  });
</script>
</body>
</html>
