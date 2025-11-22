<?php
// get_bkash_name.php

if (!isset($_GET['phone'])) {
    echo json_encode(['name' => '']);
    exit;
}

$phone = trim($_GET['phone']);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['name' => '']);
    exit;
}

$stmt = $conn->prepare("SELECT user_name FROM bkash WHERE phone = ?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$res = $stmt->get_result();

$name = "";
if ($row = $res->fetch_assoc()) {
    $name = $row['user_name'];
}

echo json_encode(['name' => $name]);
?>
