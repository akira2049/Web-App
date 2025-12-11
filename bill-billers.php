<?php
session_start();
/*if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}*/

// Get category from query (e.g. ?cat=Internet, ?cat=gas, ?cat=internet)
$categoryRaw = $_GET['cat'] ?? '';
$categoryRaw = trim($categoryRaw);

if ($categoryRaw === '') {
    header("Location: bill-payments.php");
    exit;
}

// For DB comparison, use lowercase (matches biller_category like 'internet', 'gas')
$categoryKey   = strtolower($categoryRaw);
$categoryTitle = ucwords($categoryKey);   // For display (Internet, Gas, etc.)

// DB connection
$host="localhost"; $user="root"; $pass=""; $db="my_bank";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/*
  Table: billers(
    biller_code VARCHAR,
    biller_name VARCHAR,
    short_name VARCHAR,
    biller_category VARCHAR,
    biller_status VARCHAR
  )
*/
$sql = "
    SELECT biller_code, biller_name, short_name, biller_category
    FROM billers
    WHERE LOWER(biller_category) = ? 
      AND LOWER(biller_status) = 'active'
    ORDER BY biller_name ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error); // helps debug if something is off
}

$stmt->bind_param("s", $categoryKey);
$stmt->execute();
$res = $stmt->get_result();

$billers = [];
while ($row = $res->fetch_assoc()) {
    $billers[] = $row;
}
$stmt->close();
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Select Biller — <?php echo htmlspecialchars($categoryTitle); ?></title>

  <!-- Global styles -->
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="transfer.css">

  <style>
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
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    .backlink{
      text-decoration:none;
      color:var(--primary);
      font-weight:600;
      display:flex;
      align-items:center;
      gap:6px;
      font-size:15px;
    }
    .biller-list{
      display:flex;
      flex-direction:column;
      gap:16px;
    }
    .biller-item{
      padding:12px 0;
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
    .biller-type{
      color:var(--muted);
      font-size:13px;
      line-height:1.3;
    }
  </style>
</head>
<body>
  <div class="app">

    <div class="topbar" style="margin-bottom:4px;">
      <a class="backlink" href="bill-payments.php">← Back to categories</a>
      <span style="margin-left:auto;font-weight:600;"><?php echo htmlspecialchars($categoryTitle); ?></span>
    </div>

    <div class="card">
      <div class="section">

        <?php if (empty($billers)): ?>
          <p style="font-size:14px;color:var(--muted);">
            No billers found for this category.
          </p>
        <?php else: ?>
          <div class="biller-list">
            <?php foreach ($billers as $b): ?>
              <div class="biller-item">
                <form method="post" action="bill-amount.php">
                  <!-- use biller_code as the ID we carry forward -->
                  <input type="hidden" name="biller_id" value="<?php echo htmlspecialchars($b['biller_code']); ?>">
                  <input type="hidden" name="biller_name" value="<?php echo htmlspecialchars($b['biller_name']); ?>">
                  <input type="hidden" name="biller_logo" value="<?php echo htmlspecialchars($b['short_name']); ?>">
                  <input type="hidden" name="biller_category" value="<?php echo htmlspecialchars($b['biller_category']); ?>">
                  <button type="submit" class="biller-btn">
                    <div class="biller-left">
                      <div class="biller-logo">
                        <?php echo htmlspecialchars($b['short_name'] ?: substr($b['biller_name'],0,3)); ?>
                      </div>
                      <div>
                        <div class="biller-name">
                          <?php echo htmlspecialchars($b['biller_name']); ?>
                        </div>
                        <div class="biller-type">
                          Code: <?php echo htmlspecialchars($b['biller_code']); ?> • 
                          Category: <?php echo htmlspecialchars($b['biller_category']); ?>
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
    </div>

  </div>
</body>
</html>
