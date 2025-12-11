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

            // 2) Check if this CID already exists in `user` (Astra portal)
            if (!$errors) {
                $stmt = $conn->prepare("SELECT cid FROM user WHERE cid = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $cid);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $errors[] = "This Customer ID is already registered for the online portal.";
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
                        $success = "Your Astra Bank Customer Portal account has been created. You can now log in.";
                        // Optionally auto-redirect:
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
  <title>Sign Up — Astra Bank Customer Portal</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary:#00416A;
      --accent:#00A1FF;
      --bg:#F3F6F9;
      --muted:#6b7280;
      --border:#e5e7eb;
      --radius:14px;
    }
    * {
      margin:0;
      padding:0;
      box-sizing:border-box;
      font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }
    body {
      background: linear-gradient(135deg, #00416A, #E4E5E6);
      min-height:100vh;
      display:flex;
      flex-direction:column;
      color:#111827;
    }
    .topbar {
      width:100%;
      background:rgba(0,0,0,0.15);
      backdrop-filter:blur(8px);
      padding:14px 0;
      border-bottom:1px solid rgba(255,255,255,0.3);
    }
    .container {
      width:95%;
      max-width:1100px;
      margin:0 auto;
      display:flex;
      align-items:center;
      justify-content:space-between;
    }
    .brand {
      display:flex;
      align-items:center;
      gap:10px;
      color:#fff;
      font-weight:700;
      font-size:20px;
    }
    .logo {
      background:#ffffff;
      color:#00416A;
      border-radius:50%;
      width:36px;
      height:36px;
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:900;
      font-size:17px;
    }
    .brand-text {
      letter-spacing:.02em;
    }
    .nav a {
      color:#f9fafb;
      text-decoration:none;
      margin:0 10px;
      font-weight:500;
      opacity:.9;
      font-size:14px;
    }
    .nav a[aria-current="page"] {
      text-decoration:underline;
      opacity:1;
    }
    .nav a:hover {
      opacity:1;
      text-decoration:underline;
    }
    main {
      flex:1;
      width:95%;
      max-width:1100px;
      margin:0 auto;
      padding:30px 0 60px;
      color:#111827;
    }

    h1 {
      color:#ffffff;
      font-size:28px;
      font-weight:800;
      margin:0 0 8px;
      text-shadow:0 1px 3px rgba(0,0,0,0.35);
    }
    .intro {
      color:#e5e7eb;
      margin:0 0 20px;
      font-size:14px;
      max-width:700px;
    }
    .intro a {
      color:#facc15;
      text-decoration:underline;
    }

    .form-card {
      background:#ffffff;
      border-radius:var(--radius);
      padding:22px 20px 24px;
      box-shadow:0 8px 30px rgba(0,0,0,0.25);
      border:1px solid rgba(255,255,255,0.7);
    }

    fieldset.fieldset {
      border:none;
      margin:0 0 18px;
      padding:12px 0 0;
      border-top:1px solid var(--border);
    }
    fieldset.fieldset:first-of-type {
      border-top:none;
      padding-top:0;
    }
    legend {
      font-size:14px;
      font-weight:700;
      color:#111827;
      padding:0 3px;
    }

    .row {
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:14px;
      margin-top:10px;
    }
    @media (max-width:900px){
      .row { grid-template-columns:1fr; }
    }

    .field {
      display:flex;
      flex-direction:column;
      gap:4px;
      font-size:13px;
    }
    .field span {
      font-weight:600;
      color:#374151;
    }
    .field input,
    .field select,
    .field textarea {
      border:1px solid var(--border);
      border-radius:10px;
      padding:8px 10px;
      font-size:14px;
      outline:none;
      background:#f9fafb;
    }
    .field input:focus,
    .field select:focus,
    .field textarea:focus {
      border-color:var(--accent);
      box-shadow:0 0 0 1px rgba(0,161,255,0.25);
      background:#ffffff;
    }

    .help-text {
      font-size:12px;
      color:var(--muted);
      margin-top:6px;
    }

    .checkbox {
      font-size:13px;
      color:#374151;
      display:flex;
      gap:8px;
      align-items:flex-start;
      margin-top:6px;
    }
    .checkbox input {
      margin-top:2px;
    }
    .checkbox a {
      color:var(--primary);
      text-decoration:underline;
    }

    .actions {
      margin-top:14px;
      display:flex;
      justify-content:flex-end;
      gap:10px;
      flex-wrap:wrap;
    }

    .btn {
      padding:9px 18px;
      border-radius:999px;
      border:none;
      background:var(--accent);
      color:#ffffff;
      font-weight:600;
      font-size:14px;
      cursor:pointer;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:6px;
    }
    .btn:hover {
      filter:brightness(1.05);
    }
    .btn-outline {
      background:transparent;
      color:#ffffff;
      border:1.5px solid #ffffff;
    }
    .btn-outline:hover {
      background:rgba(255,255,255,0.18);
    }

    .alert-error {
      background:#fee2e2;
      border:1px solid #fca5a5;
      border-radius:10px;
      padding:10px 12px;
      font-size:13px;
      color:#b91c1c;
    }
    .alert-success {
      background:#dcfce7;
      border:1px solid #86efac;
      border-radius:10px;
      padding:10px 12px;
      font-size:13px;
      color:#166534;
    }

    .footer {
      background:rgba(0,0,0,0.2);
      color:#e5e7eb;
      padding:14px 0;
      margin-top:auto;
      backdrop-filter:blur(6px);
      font-size:13px;
    }
    .footer-inner {
      width:95%;
      max-width:1100px;
      margin:0 auto;
      display:flex;
      justify-content:space-between;
      align-items:center;
      flex-wrap:wrap;
      gap:8px;
    }
    .footer-links a {
      color:#e5e7eb;
      text-decoration:none;
      margin-left:10px;
      opacity:.85;
    }
    .footer-links a:hover {
      opacity:1;
      text-decoration:underline;
    }
  </style>
</head>
<body>

  <!-- TOP NAV -->
  <header class="topbar">
    <div class="container">
      <div class="brand">
        <span class="logo">AB</span>
        <span class="brand-text">Astra Bank</span>
      </div>
      <nav class="nav">
        <a href="index.php">Home</a>
        <a href="open-account.php">Open an Account</a>
        <a href="#" aria-current="page">Sign Up</a>
        <a href="#">Support</a>
      </nav>
    </div>
  </header>

  <!-- MAIN CONTENT -->
  <main>
    <h1>Create Your Customer Portal Account</h1>
    <p class="intro">
      For existing Astra Bank customers who don’t have an online portal account yet.
      New to Astra Bank?
      <a href="open-account.php">Open a bank account</a> first.
    </p>

    <?php if ($errors): ?>
      <div class="alert-error" style="margin-bottom:16px;">
        <ul style="margin:0; padding-left:18px;">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert-success" style="margin-bottom:16px;">
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
        <a class="btn btn-outline" href="index.php">Back</a>
        <button class="btn" type="submit">Create Portal Account</button>
      </div>

      <p class="help-text">You’ll receive a verification code to activate your Astra Bank Customer Portal access.</p>
    </form>
  </main>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-inner">
      <p>© <span id="year"></span> Astra Bank.</p>
      <div class="footer-links">
        <a href="#">Privacy</a>
        <a href="#">Terms</a>
      </div>
    </div>
  </footer>

  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>
