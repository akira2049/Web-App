<?php
session_start();

// Require login
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumber = trim($_POST['card_number'] ?? '');
    $expiry     = trim($_POST['expiry'] ?? '');
    $cvc        = trim($_POST['cvc'] ?? '');
    $otpMethod  = $_POST['otp'] ?? 'sms';

    if ($cardNumber === "" || $expiry === "" || $cvc === "") {
        $error = "Please fill in all card details.";
    } else {
        // Save pending card data in session
        $_SESSION['add_card_pending'] = [
            'card_number' => $cardNumber,
            'expiry'      => $expiry,
            'cvc'         => $cvc,
            'otp_method'  => $otpMethod
        ];

        // Generate OTP and store in session (5 minutes expiry)
        $otp = rand(100000, 999999);
        $_SESSION['add_card_otp']         = (string)$otp;
        $_SESSION['add_card_otp_expires'] = time() + 300; // 5 minutes

        // TODO: send $otp via SMS/Email using your Infobip or mail setup
        // For now we just "simulate" sending.

        header("Location: add-card-otp.php");
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Card — Step 1</title>
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

    .field-wrap{position:relative;}
    .field-icon{
      position:absolute; right:10px; top:50%; transform:translateY(-50%);
      font-size:18px; opacity:.55; pointer-events:none;
    }
    .helper-link{font-size:13px; color:var(--primary); text-decoration:none; font-weight:600;}
    .helper-link:hover{text-decoration:underline;}
    .v-help{margin-top:6px; font-size:13px; color:var(--muted);}
    .tutorial-link{
      display:inline-flex; align-items:center; gap:6px;
      font-size:14px; text-decoration:none; color:var(--primary); font-weight:600;
      margin-top:4px;
    }
    .tutorial-link span{font-size:16px;}
    .error-msg{
      margin-bottom:10px;
      padding:8px 10px;
      border-radius:6px;
      background:#ffe4e4;
      color:#b30000;
      font-size:14px;
    }
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="dashboard.php">← Back</a>
      <span class="step">1 / 2</span>
    </div>

    <div class="h1">Add Card</div>

    <div class="card">
      <div class="section">
        <div class="kv" style="margin-bottom:18px">
          <b>Card Information</b><br>
          <span class="note">
            Enter your Card Number, Card Expiry &amp; Card CVC to proceed
          </span>
        </div>

        <?php if ($error !== ""): ?>
          <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="add-card.php">
          <!-- Card Number -->
          <div class="row">
            <div class="label">Card Number</div>
            <div class="field-wrap">
              <input class="input" type="text" name="card_number" inputmode="numeric" maxlength="19"
                     placeholder="Enter card number" required>
              <span class="field-icon">💳</span>
            </div>
          </div>

          <!-- Expiry Date -->
          <div class="row">
            <div class="label">Expiry Date</div>
            <div class="field-wrap">
              <!-- type="month" gives YYYY-MM -->
              <input class="input" type="month" name="expiry" required>
              <span class="field-icon">📅</span>
            </div>
          </div>

          <!-- Card CVC -->
          <div class="row">
            <div class="label">CVC</div>
            <div>
              <div class="field-wrap">
                <input class="input" id="cardPin" name="cvc" type="password" inputmode="numeric"
                       maxlength="3" placeholder="•••" required>
                <span class="field-icon" id="togglePin" style="cursor:pointer; pointer-events:auto;">👁️</span>
              </div>
            </div>
          </div>

          <!-- Verification channel -->
          <div class="row">
            <div class="label">Receive OTP via</div>
            <div>
              <div class="veri-grid" id="otpMethod">
                <label class="vcard active">
                  <input type="radio" name="otp" value="sms" checked> SMS
                </label>
                <label class="vcard">
                  <input type="radio" name="otp" value="email"> Email
                </label>
              </div>
              <div class="v-help">Receive OTP for verification via your preferred channel.</div>
            </div>
          </div>

          <div class="footerbar">
            <button class="btn" type="submit">Continue</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
  // toggle PIN visibility
  const pin = document.getElementById('cardPin');
  const toggle = document.getElementById('togglePin');
  if (pin && toggle){
    toggle.addEventListener('click', () => {
      const isPwd = pin.type === 'password';
      pin.type = isPwd ? 'text' : 'password';
    });
  }

  // active border for OTP method cards
  const cards = document.querySelectorAll('#otpMethod .vcard');
  cards.forEach(c=>{
    c.addEventListener('click', ()=>{
      cards.forEach(x=>x.classList.remove('active'));
      c.classList.add('active');
      c.querySelector('input').checked = true;
    });
  });
</script>
</body>
</html>
