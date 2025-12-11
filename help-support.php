<?php
// help-support.php
session_start();

// Must be logged in
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = (int)$_SESSION['cid'];

$success_msg = "";
$error_msg   = "";

/* ---------- DB CONNECTION ---------- */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/* ---------- Handle Form Submission ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $topic   = trim($_POST['topic'] ?? '');
    $channel = trim($_POST['channel'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($topic === '' || $message === '') {
        $error_msg = "Please select a topic and write your message.";
    } else {

        // Insert into DB
        $sql = "INSERT INTO support_requests (cid, topic, channel, message)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("isss", $cid, $topic, $channel, $message);

            if ($stmt->execute()) {
                $success_msg = "Your request has been submitted. Our team will contact you soon.";
            } else {
                $error_msg = "Database error: Could not submit request.";
            }

            $stmt->close();
        } else {
            $error_msg = "Server error: Could not prepare statement.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Help &amp; Support — Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="transfer.css">
    <style>
        :root { --primary:#00416A; }

        /* Dashboard-style background + centering */
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            background: linear-gradient(135deg, #00416A, #E4E5E6);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 24px 12px;
        }

        .app {
            width: 100%;
            max-width: 960px;
        }

        .card {
            background:#ffffff;
            border-radius:12px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
        }

        .h1 {
            color:#ffffff;
            text-shadow:0 1px 2px rgba(0,0,0,0.25);
        }

        .note {
            color: var(--muted);
            font-size: 13px;
        }

        /* status messages */
        .status-box{
            display:flex;
            flex-direction:column;
            gap:4px;
            padding:10px 12px;
            border-radius:10px;
            border:1px solid;
            font-size:14px;
        }
    </style>
</head>
<body>
<div class="app">

    <!-- Top Bar -->
    <div class="topbar">
        <a href="dashboard.php" class="linkish">&larr; Back to Dashboard</a>
        <span class="step">Help &amp; Support</span>
    </div>

    <h1 class="h1">Help &amp; Support</h1>

    <!-- Quick Help Section ... (unchanged) -->
    <!-- -------------------------------------- -->

    <!-- Contact Support Form -->
    <div class="card">
        <div class="section">
            <h2 style="margin-top:0; margin-bottom:10px;">Contact Support</h2>
            <p class="note" style="margin-bottom:14px;">
                Submit a ticket to our support team. We will contact you via your registered phone or email.
            </p>

            <?php if ($success_msg): ?>
                <div class="status-box" style="margin-bottom:14px; border-color:#4caf50; background:#ecfdf3; color:#14532d;">
                    <span class="name" style="font-weight:700;">Success</span>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php elseif ($error_msg): ?>
                <div class="status-box" style="margin-bottom:14px; border-color:#f44336; background:#fef2f2; color:#7f1d1d;">
                    <span class="name" style="font-weight:700;">Error</span>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="help-support.php">
                <div class="row">
                    <label class="label" for="topic">Issue Type</label>
                    <select id="topic" name="topic">
                        <option value="">-- Select --</option>
                        <option value="login">Login / Password Issue</option>
                        <option value="transfer">Bank Transfer / MFS Transfer</option>
                        <option value="card">Card / Add Money Issue</option>
                        <option value="bill">Bill Payment / Recharge</option>
                        <option value="profile">Profile / Account Information</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="row">
                    <label class="label" for="channel">Preferred Contact</label>
                    <select id="channel" name="channel">
                        <option value="any">Any</option>
                        <option value="phone">Phone Call</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>

                <div class="row">
                    <label class="label" for="message">Describe Your Issue</label>
                    <textarea id="message" name="message" rows="5"
                        placeholder="Write details about your problem (date, amount, account, reference, etc.)"></textarea>
                </div>

                <div class="footerbar">
                    <button type="submit" class="btn">Submit Support Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Extra Contact Info Section ... (unchanged) -->

</div>
</body>
</html>
