<?php
session_start();

if (!isset($_SESSION['resident_id']) || (int) $_SESSION['resident_id'] < 1) {
    header('Location: ../login.php');
    exit();
}
require_once __DIR__ . '/../config/database.php';

// Withdrawal is a state-changing action: viewing a URL must never delete data.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my-complaints.php', true, 303);
    exit();
}

function sc_withdraw_redirect($type, $message) {
    $_SESSION['societycare_withdraw_flash'] = [
        'type' => $type,
        'text' => $message,
    ];
    header('Location: my-complaints.php', true, 303);
    exit();
}

$stored_token = $_SESSION['societycare_withdraw_csrf'] ?? null;
$submitted_token = $_POST['csrf_token'] ?? null;
if (!is_string($stored_token) || !is_string($submitted_token) ||
    !hash_equals($stored_token, $submitted_token)) {
    sc_withdraw_redirect('danger', 'Invalid or expired withdrawal form. Please try again.');
}

$id_input = $_POST['complaint_id'] ?? null;
$complaint_id = is_string($id_input)
    ? filter_var($id_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($complaint_id === false) {
    sc_withdraw_redirect('danger', 'Invalid complaint. Please try again.');
}
$resident_id = (int) $_SESSION['resident_id'];

$transaction_started = false;
try {
    mysqli_begin_transaction($conn);
    $transaction_started = true;

    // Lock the exact complaint so its owner/status cannot change mid-withdrawal.
    $stmt = mysqli_prepare($conn, "SELECT complaint_id FROM complaints
                                  WHERE complaint_id = ? AND resident_id = ? AND status = 'Pending'
                                  FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'ii', $complaint_id, $resident_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $allowed = mysqli_stmt_num_rows($stmt) === 1;
    mysqli_stmt_close($stmt);
    if (!$allowed) {
        mysqli_rollback($conn);
        sc_withdraw_redirect('danger', 'Complaint could not be withdrawn. Only your pending complaints can be withdrawn.');
    }

    // Feedback references complaints with a restrictive foreign key.
    // Do not remove feedback to make withdrawal succeed.
    $stmt = mysqli_prepare($conn, 'SELECT feedback_id FROM feedback WHERE complaint_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $complaint_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $has_feedback = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if ($has_feedback) {
        mysqli_rollback($conn);
        sc_withdraw_redirect('danger', 'Complaint could not be withdrawn because feedback already exists.');
    }

    // This demo schema does not declare a history -> complaints foreign key.
    // Clean up related history inside the same transaction to avoid orphaned rows.
    $stmt = mysqli_prepare($conn, 'DELETE FROM complaint_history WHERE complaint_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $complaint_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Recheck ownership and Pending status in the actual destructive statement.
    $stmt = mysqli_prepare($conn, "DELETE FROM complaints
                                  WHERE complaint_id = ? AND resident_id = ? AND status = 'Pending'");
    mysqli_stmt_bind_param($stmt, 'ii', $complaint_id, $resident_id);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($deleted !== 1) {
        mysqli_rollback($conn);
        sc_withdraw_redirect('danger', 'Complaint could not be withdrawn. Please try again.');
    }

    mysqli_commit($conn);
    $transaction_started = false;
    // Rotate the session token after a successful destructive action.
    $_SESSION['societycare_withdraw_csrf'] = bin2hex(random_bytes(32));
    sc_withdraw_redirect('success', 'Complaint withdrawn successfully.');
} catch (Throwable $e) {
    if ($transaction_started) {
        try {
            mysqli_rollback($conn);
        } catch (Throwable $rollback_error) {
            error_log('SocietyCare withdrawal rollback error: ' . $rollback_error->getMessage());
        }
    }
    error_log('SocietyCare withdrawal error: ' . $e->getMessage());
    sc_withdraw_redirect('danger', 'Unable to withdraw complaint right now. Please try again later.');
}
