<?php
// signup.php
session_start();

// ---- DB CONFIG ----
$host     = "localhost";
$db_user  = "root";
$db_pass  = "";
$db_name  = "my_bank";

$errors = [];
$success = "";

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic sanitization
    $account_number = trim($_POST['account_number'] ?? '');
    $dob            = trim($_POST['dob'] ?? '');
    $id_last4       = trim($_POST['id_last4'] ?? '');

    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $cid_input      = trim($_POST['username'] ?? '');   // Customer ID from form
    $password       = trim($_POST['password'] ?? '');
    $password2      = trim($_POST['password_confirm'] ?? '');
    $mfa            = trim($_POST['mfa'] ?? '');
    $terms          = isset($_POST['terms']);

    // ---- SERVER-SIDE VALIDATION ----

    if ($account_number === '') {
        $errors[] = "Bank account number is required.";
    }
    if ($dob === '') {
        $errors[] = "Date of birth is required.";
    }
    if ($id_last4 === '' || strlen($id_last4) !== 4) {
        $errors[] = "Last 4 of NID/Passport is required.";
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email address is required.";
    }
    if ($phone === '') {
        $errors[] = "Mobile number is required.";
    }
    if ($cid_input === '' || !ctype_digit($cid_input)) {
        $errors[] = "Customer ID must be numeric.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($password !== $password2) {
        $errors[] = "Passwords do not match.";
    }

    if ($mfa === '') {
        $errors[] = "Please select a 2-step verification method.";
    }
    if (!$terms) {
        $errors[] = "You must agree to the Terms of Use and Privacy Policy.";
    }

    // Convert Customer ID to integer for DB
    $cid = (int)$cid_input;

    if (!$errors) {
        // Connect DB
        $conn = new mysqli($host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            $errors[] = "Database connection failed: " . $conn->connect_error;
        } else {

            /* -------------------------------------------------
               STEP 1: Verify banking relationship (REAL SQL)
               - Check that account exists in `accounts`
               - Check last 4 of NID matches
               - Check CustomerID matches provided Customer ID
            --------------------------------------------------*/
            $stmt = $conn->prepare("SELECT CustomerID, nid FROM accounts WHERE AccountNo = ?");
            if ($stmt) {
                $stmt->bind_param("s", $account_number);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows === 0) {
                    $errors[] = "We couldn't find a bank account with that number. Please check and try again.";
                } else {
                    $accRow       = $res->fetch_assoc();
                    $dbCustomerID = (string)$accRow['CustomerID'];
                    $dbNid        = $accRow['nid'];
                    $dbNidLast4   = substr($dbNid, -4);

                    // Check last-4 of NID
                    if ($dbNidLast4 !== $id_last4) {
                        $errors[] = "The last 4 digits of your NID/Passport do not match our records.";
                    }

                    // Ensure Customer ID matches the account's CustomerID
                    if ($dbCustomerID !== $cid_input) {
                        $errors[] = "The Customer ID does not match the owner of this account.";
                    }

                    // If later you store DOB in DB, add DOB check here
                }

                $stmt->close();
            } else {
                $errors[] = "Failed to prepare account verification query.";
            }

            // 2) Check if this CID already exists in `user` (iCloud portal)
            if (!$errors) {
                $stmt = $conn->prepare("SELECT cid FROM user WHERE cid = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $cid);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $errors[] = "This Customer ID is already registered for iCloud portal.";
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Failed to prepare user lookup query.";
                }
            }

            // If still no errors, create new user
            if (!$errors) {
                // For production, you SHOULD hash the password:
                // $hash = password_hash($password, PASSWORD_DEFAULT);
                // and store $hash instead of $password.
                $user_type = "customer";

                $stmt = $conn->prepare(
                    "INSERT INTO user (cid, phone, email, user_password, user_type) 
                     VALUES (?, ?, ?, ?, ?)"
                );

                if ($stmt) {
                    $stmt->bind_param("issss", $cid, $phone, $email, $password, $user_type);
                    if ($stmt->execute()) {
                        $success = "Your iCloud portal account has been created. You can now log in.";
                        // If you want auto-redirect to login:
                        // header("Location: login.php");
                        // exit;
                    } else {
                        $errors[] = "Failed to create account. Please try again.";
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Failed to prepare signup query.";
                }
            }

            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign Up — iCloud Customer Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="container">
      <div class="brand">
        <span class="logo">MB</span>
        <span class="brand-text">My Bank</span>
      </div>
      <nav class="nav">
        <a href="index.html">Home</a>
        <a href="open-account.html">Open an Account</a>
        <a href="#" aria-current="page">Sign Up</a>
        <a href="#">Support</a>
      </nav>
    </div>
  </header>

  <main class="container" style="padding:30px 0 60px;">
    <h1 style="color:#4b248c;margin:0 0 8px;">Create Your iCloud Portal Account</h1>
    <p style="color:#5e5e6a;margin:0 0 20px;">
      For existing Shanto Bank customers who don’t have an iCloud portal account. New to Shanto Bank?
      <a href="open-account.html">Open a bank account</a> first.
    </p>

    <?php if ($errors): ?>
      <div class="alert alert-error" style="margin-bottom:16px; color:#b00020;">
        <ul style="margin:0; padding-left:18px;">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:16px; color:#1b5e20;">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <form id="signup-form" class="form-card" action="" method="post" novalidate>
      <!-- Verify Banking Relationship -->
      <fieldset class="fieldset">
        <legend>Verify Banking Relationship</legend>
        <div class="row">
          <label class="field">
            <span>Bank Account Number</span>
            <input type="text" name="account_number" inputmode="numeric" minlength="8" maxlength="20" required
                   value="<?= htmlspecialchars($_POST['account_number'] ?? '') ?>" />
          </label>
          <label class="field">
            <span>Date of Birth</span>
            <input type="date" name="dob" required
                   value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" />
          </label>
          <label class="field">
            <span>Last 4 of NID/Passport</span>
            <input type="text" name="id_last4" pattern="[A-Za-z0-9]{4}" maxlength="4" required placeholder="XXXX"
                   value="<?= htmlspecialchars($_POST['id_last4'] ?? '') ?>" />
          </label>
        </div>
      </fieldset>

      <!-- Create Login -->
      <fieldset class="fieldset">
        <legend>Set Up Login</legend>
        <div class="row">
          <label class="field">
            <span>Email</span>
            <input type="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
          </label>
          <label class="field">
            <span>Mobile Number</span>
            <input type="tel" name="phone" pattern="[0-9+\-\s]{7,}" placeholder="+8801XXXXXXXXX" required
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" />
          </label>
          <label class="field">
            <span>Customer ID</span>
            <input type="text" name="username" minlength="4" maxlength="20" required
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" />
          </label>
        </div>
        <div class="row">
          <label class="field">
            <span>Password</span>
            <input type="password" name="password" id="pw1" minlength="8" required aria-describedby="pw-rules" />
          </label>
          <label class="field">
            <span>Confirm Password</span>
            <input type="password" name="password_confirm" id="pw2" minlength="8" required />
          </label>
          <label class="field">
            <span>2-Step Verification</span>
            <select name="mfa" required>
              <option value="" disabled <?= empty($_POST['mfa']) ? 'selected' : '' ?>>Select a method</option>
              <option value="sms"   <?= (($_POST['mfa'] ?? '') === 'sms') ? 'selected' : '' ?>>SMS Code</option>
              <option value="email" <?= (($_POST['mfa'] ?? '') === 'email') ? 'selected' : '' ?>>Email Code</option>
              <option value="auth_app" <?= (($_POST['mfa'] ?? '') === 'auth_app') ? 'selected' : '' ?>>Authenticator App</option>
            </select>
          </label>
        </div>
        <p id="pw-rules" class="help-text">
          Use at least 8 characters with a mix of letters, numbers, and symbols.
        </p>
      </fieldset>

      <!-- Agreements -->
      <fieldset class="fieldset">
        <legend>Agreements</legend>
        <label class="checkbox">
          <input type="checkbox" name="terms" <?= isset($_POST['terms']) ? 'checked' : '' ?> />
          <span>I agree to the <a href="#">Terms of Use</a> and <a href="#">Privacy Policy</a>.</span>
        </label>
        <label class="checkbox">
          <input type="checkbox" name="comm" <?= isset($_POST['comm']) ? 'checked' : '' ?> />
          <span>Send me security alerts and important account notifications.</span>
        </label>
      </fieldset>

      <div class="actions">
        <a class="btn btn-outline" href="index.html">Back</a>
        <button class="btn" type="submit">Create iCloud Account</button>
      </div>

      <p class="help-text">You’ll receive a verification code to activate your iCloud portal access.</p>
    </form>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <p>© <span id="year"></span> Shanto Bank.</p>
      <div class="footer-links"><a href="#">Privacy</a><a href="#">Terms</a></div>
    </div>
  </footer>

  <!-- Tiny inline script ONLY for year; no form blocking -->
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>
