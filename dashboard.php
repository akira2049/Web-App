<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = $_SESSION['cid'];  // logged-in customer ID

// --- DB CONNECTION ---
$host = "localhost";
$dbname = "my_bank";
$db_user = "root";
$db_pass = "";

$conn = new mysqli($host, $db_user, $db_pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* -------------------------------------------------
   STEP 1: Get phone number of the logged-in cid
--------------------------------------------------*/
$sql = "SELECT phone FROM user WHERE cid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $cid);
$stmt->execute();
$result = $stmt->get_result();

$phone = null;

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $phone = $row['phone'];
}

$stmt->close();

/* -------------------------------------------------
   STEP 2: Search ACCOUNT table using phone number
--------------------------------------------------*/

$balance = 0;

if ($phone !== null) {
    $sql2 = "SELECT Balance FROM accounts WHERE phone = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("s", $phone);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($result2 && $result2->num_rows === 1) {
        $row2 = $result2->fetch_assoc();
        $balance = $row2['Balance'];
    }

    $stmt2->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Banking Dashboard</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

  <!-- Header -->
  <header>
    <h1>Welcome to Your Banking Dashboard</h1>

    <p style="text-align:center; color:#FFFFFF;">
        CID: <strong><?php echo htmlspecialchars($cid); ?></strong>
        <br>
        Balance: <strong style="color:#FFFFFF;">৳ <?php echo number_format($balance, 2); ?></strong>
    </p>
  </header>

  <!-- Dashboard Features -->
  <section class="dashboard">
    <a href="fund-transfer.php" class="card">
      <i class="fa-solid fa-money-bill-transfer fa-3x"></i>
      <h3>Fund Transfer</h3>
      <p>Send money securely to any bank account.</p>
    </a>

    <a href="bill-payments.php" class="card">
      <i class="fa-solid fa-wallet fa-3x"></i>
      <h3>Bill Payment</h3>
      <p>Pay utility and service bills online.</p>
    </a>

    <a href="card-payment.php" class="card">
      <i class="fa-regular fa-credit-card fa-3x"></i>
      <h3>Card Payment</h3>
      <p>Pay credit card bills quickly and easily.</p>
    </a>

    <a href="recharge.php" class="card">
      <i class="fa-solid fa-mobile fa-3x"></i>
      <h3>Recharge</h3>
      <p>Top up mobile and DTH services instantly.</p>
    </a>

    <a href="add-money.php" class="card">
      <i class="fa-solid fa-money-bill-wave fa-3x"></i>
      <h3>Add Money</h3>
      <p>Deposit funds to your account anytime.</p>
    </a>

    <a href="statement.php" class="card">
      <i class="fa-solid fa-calendar-days fa-3x"></i>
      <h3>Statement</h3>
      <p>View and download your account statements.</p>
    </a>

    <a href="certificate.php" class="card">
      <i class="fa-solid fa-file-lines fa-3x"></i>
      <h3>Certificate</h3>
      <p>View and download your account certificates.</p>
    </a>

    <a href="history.php" class="card">
      <i class="fa-solid fa-clock-rotate-left fa-3x"></i>
      <h3>History</h3>
      <p>Check all your past transactions.</p>
    </a>

    <a href="info-update.php" class="card">
      <i class="fa-solid fa-user fa-3x"></i>
      <h3>Info Update</h3>
      <p>Update your account information</p>
    </a>

    <a href="add-card.php" class="card">
      <i class="fa-brands fa-cc-visa fa-3x"></i>
      <h3>Add Card</h3>
      <p>Add VISA or MasterCard</p>
    </a>

    <a href="support.php" class="card">
      <i class="fa-solid fa-phone-volume fa-3x"></i>
      <h3>Help & Support</h3>
      <p>Get connected with our support line for any query</p>
    </a>

    <a href="find.php" class="card">
      <i class="fa-solid fa-location-dot fa-3x"></i>
      <h3>Find SBL</h3>
      <p>Find our branches, offices, agent banking outlets and others</p>
    </a>
  </section>

  <!-- Logout -->
  <a href="logout.php" class="logout">Logout</a>

</body>
</html>
