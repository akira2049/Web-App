<?php
// open_account.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Collect form values
    $account_type     = $_POST['account_type']    ?? '';
    $currency         = $_POST['currency']        ?? '';
    $initial_deposit  = isset($_POST['initial_deposit'])
                        ? (float)$_POST['initial_deposit']
                        : 0;
    $first_name       = $_POST['first_name']      ?? '';
    $last_name        = $_POST['last_name']       ?? '';
    $dob              = $_POST['dob']             ?? '';
    $nid              = $_POST['nid']             ?? '';
    $occupation       = $_POST['occupation']      ?? '';
    $source_of_funds  = $_POST['source_of_funds'] ?? '';
    $email            = $_POST['email']           ?? '';
    $phone            = $_POST['phone']           ?? '';
    $alt_phone        = $_POST['alt_phone']       ?? '';
    $addr1            = $_POST['addr1']           ?? '';
    $addr2            = $_POST['addr2']           ?? '';
    $city             = $_POST['city']            ?? '';
    $postal           = $_POST['postal']          ?? '';
    $country          = $_POST['country']         ?? '';
    $kyc              = isset($_POST['kyc'])   ? 1 : 0;
    $terms            = isset($_POST['terms']) ? 1 : 0;

    // ---- Handle NID file upload (simple version) ----
    $nid_file_path = null;

    if (!empty($_FILES['nid_file']['name'])) {
        $uploadDir = __DIR__ . "/uploads/nid/";
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $originalName = basename($_FILES['nid_file']['name']);
        $ext          = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName     = time() . "_" . mt_rand(1000,9999) . "." . $ext;

        $targetPath   = $uploadDir . $safeName;

        if (move_uploaded_file($_FILES['nid_file']['tmp_name'], $targetPath)) {
            // Save relative path in DB (e.g. uploads/nid/xxxxx.jpg)
            $nid_file_path = "uploads/nid/" . $safeName;
        } else {
            // OPTIONAL: you can debug upload problems here
            // echo "Upload failed: " . print_r($_FILES['nid_file'], true);
        }
    }

    // Insert into account_open_requests
    $sql = "INSERT INTO account_open_requests (
                account_type, currency, initial_deposit,
                first_name, last_name, dob, nid, nid_file,
                occupation, source_of_funds,
                email, phone, alt_phone,
                addr1, addr2, city, postal, country,
                kyc, terms
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Show exact prepare error
        die("Prepare failed: " . $conn->error);
    }

    // Types:
    // s  s   d   s   s   s   s   s   s   s   s   s   s   s   s   s   s   s   i   i
    // |  |   |   |   |   |   |   |   |   |   |   |   |   |   |   |   |   |   |   |
    // 1  2   3   4   5   6   7   8   9  10  11  12  13  14  15  16  17  18  19  20
    $stmt->bind_param(
        "ssdsssssssssssssssii",
        $account_type,
        $currency,
        $initial_deposit,
        $first_name,
        $last_name,
        $dob,
        $nid,
        $nid_file_path,
        $occupation,
        $source_of_funds,
        $email,
        $phone,
        $alt_phone,
        $addr1,
        $addr2,
        $city,
        $postal,
        $country,
        $kyc,
        $terms
    );

    if ($stmt->execute()) {
        echo "<script>alert('Application submitted successfully! Our team will review it.');</script>";
    } else {
        // Show the real MySQL error so we can debug
        echo "<pre>Insert failed: " . htmlspecialchars($stmt->error) . "</pre>";
        echo "<script>alert('Failed to submit application. Check server error output for details.');</script>";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Open a Bank Account — Shanto Bank</title>
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
        <a href="index.php">Home</a>
        <a href="#" aria-current="page">Open an Account</a>
        <a href="signup.php">Sign Up</a>
        <a href="#">Support</a>
      </nav>
    </div>
  </header>

  <main class="container" style="padding:30px 0 60px;">
    <h1 style="color:#4b248c;margin:0 0 8px;">Open a New Bank Account</h1>
    <p style="color:#5e5e6a;margin:0 0 20px;">No account yet? Complete the steps below to create a <strong>Savings</strong> or <strong>Current</strong> account. Already a customer? <a href="signup.php">Create your iCloud portal account</a>.</p>

    <!-- IMPORTANT: enctype for file upload -->
    <form id="open-account-form" class="form-card" action="" method="post" novalidate enctype="multipart/form-data">

      <!-- Account Preferences -->
      <fieldset class="fieldset">
        <legend>Account Preferences</legend>
        <div class="row">
          <label class="field">
            <span>Account Type</span>
            <select name="account_type" required>
              <option value="" selected disabled>Select account type</option>
              <option value="savings">Savings Account</option>
              <option value="current">Current Account</option>
              <option value="student">Student Account</option>
            </select>
          </label>
          <label class="field">
            <span>Currency</span>
            <select name="currency" required>
              <option value="" selected disabled>Select currency</option>
              <option value="bdt">BDT (৳)</option>
              <option value="usd">USD ($)</option>
            </select>
          </label>
          <label class="field">
            <span>Initial Deposit (BDT)</span>
            <input type="number" min="0" step="100" name="initial_deposit" placeholder="e.g., 5000" required />
          </label>
        </div>
      </fieldset>

      <!-- Personal Info -->
      <fieldset class="fieldset">
        <legend>Personal Information</legend>
        <div class="row">
          <label class="field">
            <span>First Name</span>
            <input type="text" name="first_name" required />
          </label>
          <label class="field">
            <span>Last Name</span>
            <input type="text" name="last_name" required />
          </label>
          <label class="field">
            <span>Date of Birth</span>
            <input type="date" name="dob" required />
          </label>
        </div>

        <div class="row">
          <label class="field">
            <span>National ID / Passport No.</span>
            <input type="text" name="nid" minlength="6" maxlength="25" required />
          </label>

          <!-- UPLOAD FIELD -->
          <label class="field">
            <span>Upload NID / Passport Photo</span>
            <input type="file" name="nid_file" accept="image/*,.pdf" required />
          </label>

          <label class="field">
            <span>Occupation</span>
            <input type="text" name="occupation" placeholder="e.g., Student, Engineer" required />
          </label>

          <label class="field">
            <span>Source of Funds</span>
            <select name="source_of_funds" required>
              <option value="" disabled selected>Select source</option>
              <option value="salary">Salary</option>
              <option value="business">Business</option>
              <option value="family">Family support</option>
              <option value="other">Other</option>
            </select>
          </label>
        </div>
      </fieldset>

      <!-- Contact & Address -->
      <fieldset class="fieldset">
        <legend>Contact & Address</legend>
        <div class="row">
          <label class="field">
            <span>Email</span>
            <input type="email" name="email" required />
          </label>
          <label class="field">
            <span>Mobile Number</span>
            <input type="tel" name="phone" pattern="[0-9+\-\s]{7,}" placeholder="+8801XXXXXXXXX" required />
          </label>
          <label class="field">
            <span>Alternate Phone (optional)</span>
            <input type="tel" name="alt_phone" pattern="[0-9+\-\s]{7,}" />
          </label>
        </div>
        <div class="row">
          <label class="field col-2">
            <span>Address Line 1</span>
            <input type="text" name="addr1" required />
          </label>
          <label class="field col-2">
            <span>Address Line 2</span>
            <input type="text" name="addr2" />
          </label>
        </div>
        <div class="row">
          <label class="field">
            <span>City</span>
            <input type="text" name="city" required />
          </label>
          <label class="field">
            <span>Postal Code</span>
            <input type="text" name="postal" required />
          </label>
          <label class="field">
            <span>Country</span>
            <input type="text" name="country" value="Bangladesh" required />
          </label>
        </div>
      </fieldset>

      <!-- Declarations -->
      <fieldset class="fieldset">
        <legend>Declarations</legend>
        <label class="checkbox">
          <input type="checkbox" name="kyc" required />
          <span>I confirm the information provided is accurate and consent to KYC/AML verification.</span>
        </label>
        <label class="checkbox">
          <input type="checkbox" name="terms" required />
          <span>I agree to the <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a>.</span>
        </label>
      </fieldset>

      <div class="actions">
        <a class="btn btn-outline" href="index.php">Back</a>
        <button class="btn" type="submit">Submit Application</button>
      </div>

      <p class="help-text">After submission, we’ll review your application and notify you by email/SMS with next steps.</p>
    </form>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <p>© <span id="year"></span> Shanto Bank.</p>
      <div class="footer-links">
        <a href="#">Privacy</a><a href="#">Terms</a>
      </div>
    </div>
  </footer>

  <script src="scripts.js"></script>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
</body>
</html>
<?php
$conn->close();
?>
