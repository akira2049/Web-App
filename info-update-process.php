<?php
session_start();
/*
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
*/

$cid = $_SESSION['cid'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: info-update.php");
    exit;
}

// Get posted values
$auth_mode      = trim($_POST['auth_mode']      ?? '');
$auth_value     = trim($_POST['auth_value']     ?? '');
$info_type      = trim($_POST['info_type']      ?? '');
$target_type    = trim($_POST['target_type']    ?? '');
$target_account = trim($_POST['target_account'] ?? '');
$card_pin       = trim($_POST['card_pin']       ?? '');

$errors = [];

// Basic validations
if (!$cid) {
    $errors[] = "User not logged in.";
}
if ($auth_mode !== 'password' && $auth_mode !== 'pin') {
    $errors[] = "Invalid verification mode.";
}
if ($auth_value === '') {
    $errors[] = "Verification value is required.";
}
if (!in_array($info_type, ['mobile','email','mailing'], true)) {
    $errors[] = "Invalid info type.";
}
if (!in_array($target_type, ['Account','Card'], true)) {
    $errors[] = "Invalid target type.";
}
if ($target_account === '') {
    $errors[] = "Target account/card is required.";
}
if ($card_pin === '' || strlen($card_pin) < 4) {
    $errors[] = "Invalid card PIN.";
}

if (!empty($errors)) {
    echo "<h2>Validation errors</h2><ul>";
    foreach ($errors as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul><p><a href='info-update.php'>&larr; Back</a></p>";
    exit;
}

// ---- DB CONNECTION ----
$host="localhost"; $user="root"; $pass=""; $db="my_bank";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/*
  user table: cid, phone, email, user_password, user_type
*/

// Load user row
$stmt = $conn->prepare("SELECT user_password FROM user WHERE cid = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
$userRow = $res->fetch_assoc();
$stmt->close();

if (!$userRow) {
    $conn->close();
    die("User not found.");
}

$dbPassword = $userRow['user_password'];

// Simple/plain comparison (replace with password_verify if you hash passwords)
if ($auth_value !== $dbPassword) {
    $conn->close();
    die("Invalid credentials. <br><a href='info-update.php'>&larr; Back</a>");
}

/*
  At this point, verification passed.

  Right now we are NOT writing anything to another table,
  because there is no info_update_requests table in your DB.

  Later, if you add fields like new_mobile / new_email / new_address,
  you can do an UPDATE on the user table here based on $info_type.
*/

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Info Update — Submitted</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    :root { --primary:#00416A; }

    /* Dashboard-style background */
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

    .h1 {
      color:#ffffff;
      text-shadow:0 1px 2px rgba(0,0,0,0.25);
      margin-bottom: 12px;
    }

    .msg-title {
      font-size:20px;
      font-weight:800;
      color:var(--primary);
      margin-bottom:6px;
    }

    .msg-body {
      font-size:14px;
      color:#4b5563;
      line-height:1.5;
    }

    .kv-label {
      font-size:13px;
      font-weight:600;
      color:#6b7280;
    }

    .kv-value {
      font-size:14px;
      font-weight:700;
      color:var(--primary);
    }

    .summary-box {
      margin-top:12px;
      padding:12px 14px;
      border-radius:10px;
      border:1px solid var(--border);
      background:#f9fafb;
      font-size:14px;
    }

    .btn-row {
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      margin-top:18px;
    }

    .btn-ghost {
      background:#fff;
      color:var(--primary);
      border:1px solid var(--border);
      border-radius:14px;
      padding:10px 16px;
      font-size:14px;
      font-weight:600;
      cursor:pointer;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
    }
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="info-update.php">← Back</a>
    </div>

    <div class="h1">Info Update Request</div>

    <div class="card">
      <div class="section">
        <div class="msg-title">Request Submitted</div>
        <p class="msg-body">
          Your verification was successful and your information update request has been recorded.
        </p>

        <div class="summary-box">
          <div style="margin-bottom:6px;">
            <span class="kv-label">Info to update:</span>
            <span class="kv-value">
              <?php echo htmlspecialchars($info_type); ?>
            </span>
          </div>
          <div>
            <span class="kv-label">Target:</span>
            <span class="kv-value">
              <?php echo htmlspecialchars($target_type); ?>
              — <?php echo htmlspecialchars($target_account); ?>
            </span>
          </div>
        </div>

        <div class="btn-row">
          <a href="dashboard.php" class="btn">Go to Dashboard</a>
          <a href="info-update.php" class="btn-ghost">Submit Another Request</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
