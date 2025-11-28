<?php
header('Content-Type: application/json');

$phone = $_GET['phone'] ?? '';
$phone = trim($phone);

if ($phone === '') {
    echo json_encode(['name' => '']);
    exit;
}

$host="localhost"; $user="root"; $pass=""; $db="my_bank";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) {
    echo json_encode(['name' => '']);
    exit;
}

$stmt = $conn->prepare("SELECT user_name FROM nagad WHERE phone = ? LIMIT 1");
$stmt->bind_param("s", $phone);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'name' => $row['user_name'] ?? ''
]);
