<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];

// DB connect (same style you use elsewhere)
$host="localhost"; $user="root"; $pass=""; $db="my_bank";
$conn = new mysqli($host,$user,$pass,$db);
if($conn->connect_error){ die("DB connection failed"); }

// Load user accounts for dropdown
$accs = [];
$stmt = $conn->prepare("SELECT AccountNo, Balance FROM accounts WHERE CustomerID=?");
$stmt->bind_param("i",$cid);
$stmt->execute();
$res = $stmt->get_result();
while($row=$res->fetch_assoc()){ $accs[]=$row; }
$stmt->close();
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>MFS Transfer — Step 1</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    :root{ --primary:#00416A; --primary-600:#005a91; }

    .wallet-grid{ display:grid; gap:12px; grid-template-columns:repeat(3,minmax(0,1fr)); }
    @media (max-width:720px){ .wallet-grid{grid-template-columns:1fr;} }

    .wallet-card{
      padding:16px; border:1.5px solid var(--border); border-radius:14px;
      cursor:pointer; background:#fff; display:flex; align-items:center; gap:10px;
      font-weight:800;
    }
    .wallet-card.active{border-color:var(--primary); outline:2px solid #00416a22;}
    .wallet-ico{
      width:40px;height:40px;border-radius:10px;display:grid;place-items:center;
      background:#f3f6f8;font-size:20px;
    }
    .help{color:var(--muted);font-size:13px;margin-top:6px}
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="fund-transfer_ui.html">← Back</a>
      <span class="step">1 / 2</span>
    </div>

    <div class="h1">MFS Transfer</div>

    <div class="card">
      <div class="section">
        <form action="mfs-transfer-overview.php" method="post" id="mfsForm">

          <!-- Transfer From -->
          <div class="row">
            <div class="label">Transfer From</div>
            <select name="fromAcc" id="fromAcc" required>
              <option value="">Select account</option>
              <?php foreach($accs as $a): ?>
                <option value="<?= htmlspecialchars($a['AccountNo']) ?>">
                  Acc. <?= htmlspecialchars($a['AccountNo']) ?> (Bal: <?= number_format($a['Balance'],2) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Wallet Type -->
          <div class="row">
            <div class="label">Select Wallet</div>
            <div class="wallet-grid" id="walletGrid">
              <div class="wallet-card active" data-wallet="bKash">
                <div class="wallet-ico">📱</div>
                <div>bKash<div class="help">Transfer to bKash wallet</div></div>
              </div>

              <div class="wallet-card" data-wallet="Nagad">
                <div class="wallet-ico">💳</div>
                <div>Nagad<div class="help">Transfer to Nagad wallet</div></div>
              </div>

              <div class="wallet-card" data-wallet="Rocket">
                <div class="wallet-ico">🚀</div>
                <div>Rocket<div class="help">Transfer to Rocket wallet</div></div>
              </div>
            </div>
            <input type="hidden" name="walletType" id="walletType" value="bKash">
          </div>

          <!-- Wallet Number -->
          <div class="row">
            <div class="label">Wallet Number</div>
            <div>
              <input class="input" name="walletNo" id="walletNo" type="tel" placeholder="01XXXXXXXXX" required>
              <div class="help">Enter receiver mobile wallet number</div>
            </div>
          </div>

          <!-- Receiver Name (manual, optional auto-fill later) -->
          <div class="row">
            <div class="label">Receiver Name</div>
            <input class="input" name="receiverName" id="receiverName" placeholder="Receiver name" required>
          </div>

          <!-- Amount -->
          <div class="row">
            <div class="label">Transfer Amount</div>
            <div>
              <input class="input" name="amount" id="amount" type="number" step="0.01" placeholder="৳ 0.00" required>
              <div class="amounts" style="margin-top:10px">
                <button type="button" class="chip" data-a="500">৳ 500</button>
                <button type="button" class="chip" data-a="1000">৳ 1000</button>
                <button type="button" class="chip" data-a="2000">৳ 2000</button>
                <button type="button" class="chip" data-a="5000">৳ 5000</button>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="row">
            <div class="label">Notes</div>
            <input class="input" name="note" id="note" placeholder="MFS Transfer">
          </div>

          <div class="footerbar">
            <button class="btn" type="submit">Next</button>
          </div>

        </form>
      </div>
    </div>
  </div>

<script>
  // amount chips
  document.querySelectorAll('.chip').forEach(c=>{
    c.addEventListener('click', ()=> document.getElementById('amount').value = c.dataset.a);
  });

  // wallet selection
  let selectedWallet = "bKash";
  const walletCards = document.querySelectorAll('.wallet-card');
  walletCards.forEach(card=>{
    card.addEventListener('click', ()=>{
      walletCards.forEach(x=>x.classList.remove('active'));
      card.classList.add('active');
      selectedWallet = card.dataset.wallet;
      document.getElementById('walletType').value = selectedWallet;
    });
  });

  // Auto fetch receiver name from bkash table
  document.getElementById('walletNo').addEventListener('keyup', async function () {
      const phone = this.value.trim();
      const currentWallet = document.getElementById('walletType').value;

      // Only auto fetch for bKash wallet
      if (currentWallet !== "bKash") {
          return;
      }

      if (phone.length >= 5) {  // start searching after few digits
          try {
              const res = await fetch("get_bkash_name.php?phone=" + phone);
              const data = await res.json();

              if (data.name && data.name !== "") {
                  document.getElementById('receiverName').value = data.name;
              }
          } catch (e) {
              console.log("Lookup failed");
          }
      }
  });
</script>
</body>
</html>
