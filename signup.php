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
        <span class="logo">SB</span>
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
    <p style="color:#5e5e6a;margin:0 0 20px;">For existing Shanto Bank customers who don’t have an iCloud portal account. New to Shanto Bank? <a href="open-account.html">Open a bank account</a> first.</p>

    <form id="signup-form" class="form-card" action="#" method="post" novalidate>
      <!-- Verify Banking Relationship -->
      <fieldset class="fieldset">
        <legend>Verify Banking Relationship</legend>
        <div class="row">
          <label class="field">
            <span>Bank Account Number</span>
            <input type="text" name="account_number" inputmode="numeric" minlength="8" maxlength="20" required />
          </label>
          <label class="field">
            <span>Date of Birth</span>
            <input type="date" name="dob" required />
          </label>
          <label class="field">
            <span>Last 4 of NID/Passport</span>
            <input type="text" name="id_last4" pattern="[A-Za-z0-9]{4}" maxlength="4" required placeholder="XXXX" />
          </label>
        </div>
      </fieldset>

      <!-- Create Login -->
      <fieldset class="fieldset">
        <legend>Set Up Login</legend>
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
            <span>Customer ID</span>
            <input type="text" name="username" minlength="4" maxlength="20" required />
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
            <span>2‑Step Verification</span>
            <select name="mfa" required>
              <option value="" disabled selected>Select a method</option>
              <option value="sms">SMS Code</option>
              <option value="email">Email Code</option>
              <option value="auth_app">Authenticator App</option>
            </select>
          </label>
        </div>
        <p id="pw-rules" class="help-text">Use at least 8 characters with a mix of letters, numbers, and symbols.</p>
      </fieldset>

      <!-- Agreements -->
      <fieldset class="fieldset">
        <legend>Agreements</legend>
        <label class="checkbox">
          <input type="checkbox" name="terms" required />
          <span>I agree to the <a href="#">Terms of Use</a> and <a href="#">Privacy Policy</a>.</span>
        </label>
        <label class="checkbox">
          <input type="checkbox" name="comm" />
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

  <script src="scripts.js"></script>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
</body>
</html>
