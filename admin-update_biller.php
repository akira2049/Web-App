<?php
// add_biller.php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $biller_name  = $_POST['biller_name']  ?? '';
    $biller_code  = $_POST['biller_code']  ?? '';
    $category     = $_POST['category']     ?? '';
    $status       = $_POST['status']       ?? 'ACTIVE';
    //$ref_format   = $_POST['ref_format']   ?? null;
    //$description  = $_POST['description']  ?? null;

    // Adjust table/column names to match your `billers` table
    $sql = "UPDATE billers
            SET biller_status = ?
            WHERE biller_code = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssss",
        $biller_name,
        $biller_code,
        $biller_category,
        $biller_status,
    );

    if ($stmt->execute()) {
        echo "<h2>Biller added successfully.</h2>";
    } else {
        echo "<h2>Error adding biller: " . htmlspecialchars($stmt->error) . "</h2>";
    }

    echo '<p><a href="admin.php">Back to Dashboard</a></p>';

    $stmt->close();
}

$conn->close();
?>
