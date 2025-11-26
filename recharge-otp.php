<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid   = $_SESSION['cid'];
$error = "";

/* ---------- DB CONNECTION ---------- */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/* ---------- Get registered phone number ---------- */
$phone = "";
$stmt = $conn->prepare("SELECT phone FROM user WHERE cid = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $phone = $row['phone'];
}
$stmt->close();

/* Mask phone for UI */
function mask_phone($n) {
    $digits = preg_replace('/\D+/', '', $n);
    $last2  = substr($digits, -2);
    $prefix = substr($n, 0, 3);
    if ($prefix === "") $prefix = "***";
    return $prefix . "******" . $last2;
}

/* ---------- Infobip SMS sender (fill your credentials) ---------- */
function send_infobip_otp($to, $otp) {
    // TODO: replace with your real Infobip credentials
    $baseUrl = "https://{your-base-url}.api.infobip.com";
    $apiKey  = "YOUR_API_KEY_HERE";

    $payload = [
        "messages" => [
            [
                "destinations" => [["to" => $to]],
                "from"         => "MyBank",
                "text"         => "Your MyBank mobile recharge OTP code is: {$otp}"
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $baseUrl . "/sms/2/text/advanced",
        CURLOPT_HTTPHEADER     => [
            "Authorization: App {$apiKey}",
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        // You can log curl_error($ch) if needed
    }
    curl_close($ch);
}

/* ---------- Generate & send OTP (initial + resend) ---------- */
function generate_and_send_otp($phone) {
    // 6-digit random OTP
    $otp = str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);

    $_SESSION['recharge_otp']         = $otp;
    $_SESSION['recharge_otp_expires'] = time() + 300; // valid for 5 minutes

    if ($phone) {
        send_infobip_otp($phone, $otp);
    }
}

// If first time here or resend requested, send a new OTP
if (!isset($_SESSION['recharge_otp']) || isset($_GET['resend'])) {
    generate_and_send_otp($phone);
}

/* ---------- Handle OTP submit ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otpInput = trim($_POST['otp'] ?? '');

    if ($otpInput === "") {
        $error = "Enter the 6-digit OTP.";
    } elseif (!isset($_SESSION['recharge_otp'], $_SESSION['recharge_otp_expires'])) {
        $error = "OTP not found. Please request a new code.";
    } elseif (time() > $_SESSION['recharge_otp_expires']) {
        $error = "OTP expired. Please request a new code.";
    } elseif ($otpInput !== $_SESSION['recharge_otp']) {
        $error = "Incorrect OTP. Please try again.";
    } else {
        // OTP OK
        $_SESSION['recharge_verified'] = true;

        // clear OTP
        unset($_SESSION['recharge_otp'], $_SESSION['recharge_otp_expires']);

        // continue to recharge success page
        header("Location: recharge-success.php");
        exit;
    }
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>OTP Verification</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .center{max-width:520px;margin:48px auto}
    .otp-box{display:flex;gap:10px;justify-content:center;margin:16px 0 8px}
    .otp-input{width:56px;height:56px;text-align:center;font-size:22px;border:1.5px solid var(--border);border-radius:12px}
    .small{color:var(--muted);text-align:center}
    .row-aux{display:flex;justify-content:space-between;align-items:center;margin:8px 0 18px}
    .timer{color:var(--muted)}
    .btn.full{width:100%}
    .linkish{cursor:pointer}
    .error-msg{color:#d00;font-size:14px;text-align:center;margin-top:8px}
  </style>
</head>
<body>
  <div class="app center card">
    <div class="section">
      <div class="h1" style="margin-bottom:6px">OTP Verification</div>
      <div class="small">
        Enter the verification code sent to your registered phone number
        <span id="msk"><?php echo $phone ? htmlspecialchars(mask_phone($phone)) : "**********"; ?></span>
      </div>

      <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" id="otpForm">
        <div class="otp-box" id="box">
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
          <input class="otp-input" maxlength="1" inputmode="numeric" />
        </div>

        <input type="hidden" name="otp" id="otpHidden">

        <div class="row-aux">
          <span class="linkish" id="resend">Resend code</span>
          <span class="timer" id="count">01:30</span>
        </div>

        <button type="submit" class="btn full" id="submit" disabled>Submit</button>

        <!-- For development you can show the OTP, remove in production -->
        <?php if (isset($_SESSION['recharge_otp'])): ?>
          <div class="small" style="margin-top:8px">Demo code: <b><?php echo htmlspecialchars($_SESSION['recharge_otp']); ?></b></div>
        <?php endif; ?>
      </form>
    </div>
  </div>

<script>
  const inputs  = [...document.querySelectorAll('.otp-input')];
  const submit  = document.getElementById('submit');
  const count   = document.getElementById('count');
  const resend  = document.getElementById('resend');
  const form    = document.getElementById('otpForm');
  const otpHidden = document.getElementById('otpHidden');

  // move focus + numeric only
  inputs.forEach((el, i)=>{
    el.addEventListener('input', ()=>{
      el.value = el.value.replace(/\D/g,'');
      if (el.value && i < inputs.length - 1) {
        inputs[i+1].focus();
      }
      validate();
    });
    el.addEventListener('keydown', (e)=>{
      if (e.key === 'Backspace' && !el.value && i > 0) {
        inputs[i-1].focus();
      }
    });
  });

  function validate(){
    const code = inputs.map(x=>x.value).join('');
    submit.disabled = code.length !== 6;
  }

  function timer(sec){
    let t = sec;
    const tick = ()=>{
      const m = String(Math.floor(t/60)).padStart(2,'0');
      const s = String(t%60).padStart(2,'0');
      count.textContent = `${m}:${s}`;
      if(t>0){
        t--;
        setTimeout(tick, 1000);
      }
    };
    tick();
  }
  timer(90);

  // Resend: reload page with ?resend=1 so PHP sends a new OTP via Infobip
  resend.addEventListener('click', ()=>{
    window.location.href = 'recharge-otp.php?resend=1';
  });

  // On submit: put combined code into hidden input
  form.addEventListener('submit', (e)=>{
    const code = inputs.map(x=>x.value).join('');
    if (code.length !== 6) {
      e.preventDefault();
      return;
    }
    otpHidden.value = code;
  });

  // focus first box
  if (inputs[0]) inputs[0].focus();
</script>
</body>
</html>
