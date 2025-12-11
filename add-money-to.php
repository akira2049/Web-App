<?php
session_start();
if (!isset($_SESSION['cid'])) { 
    header("Location: login.php"); 
    exit; 
}
if (!isset($_SESSION['am_amount'])) { 
    header("Location: add-money-amount.php"); 
    exit; 
}

$cid = $_SESSION['cid'];

// DB connection
$host = "localhost"; 
$user = "root"; 
$pass = ""; 
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/* -------------------------------------------
   LOAD MY ACCOUNTS + HOLDER NAME
--------------------------------------------*/
$myAccounts = [];
$userName = "Account Holder";

$stmt = $conn->prepare("
    SELECT AccountNo, account_name 
    FROM accounts 
    WHERE CustomerID = ? 
    ORDER BY AccountNo ASC
");
if (!$stmt) die("Prepare failed: ".$conn->error);

// CustomerID is VARCHAR in your schema → use "s"
$stmt->bind_param("s", $cid);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $myAccounts[] = $row['AccountNo'];
    if ($userName === "Account Holder" && !empty($row['account_name'])) {
        $userName = $row['account_name'];
    }
}
$stmt->close();

/* -------------------------------------------
   LOAD OTHER DBL ACCOUNTS (EXCLUDE MINE)
   (currently not shown in UI, but kept for future)
--------------------------------------------*/
$otherAccounts = [];
$otherNames = [];

$stmt = $conn->prepare("
    SELECT AccountNo, account_name
    FROM accounts 
    WHERE CustomerID <> ?
    ORDER BY AccountNo ASC
");
// CustomerID is VARCHAR → "s"
$stmt->bind_param("s", $cid);
$stmt->execute();
$r2 = $stmt->get_result();

while ($row = $r2->fetch_assoc()) {
    $otherAccounts[] = $row['AccountNo'];
    $otherNames[$row['AccountNo']] = $row['account_name'];
}
$stmt->close();
$conn->close();

/* -------------------------------------------
   HANDLE SUBMISSION
--------------------------------------------*/
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $toAcc = $_POST['acct'] ?? '';
    $mode  = $_POST['mode'] ?? 'my';  // which tab selected

    if ($toAcc === '') {
        $error = "Please select an account.";
    } else {

        if ($mode === "my") {
            $_SESSION['am_to_acc']  = $toAcc;
            $_SESSION['am_to_name'] = $userName;
        } else {
            $_SESSION['am_to_acc']  = $toAcc;
            $_SESSION['am_to_name'] = "Other DBL User";
        }

        header("Location: add-money-overview.php");
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add To — Account</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    /* DASHBOARD-THEME BACKGROUND + CENTERED CARD */
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 24px 12px;
    }

    .app {
      width: 100%;
      max-width: 720px;
    }

    .card {
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }

    /* Existing local styles */
    .tabs{display:flex; gap:14px; margin-bottom:8px}
    .tab{padding:10px 14px; border:1px solid var(--border); border-radius:999px; cursor:pointer; font-weight:700}
    .tab[aria-selected="true"]{background:var(--primary); color:#fff; border-color:var(--primary)}
    .err{color:#b00020;font-weight:700;margin:8px 0;}
  </style>
</head>
<body>
  <div class="app">

    <div class="topbar">
      <a class="linkish" href="add-money-amount.php">← Back</a>
      <span class="step">3 / 4</span>
    </div>

    <div class="h1">Add To</div>

    <div class="card">
      <div class="section">

        <div class="tabs">
          <button id="myTab" class="tab" aria-selected="true" type="button">My Account</button>
        </div>

        <?php if($error): ?>
          <div class="err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" id="mode" name="mode" value="my">

          <div class="row">
            <div class="label">Add Money To</div>

            <select id="acct" name="acct" required>
              <!-- Default loads MY accounts -->
              <option value="">-- Select --</option>
              <?php foreach($myAccounts as $acc): ?>
                <option value="<?= htmlspecialchars($acc) ?>">
                  <?= htmlspecialchars($acc) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row">
            <div class="label"></div>
            <div class="ahd">
              <div style="font-size:28px">🏦</div>
              <div>
                <div class="name" id="accName">
                  ACCOUNT HOLDER : SELECT ACCOUNT
                </div>
                <div class="kv" id="accType"></div>
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
  const acct    = document.getElementById('acct');
  const accName = document.getElementById('accName');
  const accType = document.getElementById('accType');

  const myAccounts    = <?php echo json_encode($myAccounts); ?>;
  const myHolderName  = "<?= htmlspecialchars($userName) ?>";

  // Initially EMPTY name
  accName.innerText = "ACCOUNT HOLDER : SELECT ACCOUNT";
  accType.innerText = "";

  // When selecting an account, show your name
  acct.addEventListener('change', () => {
      const selected = acct.value;

      if (selected !== "") {
          accName.innerText = "ACCOUNT HOLDER : " + myHolderName.toUpperCase();
          accType.innerText = "My Account";
      } else {
          accName.innerText = "ACCOUNT HOLDER : SELECT ACCOUNT";
          accType.innerText = "";
      }
  });
</script>

</body>
</html>
