<?php
// admin-get_account_by_cid.php
header('Content-Type: application/json');
session_start();

/*if (!isset($_SESSION['cid'], $_SESSION['type'], $_SESSION['admin_verified'])) {
    echo json_encode(["ok" => false, "message" => "Unauthorized"]);
    exit;
}*/


$host     = "localhost";
$user     = "root";
$password = "";
$database = "my_bank";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["ok" => false, "message" => "DB connection failed"]);
    exit;
}

$cidRaw = trim($_GET['cid'] ?? '');
if ($cidRaw === '' || !ctype_digit($cidRaw)) {
    echo json_encode(["ok" => false, "message" => "CID must be a number"]);
    exit;
}

$cid = (int)$cidRaw;

$sql = "
    SELECT AccountNo, account_name
    FROM accounts
    WHERE CustomerID = ?
    ORDER BY AccountNo ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cid);
$stmt->execute();
$res = $stmt->get_result();

$accounts = [];
$holderName = null;

while ($row = $res->fetch_assoc()) {
    $accounts[] = $row['AccountNo'];
    if ($holderName === null && $row['account_name'] !== '') {
        $holderName = $row['account_name']; // first name found
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    "ok" => true,
    "accounts" => $accounts,
    "holderName" => $holderName
]);
