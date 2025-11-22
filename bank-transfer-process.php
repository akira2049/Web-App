<?php
session_start();

if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

if (
    !isset($_SESSION['ebl_transfer_verified']) ||
    $_SESSION['ebl_transfer_verified'] !== true ||
    !isset($_SESSION['ebl_pending_transfer'])
) {
    header("Location: bank-transfer.php");
    exit;
}

$pending  = $_SESSION['ebl_pending_transfer'];

$from_acc = trim($pending['from_acc'] ?? '');
$to_acc   = trim($pending['to_acc'] ?? '');
$amount   = (float)($pending['amount'] ?? 0);
$note     = trim($pending['note'] ?? '');

if ($from_acc=='' || $to_acc=='' || $amount<=0 || $from_acc==$to_acc) {
    die("Invalid transfer data.");
}


/* DB */
$host="localhost"; $user="root"; $password=""; $database="my_bank";
$conn = new mysqli($host,$user,$password,$database);
if ($conn->connect_error) die("DB failed: ".$conn->connect_error);

$conn->begin_transaction();

try {
    // Lock both accounts for safe balance update
    $stmt = $conn->prepare("SELECT AccountNo, Balance FROM accounts WHERE AccountNo=? FOR UPDATE");
    $stmt->bind_param("s", $from_acc);
    $stmt->execute();
    $fromRes = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT AccountNo, Balance FROM accounts WHERE AccountNo=? FOR UPDATE");
    $stmt->bind_param("s", $to_acc);
    $stmt->execute();
    $toRes = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fromRes || !$toRes) {
        throw new Exception("Account not found.");
    }

    $fromBal = (float)$fromRes['Balance'];
    $toBal   = (float)$toRes['Balance'];

    if ($fromBal < $amount) {
        throw new Exception("Insufficient balance.");
    }

    $newFrom = $fromBal - $amount;
    $newTo   = $toBal + $amount;

    // Update balances
    $stmt = $conn->prepare("UPDATE accounts SET Balance=? WHERE AccountNo=?");
    $stmt->bind_param("ds", $newFrom, $from_acc);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE accounts SET Balance=? WHERE AccountNo=?");
    $stmt->bind_param("ds", $newTo, $to_acc);
    $stmt->execute();
    $stmt->close();

    // Insert transaction record
    $txType = "OWN_TRANSFER";
    $stmt = $conn->prepare("
        INSERT INTO bank_transfers (transfer_type, from_acc, to_acc, amount, note)
        VALUES (?,?,?,?,?)
    ");
    $stmt->bind_param("sssds", $txType, $from_acc, $to_acc, $amount, $note);
    $stmt->execute();
    $txId = $stmt->insert_id;
    $stmt->close();

    $conn->commit();
    // clear transfer session after success
    unset($_SESSION['ebl_pending_transfer'], $_SESSION['ebl_transfer_verified'], $_SESSION['otp_code'], $_SESSION['otp_exp']);

    // redirect to success page
    header("Location: bank-transfer-success.php?tx=$txId");
    exit;


} catch (Exception $e) {
    $conn->rollback();
    die("Transfer failed: " . $e->getMessage());
}
