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

$accountNo   = $_GET['account_no'] ?? $_POST['account_no'] ?? '';
$account     = null;
$error       = "";
$success     = "";

// ---------- HANDLE POST (UPDATE) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountNo   = trim($_POST['account_no'] ?? '');
    $newStatus   = trim($_POST['new_status'] ?? '');

    if ($accountNo === "" || $newStatus === "") {
        $error = "Account number and new status are required.";
    } else {
        $stmt = $conn->prepare("UPDATE accounts SET account_status = ? WHERE AccountNo = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $newStatus, $accountNo);
            if ($stmt->execute()) {
                $stmt->close();
                // After successful update, go back to admin overview
                header("Location: admin.php");
                exit;
            } else {
                $error = "Failed to update account status.";
            }
            $stmt->close();
        } else {
            $error = "Failed to prepare update statement.";
        }
    }
}

// ---------- LOAD ACCOUNT FOR FORM (GET) ----------
if ($accountNo !== "") {
    $stmt = $conn->prepare("
        SELECT AccountNo, CustomerID, account_type, account_status, Balance 
        FROM accounts
        WHERE AccountNo = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $accountNo);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $account = $res->fetch_assoc();
        } else {
            $error = "Account not found.";
        }
        $stmt->close();
    } else {
        $error = "Failed to prepare account lookup.";
    }
} else {
    $error = "No account number specified.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update Account Status</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #e5f2ff;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Inter", sans-serif;
    }
    .update-wrapper {
      background: #ffffff;
      border-radius: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      max-width: 520px;
      width: 100%;
      padding: 22px 22px 18px;
    }
    .update-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    .update-header h1 {
      margin: 0;
      font-size: 20px;
      color: #00416A;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .back-link {
      font-size: 13px;
      text-decoration: none;
      color: #00416A;
    }
    .back-link i {
      margin-right: 4px;
    }
    .meta {
      font-size: 13px;
      color: #6b7280;
      margin-bottom: 14px;
    }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px 14px;
      font-size: 14px;
    }
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .form-group.full {
      grid-column: 1 / -1;
    }
    label {
      font-weight: 600;
      color: #111827;
    }
    input, select, textarea {
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 7px 9px;
      font-size: 14px;
      outline: none;
      font-family: inherit;
    }
    input[readonly] {
      background: #f3f4f6;
    }
    textarea {
      min-height: 60px;
      resize: vertical;
    }
    .actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 12px;
      grid-column: 1/-1;
    }
    .btn-primary {
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
    }
    .btn-primary:hover {
      background: #006699;
    }
    .msg-error {
      background: #fee2e2;
      color: #b91c1c;
      border-radius: 8px;
      padding: 6px 9px;
      font-size: 13px;
      margin-bottom: 10px;
    }
    .msg-ok {
      background: #dcfce7;
      color: #166534;
      border-radius: 8px;
      padding: 6px 9px;
      font-size: 13px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>

<div class="update-wrapper">
  <div class="update-header">
    <h1><i class="fa-solid fa-landmark"></i>Update Account Status</h1>
    <a href="admin.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
  </div>

  <?php if ($error): ?>
    <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if ($account): ?>
    <div class="meta">
      Update status for account <strong><?php echo htmlspecialchars($account['AccountNo']); ?></strong>
      (Customer ID: <?php echo htmlspecialchars($account['CustomerID']); ?>)
    </div>

    <form method="post">
      <input type="hidden" name="account_no" value="<?php echo htmlspecialchars($account['AccountNo']); ?>">

      <div class="form-grid">
        <div class="form-group">
          <label>Account No</label>
          <input type="text" value="<?php echo htmlspecialchars($account['AccountNo']); ?>" readonly>
        </div>

        <div class="form-group">
          <label>Customer ID</label>
          <input type="text" value="<?php echo htmlspecialchars($account['CustomerID']); ?>" readonly>
        </div>

        <div class="form-group">
          <label>Account Type</label>
          <input type="text" value="<?php echo htmlspecialchars($account['account_type']); ?>" readonly>
        </div>

        <div class="form-group">
          <label>Current Status</label>
          <input type="text" value="<?php echo htmlspecialchars($account['account_status']); ?>" readonly>
        </div>

        <div class="form-group">
          <label>Balance (BDT)</label>
          <input type="text" value="<?php echo number_format((float)$account['Balance'], 2); ?>" readonly>
        </div>

        <div class="form-group">
          <label for="new_status">New Status</label>
          <select id="new_status" name="new_status" required>
            <?php
              $statuses = ['ACTIVE','DORMANT','CLOSED'];
              $current  = strtoupper($account['account_status']);
              foreach ($statuses as $st) {
                  $sel = ($st === $current) ? 'selected' : '';
                  echo "<option value=\"{$st}\" {$sel}>{$st}</option>";
              }
            ?>
          </select>
        </div>

        <div class="form-group full">
          <label for="reason">Reason (optional)</label>
          <input type="text" id="reason" name="reason" placeholder="">
        </div>

        <div class="form-group full">
          <label for="remarks">Remarks</label>
          <textarea id="remarks" name="remarks" placeholder=""></textarea>
        </div>

        <div class="actions">
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-floppy-disk"></i>Save Status
          </button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
<?php
$conn->close();
?>
