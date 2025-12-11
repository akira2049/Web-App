<?php
// optional php here
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Astra Bank — Customer Portal</title>

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
    }

    /* Topbar */
    .topbar {
      width:100%;
      background:rgba(255,255,255,0.12);
      backdrop-filter:blur(8px);
      padding:14px 0;
      border-bottom:1px solid rgba(255,255,255,0.25);
    }
    .container {
      width:95%;
      max-width:1200px;
      margin:0 auto;
      display:flex;
      align-items:center;
      justify-content:space-between;
      color:#fff;
    }

    .brand {
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:700;
      font-size:20px;
    }
    .logo {
      background:#fff;
      color:#00416A;
      border-radius:50%;
      width:36px;
      height:36px;
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:900;
    }

    .nav a {
      color:#fff;
      text-decoration:none;
      margin:0 10px;
      font-weight:500;
      opacity:.9;
    }
    .nav a:hover {
      opacity:1;
      text-decoration:underline;
    }

    .btn {
      padding:10px 18px;
      border-radius:999px;
      font-size:14px;
      font-weight:600;
      cursor:pointer;
      text-decoration:none;
      display:inline-block;
    }
    .btn-outline {
      border:1.5px solid #fff;
      color:#fff;
      background:transparent;
    }
    .btn-outline:hover { background:rgba(255,255,255,0.18); }

    .btn-accent {
      background:#00A1FF;
      color:#fff;
    }

    .btn-light {
      background:#fff;
      color:#00416A;
    }

    main { flex:1; }

    /* HERO SECTION */
    .hero {
      color:#fff;
      padding:70px 0;
    }
    .hero-inner {
      display:flex;
      align-items:center;
      justify-content:space-between;
      flex-wrap:wrap;
      gap:30px;
    }
    .hero-copy {
      max-width:500px;
    }
    .hero-copy h1 {
      font-size:46px;
      font-weight:800;
      margin-bottom:14px;
      text-shadow:0 1px 3px rgba(0,0,0,0.3);
    }
    .hero-copy p {
      font-size:17px;
      margin-bottom:20px;
      opacity:.9;
    }
    .cta-row {
      display:flex;
      gap:14px;
      margin-top:10px;
    }

    /* HERO CARD */
    .hero-card {
      width:380px;
      background:#ffffff;
      color:#111;
      padding:24px;
      border-radius:var(--radius);
      box-shadow:0 6px 20px rgba(0,0,0,0.20);
      backdrop-filter:blur(6px);
    }

    .muted-heading {
      color:#333;
      font-weight:700;
      margin-bottom:14px;
      font-size:18px;
    }

    .features {
      list-style:none;
      display:flex;
      flex-direction:column;
      gap:14px;
    }
    .feature {
      display:flex;
      align-items:flex-start;
      gap:12px;
    }
    .feature h3 { font-size:16px; font-weight:700; margin-bottom:4px; }
    .feature p { font-size:14px; color:#555; }

    /* Quick actions */
    .quick-actions {
      margin-top:40px;
      padding:30px 0;
    }
    .grid {
      display:flex;
      gap:20px;
      justify-content:flex-start;
      flex-wrap:wrap;
    }
    .qa-card {
      background:#ffffff;
      padding:22px;
      border-radius:var(--radius);
      width:280px;
      text-decoration:none;
      color:#111;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
      transition:.2s;
    }
    .qa-card:hover {
      transform:translateY(-4px);
      box-shadow:0 6px 14px rgba(0,0,0,0.20);
    }

    /* FOOTER */
    .footer {
      margin-top:40px;
      background:rgba(0,0,0,0.2);
      padding:18px 0;
      color:#fff;
      text-align:center;
      backdrop-filter:blur(6px);
    }

    .footer-links a {
      color:#fff;
      opacity:.8;
      margin:0 6px;
      text-decoration:none;
    }
    .footer-links a:hover {
      opacity:1;
      text-decoration:underline;
    }
  </style>
</head>

<body>

  <!-- TOP BAR -->
  <header class="topbar">
    <div class="container">
      <div class="brand">
        <span class="logo">AB</span>
        <span class="brand-text">Astra Bank</span>
      </div>

      <nav class="nav">
        <a href="#" aria-current="page">Home</a>
        <a href="products.php">Products</a>
        <a href="#">Support</a>
        <a href="#">About</a>
      </nav>

      <div class="nav-cta">
        <a class="btn btn-outline" href="login.php">Log In</a>
        <a class="btn btn-light" href="signup.php">Sign Up</a>
      </div>
    </div>
  </header>

  <main>
    <!-- HERO -->
    <section class="hero">
      <div class="container hero-inner">

        <div class="hero-copy">
          <h1>All-in-One Customer Portal</h1>
          <p>Manage accounts, pay bills, and apply for loans in seconds with secure 24/7 access.</p>

          <div class="cta-row">
            <a class="btn btn-light" href="signup.php">Sign Up</a>
            <a class="btn btn-accent" href="login.php">Login</a>
          </div>
        </div>

        <!-- Feature Card -->
        <div class="hero-card">
          <h2 class="muted-heading">Key Features</h2>

          <ul class="features">
            <li class="feature">
              <span class="ico">💰</span>
              <div>
                <h3>Competitive Interest Rate</h3>
                <p>Earn more and pay less with market-leading rates.</p>
              </div>
            </li>

            <li class="feature">
              <span class="ico">📄</span>
              <div>
                <h3>Loan amount up to BDT 2 Cr</h3>
                <p>Apply digitally and get flexible funding.</p>
              </div>
            </li>

            <li class="feature">
              <span class="ico">📅</span>
              <div>
                <h3>Flexible tenor up to 300 months</h3>
                <p>Choose a repayment plan that fits your goals.</p>
              </div>
            </li>

            <li class="feature">
              <span class="ico">⭐</span>
              <div>
                <h3>50% waiver on processing fees</h3>
                <p>Save more when you apply online.</p>
              </div>
            </li>

            <li class="feature">
              <span class="ico">🎁</span>
              <div>
                <h3>Exclusive gift vouchers</h3>
                <p>Enjoy partner deals and rewards.</p>
              </div>
            </li>
          </ul>

        </div>
      </div>
    </section>

    <!-- Quick Action -->
    <section class="quick-actions">
      <div class="container grid">
        <a class="qa-card" href="open-account.php">
          <h3>Open an Account</h3>
          <p>Start banking with Astra Bank in minutes.</p>
        </a>
      </div>
    </section>
  </main>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="container footer-inner">
      <p>© <?= date('Y') ?> Astra Bank. All rights reserved.</p>
      <div class="footer-links">
        <a href="#">Privacy</a>
        <a href="#">Terms</a>
        <a href="#">Contact</a>
      </div>
    </div>
  </footer>

</body>
</html>
