<?php
session_start();

if (!isset($_SESSION['pending_admin_id'], $_SESSION['login_otp'], $_SESSION['otp_expires'])) {
    header("Location: admin_login.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp']);

    if ($otp === "") {
        $error = "Enter OTP.";
    } else {

        if (time() > $_SESSION['otp_expires']) {
            $error = "OTP expired. Please login again.";
        } 
        else if ($otp == $_SESSION['login_otp']) {

            $_SESSION['cid'] = $_SESSION['pending_admin_id'];
            $_SESSION['type'] = "admin";
            $_SESSION['admin_verified'] = true;

            unset($_SESSION['pending_admin_id'], $_SESSION['login_otp'], $_SESSION['otp_expires']);

            header("Location: admin.php");
            exit;
        } 
        else {
            $error = "Invalid OTP.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin OTP</title>
<link rel="stylesheet" href="login.css">
</head>
<body>
<div class="login-container">
<h2>OTP Verification</h2>
<p>Enter the 6-digit OTP sent to your phone.</p>

<?php if ($error): ?>
<p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>

<form method="post">
    <div class="input-group">
        <label>OTP</label>
        <input type="text" name="otp" required placeholder="">
    </div>
    <button class="login-btn">Verify</button>
</form>

</div>
</body>
</html>
