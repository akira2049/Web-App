<?php
// update_card_status.php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_number = $_POST['card_number'] ?? '';
    $new_status  = $_POST['new_status']  ?? '';
    //$reason      = $_POST['reason']      ?? null;
    //$remarks     = $_POST['remarks']     ?? null;

    // Adjust columns if necessary (status, reason, remarks)
    $sql = "UPDATE cards
            SET cardStatus = ?
            WHERE cardNo = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $new_status, $card_number);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "<h2>Card status updated successfully.</h2>";
        } else {
            echo "<h2>No card found with this number, or no changes made.</h2>";
        }
    } else {
        echo "<h2>Error updating card: " . htmlspecialchars($stmt->error) . "</h2>";
    }

    echo '<p><a href="admin_dashboard.php">Back to Dashboard</a></p>';

    $stmt->close();
}

$conn->close();
?>
