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
// CustomerID is VARCHAR in your schema, so bind as string ("s")
$stmt = $conn->prepare("SELECT AccountNo, Balance FROM accounts WHERE CustomerID=?");
$stmt->bind_param("s", $cid);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $accs[] = $row;
}
$stmt->close();
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>MFS Transfer — Step 1</title>

  <!-- Global styles -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">

  <style>
    :root{ --primary:#00416A; --primary-600:#005a91; }

    /* Match dashboard background + layout */
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
      background:#fff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

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
      <a class="linkish" href="fund-transfer.php">← Back</a>
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

          <!-- Receiver Name -->
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

          <!-- Hidden TX ID (generated by JS) -->
          <input type="hidden" name="tx_id" id="tx_id">

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
  const walletTypeInput = document.getElementById('walletType');
  const receiverInput = document.getElementById('receiverName');

  walletCards.forEach(card=>{
    card.addEventListener('click', ()=>{
      walletCards.forEach(x=>x.classList.remove('active'));
      card.classList.add('active');
      selectedWallet = card.dataset.wallet;
      walletTypeInput.value = selectedWallet;

      // clear receiver name when wallet changes (optional but nice UX)
      receiverInput.value = "";
    });
  });

  // Auto fetch receiver name from bkash / nagad / rocket tables
  document.getElementById('walletNo').addEventListener('keyup', async function () {
      const phone = this.value.trim();
      const currentWallet = walletTypeInput.value;

      if (phone.length < 5) {
          return; // wait until user types enough digits
      }

      let endpoint = "";

      if (currentWallet === "bKash") {
          endpoint = "get_bkash_name.php";
      } else if (currentWallet === "Nagad") {
          endpoint = "get_nagad_name.php";
      } else if (currentWallet === "Rocket") {
          endpoint = "get_rocket_name.php";
      } else {
          return; // unknown wallet type
      }

      try {
          const res = await fetch(endpoint + "?phone=" + encodeURIComponent(phone));
          const data = await res.json();

          if (data.name && data.name !== "") {
              receiverInput.value = data.name;
          }
      } catch (e) {
          console.log("Lookup failed", e);
      }
  });
</script>

<script>
  function generateTxId() {
    const now = new Date();

    const pad = n => n.toString().padStart(2, "0");

    const yy   = now.getFullYear().toString().slice(-2);
    const mm   = pad(now.getMonth() + 1);
    const dd   = pad(now.getDate());
    const hh   = pad(now.getHours());
    const min  = pad(now.getMinutes());
    const ss   = pad(now.getSeconds());

    const rand = Math.floor(Math.random() * 9000) + 1000; // 4-digit random

    // Example: MFS2511281530451234 (MFS + yymmddhhmmss + 4 digits)
    return `MFS${yy}${mm}${dd}${hh}${min}${ss}${rand}`;
  }

  document.addEventListener("DOMContentLoaded", function () {
    const form    = document.getElementById("mfsForm");
    const txInput = document.getElementById("tx_id");

    if (!form || !txInput) return;

    // Set once on page load
    txInput.value = generateTxId();

    // Safety: if tx_id somehow empty, regenerate on submit
    form.addEventListener("submit", function () {
      if (!txInput.value) {
        txInput.value = generateTxId();
      }
    });
  });
</script>

</body>
</html>
