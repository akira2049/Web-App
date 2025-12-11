<?php
session_start();

if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['ebl_pending_transfer'])) {
    die("No pending transfer.");
}

$cid  = $_SESSION['cid'];
$data = $_SESSION['ebl_pending_transfer'];

$fromAcc = $data['from_acc'];
$toAcc   = $data['to_acc'];
$amount  = floatval($data['amount']);
$note    = $data['note'] ?? "";
$transferType = $data['transfer_type'] ?? ""; // from your overview page

/* ---------- DB CONNECTION ---------- */
$host="localhost"; 
$user="root"; 
$password="";
$database="my_bank";

$conn = new mysqli($host,$user,$password,$database);
if ($conn->connect_error) die("DB connection failed: ".$conn->connect_error);

/* ------------------------------------------
   STEP 1 — FETCH SENDER BALANCE BEFORE
------------------------------------------- */
$stmt = $conn->prepare("SELECT Balance FROM accounts WHERE AccountNo = ?");
$stmt->bind_param("s", $fromAcc);
$stmt->execute();
$senderResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$senderResult) die("Sender account not found: $fromAcc");

$from_before = floatval($senderResult['Balance']);
$from_after  = $from_before - $amount;

if ($from_after < 0) die("Insufficient balance.");

/* ------------------------------------------
   STEP 2 — FETCH RECEIVER BALANCE BEFORE
------------------------------------------- */
$stmt = $conn->prepare("SELECT Balance FROM accounts WHERE AccountNo = ?");
$stmt->bind_param("s", $toAcc);
$stmt->execute();
$receiverResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$receiverResult) die("Receiver account not found: $toAcc");

$to_before = floatval($receiverResult['Balance']);
$to_after  = $to_before + $amount;

/* ------------------------------------------
   STEP 3 — UPDATE SENDER BALANCE
------------------------------------------- */
$stmt = $conn->prepare("UPDATE accounts SET Balance = ? WHERE AccountNo = ?");
$stmt->bind_param("ds", $from_after, $fromAcc);
$stmt->execute();
$stmt->close();

/* ------------------------------------------
   STEP 4 — UPDATE RECEIVER BALANCE
------------------------------------------- */
$stmt = $conn->prepare("UPDATE accounts SET Balance = ? WHERE AccountNo = ?");
$stmt->bind_param("ds", $to_after, $toAcc);
$stmt->execute();
$stmt->close();

/* ------------------------------------------
   STEP 5 — GENERATE TRANSACTION ID (ref_id)
------------------------------------------- */
$ref_id = $cid . "-TX-" . random_int(100000, 999999);

/* ------------------------------------------
   STEP 6 — INSERT INTO bank_transfers
------------------------------------------- */
/*
TABLE STRUCTURE:
cid, transfer_type, from_acc, to_acc, amount,
from_balance_before, from_balance_after,
to_balance_before, to_balance_after,
note, status, created_at, ref_id
*/

$sql = "
INSERT INTO bank_transfers
(cid, transfer_type, from_acc, to_acc, amount,
 from_balance_before, from_balance_after,
 to_balance_before, to_balance_after,
 note, status, created_at, ref_id)
VALUES
(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SUCCESS', NOW(), ?)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "isssddddsss",
    $cid,
    $transferType,
    $fromAcc,
    $toAcc,
    $amount,
    $from_before,
    $from_after,
    $to_before,
    $to_after,
    $note,
    $ref_id
);

$stmt->execute();
$stmt->close();

/* ------------------------------------------
   STEP 7 — CLEAR SESSION + REDIRECT
------------------------------------------- */
unset($_SESSION['ebl_pending_transfer']);

header("Location: bank-transfer-success.php?tid=" . urlencode($ref_id));
exit;

?>
