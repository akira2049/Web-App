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

$message      = "";
$step         = "password"; // password | update | done
$currentEmail = "";
$currentPhone = "";

// Load current info from accounts table
$stmt = $conn->prepare("SELECT email, phone FROM accounts WHERE CustomerID = ? LIMIT 1");
$stmt->bind_param("s", $cid);
$stmt->execute();
$stmt->bind_result($currentEmail, $currentPhone);
$stmt->fetch();
$stmt->close();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '';

    // STEP 1: Verify password
    if ($mode === 'verify') {
        $password = trim($_POST['password'] ?? '');

        // Check password from `user` table (adjust table/column names if different)
        $stmt = $conn->prepare("SELECT user_password FROM user WHERE cid = ? LIMIT 1");
        $stmt->bind_param("s", $cid);
        $stmt->execute();
        $stmt->bind_result($dbPassword);
        if ($stmt->fetch()) {
            // Plain text check; change to password_verify() if you use hashed passwords
            if ($password === $dbPassword) {
                $_SESSION['info_update_verified'] = true;
                $step = "update";
            } else {
                $message = "Incorrect password. Please try again.";
                $step = "password";
            }
        } else {
            $message = "User not found.";
            $step = "password";
        }
        $stmt->close();
    }

    // STEP 2: Submit new email/phone
    elseif ($mode === 'update') {
        if (empty($_SESSION['info_update_verified'])) {
            $message = "Please verify your password first.";
            $step = "password";
        } else {
            $newEmail = trim($_POST['email'] ?? '');
            $newPhone = trim($_POST['phone'] ?? '');

            if ($newEmail === "" && $newPhone === "") {
                $message = "Please enter at least a new email or a new phone number.";
                $step = "update";
            } else {
                // Make sure there is only one pending request per CID
                $stmt = $conn->prepare("DELETE FROM info_update_requests WHERE cid = ?");
                $stmt->bind_param("s", $cid);
                $stmt->execute();
                $stmt->close();

                // Insert new request (empty fields allowed if user only changes one)
                $stmt = $conn->prepare(
                    "INSERT INTO info_update_requests (cid, email, phone) VALUES (?,?,?)"
                );
                $emailParam = ($newEmail === "") ? null : $newEmail;
                $phoneParam = ($newPhone === "") ? null : $newPhone;

                $stmt->bind_param("sss", $cid, $emailParam, $phoneParam);
                $stmt->execute();
                $stmt->close();

                unset($_SESSION['info_update_verified']);
                $message = "Your information update request has been submitted. An admin will review it.";
                $step = "done";
            }
        }
    }
} else {
    if (!empty($_SESSION['info_update_verified'])) {
        $step = "update";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Update Contact Info</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .subtitle {
      color: var(--muted);
      font-size: 14px;
      margin-bottom: 18px;
    }
    .section-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .info-row {
      font-size: 14px;
      margin-bottom: 4px;
    }
    .info-label {
      font-weight: 600;
      color: #4b5563;
    }
    .msg {
      margin-bottom: 12px;
      font-size: 14px;
    }
    .msg.error { color: #b91c1c; }
    .msg.success { color: #166534; }
  </style>
</head>
<body>
<div class="app">
  <div class="topbar">
    <a class="linkish" href="dashboard.php">← Back to Dashboard</a>
  </div>

  <div class="h1">Update Contact Info</div>

  <div class="card">
    <div class="section">
      <?php if ($message): ?>
        <div class="msg <?php echo ($step === 'done') ? 'success' : 'error'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>

      <?php if ($step === 'password'): ?>
        <div class="section-title">Step 1 — Confirm Password</div>
        <p class="subtitle">
          For security reasons, please enter your account password before changing your email or phone number.
        </p>

        <form method="post">
          <input type="hidden" name="mode" value="verify">
          <label class="field-label">Password</label>
          <input type="password" name="password" required class="field">
          <div style="margin-top: 12px;">
            <button type="submit" class="btn">Verify Password</button>
          </div>
        </form>

      <?php elseif ($step === 'update'): ?>
        <div class="section-title">Step 2 — Request New Contact Info</div>
        <p class="subtitle">
          Current details from your account. Enter new values below to request an update.
        </p>

        <div class="info-row">
          <span class="info-label">Current Email:</span>
          <span><?php echo htmlspecialchars($currentEmail ?: "Not set"); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Current Phone:</span>
          <span><?php echo htmlspecialchars($currentPhone ?: "Not set"); ?></span>
        </div>

        <form method="post" style="margin-top: 16px;">
          <input type="hidden" name="mode" value="update">

          <label class="field-label">New Email</label>
          <input type="email" name="email" class="field" placeholder="Enter new email">

          <label class="field-label" style="margin-top:10px;">New Phone</label>
          <input type="text" name="phone" class="field" placeholder="Enter new phone">

          <p class="subtitle" style="margin-top:8px;">
            Leave a field blank if you do not want to change it.
          </p>

          <div style="margin-top: 12px;">
            <button type="submit" class="btn">Submit Update Request</button>
          </div>
        </form>

      <?php elseif ($step === 'done'): ?>
        <div class="section-title">Request Submitted</div>
        <p class="subtitle">
          Your request has been sent to the admin. Once approved, your contact information will be updated.
        </p>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
<?php
$conn->close();
?>
