<?php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Values from admin form
    $customer_id   = $_POST['customer_id']  ?? '';
    $linkedAccount = $_POST['account_no']   ?? '';
    $cardHolder    = $_POST['card_holder']  ?? '';
    $cardNo        = $_POST['card_number']  ?? '';
    $cvc           = $_POST['cvc']          ?? '';
    $cardType      = $_POST['card_type']    ?? '';
    $cardLimit     = $_POST['credit_limit'] ?? 0;
    $expiryInput   = $_POST['expiry_date']  ?? ''; // from <input type="month">: YYYY-MM
    $cardStatus    = $_POST['status']       ?? 'ACTIVE';

    // Convert YYYY-MM to YYYY-MM-01 for DATE column
    if (!empty($expiryInput)) {
        $expiryDate = $expiryInput . "-01";
    } else {
        // fallback if user doesn't select anything
        $expiryDate = "2099-12-01";
    }

    // ---- Get balance from accounts table using CustomerID ----
    $balance = 0.0; // default if not found

    if (!empty($customer_id)) {
        // safer prepared statement
        $balStmt = $conn->prepare("SELECT Balance FROM accounts WHERE CustomerID = ?");
        if ($balStmt) {
            $balStmt->bind_param("s", $customer_id);
            $balStmt->execute();
            $balStmt->bind_result($dbBalance);
            if ($balStmt->fetch()) {
                $balance = (float)$dbBalance;
            }
            $balStmt->close();
        }
    }

    // Ensure cardLimit is integer
    if ($cardLimit === '' || $cardLimit === null) {
        $cardLimit = 0;
    } else {
        $cardLimit = (int)$cardLimit;
    }

    // EXACT SQL for your 10-column table
    $sql = "INSERT INTO cards
            (customer_id, cardNo, cardHolderName, expiryDate, cvc, linkedAccount, balance, cardLimit, cardType, cardStatus)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind params: s = string, d = double, i = integer
    $stmt->bind_param(
        "ssssssdiss",
        $customer_id,   // customer_id
        $cardNo,        // cardNo
        $cardHolder,    // cardHolderName
        $expiryDate,    // expiryDate (YYYY-MM-01)
        $cvc,           // cvc
        $linkedAccount, // linkedAccount
        $balance,       // balance from accounts
        $cardLimit,     // cardLimit
        $cardType,      // cardType
        $cardStatus     // cardStatus
    );

    if ($stmt->execute()) {
        echo "<h2>Card created successfully.</h2>";
    } else {
        echo "<h2>Error creating card: " . htmlspecialchars($stmt->error) . "</h2>";
    }

    echo '<p><a href="admin.php">Back to Dashboard</a></p>';

    $stmt->close();
    $conn->close();
}
?>
