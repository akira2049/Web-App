<?php
session_start();
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}
$cid = $_SESSION['cid'];

$fromAcc     = trim($_POST['fromAcc'] ?? '');
$walletType  = trim($_POST['walletType'] ?? '');
$walletNo    = trim($_POST['walletNo'] ?? '');
$amount      = (float)($_POST['amount'] ?? 0);
$note        = trim($_POST['note'] ?? '');
$txId        = trim($_POST['tx_id'] ?? '');  // JS-generated TX ID

if ($fromAcc === '' || $walletType === '' || $walletNo === '' || $amount <= 0 || $txId === '') {
    die("Invalid transfer request.");
}

/* ---------- DB CONNECTION ---------- */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

try {
    $conn->begin_transaction();

    // 1) Get current balance (lock row)
    $stmt = $conn->prepare(
        "SELECT Balance 
         FROM accounts 
         WHERE AccountNo = ? AND CustomerID = ?
         FOR UPDATE"
    );
    if (!$stmt) {
        throw new Exception("Prepare failed (SELECT account): " . $conn->error);
    }

    // treat both as strings
    $stmt->bind_param("ss", $fromAcc, $cid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        throw new Exception("Account not found.");
    }

    $row = $res->fetch_assoc();
    $currentBal = (float)$row['Balance'];
    $stmt->close();

    if ($currentBal < $amount) {
        throw new Exception("Insufficient balance.");
    }

    // 2) Deduct balance from bank account
    $newBal = $currentBal - $amount;

    $stmt = $conn->prepare(
        "UPDATE accounts 
         SET Balance = ? 
         WHERE AccountNo = ? AND CustomerID = ?"
    );
    if (!$stmt) {
        throw new Exception("Prepare failed (UPDATE account): " . $conn->error);
    }

    $stmt->bind_param("dss", $newBal, $fromAcc, $cid);
    $stmt->execute();

    if ($stmt->affected_rows <= 0) {
        throw new Exception("Failed to update account balance.");
    }
    $stmt->close();

    // 3) If wallet is bKash, credit the bkash table
    if (strcasecmp($walletType, 'bKash') === 0) {
        // Lock this wallet row
        $stmt = $conn->prepare("
            SELECT balance 
            FROM bkash 
            WHERE phone = ?
            FOR UPDATE
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed (SELECT bkash): " . $conn->error);
        }

        $stmt->bind_param("s", $walletNo);
        $stmt->execute();
        $resBk = $stmt->get_result();

        if ($resBk->num_rows == 0) {
            $stmt->close();
            throw new Exception("bKash wallet not found for this number.");
        }

        $bkRow    = $resBk->fetch_assoc();
        $oldBkBal = (float)$bkRow['balance'];
        $stmt->close();

        $newBkBal = $oldBkBal + $amount;

        // Update bKash balance
        $stmt = $conn->prepare("
            UPDATE bkash
            SET balance = ?
            WHERE phone = ?
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed (UPDATE bkash): " . $conn->error);
        }

        $stmt->bind_param("ds", $newBkBal, $walletNo);
        $stmt->execute();

        if ($stmt->affected_rows <= 0) {
            $stmt->close();
            throw new Exception("Failed to update bKash balance.");
        }

        $stmt->close();
    }

    // 4) Insert MFS transfer history
    // mfs_transfers: tx_id, cid, from_acc, wallet_type, wallet_number,
    // amount, from_balance_before, from_balance_after, note, status
    $stmt = $conn->prepare("
        INSERT INTO mfs_transfers
        (tx_id, cid, from_acc, wallet_type, wallet_number,
         amount, from_balance_before, from_balance_after, note, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed (INSERT mfs_transfers): " . $conn->error);
    }

    $status = "SUCCESS";

    $stmt->bind_param(
        "sssssdddss",
        $txId,        // tx_id
        $cid,         // cid
        $fromAcc,     // from_acc
        $walletType,  // wallet_type
        $walletNo,    // wallet_number
        $amount,      // amount
        $currentBal,  // from_balance_before
        $newBal,      // from_balance_after
        $note,        // note
        $status       // status
    );

    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $conn->close();

    // use tx_id on success page
    header("Location: mfs-transfer-success.php?tid=" . urlencode($txId));
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    echo "
      <div style='font-family:Inter,Arial;padding:20px'>
        <h2 style='color:#c00'>Transfer Failed</h2>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <a href='mfs-transfer.php'>← Back to MFS Transfer</a>
      </div>
    ";
}
?>
