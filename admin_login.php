<?php
session_start();
// require_once 'infobip_sms.php'; // DEMO MODE: not needed

// --- DATABASE CONFIG ---
$host     = "localhost";
$dbname   = "my_bank";
$db_user  = "root";
$db_pass  = "";
$table    = "user";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerID = trim($_POST['customer_id'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if ($customerID === "" || $password === "") {
        $error = "Please enter both User ID and Password.";
    } else {

        $conn = new mysqli($host, $db_user, $db_pass, $dbname);

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $sql = "SELECT cid, phone, user_password, user_type FROM $table WHERE cid = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }

        $stmt->bind_param("i", $customerID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($password === $user['user_password']) {

                if ($user['user_type'] !== "admin") {
                    $error = "This login page is only for admin.";
                } else {

                    if (empty($user['phone'])) {
                        $error = "Admin phone number is missing in database.";
                    } else {

                        // DEMO OTP: fixed 123456, no SMS sending
                        $otp = 123456;

                        $_SESSION['pending_admin_id'] = $user['cid'];
                        $_SESSION['admin_phone']      = $user['phone'];
                        $_SESSION['login_otp']        = $otp;
                        $_SESSION['otp_expires']      = time() + 300; // 5 minutes

                        // In demo mode we skip Infobip and just go to OTP page
                        header("Location: admin_otp.php");
                        exit;
                    }
                }
            } else {
                $error = "Invalid ID or password.";
            }
        } else {
            $error = "Invalid ID or password.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Banking Customer Portal - Admin Login</title>
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="login-container">
    <h2>Admin Login</h2>

    <?php if (!empty($error)): ?>
      <p style="color:red; margin-bottom:10px;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form id="loginForm" method="post" action="admin_login.php">
      <div class="input-group">
        <label for="customer_id">Admin User ID</label>
        <input type="text" id="customer_id" name="customer_id" placeholder="Enter your user ID" required>
      </div>
      <div class="input-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your Password" required>
      </div>
      <button type="submit" class="login-btn">Login</button>
    </form>
    <div class="extra-links">
      <p><a href="pass_reset.php">Forgot Password?</a></p>
    </div>
  </div>
</body>
</html>
