<?php
// help-support.php
session_start();

// Must be logged in
/*if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}*/

$cid = (string)$_SESSION['cid'];

$success_msg = "";
$error_msg   = "";

// Simple demo handler – later you can insert into DB or send email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic   = trim($_POST['topic'] ?? '');
    $channel = trim($_POST['channel'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($topic === '' || $message === '') {
        $error_msg = "Please select a topic and write your message.";
    } else {
        // TODO: Insert into support_tickets table or send email to support
        $success_msg = "Your request has been submitted. Our team will contact you soon.";
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
</head>
<body>
<div class="app">

    <!-- Top Bar -->
    <div class="topbar">
        <a href="dashboard.php" class="linkish">&larr; Back to Dashboard</a>
        <span class="step">Help &amp; Support</span>
    </div>

    <h1 class="h1">Help &amp; Support</h1>

    <!-- Quick Help / FAQs -->
    <div class="card" style="margin-bottom:18px;">
        <div class="section">
            <h2 style="margin-top:0; margin-bottom:10px;">Quick Help</h2>
            <p class="note" style="margin-bottom:14px;">
                Find instant answers to common questions before submitting a support request.
            </p>

            <div class="grid">
                <div class="opt" style="cursor:default;">
                    <div>
                        <div class="title">Forgot Login Password</div>
                        <small>Use the “Forgot Password” option on the login page or contact support.</small>
                    </div>
                </div>

                <div class="opt" style="cursor:default;">
                    <div>
                        <div class="title">Transaction Not Showing</div>
                        <small>
                            Refresh your dashboard or check the statement for the correct date and account.
                        </small>
                    </div>
                </div>

                <div class="opt" style="cursor:default;">
                    <div>
                        <div class="title">Change Phone / Email</div>
                        <small>
                            Go to “Profile / Info Update” to submit a request for updating your contact details.
                        </small>
                    </div>
                </div>

                <div class="opt" style="cursor:default;">
                    <div>
                        <div class="title">Card Block / Unblock</div>
                        <small>
                            Use “My Cards” section to manage card status or contact support immediately.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Support Form -->
    <div class="card">
        <div class="section">
            <h2 style="margin-top:0; margin-bottom:10px;">Contact Support</h2>
            <p class="note" style="margin-bottom:14px;">
                Submit a ticket to our support team. We will contact you via your registered phone or email.
            </p>

            <?php if ($success_msg): ?>
                <div class="ahd" style="margin-bottom:14px; border-color:#4caf50;">
                    <span class="name">Success</span>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php elseif ($error_msg): ?>
                <div class="ahd" style="margin-bottom:14px; border-color:#f44336;">
                    <span class="name">Error</span>
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
                        <option value="any">Any (Phone or Email)</option>
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

    <!-- Extra Contact Info -->
    <div class="card" style="margin-top:18px;">
        <div class="section">
            <h3 style="margin-top:0;">Other Contact Channels</h3>
            <p class="note">
                Hotline: <b>162xx</b> (inside country) &nbsp;&bull;&nbsp;
                Email: <b>support@sbl-bank.com</b> (example)
            </p>
        </div>
    </div>

</div>
</body>
</html>
