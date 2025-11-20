<?php
// add_account.php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id      = $_POST['customer_id']      ?? '';
    $account_no       = $_POST['account_no']       ?? '';
    $account_name      = $_POST['account_name']       ?? '';
    $account_type     = $_POST['account_type']     ?? '';
    $opening_balance  = $_POST['opening_balance']  ?? 0;
    $currency         = $_POST['currency']         ?? 'BDT';
    $account_status           = $_POST['status']           ?? 'ACTIVE';
    $email    = $_POST['email']     ?? '';
    $phone    = $_POST['phone']     ?? '';
    $nid      = $_POST['nid']     ?? '';      
    //$notes            = $_POST['notes']            ?? null;

    // Adjust column names to match your `accounts` table
    $sql = "INSERT INTO accounts 
            (CustomerID, AccountNo, account_name, account_type, Balance, currency, account_status, Email, Phone, nid)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "isssssssss",
        $customer_id,
        $account_no,
        $account_name,
        $account_type,
        $opening_balance,
        $currency,
        $account_status,
        $email,
        $phone,
        $nid
    );

    if ($stmt->execute()) {
        echo "<h2>Account created successfully.</h2>";
    } else {
        echo "<h2>Error creating account: " . htmlspecialchars($stmt->error) . "</h2>";
    }

    echo '<p><a href="admin.php">Back to Dashboard</a></p>';

    $stmt->close();
}

$conn->close();
?>
