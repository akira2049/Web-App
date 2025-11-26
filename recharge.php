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

/* -------------------------------
   Load user's accounts
------------------------------- */
$accounts = [];
$stmt = $conn->prepare("SELECT AccountNo FROM accounts WHERE CustomerID = ? ORDER BY AccountNo ASC");
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $accounts[] = $row['AccountNo'];
}
$stmt->close();

/* -------------------------------
   Load last 5 recharge numbers
   from mobile_recharges table
   (assumed columns: id, cid, recharge_number, amount, created_at)
------------------------------- */
$historyNumbers = [];
$stmt = $conn->prepare("
    SELECT DISTINCT mobile_number 
    FROM mobile_recharges 
    WHERE cid = ? 
    ORDER BY id DESC 
    LIMIT 5
");
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $historyNumbers[] = $row['mobile_number'];
}
$stmt->close();

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Mobile Recharge — Step 1</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .tabs{display:flex;gap:8px;margin:6px 0 12px}
    .tab{padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:#fff;color:var(--primary);cursor:pointer;font-weight:700}
    .tab.active{outline:2px solid var(--primary);outline-offset:2px}
    .quick{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
    .pickerBtn{display:flex;justify-content:space-between;align-items:center}
    .next-disabled{opacity:.5;pointer-events:none}

    /* history list style (similar to contacts) */
    .list{display:flex;flex-direction:column;gap:10px;margin-top:10px}
    .item{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:#fff;cursor:pointer;font-size:14px}
    .left{display:flex;align-items:center;gap:10px}
    .nm{font-weight:700;color:var(--primary)}
    .ph{color:var(--muted);font-size:13px}

    /* internet packages */
    .data-pack{display:flex;flex-direction:column;align-items:flex-start;gap:3px;padding:10px 12px;border-radius:12px;border:1px solid var(--border);background:#fff;cursor:pointer;font-size:13px;min-width:140px}
    .data-pack span:first-child{font-weight:700;color:var(--primary)}
    .data-pack span:nth-child(2){color:var(--muted)}
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="dashboard.php">← Back</a>
      <span class="step">1 / 2</span>
    </div>
    <div class="h1">Mobile Recharge</div>

    <div class="card">
      <div class="section">

        <!-- Pay From -->
        <div class="row">
          <div class="label">Pay From</div>
          <select id="payFrom">
            <option value="" selected>Select account or card</option>
            <?php if (!empty($accounts)): ?>
              <?php foreach ($accounts as $accNo): ?>
                <option value="<?php echo htmlspecialchars($accNo); ?>">
                  Acc. <?php echo htmlspecialchars($accNo); ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <!-- Recharge Number as input -->
        <div class="row">
          <div class="label">Recharge Number</div>
          <div>
            <input class="input" id="msisdn" placeholder="+8801XXXXXXXXX" inputmode="tel">
            <div style="margin-top:6px;font-size:13px;">
              <a href="recharge-contacts.php" class="linkish">Choose from contacts</a>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="row">
          <div class="label">Recharge Option :</div>
          <div>
            <div class="tabs">
              <button class="tab" data-t="history">History</button>
              <button class="tab active" data-t="recharge">Recharge</button>
              <button class="tab" data-t="internet">Internet</button>
            </div>
          </div>
        </div>

        <!-- History section (last 5 numbers) -->
        <div class="row" id="historySection" style="display:none;">
          <div class="label">Last 5 Numbers</div>
          <div>
            <?php if (!empty($historyNumbers)): ?>
              <div class="list">
                <?php foreach ($historyNumbers as $num): ?>
                  <button type="button" class="item history-item" data-msisdn="<?php echo htmlspecialchars($num); ?>">
                    <div class="left">
                      <div>📱</div>
                      <div>
                        <div class="nm">Saved</div>
                        <div class="ph"><?php echo htmlspecialchars($num); ?></div>
                      </div>
                    </div>
                    <div>›</div>
                  </button>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="font-size:13px;color:var(--muted);margin-top:4px;">
                No previous recharges found.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Internet packages section -->
        <div class="row" id="internetSection" style="display:none;">
          <div class="label">Internet Packs</div>
          <div>
            <div class="quick">
              <!-- Example data packs; adjust amounts/labels as you like -->
              <button type="button" class="data-pack" data-a="49">
                <span>1 GB</span>
                <span>3 Days</span>
                <span>৳ 49</span>
              </button>
              <button type="button" class="data-pack" data-a="99">
                <span>2 GB</span>
                <span>7 Days</span>
                <span>৳ 99</span>
              </button>
              <button type="button" class="data-pack" data-a="199">
                <span>5 GB</span>
                <span>15 Days</span>
                <span>৳ 199</span>
              </button>
              <button type="button" class="data-pack" data-a="299">
                <span>10 GB</span>
                <span>30 Days</span>
                <span>৳ 299</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Amount -->
        <div class="row">
          <div class="label">৳ Recharge Amount</div>
          <div>
            <input class="input" id="amount" type="number" inputmode="decimal" placeholder="৳ Enter amount here">
            <div class="quick" id="talktimeQuick">
              <button class="chip" data-a="20">৳ 20</button>
              <button class="chip" data-a="50">৳ 50</button>
              <button class="chip" data-a="100">৳ 100</button>
              <button class="chip" data-a="500">৳ 500</button>
            </div>
          </div>
        </div>

        <div class="footerbar">
          <a class="btn next-disabled" id="next" href="recharge-overview.php">Next</a>
        </div>

      </div>
    </div>
  </div>

<script>
  // hydrate MSISDN from localStorage if coming from contacts
  function hydrate(){
    var saved = localStorage.getItem('re_msisdn');
    if(saved){
      document.getElementById('msisdn').value = saved;
    }
  }

  function validate(){
    var payFrom = document.getElementById('payFrom').value;
    var amount  = document.getElementById('amount').value;
    var msisdn  = document.getElementById('msisdn').value.trim();
    var ok = payFrom && (amount > 0) && msisdn;
    var nextBtn = document.getElementById('next');
    nextBtn.classList.toggle('next-disabled', !ok);
  }

  // amount quick chips (talktime)
  document.querySelectorAll('.chip').forEach(function(c){
    c.addEventListener('click', function(){
      document.getElementById('amount').value = c.dataset.a;
      validate();
    });
  });

  // internet data packs
  document.querySelectorAll('.data-pack').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.getElementById('amount').value = btn.dataset.a;
      validate();
    });
  });

  // history items -> set msisdn
  document.querySelectorAll('.history-item').forEach(function(item){
    item.addEventListener('click', function(){
      document.getElementById('msisdn').value = item.dataset.msisdn;
      validate();
    });
  });

  // tabs
  document.querySelectorAll('.tab').forEach(function(t){
    t.addEventListener('click', function(){
      document.querySelectorAll('.tab').forEach(function(x){ x.classList.remove('active'); });
      t.classList.add('active');

      var type = t.getAttribute('data-t');

      // show/hide sections based on selected tab
      var historySec  = document.getElementById('historySection');
      var internetSec = document.getElementById('internetSection');

      if (type === 'history') {
        if (historySec) historySec.style.display  = 'flex';
        if (internetSec) internetSec.style.display = 'none';
      } else if (type === 'internet') {
        if (historySec) historySec.style.display  = 'none';
        if (internetSec) internetSec.style.display = 'flex';
      } else { // 'recharge'
        if (historySec) historySec.style.display  = 'none';
        if (internetSec) internetSec.style.display = 'none';
      }
    });
  });

  hydrate();
  validate();

  document.getElementById('payFrom').addEventListener('change', validate);
  document.getElementById('amount').addEventListener('input', validate);
  document.getElementById('msisdn').addEventListener('input', validate);

  // persist selections for overview
  document.getElementById('next').addEventListener('click', function(e){
    if (this.classList.contains('next-disabled')) {
      e.preventDefault();
      return false;
    }
    localStorage.setItem('re_from', document.getElementById('payFrom').value || '');
    localStorage.setItem('re_amt', document.getElementById('amount').value || '0');
    localStorage.setItem('re_msisdn', document.getElementById('msisdn').value.trim() || '');
  });
</script>
</body>
</html>
