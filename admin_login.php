<?php
session_start();

// --- DATABASE CONFIG ---
$host     = "localhost";
$dbname   = "my_bank";
$db_user  = "root";
$db_pass  = "";
$table    = "user"; 
// -----------------------

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Form values
    $customerID = trim($_POST['customer_id'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if ($customerID === "" || $password === "") {
        $error = "Please enter both Customer ID and Password.";
    } else {

        // Connect to DB
        $conn = new mysqli($host, $db_user, $db_pass, $dbname);

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Get cid + password + user_type from DB
        $sql = "SELECT cid, user_password, user_type FROM $table WHERE cid = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }

        $stmt->bind_param("i", $customerID);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if CID exists
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Compare plain text passwords
            if ($password === $user['user_password']) {

                // Save cid + type in session
                $_SESSION['cid'] = $user['cid'];
                $_SESSION['type'] = $user['user_type'];

                // --- REDIRECT BASED ON USER TYPE ---
                if ($user['user_type'] === "admin") {
                    header("Location: admin.php");
                    exit;
                } else {
                    header("Location: dashboard.php");
                    exit;
                }
            }

            $error = "Invalid Customer ID or Password.";
        } else {
            $error = "Invalid Customer ID or Password.";
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
  <title>Banking Customer Portal - Login</title>
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="login-container">
    <h2>Login</h2>

    <?php if (!empty($error)): ?>
      <p style="color:red; margin-bottom:10px;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form id="loginForm" method="post" action="admin_login.php">
      <div class="input-group">
        <label for="customer_id">User ID</label>
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
