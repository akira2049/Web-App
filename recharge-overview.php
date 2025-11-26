<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Mobile Recharge — Overview</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .total{display:flex;justify-content:space-between;gap:10px;padding:14px;border:1px solid var(--border);border-radius:12px;background:#fbfbfc}
    .note{color:var(--muted);font-size:13px}
    .veri{display:grid;gap:12px;grid-template-columns:repeat(3,minmax(0,1fr))}
    .vopt{padding:16px;border:1.5px solid var(--border);border-radius:14px;cursor:pointer}
    .vopt.active{border-color:var(--primary)}
  </style>
</head>
<body>
  <div class="app">
    <div class="topbar">
      <!-- back to PHP recharge step-1 page -->
      <a class="linkish" href="mobile-recharge.php">← Back</a>
      <span class="step">2 / 2</span>
    </div>
    <div class="h1" id="title">Mobile Recharge</div>

    <div class="card">
      <div class="section">

        <div class="row">
          <div class="label">Transfer From</div>
          <div class="kv" id="fromAcc"><b>Acc. 14512400000072</b></div>
        </div>

        <div class="row">
          <div class="label">Total Amount</div>
          <div class="total">
            <div>
              <div class="kv">BDT</div>
              <div style="font-size:24px;font-weight:900;color:var(--primary)" id="amt">0.00</div>
              <div class="note" id="fee">BDT <span id="amt2">0.00</span> + BDT 0.00</div>
            </div>
            <div class="kv">View Breakdown ⌄</div>
          </div>
        </div>

        <div class="row">
          <div class="label">Connection type</div>
          <select id="ctype">
            <option>Prepaid</option>
            <option>Postpaid</option>
          </select>
        </div>

        <div class="row">
          <div class="label">Notes</div>
          <input class="input" id="note" value="Mobile Recharge">
        </div>

        <div class="row">
          <div class="label">Complete payment confirmation via</div>
          <div class="veri" id="veri">
            <label class="vopt">
              <input type="radio" name="v" value="email"> Email
            </label>
            <label class="vopt">
              <input type="radio" name="v" value="sms"> SMS
            </label>
          </div>
        </div>

        <div class="footerbar">
          <button class="btn" id="go">Next</button>
        </div>

      </div>
    </div>
  </div>

<script>
  // hydrate from localStorage (values set in mobile-recharge.php)
  (function(){
    const msisdn = localStorage.getItem('re_msisdn') || '';
    const amt = localStorage.getItem('re_amt') || '0.00';
    const acct = localStorage.getItem('re_from') || '14512400000072';
    const ctypeSaved = localStorage.getItem('re_ctype') || '';
    const noteSaved = localStorage.getItem('re_note') || '';

    document.getElementById('title').innerHTML =
      'Mobile Recharge<br><span class="kv">'+ (msisdn || '') +'</span>';

    const amtNum = parseFloat(amt || '0') || 0;
    document.getElementById('amt').textContent = amtNum.toFixed(2);
    document.getElementById('amt2').textContent = amtNum.toFixed(2);
    document.getElementById('fromAcc').innerHTML = '<b>Acc. '+acct+'</b>';

    if (ctypeSaved) {
      document.getElementById('ctype').value = ctypeSaved;
    }
    if (noteSaved) {
      document.getElementById('note').value = noteSaved;
    }
  })();

  // selection effect for verification options
  document.querySelectorAll('.vopt').forEach(c=>{
    c.addEventListener('click', ()=>{
      document.querySelectorAll('.vopt').forEach(x=>x.classList.remove('active'));
      c.classList.add('active');
      c.querySelector('input').checked = true;
    });
  });

  document.getElementById('go').addEventListener('click', ()=>{
    // store connection type, note and verification method
    const ctype = document.getElementById('ctype').value;
    const note  = document.getElementById('note').value || 'Mobile Recharge';
    localStorage.setItem('re_ctype', ctype);
    localStorage.setItem('re_note', note);

    const selectedVeri = document.querySelector('input[name="v"]:checked');
    if (selectedVeri) {
      localStorage.setItem('re_veri', selectedVeri.value);
    } else {
      // optional: force user to pick a verification method
      alert('Please choose a verification method.');
      return;
    }

    // ensure amount present
    const a = document.getElementById('amt').textContent || '0';
    localStorage.setItem('re_amt', a);

    // go to OTP page (PHP version)
    location.href = 'recharge-otp.php';
  });
</script>
</body>
</html>
