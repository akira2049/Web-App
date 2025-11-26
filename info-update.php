<?php
session_start();
/*
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
*/
$cid = $_SESSION['cid'] ?? null;

/* ---------- DB: Load accounts + cards for logged in user ---------- */
$accounts = [];
$cards    = [];
$accCardsMap = []; // accountNo => ['card_label' => ..., 'holder' => ...]

if ($cid) {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "my_bank";

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("DB connection failed: " . $conn->connect_error);
    }

    // 1) Load accounts
    // Adjust column names if needed (e.g., WHERE cid = ? instead of CustomerID)
    $stmt = $conn->prepare("
        SELECT AccountNo, Balance 
        FROM accounts 
        WHERE CustomerID = ?
        ORDER BY AccountNo ASC
    ");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $accounts[] = $row;
    }
    $stmt->close();

    // 2) Load cards
    // Adjust columns to match your actual cards table
    // Example schema: cards(CardNo, CardType, CardHolderName, LinkedAccount, CustomerID)
    $stmt = $conn->prepare("
        SELECT CardNo, CardType, CardHolderName, LinkedAccount
        FROM cards
        WHERE CustomerID = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $cards[] = $row;
        }
        $stmt->close();
    }

    $conn->close();

    // 3) Build map: accountNo => first associated card (for the “Associated Card” section)
    foreach ($cards as $c) {
        $accNo = $c['LinkedAccount'] ?? '';
        if (!$accNo) continue;
        if (!isset($accCardsMap[$accNo])) {
            $cardNo   = $c['CardNo'] ?? '';
            $last4    = $cardNo ? substr($cardNo, -4) : '****';
            $type     = $c['CardType'] ?? 'CARD';
            $holder   = $c['CardHolderName'] ?? '';

            $accCardsMap[$accNo] = [
                'card_label' => trim($type . " **" . $last4),
                'holder'     => $holder
            ];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Info Update</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    /* --- Extra styles just for Info Update --- */

    .subtitle{
      color:var(--muted);
      font-size:14px;
      margin-bottom:18px;
    }

    /* tabs: Password / PIN Number */
    .tabs{
      display:inline-flex;
      border-radius:999px;
      border:1px solid var(--border);
      padding:3px;
      background:#f7f7f9;
      margin-bottom:18px;
    }
    .tabs button{
      border:none;
      background:transparent;
      padding:8px 18px;
      border-radius:999px;
      font-size:14px;
      font-weight:600;
      cursor:pointer;
      color:var(--muted);
    }
    .tabs button.active{
      background:#fff;
      color:var(--primary);
      box-shadow:0 1px 4px rgba(0,0,0,.08);
    }

    .field-label{
      color:var(--muted);
      font-size:13px;
      font-weight:600;
      margin-bottom:4px;
    }

    .input-wrap{
      position:relative;
    }
    .input-wrap .toggle-eye{
      position:absolute;
      right:12px;
      top:50%;
      transform:translateY(-50%);
      font-size:16px;
      cursor:pointer;
      color:var(--muted);
    }

    .btn-primary{
      width:100%;
      text-align:center;
    }

    /* step sections */
    .wizard-step{display:none;}
    .wizard-step.active{display:block;}

    /* info type options */
    .info-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:14px;
    }
    @media (max-width:640px){
      .info-grid{grid-template-columns:1fr;}
    }
    .info-opt{
      text-align:left;
      gap:12px;
    }
    .info-icon{
      width:40px; height:40px;
      border-radius:999px;
      background:#fff7d0;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      margin-bottom:8px;
      font-size:20px;
    }
    .info-title{
      font-weight:700;
      margin-bottom:2px;
    }
    .info-opt.active{
      border-color:var(--primary);
    }

    /* step caption on top */
    .steps-caption{
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.12em;
      color:var(--muted);
      margin-bottom:4px;
    }

    /* select-like buttons that open sheets */
    .select-btn{
      width:100%;
      text-align:left;
      padding:14px 12px;
      border-radius:12px;
      border:1px solid var(--border);
      background:#fff;
      font-size:15px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      cursor:pointer;
    }
    .select-placeholder{color:#a0a2a8;}
    .select-label{
      display:flex;
      align-items:center;
      gap:8px;
    }

    /* bottom sheet */
    .sheet{
      position:fixed;
      inset:0;
      background:rgba(0,0,0,.35);
      display:none;
      align-items:flex-end;
      justify-content:center;
      z-index:50;
    }
    .sheet.active{display:flex;}
    .sheet-panel{
      width:100%;
      max-width:520px;
      background:#fff;
      border-radius:18px 18px 0 0;
      box-shadow:0 -10px 25px rgba(0,0,0,.25);
      padding:16px 18px 24px;
    }
    .sheet-header{
      text-align:left;
      margin-bottom:10px;
    }
    .sheet-bar{
      width:60px;
      height:4px;
      background:#dedfe3;
      border-radius:999px;
      margin:0 auto 12px;
    }
    .sheet-title{
      font-weight:700;
      margin-bottom:2px;
      color:var(--primary);
    }
    .sheet-sub{
      color:var(--muted);
      font-size:13px;
    }
    .sheet-list{
      margin-top:12px;
      display:grid;
      gap:8px;
    }
    .sheet-item{
      padding:10px 2px;
      border-bottom:1px solid #f0f0f3;
      cursor:pointer;
      display:flex;
      flex-direction:column;
      gap:2px;
    }
    .sheet-item:last-child{border-bottom:none;}
    .sheet-item b{font-size:15px; color:var(--primary);}
    .sheet-item span{font-size:13px; color:var(--muted);}

    .assoc{
      margin-top:10px;
      font-size:14px;
    }
    .assoc div{margin-bottom:4px;}
    .assoc-label{color:var(--muted);}
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <a class="linkish" href="dashboard.php">← Back</a>
      <span class="step" id="stepIndicator">1 / 3</span>
    </div>
    <div class="h1">Info Update</div>

    <div class="card">
      <div class="section">

        <!-- STEP 1: VERIFY -->
        <div class="wizard-step active" id="step1">
          <div class="subtitle">
            Verify to Proceed<br>
            <span class="note">Please enter your password or PIN below to proceed info update.</span>
          </div>

          <div class="tabs" id="authTabs">
            <button type="button" data-mode="password" class="active">Password</button>
            <button type="button" data-mode="pin">PIN Number</button>
          </div>

          <div>
            <div class="field-label" id="authFieldLabel">Password</div>
            <div class="input-wrap">
              <input class="input" id="authInput" type="password" placeholder="Enter password" required>
              <span class="toggle-eye" id="toggleEye">👁</span>
            </div>
          </div>

          <div class="footerbar">
            <button class="btn btn-primary" id="btnToStep2" disabled>Next</button>
          </div>
        </div>

        <!-- STEP 2: CHOOSE INFO TYPE -->
        <div class="wizard-step" id="step2">
          <p class="subtitle" style="margin-top:0">
            Which information would you like to update?
          </p>

          <div class="info-grid">
            <button type="button" class="opt card info-opt" data-info="mobile">
              <div>
                <div class="info-icon">📱</div>
                <div class="info-title">Mobile Number</div>
                <small class="note">Change your registered mobile number</small>
              </div>
            </button>

            <button type="button" class="opt card info-opt" data-info="email">
              <div>
                <div class="info-icon">📧</div>
                <div class="info-title">Email Address</div>
                <small class="note">Update your contact email</small>
              </div>
            </button>
          </div>

          <div class="footerbar">
            <button class="btn btn-primary" id="btnToStep3" disabled>Next</button>
          </div>
        </div>

        <!-- STEP 3: UPDATE INFORMATION DETAILS -->
        <div class="wizard-step" id="step3">
          <form id="infoForm" method="post" action="info-update-process.php">
            <div class="steps-caption">Step 3 / 3</div>
            <div class="subtitle" style="margin-bottom:16px">
              Update Information<br>
              <span class="note">Please enter the following details.</span>
            </div>

            <!-- Hidden fields carrying data from Step 1 & 2 -->
            <input type="hidden" name="auth_mode" id="authModeInput">
            <input type="hidden" name="auth_value" id="authValueInput">
            <input type="hidden" name="info_type" id="infoTypeInput">
            <input type="hidden" name="target_type" id="targetTypeInput">
            <input type="hidden" name="target_account" id="targetAccountInput">

            <div class="row">
              <div class="label">Select Type*</div>
              <div>
                <button type="button" class="select-btn" id="btnType">
                  <span class="select-label">
                    <span>🏦</span>
                    <span id="typeText" class="select-placeholder">Select type</span>
                  </span>
                  <span>⌄</span>
                </button>
              </div>
            </div>

            <div class="row">
              <div class="label">Select account or card</div>
              <div>
                <button type="button" class="select-btn" id="btnAccount">
                  <span class="select-label">
                    <span>🏦</span>
                    <span id="accountText" class="select-placeholder">Select account or card</span>
                  </span>
                  <span>⌄</span>
                </button>

                <div class="assoc" id="assocBlock" style="display:none">
                  <div class="assoc-label">Associated Card</div>
                  <div id="assocCard"><b></b></div>
                  <div class="assoc-label">Card Holder</div>
                  <div id="cardHolder"><b></b></div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="label">Card PIN</div>
              <div class="input-wrap">
                <input class="input" id="cardPin" name="card_pin" type="password" maxlength="6" placeholder="Enter your card PIN">
              </div>
            </div>

            <div class="footerbar">
              <button class="btn btn-primary" id="btnSubmit" type="submit">Submit</button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

  <!-- Bottom sheet: Select Type -->
  <div class="sheet" id="sheetType">
    <div class="sheet-panel">
      <div class="sheet-bar"></div>
      <div class="sheet-header">
        <div class="sheet-title">Select Type</div>
        <div class="sheet-sub">Select the required type for update</div>
      </div>
      <div class="sheet-list">
        <div class="sheet-item" data-type="Account">
          <b>Account</b>
          <span>Update info against your bank account</span>
        </div>
        <div class="sheet-item" data-type="Card">
          <b>Card</b>
          <span>Update info against your debit or credit card</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom sheet: Select Account (from DB) -->
  <div class="sheet" id="sheetAccount">
    <div class="sheet-panel">
      <div class="sheet-bar"></div>
      <div class="sheet-header">
        <div class="sheet-title">Account Number</div>
        <div class="sheet-sub">Choose the Account Number</div>
      </div>
      <div class="sheet-list">
        <?php if (empty($accounts)): ?>
          <div class="sheet-item">
            <b>No accounts found</b>
            <span>Please contact the bank.</span>
          </div>
        <?php else: ?>
          <?php foreach ($accounts as $acc): 
              $accNo  = $acc['AccountNo'];
              $bal    = $acc['Balance'];
              $disp   = "Acc. {$accNo} | BDT " . number_format($bal,2);
              $cardLabel = $accCardsMap[$accNo]['card_label'] ?? '';
              $holder    = $accCardsMap[$accNo]['holder'] ?? '';
          ?>
            <div class="sheet-item"
                 data-account="<?php echo htmlspecialchars($accNo); ?>"
                 data-display="<?php echo htmlspecialchars($disp); ?>"
                 data-card="<?php echo htmlspecialchars($cardLabel); ?>"
                 data-holder="<?php echo htmlspecialchars($holder); ?>">
              <b><?php echo htmlspecialchars($disp); ?></b>
              <?php if ($cardLabel): ?>
                <span>Linked card: <?php echo htmlspecialchars($cardLabel); ?></span>
              <?php else: ?>
                <span>No card linked</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

<script>
  // helper to switch steps
  const steps = ['step1','step2','step3'];
  function showStep(id){
    steps.forEach(s=>{
      document.getElementById(s).classList.toggle('active', s===id);
    });
    const indicator = document.getElementById('stepIndicator');
    if(id === 'step1') indicator.textContent = '1 / 3';
    if(id === 'step2') indicator.textContent = '2 / 3';
    if(id === 'step3') indicator.textContent = '3 / 3';
  }

  // Hidden input refs
  const authModeInput      = document.getElementById('authModeInput');
  const authValueInput     = document.getElementById('authValueInput');
  const infoTypeInput      = document.getElementById('infoTypeInput');
  const targetTypeInput    = document.getElementById('targetTypeInput');
  const targetAccountInput = document.getElementById('targetAccountInput');

  // STEP 1: auth input + tabs
  const authInput   = document.getElementById('authInput');
  const btnToStep2  = document.getElementById('btnToStep2');
  let currentAuthMode = 'password';

  authInput.addEventListener('input', ()=>{
    btnToStep2.disabled = authInput.value.trim().length === 0;
  });

  document.getElementById('toggleEye').addEventListener('click', ()=>{
    authInput.type = authInput.type === 'password' ? 'text' : 'password';
  });

  const tabs = document.querySelectorAll('#authTabs button');
  const authFieldLabel = document.getElementById('authFieldLabel');
  tabs.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      tabs.forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      currentAuthMode = btn.dataset.mode === 'pin' ? 'pin' : 'password';
      if(currentAuthMode === 'password'){
        authFieldLabel.textContent = 'Password';
        authInput.placeholder = 'Enter password';
        authInput.type = 'password';
      }else{
        authFieldLabel.textContent = 'PIN';
        authInput.placeholder = 'Enter PIN';
        authInput.type = 'password';
      }
      authInput.value = '';
      btnToStep2.disabled = true;
    });
  });

  btnToStep2.addEventListener('click', ()=>{
    // store auth data into hidden fields
    authModeInput.value  = currentAuthMode;
    authValueInput.value = authInput.value.trim();
    showStep('step2');
  });

  // STEP 2: choose info type
  const infoOpts = document.querySelectorAll('.info-opt');
  const btnToStep3 = document.getElementById('btnToStep3');
  let chosenInfo = null;
  infoOpts.forEach(opt=>{
    opt.addEventListener('click', ()=>{
      infoOpts.forEach(o=>o.classList.remove('active'));
      opt.classList.add('active');
      chosenInfo = opt.dataset.info;
      infoTypeInput.value = chosenInfo;
      btnToStep3.disabled = false;
    });
  });

  btnToStep3.addEventListener('click', ()=>{
    showStep('step3');
  });

  // Bottom sheet helpers
  function openSheet(id){ document.getElementById(id).classList.add('active'); }
  function closeSheet(id){ document.getElementById(id).classList.remove('active'); }

  // close when clicking dimmed background
  document.querySelectorAll('.sheet').forEach(sh=>{
    sh.addEventListener('click', e=>{
      if(e.target === sh) sh.classList.remove('active');
    });
  });

  // Select Type sheet
  const btnType   = document.getElementById('btnType');
  const typeText  = document.getElementById('typeText');
  btnType.addEventListener('click', ()=>openSheet('sheetType'));
  document.querySelectorAll('#sheetType .sheet-item').forEach(item=>{
    item.addEventListener('click', ()=>{
      const t = item.dataset.type;
      typeText.textContent = t;
      typeText.classList.remove('select-placeholder');
      targetTypeInput.value = t; // "Account" or "Card"
      closeSheet('sheetType');
    });
  });

  // Select Account sheet (from DB)
  const btnAccount   = document.getElementById('btnAccount');
  const accountText  = document.getElementById('accountText');
  const assocBlock   = document.getElementById('assocBlock');
  const assocCard    = document.getElementById('assocCard');
  const cardHolder   = document.getElementById('cardHolder');

  btnAccount.addEventListener('click', ()=>{
    // Only open sheet if a type is chosen (Account/Card)
    if (!targetTypeInput.value) {
      alert('Please select type (Account or Card) first.');
      return;
    }
    openSheet('sheetAccount');
  });

  document.querySelectorAll('#sheetAccount .sheet-item').forEach(item=>{
    item.addEventListener('click', ()=>{
      const display = item.dataset.display || item.dataset.account;
      const accNo   = item.dataset.account || '';
      const cardLbl = item.dataset.card || '';
      const holder  = item.dataset.holder || '';

      accountText.textContent = display;
      accountText.classList.remove('select-placeholder');
      targetAccountInput.value = accNo;

      if (cardLbl) {
        assocCard.innerHTML  = "<b>" + cardLbl + "</b>";
        cardHolder.innerHTML = "<b>" + holder + "</b>";
        assocBlock.style.display = 'block';
      } else {
        assocBlock.style.display = 'none';
      }

      closeSheet('sheetAccount');
    });
  });

  // Final submit: simple front-end validation
  const form = document.getElementById('infoForm');
  const cardPinInput = document.getElementById('cardPin');

  form.addEventListener('submit', (e)=>{
    const errors = [];
    if (!authModeInput.value || !authValueInput.value) {
      errors.push('Verification (password/PIN) missing.');
    }
    if (!infoTypeInput.value) {
      errors.push('Please choose which info to update (mobile/email/address).');
    }
    if (!targetTypeInput.value) {
      errors.push('Please select type: Account or Card.');
    }
    if (!targetAccountInput.value) {
      errors.push('Please select an account or card.');
    }
    if (!cardPinInput.value || cardPinInput.value.trim().length < 4) {
      errors.push('Please enter a valid Card PIN (at least 4 digits).');
    }

    if (errors.length > 0) {
      e.preventDefault();
      alert(errors.join('\n'));
    }
  });
</script>
</body>
</html>
