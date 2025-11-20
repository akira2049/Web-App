<?php
// update_account_status.php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_no = $_POST['account_no'] ?? '';
    $new_status = $_POST['new_status'] ?? '';
    //$reason     = $_POST['reason']     ?? null;
    //$remarks    = $_POST['remarks']    ?? null;

    // Adjust column names (status, reason, remarks) if different in your DB
    $sql = "UPDATE accounts
            SET account_status = ?
            WHERE AccountNo = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $new_status, $account_no);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "<h2>Account status updated successfully.</h2>";
        } else {
            echo "<h2>No account found with this Account No, or no changes made.</h2>";
        }
    } else {
        echo "<h2>Error updating status: " . htmlspecialchars($stmt->error) . "</h2>";
    }

    echo '<p><a href="admin_dashboard.php">Back to Admin Dashboard</a></p>';

    $stmt->close();
}

$conn->close();
?>
