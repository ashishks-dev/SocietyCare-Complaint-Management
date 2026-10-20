<?php
session_start();
include("../config/database.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Only accept a valid numeric complaint ID.
$complaint_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($complaint_id === false || $complaint_id === null) {
    header("Location: manage-complaints.php");
    exit();
}

// Prepared statements safely handle values containing apostrophes and quotes.
$query = "SELECT complaints.*, residents.full_name, residents.house_number, residents.block, categories.category_name
FROM complaints
JOIN residents ON complaints.resident_id = residents.resident_id
JOIN categories ON complaints.category_id = categories.category_id
WHERE complaints.complaint_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $complaint_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$complaint = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$complaint) {
    header("Location: manage-complaints.php");
    exit();
}

// Each administrator session gets a random token for this status-update form.
if (empty($_SESSION['admin_update_complaint_csrf'])) {
    $_SESSION['admin_update_complaint_csrf'] = bin2hex(random_bytes(32));
}

// Display the previous POST result once, after redirecting to this GET page.
$flash_key = 'admin_update_complaint_flash';
$message = '';
$message_type = 'success';
if (isset($_SESSION[$flash_key]) && is_array($_SESSION[$flash_key])) {
    $message = (string) ($_SESSION[$flash_key]['message'] ?? '');
    $message_type = ($_SESSION[$flash_key]['type'] ?? '') === 'danger' ? 'danger' : 'success';
    unset($_SESSION[$flash_key]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? null;
    $status = $_POST['status'] ?? null;
    $raw_remark = $_POST['admin_remark'] ?? null;
    $valid_statuses = ['Pending', 'In Progress', 'Resolved', 'Closed'];
    $redirect_url = 'update-complaint.php?id=' . $complaint_id;

    if (!is_string($posted_token) ||
        !hash_equals($_SESSION['admin_update_complaint_csrf'], $posted_token)) {
        $_SESSION[$flash_key] = [
            'message' => 'Invalid or expired form. Please try again.',
            'type' => 'danger'
        ];
    } elseif (!isset($_POST['update_status']) || !is_string($status) ||
              !in_array($status, $valid_statuses, true) || !is_string($raw_remark)) {
        $_SESSION[$flash_key] = [
            'message' => 'Invalid complaint status or remark.',
            'type' => 'danger'
        ];
    } else {
        $admin_remark = trim($raw_remark);
        $transaction_started = false;
        try {
            // The status change and its history entry must succeed together.
            if (!mysqli_begin_transaction($conn)) {
                throw new RuntimeException('Could not begin transaction.');
            }
            $transaction_started = true;

            $update_query = 'UPDATE complaints SET status = ?, admin_remark = ? WHERE complaint_id = ?';
            $update_stmt = mysqli_prepare($conn, $update_query);
            if (!$update_stmt) {
                throw new RuntimeException('Could not prepare complaint update.');
            }
            mysqli_stmt_bind_param($update_stmt, 'ssi', $status, $admin_remark, $complaint_id);
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new RuntimeException('Could not execute complaint update.');
            }
            mysqli_stmt_close($update_stmt);

            $history_query = "INSERT INTO complaint_history (complaint_id, status, remark, updated_by)
                              VALUES (?, ?, ?, 'Admin')";
            $history_stmt = mysqli_prepare($conn, $history_query);
            if (!$history_stmt) {
                throw new RuntimeException('Could not prepare history insert.');
            }
            mysqli_stmt_bind_param($history_stmt, 'iss', $complaint_id, $status, $admin_remark);
            if (!mysqli_stmt_execute($history_stmt)) {
                throw new RuntimeException('Could not execute history insert.');
            }
            mysqli_stmt_close($history_stmt);

            if (!mysqli_commit($conn)) {
                throw new RuntimeException('Could not commit complaint update.');
            }
            $transaction_started = false;
            $_SESSION[$flash_key] = [
                'message' => 'Complaint status and remark updated successfully!',
                'type' => 'success'
            ];
        } catch (Throwable $e) {
            if ($transaction_started) {
                try {
                    mysqli_rollback($conn);
                } catch (Throwable $rollback_error) {
                    error_log('SocietyCare complaint rollback failed: ' . $rollback_error->getMessage());
                }
            }
            error_log('SocietyCare complaint update failed: ' . $e->getMessage());
            $_SESSION[$flash_key] = [
                'message' => 'The complaint could not be updated. Please try again.',
                'type' => 'danger'
            ];
        }
    }

    // Redirect after EVERY POST, even validation errors, to avoid re-submission on refresh.
    header('Location: ' . $redirect_url, true, 303);
    exit();
}

// Encode database text before displaying it in HTML.
function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Update Complaint | SocietyCare</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<nav class="navbar navbar-dark bg-primary px-4">
    <span class="navbar-brand fs-3">🏠 SocietyCare Admin</span>
    <a href="manage-complaints.php" class="btn btn-light">Back</a>
</nav>

<div class="container mt-5">
    <div class="card shadow p-4">

        <h2 class="fw-bold mb-3">Update Complaint Status</h2>

        <?php if ($message != "") { ?>
            <div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div>
        <?php } ?>

        <p><strong>Resident:</strong> <?php echo h($complaint['full_name']); ?></p>
        <p><strong>House:</strong> <?php echo h($complaint['house_number']); ?>, Block <?php echo h($complaint['block']); ?></p>
        <p><strong>Category:</strong> <?php echo h($complaint['category_name']); ?></p>
        <p><strong>Title:</strong> <?php echo h($complaint['title']); ?></p>
        <p><strong>Description:</strong> <?php echo h($complaint['description']); ?></p>
        <p><strong>Location:</strong> <?php echo h($complaint['location']); ?></p>
        <p><strong>Priority:</strong> <?php echo h($complaint['priority']); ?></p>

        <form method="POST" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['admin_update_complaint_csrf']); ?>">
            <label class="mb-2">Complaint Status</label>
            <select name="status" class="form-control mb-3" required>
                <option value="Pending" <?php if($complaint['status']=="Pending") echo "selected"; ?>>Pending</option>
                <option value="In Progress" <?php if($complaint['status']=="In Progress") echo "selected"; ?>>In Progress</option>
                <option value="Resolved" <?php if($complaint['status']=="Resolved") echo "selected"; ?>>Resolved</option>
                <option value="Closed" <?php if($complaint['status']=="Closed") echo "selected"; ?>>Closed</option>
            </select>

            <label class="mb-2">Admin Remark</label>

            <textarea name="admin_remark"
                      class="form-control mb-3"
                      rows="4"
                      placeholder="Enter update for the resident..."><?php
            echo h($complaint['admin_remark']);
            ?></textarea>

            <button type="submit" name="update_status" class="btn btn-primary">
                Update Status
            </button>
        </form>

    </div>
</div>

</body>
</html>