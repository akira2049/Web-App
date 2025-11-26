<?php
session_start();
/*
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
*/

/*
  Load all active billers for search.
  Table: billers(biller_code, biller_name, short_name, biller_category, biller_status)
*/
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$sql = "
    SELECT biller_code, biller_name, short_name, biller_category
    FROM billers
    WHERE LOWER(biller_status) = 'active'
    ORDER BY biller_name ASC
";
$res = $conn->query($sql);

$billers = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $billers[] = $row;
    }
}
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bill Payments</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .bill-search-wrap{
      display:flex;
      align-items:center;
      gap:8px;
      border:1px solid var(--border);
      border-radius:12px;
      padding:12px 14px;
      background:#fff;
    }
    .bill-search-icon{
      font-size:16px;
      line-height:1;
      color:var(--muted);
    }
    .bill-search-input{
      border:none;
      outline:none;
      flex:1;
      font-size:15px;
      color:var(--primary);
      background:transparent;
    }
    .bill-search-input::placeholder{
      color:var(--muted);
    }

    .pill-row{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:16px;
      margin-bottom:20px;
    }
    .pill-btn{
      flex:1 1 auto;
      min-width:140px;
      display:flex;
      align-items:center;
      gap:8px;
      border:1px solid var(--border);
      border-radius:14px;
      padding:12px 14px;
      background:#fff;
      cursor:pointer;
      font-weight:600;
      font-size:14px;
      color:var(--primary);
    }

    .grid-cats{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:16px;
    }
    @media (max-width:640px){
      .grid-cats{grid-template-columns:repeat(2,minmax(0,1fr));}
    }

    .cat-tile{
      border:1px solid var(--border);
      border-radius:14px;
      background:#fff;
      box-shadow:var(--shadow);
      padding:16px;
      display:flex;
      flex-direction:column;
      align-items:flex-start;
      justify-content:flex-start;
      gap:8px;
      cursor:pointer;
      font-size:14px;
      font-weight:600;
      color:var(--primary);
      min-height:110px;
      text-decoration:none;
    }
    .cat-icon{
      font-size:22px;
      line-height:1;
      color:var(--primary);
    }

    .history-row{
      display:flex;
      align-items:center;
      gap:10px;
      margin-top:28px;
      font-weight:600;
      font-size:15px;
      color:var(--primary);
      cursor:pointer;
    }

    /* Billers search result list */
    .biller-results{
      margin-top:12px;
      display:none; /* hidden until user types */
    }
    .biller-list{
      display:flex;
      flex-direction:column;
      gap:12px;
    }
    .biller-item{
      padding:10px 0;
      border-bottom:1px solid var(--border);
    }
    .biller-btn{
      all:unset;
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:10px;
      width:100%;
      cursor:pointer;
    }
    .biller-left{
      display:flex;
      gap:10px;
      align-items:flex-start;
    }
    .biller-logo{
      width:32px;
      height:32px;
      border-radius:6px;
      background:#fff;
      border:1px solid var(--border);
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:11px;
      font-weight:700;
      color:var(--primary);
      min-width:32px;
    }
    .biller-name{
      font-weight:700;
      color:var(--primary);
      font-size:15px;
      line-height:1.3;
    }
    .biller-sub{
      color:var(--muted);
      font-size:13px;
      line-height:1.3;
    }
    .biller-empty{
      font-size:14px;
      color:var(--muted);
      margin-top:8px;
    }
  </style>
</head>
<body>
  <div class="app">

    <div class="topbar">
      <a class="linkish" href="dashboard.php">← Back</a>
    </div>

    <div class="h1" style="margin-bottom:8px;">Bill Payments</div>
    <div class="kv" style="margin-top:-8px; margin-bottom:16px;">Pay your bills</div>

    <div class="card">
      <div class="section">

        <!-- Search -->
        <div class="bill-search-wrap">
          <span class="bill-search-icon">🔍</span>
          <input
            class="bill-search-input"
            id="billerSearch"
            type="text"
            placeholder="Search biller by name, code or ID (e.g. SamOnline, 2077)..."
            aria-label="Search biller"
          />
        </div>

        <!-- Search Results: Billers -->
        <div class="biller-results" id="billerResults">
          <?php if (empty($billers)): ?>
            <div class="biller-empty">No billers available.</div>
          <?php else: ?>
            <div class="biller-list" id="billerList">
              <?php foreach ($billers as $b): ?>
                <?php
                  // Build searchable label: name + short_name + code + category
                  $label = strtolower(
                    ($b['biller_name']     ?? '') . ' ' .
                    ($b['short_name']      ?? '') . ' ' .
                    ($b['biller_code']     ?? '') . ' ' .
                    ($b['biller_category'] ?? '')
                  );
                ?>
                <div class="biller-item"
                     data-label="<?php echo htmlspecialchars($label); ?>">
                  <form method="post" action="bill-amount.php">
                    <input type="hidden" name="biller_id"
                           value="<?php echo htmlspecialchars($b['biller_code']); ?>">
                    <input type="hidden" name="biller_name"
                           value="<?php echo htmlspecialchars($b['biller_name']); ?>">
                    <input type="hidden" name="biller_logo"
                           value="<?php echo htmlspecialchars($b['short_name']); ?>">
                    <!-- keep name 'biller_type' for next page, but value is category -->
                    <input type="hidden" name="biller_type"
                           value="<?php echo htmlspecialchars($b['biller_category']); ?>">

                    <button type="submit" class="biller-btn">
                      <div class="biller-left">
                        <div class="biller-logo">
                          <?php echo htmlspecialchars($b['short_name'] ?: 'BL'); ?>
                        </div>
                        <div>
                          <div class="biller-name">
                            <?php echo htmlspecialchars($b['biller_name']); ?>
                          </div>
                          <div class="biller-sub">
                            Type: <?php echo htmlspecialchars($b['biller_category']); ?>
                            • ID: <?php echo htmlspecialchars($b['biller_code']); ?>
                          </div>
                        </div>
                      </div>
                      <div>›</div>
                    </button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Pills -->
        <div class="pill-row">
          <button class="pill-btn">
            <span style="font-size:18px;">🕘</span>
            <span>Recent Payment</span>
          </button>
          <button class="pill-btn">
            <span style="font-size:18px;">💾</span>
            <span>Saved Billers</span>
          </button>
        </div>

        <!-- Categories (shown when search is empty) -->
        <div id="catGridWrap">
          <div class="grid-cats" id="catGrid">
            <a class="cat-tile"
               href="bill-billers.php?cat=Electricity"
               data-label="Electricity Power Utility Electric bill">
              <div class="cat-icon">⚡</div>
              <div>Electricity</div>
            </a>

            <a class="cat-tile"
               href="bill-billers.php?cat=Water"
               data-label="Water WASA Water bill">
              <div class="cat-icon">💧</div>
              <div>Water</div>
            </a>

            <a class="cat-tile"
               href="bill-billers.php?cat=Telephone"
               data-label="Telephone Landline Phone Bill Telephone bill">
              <div class="cat-icon">📞</div>
              <div>Telephone</div>
            </a>

            <a class="cat-tile"
               href="bill-billers.php?cat=Internet"
               data-label="Internet ISP SamOnline Internet bill">
              <div class="cat-icon">🌐</div>
              <div>Internet</div>
            </a>

            <a class="cat-tile"
               href="bill-billers.php?cat=TV"
               data-label="TV Cable DTH Television bill">
              <div class="cat-icon">📺</div>
              <div>TV</div>
            </a>

            <a class="cat-tile"
               href="bill-billers.php?cat=Tuition Fee"
               data-label="Tuition Fee School College University Education">
              <div class="cat-icon">🎓</div>
              <div>Tuition Fee</div>
            </a>

            <a class="cat-tile"
               href="bill-billers.php?cat=VISA Fee"
               data-label="VISA Fee Visa Application Embassy">
              <div class="cat-icon">📘</div>
              <div>VISA Fee</div>
            </a>
          </div>
        </div>

        <!-- Payment history row -->
        <div class="history-row" style="margin-bottom:4px;">
          <span style="font-size:18px;">📄</span>
          <span>Payment History</span>
          <span style="margin-left:auto;">›</span>
        </div>

      </div>
    </div>
  </div>

<script>
  const searchInput   = document.getElementById('billerSearch');
  const billerResults = document.getElementById('billerResults');
  const catGridWrap   = document.getElementById('catGridWrap');
  const billerItems   = Array.from(document.querySelectorAll('.biller-item'));

  function applySearch() {
    const q = (searchInput.value || '').trim().toLowerCase();

    if (!q) {
      // Empty search: hide biller results, show categories
      billerResults.style.display = 'none';
      catGridWrap.style.display   = '';
      billerItems.forEach(item => { item.style.display = ''; });
      return;
    }

    // When user types: show biller list, hide categories
    billerResults.style.display = 'block';
    catGridWrap.style.display   = 'none';

    billerItems.forEach(item => {
      const label = (item.getAttribute('data-label') || '').toLowerCase();
      item.style.display = label.includes(q) ? '' : 'none';
    });
  }

  searchInput.addEventListener('input', applySearch);
</script>
</body>
</html>
