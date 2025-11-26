<?php
session_start();
/*if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}*/
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Select Internet Provider</title>
  <link rel="stylesheet" href="transfer.css">
  <style>
    .backlink{
      text-decoration:none;
      color:var(--primary);
      font-weight:600;
      display:flex;
      align-items:center;
      gap:6px;
      font-size:15px;
    }
    .subtabs{
      display:flex;
      justify-content:space-around;
      align-items:flex-start;
      gap:8px;
      padding:12px 0 20px;
      border-bottom:1px solid var(--border);
      margin-bottom:16px;
    }
    .subtab{
      flex:1;
      text-align:center;
      font-size:14px;
      font-weight:600;
      color:var(--primary);
      cursor:pointer;
      display:flex;
      flex-direction:column;
      gap:4px;
    }
    .subtab.active .iconCircle{
      border:2px solid var(--primary);
    }
    .iconCircle{
      width:32px;
      height:32px;
      border-radius:50%;
      border:2px solid transparent;
      display:flex;
      align-items:center;
      justify-content:center;
      margin:0 auto;
      font-size:16px;
    }
    .provider-list{
      display:flex;
      flex-direction:column;
      gap:16px;
    }
    .provider-item{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      padding:12px 0;
      border-bottom:1px solid var(--border);
      cursor:pointer;
    }
    .prov-left{
      display:flex;
      align-items:flex-start;
      gap:10px;
    }
    .prov-logo{
      width:32px;
      height:32px;
      border-radius:6px;
      background:#fff;
      border:1px solid var(--border);
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:10px;
      font-weight:700;
      color:var(--primary);
      min-width:32px;
    }
    .prov-text-top{
      font-weight:700;
      color:var(--primary);
      font-size:15px;
      line-height:1.3;
    }
    .prov-text-bottom{
      color:var(--muted);
      font-size:13px;
      line-height:1.3;
    }
    form.provider-form{margin:0;}
    form.provider-form button{
      all:unset;
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      width:100%;
      cursor:pointer;
    }
  </style>
</head>
<body>
  <div class="app">

    <div class="topbar" style="margin-bottom:4px;">
      <a class="backlink" href="bill-payments.php">← Back to menu</a>
      <span class="step" style="margin-left:auto;"></span>
    </div>

    <div class="card">
      <div class="section">

        <div class="subtabs">
          <div class="subtab">
            <div class="iconCircle">📱</div>
            <div>Phone</div>
          </div>
          <div class="subtab active">
            <div class="iconCircle">🌐</div>
            <div>Internet</div>
          </div>
          <div class="subtab">
            <div class="iconCircle">📺</div>
            <div>TV</div>
          </div>
        </div>

        <div class="provider-list">

          <!-- Triangle -->
          <form class="provider-form provider-item" method="post" action="bill-payment-overview.php">
            <input type="hidden" name="bill_name" value="Triangle">
            <input type="hidden" name="bill_type" value="Internet">
            <input type="hidden" name="bill_id" value="120045">
            <input type="hidden" name="bill_logo" value="TRI">
            <input type="hidden" name="bill_amt" value="50.00">
            <input type="hidden" name="bill_flow" value="internet">
            <button type="submit">
              <div class="prov-left">
                <div class="prov-logo">TRI</div>
                <div>
                  <div class="prov-text-top">Triangle</div>
                  <div class="prov-text-bottom">Internet</div>
                </div>
              </div>
              <div>›</div>
            </button>
          </form>

          <!-- Link3 -->
          <form class="provider-form provider-item" method="post" action="bill-payment-overview.php">
            <input type="hidden" name="bill_name" value="Link3">
            <input type="hidden" name="bill_type" value="Internet">
            <input type="hidden" name="bill_id" value="50589">
            <input type="hidden" name="bill_logo" value="L3">
            <input type="hidden" name="bill_amt" value="50.00">
            <input type="hidden" name="bill_flow" value="internet">
            <button type="submit">
              <div class="prov-left">
                <div class="prov-logo">L3</div>
                <div>
                  <div class="prov-text-top">Link3</div>
                  <div class="prov-text-bottom">Internet</div>
                </div>
              </div>
              <div>›</div>
            </button>
          </form>

          <!-- Carnival -->
          <form class="provider-form provider-item" method="post" action="bill-payment-overview.php">
            <input type="hidden" name="bill_name" value="Carnival">
            <input type="hidden" name="bill_type" value="Internet">
            <input type="hidden" name="bill_id" value="80211">
            <input type="hidden" name="bill_logo" value="CRV">
            <input type="hidden" name="bill_amt" value="50.00">
            <input type="hidden" name="bill_flow" value="internet">
            <button type="submit">
              <div class="prov-left">
                <div class="prov-logo">CRV</div>
                <div>
                  <div class="prov-text-top">Carnival</div>
                  <div class="prov-text-bottom">Internet</div>
                </div>
              </div>
              <div>›</div>
            </button>
          </form>

        </div>

      </div>
    </div>

  </div>
</body>
</html>
