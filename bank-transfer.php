<?php
session_start();

if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$loggedCid = $_SESSION['cid'];

/* ---------- DATABASE CONNECTION ---------- */
$host="localhost"; $user="root"; $password=""; $database="my_bank";
$conn = new mysqli($host,$user,$password,$database);
if ($conn->connect_error) die("DB failed: ".$conn->connect_error);

/*
  Assumed tables:
  accounts(AccountNo, Balance, cid)
  user(cid, user_name)
*/

/* 1) FROM accounts: only those linked to logged-in cid */
$fromAccounts = [];
$stmt = $conn->prepare("
    SELECT AccountNo
    FROM accounts
    WHERE CustomerID = ?
    ORDER BY AccountNo ASC
");
$stmt->bind_param("i", $loggedCid);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $fromAccounts[] = $row['AccountNo'];
}
$stmt->close();

/* If user has no account, stop nicely */
if (count($fromAccounts) === 0) {
    $conn->close();
    die("No accounts linked with this CID.");
}

/* 2) TO accounts: all accounts EXCEPT user’s own accounts */
$accounts = [];
$sql = "
    SELECT a.AccountNo, a.account_name AS holder_name
    FROM accounts a
    WHERE a.CustomerID != ?
    ORDER BY a.AccountNo ASC
";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("i", $loggedCid);
$stmt2->execute();
$res2 = $stmt2->get_result();

while($row = $res2->fetch_assoc()){
    $acc  = $row['AccountNo'];
    $name = $row['holder_name'] ?? '';
    $mask = '****' . substr($acc,-4);
    $accounts[] = ["acc"=>$acc,"name"=>$name,"mask"=>$mask];
}
$stmt2->close();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>EBL Account Transfer — Step 1</title>

  <!-- CSS -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    .app{max-width:720px;margin:0 auto;padding:24px;}
    .search-input{margin-bottom:8px;width:100%;}
  </style>
</head>
<body>

  <div class="app">

    <div class="topbar">
      <a class="linkish" href="fund-transfer.php">← Back</a>
      <span class="step">1 / 2</span>
    </div>

    <div class="h1">Own Bank Account Transfer</div>

    <div class="card">
      <div class="section">

        <form method="POST" action="bank-transfer-overview.php" id="transferForm">

          <!-- Transfer From (ONLY linked to this CID) -->
          <div class="row">
            <div class="label">Transfer From</div>
            <select id="fromAcc" name="from_acc" required>
              <?php foreach($fromAccounts as $fa): ?>
                <option value="<?= htmlspecialchars($fa) ?>">
                  Acc. <?= htmlspecialchars($fa) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Transfer To (ALL accounts) -->
          <div class="row">
            <div class="label">Transfer To (Beneficiary)</div>
            <div>

              <!-- Search -->
              <input
                class="input search-input"
                id="beneficiarySearch"
                list="allAccounts"
                placeholder="Search account number..."
              />
              <datalist id="allAccounts">
                <?php foreach($accounts as $a): ?>
                  <option value="<?= htmlspecialchars($a['acc']) ?>">
                <?php endforeach; ?>
              </datalist>

              <!-- Dropdown -->
              <select id="beneficiarySelect" class="input" name="to_acc" required>
                <option value="">Select beneficiary</option>
                <?php foreach($accounts as $a): ?>
                  <option
                    value="<?= htmlspecialchars($a['acc']) ?>"
                    data-name="<?= htmlspecialchars($a['name']) ?>"
                    data-mask="<?= htmlspecialchars($a['mask']) ?>"
                  >
                    <?= htmlspecialchars($a['mask']) ?><?= $a['name'] ? " | ".$a['name'] : "" ?>
                  </option>
                <?php endforeach; ?>
              </select>

            </div>
          </div>

          <!-- Account Holder -->
          <div class="row">
            <div class="label">Account Holder</div>
            <div class="ahd" id="holderInfo" style="display:none;">
              <div style="font-size:28px">👤</div>
              <div>
                <div class="name" id="ahName"></div>
                <div class="kv">To Acc: <span class="mask" id="ahAcc"></span></div>
              </div>
            </div>
          </div>

          <input type="hidden" name="holder_name" id="holderNameHidden">

          <!-- Amount -->
          <div class="row">
            <div class="label">Transfer Amount</div>
            <div>
              <input class="input" id="amount" name="amount" type="number" min="1"
                     inputmode="decimal" placeholder="৳ 0.00" required>
              <div class="amounts" style="margin-top:10px">
                <button type="button" class="chip" data-a="500">৳ 500</button>
                <button type="button" class="chip" data-a="5000">৳ 5000</button>
                <button type="button" class="chip" data-a="10000">৳ 10000</button>
                <button type="button" class="chip" data-a="20000">৳ 20000</button>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="row">
            <div class="label">Notes</div>
            <input class="input" name="note" id="note" placeholder="EBL Transfer">
          </div>

          <div class="footerbar">
            <button class="btn" type="submit">Next</button>
          </div>

        </form>
      </div>
    </div>
  </div>

<script>
  const accounts = <?php echo json_encode($accounts); ?>;

  const beneSearch  = document.getElementById("beneficiarySearch");
  const beneSelect  = document.getElementById("beneficiarySelect");
  const holderInfo  = document.getElementById("holderInfo");
  const ahName      = document.getElementById("ahName");
  const ahAcc       = document.getElementById("ahAcc");
  const holderHidden= document.getElementById("holderNameHidden");

  function showHolder(accNo){
    const found = accounts.find(a => a.acc === accNo);
    if(found){
      ahName.textContent = found.name || "Selected Beneficiary";
      ahAcc.textContent  = found.acc;
      holderHidden.value = found.name || "";
      holderInfo.style.display = "flex";
    } else {
      holderHidden.value = "";
      holderInfo.style.display = "none";
    }
  }

  beneSelect.addEventListener("change", ()=>{
    beneSearch.value = beneSelect.value;
    showHolder(beneSelect.value);
  });

  beneSearch.addEventListener("input", ()=>{
    const val = beneSearch.value.trim();
    beneSelect.value = val;
    showHolder(val);
  });

  document.querySelectorAll(".chip").forEach(c=>{
    c.addEventListener("click", ()=>{
      document.getElementById("amount").value = c.dataset.a;
    });
  });

  // prevent same from/to
  document.getElementById("transferForm").addEventListener("submit", (e)=>{
    const fromAcc = document.getElementById("fromAcc").value;
    const toAcc = beneSelect.value;
    if(fromAcc === toAcc){
      e.preventDefault();
      alert("Transfer From and Transfer To cannot be the same account.");
    }
  });
</script>
</body>
</html>
