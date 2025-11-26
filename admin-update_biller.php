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

$billerCode = $_GET['biller_code'] ?? $_POST['biller_code'] ?? '';
$biller     = null;
$error      = "";

// ---------- HANDLE POST (UPDATE) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billerCode = trim($_POST['biller_code'] ?? '');
    $newName    = trim($_POST['biller_name'] ?? '');
    $newCat     = trim($_POST['category'] ?? '');
    $newStatus  = trim($_POST['status'] ?? '');

    if ($billerCode === "" || $newName === "" || $newCat === "" || $newStatus === "") {
        $error = "All fields are required.";
    } else {
        $stmt = $conn->prepare("
            UPDATE billers 
            SET biller_name = ?, biller_category = ?, biller_status = ?
            WHERE biller_code = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ssss", $newName, $newCat, $newStatus, $billerCode);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: admin.php");
                exit;
            } else {
                $error = "Failed to update biller.";
            }
            $stmt->close();
        } else {
            $error = "Failed to prepare update statement.";
        }
    }
}

// ---------- LOAD BILLER ----------
if ($billerCode !== "") {
    $stmt = $conn->prepare("
        SELECT biller_code, biller_name, biller_category, biller_status
        FROM billers
        WHERE biller_code = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $billerCode);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $biller = $res->fetch_assoc();
        } else {
            $error = "Biller not found.";
        }
        $stmt->close();
    } else {
        $error = "Failed to prepare biller lookup.";
    }
} else {
    $error = "No biller code specified.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update Biller</title>
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
    input, select {
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
  </style>
</head>
<body>

<div class="update-wrapper">
  <div class="update-header">
    <h1><i class="fa-solid fa-file-invoice-dollar"></i>Update Biller</h1>
    <a href="admin.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
  </div>

  <?php if ($error): ?>
    <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if ($biller): ?>
    <div class="meta">
      Update biller <strong><?php echo htmlspecialchars($biller['biller_name']); ?></strong>
      (Code: <?php echo htmlspecialchars($biller['biller_code']); ?>)
    </div>

    <form method="post">
      <input type="hidden" name="biller_code" value="<?php echo htmlspecialchars($biller['biller_code']); ?>">

      <div class="form-grid">
        <div class="form-group">
          <label>Biller Code</label>
          <input type="text" value="<?php echo htmlspecialchars($biller['biller_code']); ?>" readonly>
        </div>

        <div class="form-group">
          <label for="biller_name">Biller Name</label>
          <input type="text" id="biller_name" name="biller_name"
                 value="<?php echo htmlspecialchars($biller['biller_name']); ?>" required>
        </div>

        <div class="form-group">
          <label for="category">Category</label>
          <select id="category" name="category" required>
            <?php
              $cats = ['ELECTRICITY','GAS','WATER','INTERNET','MOBILE','TUITION','OTHERS'];
              $current = strtoupper($biller['biller_category']);
              echo '<option value="">Select category</option>';
              foreach ($cats as $cat) {
                  $sel = ($cat === $current) ? 'selected' : '';
                  echo "<option value=\"{$cat}\" {$sel}>{$cat}</option>";
              }
            ?>
          </select>
        </div>

        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status" required>
            <?php
              $statuses = ['ACTIVE','INACTIVE'];
              $current  = strtoupper($biller['biller_status']);
              foreach ($statuses as $st) {
                  $sel = ($st === $current) ? 'selected' : '';
                  echo "<option value=\"{$st}\" {$sel}>{$st}</option>";
              }
            ?>
          </select>
        </div>

        <div class="actions">
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-floppy-disk"></i>Save Changes
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
