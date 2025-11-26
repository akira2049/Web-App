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
</head>
<body>
  <div class="app">
    <div class="card">
      <div class="section">
        <h2>Info Update Request Submitted</h2>
        <p>Your verification was successful.</p>
        <p>
          Info to update: <b><?php echo htmlspecialchars($info_type); ?></b><br>
          Target: <b><?php echo htmlspecialchars($target_type); ?></b> —
          <b><?php echo htmlspecialchars($target_account); ?></b>
        </p>
        <p>You may go back to your <a href="dashboard.php">dashboard</a>.</p>
      </div>
    </div>
  </div>
</body>
</html>
