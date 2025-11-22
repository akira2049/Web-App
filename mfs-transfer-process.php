<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
$cid = $_SESSION['cid'];

$fromAcc     = $_POST['fromAcc'] ?? '';
$walletType  = $_POST['walletType'] ?? '';
$walletNo    = $_POST['walletNo'] ?? '';
$receiver    = $_POST['receiverName'] ?? '';
$amount      = (float)($_POST['amount'] ?? 0);
$note        = $_POST['note'] ?? '';

if($fromAcc=='' || $walletType=='' || $walletNo=='' || $receiver=='' || $amount<=0){
    die("Invalid transfer request.");
}

$host="localhost"; $user="root"; $pass=""; $db="my_bank";
$conn = new mysqli($host,$user,$pass,$db);
if($conn->connect_error){ die("DB connection failed"); }

try {
    $conn->begin_transaction();

    // 1) get current balance
    $stmt = $conn->prepare("SELECT Balance FROM accounts WHERE AccountNo=? AND cid=? FOR UPDATE");
    $stmt->bind_param("ss", $fromAcc, $cid);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res->num_rows==0){
        throw new Exception("Account not found.");
    }
    $balRow = $res->fetch_assoc();
    $currentBal = (float)$balRow['Balance'];
    $stmt->close();

    if($currentBal < $amount){
        throw new Exception("Insufficient balance.");
    }

    // 2) deduct balance
    $newBal = $currentBal - $amount;
    $stmt = $conn->prepare("UPDATE accounts SET Balance=? WHERE AccountNo=? AND cid=?");
    $stmt->bind_param("dss", $newBal, $fromAcc, $cid);
    $stmt->execute();
    $stmt->close();

    // 3) insert transfer history
    $stmt = $conn->prepare("
        INSERT INTO mfs_transfers
        (cid, from_account, wallet_type, wallet_number, receiver_name, amount, note, status)
        VALUES (?,?,?,?,?,?,?,'SUCCESS')
    ");
    $stmt->bind_param("sssssdss",
        $cid, $fromAcc, $walletType, $walletNo, $receiver, $amount, $note
    );
    $stmt->execute();
    $tid = $stmt->insert_id;
    $stmt->close();

    $conn->commit();
    $conn->close();

    // success redirect (make your own success page if you want)
    header("Location: mfs-transfer-success.php?tid=".$tid);
    exit;

} catch(Exception $e){
    $conn->rollback();
    $conn->close();
    echo "
      <div style='font-family:Inter,Arial;padding:20px'>
        <h2 style='color:#c00'>Transfer Failed</h2>
        <p>".$e->getMessage()."</p>
        <a href='mfs-transfer.php'>← Back to MFS Transfer</a>
      </div>
    ";
}
