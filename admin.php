<?php
session_start();

/*if (!isset($_SESSION['cid'], $_SESSION['type'], $_SESSION['admin_verified'])) {
    header("Location: admin_login.php");
    exit;
}*/

// ---------- DATABASE CONNECTION ----------
$host     = "localhost";
$user     = "root";
$password = "";
$database = "my_bank";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

/*
    Utility functions to generate unique CID and AccountNo
    used when approving new account_open_requests
*/
if (!function_exists('generateUniqueCid')) {
    function generateUniqueCid(mysqli $conn): string {
        while (true) {
            $cid = (string)mt_rand(100000, 999999); // 6 digits
            $stmt = $conn->prepare("SELECT COUNT(*) FROM accounts WHERE CustomerID = ?");
            $stmt->bind_param("s", $cid);
            $stmt->execute();
            $stmt->bind_result($cnt);
            $stmt->fetch();
            $stmt->close();
            if ($cnt == 0) {
                return $cid;
            }
        }
    }
}

if (!function_exists('generateUniqueAccountNo')) {
    function generateUniqueAccountNo(mysqli $conn): string {
        while (true) {
            $accNo = "14";
            for ($i = 0; $i < 11; $i++) {
                $accNo .= mt_rand(0, 9);
            }
            $stmt = $conn->prepare("SELECT COUNT(*) FROM accounts WHERE AccountNo = ?");
            $stmt->bind_param("s", $accNo);
            $stmt->execute();
            $stmt->bind_result($cnt);
            $stmt->fetch();
            $stmt->close();
            if ($cnt == 0) {
                return $accNo;
            }
        }
    }
}

// ---------- HANDLE INFO UPDATE REQUESTS (APPROVE / DECLINE) ----------
if (isset($_GET['approve_update']) || isset($_GET['decline_update'])) {
    $cid = $_GET['approve_update'] ?? $_GET['decline_update'];

    // Decline: just remove the request
    if (isset($_GET['decline_update'])) {
        $stmt = $conn->prepare("DELETE FROM info_update_requests WHERE cid = ?");
        if ($stmt) {
            $stmt->bind_param("s", $cid);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: admin.php");
        exit;
    }

    // Approve: read requested email/phone
    if (isset($_GET['approve_update'])) {
        $newEmail = null;
        $newPhone = null;

        $stmt = $conn->prepare("SELECT email, phone FROM info_update_requests WHERE cid = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $cid);
            $stmt->execute();
            $stmt->bind_result($newEmail, $newPhone);
            $stmt->fetch();
            $stmt->close();
        }

        // Build dynamic UPDATE only for provided fields
        $fields = [];
        $types  = "";
        $params = [];

        if (!empty($newEmail)) {
            $fields[] = "email = ?";
            $types   .= "s";
            $params[] = $newEmail;
        }

        if (!empty($newPhone)) {
            $fields[] = "phone = ?";
            $types   .= "s";
            $params[] = $newPhone;
        }

        if (!empty($fields)) {
            $sql = "UPDATE accounts SET " . implode(", ", $fields) . " WHERE CustomerID = ?";
            $types   .= "s";
            $params[] = $cid;

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Remove the request after processing
        $stmt = $conn->prepare("DELETE FROM info_update_requests WHERE cid = ?");
        if ($stmt) {
            $stmt->bind_param("s", $cid);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: admin.php");
        exit;
    }
}

// ---------- HANDLE NEW ACCOUNT OPEN REQUESTS (APPROVE / DECLINE) ----------
if (isset($_GET['approve_open']) || isset($_GET['decline_open'])) {
    $reqId = (int)($_GET['approve_open'] ?? $_GET['decline_open']);

    // Decline: mark as DECLINED
    if (isset($_GET['decline_open'])) {
        $stmt = $conn->prepare(
            "UPDATE account_open_requests 
             SET account_status='DECLINED' 
             WHERE req_id = ? AND account_status='PENDING'"
        );
        if ($stmt) {
            $stmt->bind_param("i", $reqId);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: admin.php");
        exit;
    }

    // Approve: create account in `accounts` table
    if (isset($_GET['approve_open'])) {

        // 1) Load request (only if PENDING)
        $sql = "SELECT 
                    account_type, currency, initial_deposit,
                    first_name, last_name, email, phone, nid
                FROM account_open_requests
                WHERE req_id = ? AND account_status='PENDING'
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $reqId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows !== 1) {
            $stmt->close();
            header("Location: admin.php");
            exit;
        }

        $stmt->bind_result(
            $req_account_type,
            $req_currency,
            $req_initial_deposit,
            $req_first_name,
            $req_last_name,
            $req_email,
            $req_phone,
            $req_nid
        );
        $stmt->fetch();
        $stmt->close();

        // 2) Generate unique CID and AccountNo
        $newCid    = generateUniqueCid($conn);
        $newAccNo  = generateUniqueAccountNo($conn);

        // 3) Map form account_type & currency to your accounts schema
        $accountType = strtoupper($req_account_type);   // savings/current/student
        if ($accountType === 'SAVINGS') {
            $accountType = 'SAVINGS';
        } elseif ($accountType === 'CURRENT') {
            $accountType = 'CURRENT';
        } elseif ($accountType === 'STUDENT') {
            // if you don't have STUDENT in DB, map to SAVINGS:
            $accountType = 'SAVINGS';
        } else {
            $accountType = 'SAVINGS';
        }

        $currency = strtoupper($req_currency) === 'USD' ? 'USD' : 'BDT';

        $accountName    = trim($req_first_name . " " . $req_last_name);
        $openingBalance = (float)$req_initial_deposit;

        // 4) Insert into accounts table
        $sql = "INSERT INTO accounts
                    (AccountNo, CustomerID, account_name, account_type, account_status,
                     Balance, currency, email, phone, nid)
                VALUES
                    (?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed (insert account): " . $conn->error);
        }

        $stmt->bind_param(
            "ssssdssss",
            $newAccNo,
            $newCid,
            $accountName,
            $accountType,
            $openingBalance,
            $currency,
            $req_email,
            $req_phone,
            $req_nid
        );

        $stmt->execute();
        $stmt->close();

        // 5) Mark request as APPROVED
        $stmt = $conn->prepare(
            "UPDATE account_open_requests SET account_status='APPROVED' WHERE req_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param("i", $reqId);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: admin.php");
        exit;
    }
}

// ---------- HANDLE DELETE ACTIONS ----------
if (isset($_GET['del_acc'])) {
    $accNo = $_GET['del_acc'];

    $stmt = $conn->prepare("DELETE FROM accounts WHERE AccountNo = ?");
    if ($stmt) {
        $stmt->bind_param("s", $accNo);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin.php");
    exit;
}

if (isset($_GET['del_card'])) {
    $cardNo = $_GET['del_card'];

    $stmt = $conn->prepare("DELETE FROM cards WHERE cardNo = ?");
    if ($stmt) {
        $stmt->bind_param("s", $cardNo);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin.php");
    exit;
}

if (isset($_GET['del_biller'])) {
    $billerCode = $_GET['del_biller'];

    $stmt = $conn->prepare("DELETE FROM billers WHERE biller_code = ?");
    if ($stmt) {
        $stmt->bind_param("s", $billerCode);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin.php");
    exit;
}

// ---------- FETCH STATISTICS ----------
$totalAccounts     = 0;
$totalActiveCards  = 0;
$totalBillers      = 0;

$result = $conn->query("SELECT COUNT(*) AS cnt FROM accounts");
if ($result && $row = $result->fetch_assoc()) {
    $totalAccounts = (int)$row['cnt'];
}

$result = $conn->query("SELECT COUNT(*) AS cnt FROM cards WHERE cardStatus = 'ACTIVE'");
if ($result && $row = $result->fetch_assoc()) {
    $totalActiveCards = (int)$row['cnt'];
}

$result = $conn->query("SELECT COUNT(*) AS cnt FROM billers");
if ($result && $row = $result->fetch_assoc()) {
    $totalBillers = (int)$row['cnt'];
}

// ---------- FETCH LISTS ----------
$accountsList = [];
$cardsList    = [];
$billersList  = [];

// Accounts
$accSql = "SELECT 
             AccountNo, 
             CustomerID, 
             account_name   AS AccountName,
             account_type   AS AccountType, 
             account_status AS AccountStatus, 
             Balance,
             email,
             phone
           FROM accounts
           ORDER BY AccountNo ASC";

if ($res = $conn->query($accSql)) {
    while ($r = $res->fetch_assoc()) {
        $accountsList[] = $r;
    }
    $res->free();
}

// Cards
$cardSql = "SELECT 
              cardNo,
              customer_id,
              cardHolderName,
              expiryDate,
              cvc,
              linkedAccount,
              cardType,
              cardStatus
            FROM cards
            ORDER BY cardNo ASC";

if ($res = $conn->query($cardSql)) {
    while ($r = $res->fetch_assoc()) {
        $cardsList[] = $r;
    }
    $res->free();
}

// Billers
$billerSql = "SELECT biller_code, biller_name, biller_category, biller_status 
              FROM billers
              ORDER BY biller_name ASC";
if ($res = $conn->query($billerSql)) {
    while ($r = $res->fetch_assoc()) {
        $billersList[] = $r;
    }
    $res->free();
}

// Info update requests
$infoUpdateRequests = [];
$reqSql = "SELECT cid, email, phone FROM info_update_requests ORDER BY cid ASC";
if ($res = $conn->query($reqSql)) {
    while ($r = $res->fetch_assoc()) {
        $infoUpdateRequests[] = $r;
    }
    $res->free();
}

// New account open requests (PENDING only) – only needed fields
$openRequests = [];
$sql = "SELECT
            req_id,
            first_name,
            last_name,
            nid,
            nid_file,
            phone,
            email,
            dob,
            country,
            source_of_funds,
            created_at
        FROM account_open_requests
        WHERE account_status = 'PENDING'
        ORDER BY created_at DESC";

if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()) {
        $openRequests[] = $r;
    }
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>

  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    :root {
      --admin-bg-dark: #002b45;
      --admin-bg-darker: #001a2a;
      --admin-accent: #00c0ff;
    }

    body {
      display: flex;
      min-height: 100vh;
    }

    .admin-layout {
      display: flex;
      flex: 1;
      min-height: 100vh;
    }

    .sidebar {
      width: 260px;
      background: var(--admin-bg-dark);
      color: #f1f5f9;
      display: flex;
      flex-direction: column;
      padding: 20px 18px;
      gap: 24px;
    }

    .sidebar-header {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .sidebar-logo {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      background: var(--admin-bg-darker);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 18px;
      color: #ffffff;
      box-shadow: 0 4px 10px rgba(0,0,0,0.35);
    }

    .sidebar-title {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .sidebar-title span:first-child {
      font-size: 14px;
      opacity: 0.7;
      text-transform: uppercase;
      letter-spacing: .08em;
    }

    .sidebar-title span:last-child {
      font-size: 16px;
      font-weight: 700;
    }

    .sidebar-nav {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .sidebar-nav a {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 8px;
      text-decoration: none;
      color: #e4e5e6;
      font-size: 14px;
      transition: background 0.2s, color 0.2s, transform 0.1s;
      cursor: pointer;
    }

    .sidebar-nav a i {
      width: 18px;
      text-align: center;
    }

    .sidebar-nav a.active,
    .sidebar-nav a:hover {
      background: rgba(255, 255, 255, 0.12);
      color: #ffffff;
      transform: translateX(2px);
    }

    .sidebar-footer {
      margin-top: auto;
      font-size: 12px;
      opacity: 0.7;
    }

    /* Subnav under Manage Accounts */
    .sidebar-subnav {
      margin-left: 32px;
      margin-top: 2px;
      display: none;
      flex-direction: column;
      gap: 2px;
    }
    .sidebar-subnav.open {
      display: flex;
    }
    .sidebar-subnav a {
      font-size: 13px;
      padding: 6px 10px;
    }
    .sidebar-subnav a.active {
      background: rgba(255,255,255,0.14);
      color: #fff;
    }
    .sidebar-nav .caret {
      margin-left: auto;
      font-size: 11px;
      opacity: .75;
    }

    .admin-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: transparent;
    }

    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 24px 0;
    }

    header h1 {
      margin: 0;
    }

    header .admin-meta {
      font-size: 13px;
      opacity: 0.85;
    }

    header .admin-meta span {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    header .admin-meta i {
      font-size: 12px;
    }

    .admin-content {
      padding: 16px 24px 30px;
    }

    .admin-section {
      display: none;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      align-items: flex-start;
    }

    .admin-section.active {
      display: grid;
    }

    .admin-card {
      background: #ffffff;
      border-radius: 12px;
      padding: 18px 18px 16px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .admin-card.full-width {
      grid-column: 1 / -1;
    }

    .admin-card h2 {
      margin: 0 0 4px;
      font-size: 18px;
      color: #00416A;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .admin-card .subtitle {
      font-size: 13px;
      color: #6b7280;
      margin-bottom: 14px;
    }

    .table-scroll {
      width: 100%;
      overflow-x: auto;
    }

    .table-scroll .admin-table {
      width: 100%;
      min-width: 700px;
    }

    .admin-table {
      border-collapse: collapse;
      font-size: 13px;
    }

    .admin-table th,
    .admin-table td {
      padding: 6px 8px;
      border-bottom: 1px solid #e5e7eb;
      text-align: center;
      vertical-align: middle;
      white-space: nowrap;
    }

    .admin-table th {
      font-weight: 600;
      color: #4b5563;
      background: #f9fafb;
    }

    .admin-table tr:last-child td {
      border-bottom: none;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 600;
      background: #eef2ff;
      color: #3730a3;
    }

    .badge-green {
      background: #dcfce7;
      color: #166534;
    }

    .badge-red {
      background: #fee2e2;
      color: #b91c1c;
    }

    .badge-yellow {
      background: #fef9c3;
      color: #854d0e;
    }

    .danger-link {
      color: #b91c1c;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
    }

    .danger-link:hover {
      text-decoration: underline;
    }

    .update-link {
      color: #00416A;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      margin-right: 8px;
    }

    .update-link:hover {
      text-decoration: underline;
    }

    .admin-form {
      display: grid;
      gap: 10px 14px;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-form .form-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 14px;
    }

    .admin-form .form-group.full {
      grid-column: 1 / -1;
    }

    .admin-form label {
      font-weight: 600;
      color: #111827;
    }

    .admin-form input,
    .admin-form select,
    .admin-form textarea {
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 14px;
      outline: none;
      font-family: inherit;
    }

    .admin-form input:focus,
    .admin-form select:focus,
    .admin-form textarea:focus {
      border-color: var(--admin-accent);
      box-shadow: 0 0 0 2px rgba(0, 192, 255, 0.25);
    }

    .admin-form textarea {
      min-height: 70px;
      resize: vertical;
    }

    .admin-actions {
      margin-top: 10px;
      display: flex;
      justify-content: flex-end;
      grid-column: 1 / -1;
    }

    .admin-btn {
      border: none;
      border-radius: 8px;
      padding: 8px 16px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      background: #00416A;
      color: #ffffff;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background 0.2s, transform 0.05s;
    }

    .admin-btn:hover {
      background: #006699;
    }

    .admin-btn:active {
      transform: scale(0.98);
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
      margin-top: 10px;
      margin-bottom: 12px;
    }

    .stat-card {
      background: #ffffff;
      border-radius: 10px;
      padding: 10px 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.12);
      font-size: 13px;
    }

    .stat-label {
      opacity: 0.75;
      margin-bottom: 2px;
    }

    .stat-value {
      font-size: 18px;
      font-weight: 700;
      color: #00416A;
    }

    .nid-thumb {
      max-width: 100px;
      max-height: 60px;
      object-fit: cover;
      border-radius: 4px;
      border: 1px solid #e5e7eb;
    }

    @media (max-width: 900px) {
      .admin-layout { flex-direction: column; }
      .sidebar {
        width: 100%;
        flex-direction: row;
        align-items: flex-start;
        gap: 16px;
        padding: 12px 16px;
        overflow-x: auto;
      }
      .sidebar-nav { flex-direction: row; gap: 6px; flex: 1; }
      .sidebar-footer { display: none; }
      .stats-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .sidebar-subnav { margin-left: 12px; }
    }

    @media (max-width: 640px) {
      .admin-form { grid-template-columns: 1fr; }
      .stats-row { grid-template-columns: 1fr; }
      .table-scroll .admin-table { min-width: 600px; }
    }
  </style>
</head>
<body>

<div class="admin-layout">
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">AB</div>
      <div class="sidebar-title">
        <span>Admin Panel</span>
        <span>Astra Bank</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a class="active" data-section="overview">
        <i class="fa-solid fa-gauge-high"></i>
        <span>Overview</span>
      </a>

      <!-- Manage Accounts parent (dropdown toggle) -->
      <a href="#" id="nav-accounts-toggle">
        <i class="fa-solid fa-landmark"></i>
        <span>Manage Accounts</span>
        <i class="fa-solid fa-chevron-down caret"></i>
      </a>
      <div class="sidebar-subnav" id="accounts-subnav">
        <a data-section="accounts-info">
          <span>Account Info Update</span>
        </a>
        <a data-section="accounts-open-requests">
          <span>New Account Requests</span>
        </a>
      </div>

      <a data-section="cards">
        <i class="fa-regular fa-credit-card"></i>
        <span>Manage Cards</span>
      </a>
      <a data-section="billers">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span>Manage Billers</span>
      </a>
      <a href="admin_login.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      Logged in as <strong>Admin</strong><br>
    </div>
  </aside>

  <div class="admin-main">
    <header>
      <h1>Admin Dashboard</h1>
      <div class="admin-meta">
        <span><i class="fa-regular fa-circle-user"></i>Admin Name</span>
      </div>
    </header>

    <main class="admin-content">

      <!-- OVERVIEW -->
      <section id="section-overview" class="admin-section active">

        <div class="admin-card">
          <h2><i class="fa-solid fa-chart-line"></i> System Overview</h2>
          <p class="subtitle">Quick snapshot of your system. Use sidebar to manage accounts, cards and billers.</p>

          <div class="stats-row">
            <div class="stat-card">
              <div class="stat-label">Total Accounts</div>
              <div class="stat-value"><?php echo $totalAccounts; ?></div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Active Cards</div>
              <div class="stat-value"><?php echo $totalActiveCards; ?></div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Registered Billers</div>
              <div class="stat-value"><?php echo $totalBillers; ?></div>
            </div>
          </div>
        </div>

        <!-- Accounts Table -->
        <div class="admin-card full-width">
          <h2><i class="fa-solid fa-landmark"></i> Accounts</h2>
          <p class="subtitle">All customer accounts currently available in the system.</p>

          <?php if (empty($accountsList)): ?>
            <p class="subtitle">No accounts found.</p>
          <?php else: ?>
            <div class="table-scroll">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Account No</th>
                    <th>Customer ID</th>
                    <th>Account Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Balance</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($accountsList as $a): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($a['AccountNo']); ?></td>
                    <td><?php echo htmlspecialchars($a['CustomerID']); ?></td>
                    <td><?php echo htmlspecialchars($a['AccountName']); ?></td>
                    <td><?php echo htmlspecialchars($a['AccountType']); ?></td>
                    <td>
                      <?php
                        $status = strtoupper($a['AccountStatus']);
                        $badgeClass = 'badge';
                        if ($status === 'ACTIVE')        $badgeClass .= ' badge-green';
                        elseif ($status === 'CLOSED')    $badgeClass .= ' badge-red';
                        else                             $badgeClass .= ' badge-yellow';
                      ?>
                      <span class="<?php echo $badgeClass; ?>">
                        <?php echo htmlspecialchars($status); ?>
                      </span>
                    </td>
                    <td><?php echo number_format((float)$a['Balance'], 2); ?></td>
                    <td><?php echo htmlspecialchars($a['email']); ?></td>
                    <td><?php echo htmlspecialchars($a['phone']); ?></td>
                    <td>
                      <a
                        href="admin-update_account.php?account_no=<?php echo urlencode($a['AccountNo']); ?>"
                        class="update-link"
                      >Update</a>
                      <a
                        href="admin.php?del_acc=<?php echo urlencode($a['AccountNo']); ?>"
                        class="danger-link"
                        onclick="return confirm('Are you sure you want to delete this account?');"
                      >Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Cards Table -->
        <div class="admin-card full-width">
          <h2><i class="fa-regular fa-credit-card"></i> Cards</h2>
          <p class="subtitle">All cards linked to customer accounts.</p>

          <?php if (empty($cardsList)): ?>
            <p class="subtitle">No cards found.</p>
          <?php else: ?>
            <div class="table-scroll">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Card Number</th>
                    <th>Customer ID</th>
                    <th>Card Holder</th>
                    <th>Account No</th>
                    <th>Type</th>
                    <th>Expiry</th>
                    <th>CVC</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($cardsList as $c): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($c['cardNo']); ?></td>
                    <td><?php echo htmlspecialchars($c['customer_id']); ?></td>
                    <td><?php echo htmlspecialchars($c['cardHolderName']); ?></td>
                    <td><?php echo htmlspecialchars($c['linkedAccount']); ?></td>
                    <td><?php echo htmlspecialchars($c['cardType']); ?></td>
                    <td><?php echo htmlspecialchars($c['expiryDate']); ?></td>
                    <td><?php echo htmlspecialchars($c['cvc']); ?></td>
                    <td>
                      <?php
                        $cstatus = strtoupper($c['cardStatus']);
                        $badgeClass = 'badge';
                        if ($cstatus === 'ACTIVE')       $badgeClass .= ' badge-green';
                        elseif ($cstatus === 'BLOCKED')  $badgeClass .= ' badge-red';
                        else                             $badgeClass .= ' badge-yellow';
                      ?>
                      <span class="<?php echo $badgeClass; ?>">
                        <?php echo htmlspecialchars($cstatus); ?>
                      </span>
                    </td>
                    <td>
                      <a
                        href="admin-update_card_status.php?card_number=<?php echo urlencode($c['cardNo']); ?>"
                        class="update-link"
                      >Update</a>
                      <a
                        href="admin.php?del_card=<?php echo urlencode($c['cardNo']); ?>"
                        class="danger-link"
                        onclick="return confirm('Are you sure you want to delete this card?');"
                      >Delete</a>
                    </td>
                  </tr>

                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Billers Table -->
        <div class="admin-card full-width">
          <h2><i class="fa-solid fa-file-invoice-dollar"></i> Billers</h2>
          <p class="subtitle">All registered utility and merchant billers.</p>

          <?php if (empty($billersList)): ?>
            <p class="subtitle">No billers found.</p>
          <?php else: ?>
            <div class="table-scroll">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Biller Code</th>
                    <th>Biller Name</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($billersList as $b): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($b['biller_code']); ?></td>
                    <td><?php echo htmlspecialchars($b['biller_name']); ?></td>
                    <td><?php echo htmlspecialchars($b['biller_category']); ?></td>
                    <td>
                      <?php
                        $bstatus = strtoupper($b['biller_status']);
                        $badgeClass = 'badge';
                        if ($bstatus === 'ACTIVE')  $badgeClass .= ' badge-green';
                        else                         $badgeClass .= ' badge-red';
                      ?>
                      <span class="<?php echo $badgeClass; ?>">
                        <?php echo htmlspecialchars($bstatus); ?>
                      </span>
                    </td>
                    <td>
                      <a
                        href="admin-update_biller.php?biller_code=<?php echo urlencode($b['biller_code']); ?>"
                        class="update-link"
                      >Update</a>
                      <a
                        href="admin.php?del_biller=<?php echo urlencode($b['biller_code']); ?>"
                        class="danger-link"
                        onclick="return confirm('Are you sure you want to delete this biller?');"
                      >Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- MANAGE ACCOUNTS — ACCOUNT INFO UPDATE -->
      <section id="section-accounts-info" class="admin-section">
        <div class="admin-card full-width">
          <h2><i class="fa-solid fa-user-pen"></i> Pending Info Update Requests</h2>
          <p class="subtitle">Approve or decline customer email / phone change requests.</p>

          <?php if (empty($infoUpdateRequests)): ?>
            <p class="subtitle">No pending update requests.</p>
          <?php else: ?>
            <div class="table-scroll">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Customer ID</th>
                    <th>New Email</th>
                    <th>New Phone</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($infoUpdateRequests as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['cid']); ?></td>
                    <td><?php echo htmlspecialchars($r['email'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['phone'] ?? '-'); ?></td>
                    <td>
                      <a
                        href="admin.php?approve_update=<?php echo urlencode($r['cid']); ?>"
                        class="update-link"
                        onclick="return confirm('Approve this contact info change?');"
                      >Approve</a>
                      <a
                        href="admin.php?decline_update=<?php echo urlencode($r['cid']); ?>"
                        class="danger-link"
                        onclick="return confirm('Decline and remove this request?');"
                      >Decline</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- MANAGE ACCOUNTS — NEW ACCOUNT OPEN REQUESTS -->
      <section id="section-accounts-open-requests" class="admin-section">
        <div class="admin-card full-width">
          <h2><i class="fa-solid fa-user-plus"></i> New Account Requests</h2>
          <p class="subtitle">
            Review and approve customer account opening requests.  
            (Shown: Name, NID, Phone, Email, Date of Birth, Nationality, Source of Income, NID Image)
          </p>

          <?php if (empty($openRequests)): ?>
            <p class="subtitle">No pending account opening requests.</p>
          <?php else: ?>
            <div class="table-scroll">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Request ID</th>
                    <th>Applicant Name</th>
                    <th>NID Number</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Date of Birth</th>
                    <th>Nationality</th>
                    <th>Source of Income</th>
                    <th>NID Image</th>
                    <th>Submitted At</th>
                    <th>Action</th>
                  </tr>
                </thead>

                <tbody>
                <?php foreach ($openRequests as $r): ?>
                  <tr>
                    <td><?php echo (int)$r['req_id']; ?></td>

                    <td><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>

                    <td><?php echo htmlspecialchars($r['nid']); ?></td>

                    <td><?php echo htmlspecialchars($r['phone']); ?></td>

                    <td><?php echo htmlspecialchars($r['email']); ?></td>

                    <td><?php echo htmlspecialchars($r['dob']); ?></td>

                    <td><?php echo htmlspecialchars($r['country']); ?></td>

                    <td><?php echo htmlspecialchars($r['source_of_funds']); ?></td>

                    <td>
                      <?php if (!empty($r['nid_file'])): ?>
                        <a href="<?php echo htmlspecialchars($r['nid_file']); ?>" target="_blank">
                          <img src="<?php echo htmlspecialchars($r['nid_file']); ?>" class="nid-thumb"><br>
                          View
                        </a>
                      <?php else: ?>
                        <span>-</span>
                      <?php endif; ?>
                    </td>

                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>

                    <td>
                      <a
                        href="admin.php?approve_open=<?php echo (int)$r['req_id']; ?>"
                        class="update-link"
                        onclick="return confirm('Approve and create account for this request?');"
                      >Approve</a>

                      <a
                        href="admin.php?decline_open=<?php echo (int)$r['req_id']; ?>"
                        class="danger-link"
                        onclick="return confirm('Decline this account opening request?');"
                      >Decline</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

          <?php endif; ?>
        </div>
      </section>

      <!-- MANAGE CARDS -->
      <section id="section-cards" class="admin-section">

        <div class="admin-card">
          <h2><i class="fa-regular fa-credit-card"></i> Add New Card</h2>
          <p class="subtitle">Create a new card and link it to a customer account.</p>

          <form id="card-form" class="admin-form" method="post" action="admin-add_card.php" novalidate>

            <div class="form-group">
              <label for="card_cid">Customer ID</label>
              <input type="text" id="card_cid" name="customer_id" placeholder="e.g. 1023" required>
            </div>

            <!-- UPDATED: linked account auto-fill dropdown -->
            <div class="form-group">
              <label for="card_account">Linked Account No</label>
              <select id="card_account" name="account_no" required>
                <option value="">-- enter CID first --</option>
              </select>
              <small id="acc-help" style="font-size:12px; color:#6b7280;"></small>
            </div>

            <!-- UPDATED: holder name auto-fill -->
            <div class="form-group">
              <label for="card_holder">Card Holder Name</label>
              <input type="text" id="card_holder" name="card_holder" placeholder="Name on Card" required>
            </div>

            <div class="form-group">
              <label for="card_number">Card Number</label>
              <div style="display:flex; gap:6px;">
                <input
                  type="text"
                  id="card_number"
                  name="card_number"
                  placeholder="16–19 digits"
                  minlength="16"
                  maxlength="19"
                  required
                  style="flex:1;"
                >
                <button type="button" id="generate-card-btn" class="admin-btn" style="padding-inline:10px;">
                  Auto
                </button>
              </div>
            </div>

            <div class="form-group">
              <label for="cvc">CVC</label>
              <div style="display:flex; gap:6px;">
                <input
                  type="text"
                  id="cvc"
                  name="cvc"
                  maxlength="4"
                  placeholder="3 or 4 digits"
                  required
                  style="flex:1;"
                >
                <button type="button" id="generate-cvc-btn" class="admin-btn" style="padding-inline:10px;">
                  Auto
                </button>
              </div>
            </div>

            <div class="form-group">
              <label for="card_type">Card Type</label>
              <select id="card_type" name="card_type" required>
                <option value="">Select type</option>
                <option value="VISA">VISA</option>
                <option value="MASTERCARD">MasterCard</option>
                <option value="AMEX">AMEX</option>
                <option value="DEBIT">DBL Debit</option>
                <option value="CREDIT">DBL Credit</option>
              </select>
            </div>

            <div class="form-group">
              <label for="card_limit">Credit Limit (BDT)</label>
              <input type="number" id="card_limit" name="credit_limit" placeholder="e.g. 100000">
            </div>

            <div class="form-group">
              <label for="card_expiry">Expiry Date</label>
              <input type="month" id="card_expiry" name="expiry_date" required>
            </div>

            <div class="form-group full">
              <label for="card_status">Status</label>
              <select id="card_status" name="status" required>
                <option value="ACTIVE">ACTIVE</option>
                <option value="BLOCKED">BLOCKED</option>
                <option value="INACTIVE">INACTIVE</option>
              </select>
            </div>

            <div class="admin-actions">
              <button type="submit" class="admin-btn">
                <i class="fa-solid fa-plus"></i>
                Save Card to Database
              </button>
            </div>

          </form>
        </div>

      </section>

      <!-- MANAGE BILLERS -->
      <section id="section-billers" class="admin-section">
        <div class="admin-card">
          <h2><i class="fa-solid fa-file-invoice-dollar"></i> Add New Biller</h2>
          <p class="subtitle">Register a new utility or merchant so customers can pay bills online.</p>

          <form class="admin-form" method="post" action="admin-add_biller.php">
            <div class="form-group">
              <label for="biller_name">Biller Name</label>
              <input type="text" id="biller_name" name="biller_name" required>
            </div>

            <div class="form-group">
              <label for="biller_code">Biller ID</label>
              <input type="text" id="biller_code" name="biller_code" required>
            </div>

            <div class="form-group">
              <label for="biller_type">Category</label>
              <select id="biller_type" name="category" required>
                <option value="">Select category</option>
                <option value="ELECTRICITY">Electricity</option>
                <option value="GAS">Gas</option>
                <option value="WATER">Water</option>
                <option value="INTERNET">Internet</option>
                <option value="MOBILE">Mobile</option>
                <option value="TUITION">Tuition / Education</option>
                <option value="OTHERS">Others</option>
              </select>
            </div>

            <div class="form-group">
              <label for="biller_status">Status</label>
              <select id="biller_status" name="status" required>
                <option value="ACTIVE">ACTIVE</option>
                <option value="INACTIVE">INACTIVE</option>
              </select>
            </div>

            <div class="admin-actions">
              <button type="submit" class="admin-btn">
                <i class="fa-solid fa-plus"></i>
                Add Biller to Database
              </button>
            </div>
          </form>
        </div>
      </section>

    </main>
  </div>
</div>

<!-- Sidebar section switching + accounts dropdown -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const navLinks = document.querySelectorAll('.sidebar-nav a[data-section], .sidebar-subnav a[data-section]');
    const sections = {
      overview:                 document.getElementById('section-overview'),
      'accounts-info':          document.getElementById('section-accounts-info'),
      'accounts-open-requests': document.getElementById('section-accounts-open-requests'),
      cards:                    document.getElementById('section-cards'),
      billers:                  document.getElementById('section-billers')
    };

    const accountsToggle = document.getElementById('nav-accounts-toggle');
    const accountsSubnav = document.getElementById('accounts-subnav');

    if (accountsToggle && accountsSubnav) {
      accountsToggle.addEventListener('click', function (e) {
        e.preventDefault();
        accountsSubnav.classList.toggle('open');
      });
    }

    navLinks.forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();

        const target = this.getAttribute('data-section');
        if (!target || !sections[target]) return;

        // Remove active from all section-links
        navLinks.forEach(l => l.classList.remove('active'));

        // Mark this as active
        this.classList.add('active');

        // Show correct section
        Object.keys(sections).forEach(key => {
          sections[key].classList.toggle('active', key === target);
        });
      });
    });
  });
</script>

<!-- AUTO-FILL ACCOUNT + HOLDER NAME BY CID -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const cidInput    = document.getElementById('card_cid');
  const accSelect   = document.getElementById('card_account');
  const accHelp     = document.getElementById('acc-help');
  const holderInput = document.getElementById('card_holder');

  let debounceTimer = null;

  function resetFields(msg = "") {
    accSelect.innerHTML = `<option value="">-- enter CID first --</option>`;
    accHelp.textContent = msg;
    holderInput.value = "";
  }

  async function loadByCid(cid) {
    resetFields("Searching accounts...");
    try {
      const res = await fetch(`admin-get_account_by_cid.php?cid=${encodeURIComponent(cid)}`);
      const data = await res.json();

      if (!data.ok) {
        resetFields(data.message || "Failed to load data.");
        return;
      }

      const accounts = data.accounts || [];
      const holderName = data.holderName || "";

      holderInput.value = holderName;

      if (accounts.length === 0) {
        accSelect.innerHTML = `<option value="">No account found</option>`;
        accHelp.textContent = "No account found for this CID.";
        return;
      }

      accSelect.innerHTML = `<option value="">Select account</option>`;
      accounts.forEach(acc => {
        const opt = document.createElement('option');
        opt.value = acc;
        opt.textContent = acc;
        accSelect.appendChild(opt);
      });

      accSelect.value = accounts[0];

      accHelp.textContent = accounts.length > 1
        ? `Found ${accounts.length} accounts. Pick one if needed.`
        : `Account auto-selected.`;

    } catch (err) {
      resetFields("Error fetching data.");
      console.error(err);
    }
  }

  if (cidInput) {
    cidInput.addEventListener('input', function () {
      const cid = cidInput.value.trim();
      clearTimeout(debounceTimer);

      if (cid.length === 0) {
        resetFields("");
        return;
      }

      debounceTimer = setTimeout(() => loadByCid(cid), 400);
    });
  }
});
</script>

<!-- Existing Card generation + validation -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const cardForm       = document.getElementById('card-form');
    const cardNumberInput= document.getElementById('card_number');
    const cvcInput       = document.getElementById('cvc');
    const cardTypeSelect = document.getElementById('card_type');
    const genCardBtn     = document.getElementById('generate-card-btn');
    const genCvcBtn      = document.getElementById('generate-cvc-btn');

    function randInt(min, max) {
      return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function generateDemoCardNumber(cardType) {
      let prefix = '4';
      let length = 16;

      if (cardType === 'VISA') {
        prefix = '4';
        length = 16;
      } else if (cardType === 'MASTERCARD') {
        prefix = String(randInt(51, 55));
        length = 16;
      } else if (cardType === 'AMEX') {
        prefix = Math.random() < 0.5 ? '34' : '37';
        length = 15;
      } else {
        prefix = String(randInt(40, 49));
        length = 16;
      }

      let result = prefix;
      while (result.length < length) {
        result += String(randInt(0, 9));
      }
      return result;
    }

    function generateDemoCvc(cardType) {
      const len = (cardType === 'AMEX') ? 4 : 3;
      let cvc = '';
      for (let i = 0; i < len; i++) {
        cvc += String(randInt(0, 9));
      }
      return cvc;
    }

    if (genCardBtn) {
      genCardBtn.addEventListener('click', function () {
        const type = cardTypeSelect.value || 'VISA';
        cardNumberInput.value = generateDemoCardNumber(type);
      });
    }

    if (genCvcBtn) {
      genCvcBtn.addEventListener('click', function () {
        const type = cardTypeSelect.value || 'VISA';
        cvcInput.value = generateDemoCvc(type);
      });
    }

    if (cardForm) {
      cardForm.addEventListener('submit', function (e) {
        const rawCard = cardNumberInput.value.trim().replace(/\s+/g, '');
        const rawCvc  = cvcInput.value.trim();

        if (!/^\d{16,19}$/.test(rawCard)) {
          alert('Card number must be 16–19 digits (numbers only).');
          cardNumberInput.focus();
          e.preventDefault();
          return;
        }

        if (!/^\d{3,4}$/.test(rawCvc)) {
          alert('CVC must be 3 or 4 digits.');
          cvcInput.focus();
          e.preventDefault();
          return;
        }

        if (!cardTypeSelect.value) {
          alert('Please select a card type.');
          cardTypeSelect.focus();
          e.preventDefault();
          return;
        }
      });
    }
  });
</script>

</body>
</html>
<?php
$conn->close();
?>
