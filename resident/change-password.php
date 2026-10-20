<?php
session_start();

// Only authenticated residents may change their own password.
if (!isset($_SESSION['resident_id']) || (int) $_SESSION['resident_id'] < 1) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';
$resident_id = (int) $_SESSION['resident_id'];

function sc_password_escape($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function sc_password_redirect($type, $message) {
    $_SESSION['societycare_password_flash'] = [
        'type' => $type === 'success' ? 'success' : 'danger',
        'text' => $message,
    ];
    // Always return to GET after processing a POST, including rejected submissions.
    header('Location: change-password.php', true, 303);
    exit();
}

if (!isset($_SESSION['societycare_password_csrf']) ||
    !is_string($_SESSION['societycare_password_csrf'])) {
    $_SESSION['societycare_password_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) ||
        !hash_equals($_SESSION['societycare_password_csrf'], $token)) {
        sc_password_redirect('danger', 'Invalid or expired password form. Please open the form and try again.');
    }

    $current_password = $_POST['current_password'] ?? null;
    $new_password = $_POST['new_password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;

    // Do not allow arrays or missing values to reach password functions.
    if (!is_string($current_password) || !is_string($new_password) ||
        !is_string($confirm_password) || $current_password === '' ||
        $new_password === '' || $confirm_password === '') {
        sc_password_redirect('danger', 'Please complete all password fields.');
    }

    if ($new_password !== $confirm_password) {
        sc_password_redirect('danger', 'New password and confirm password do not match.');
    }

    if (strlen($new_password) < 8) {
        sc_password_redirect('danger', 'New password must be at least 8 characters long.');
    }

    // PASSWORD_DEFAULT uses bcrypt in this demo's PHP 8.2 environment.
    // Limit bcrypt input to avoid silently truncating a password after 72 bytes.
    if (strlen($new_password) > 72) {
        sc_password_redirect('danger', 'New password must not exceed 72 bytes.');
    }

    try {
        $stmt = mysqli_prepare($conn, 'SELECT password FROM residents WHERE resident_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $resident_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $resident = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$resident || !password_verify($current_password, $resident['password'])) {
            sc_password_redirect('danger', 'Current password is incorrect.');
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Also match the previously read hash: a concurrent password change
        // cannot silently be overwritten by an out-of-date request.
        $stmt = mysqli_prepare($conn,
            'UPDATE residents SET password = ? WHERE resident_id = ? AND password = ?');
        mysqli_stmt_bind_param($stmt, 'sis', $new_hash, $resident_id, $resident['password']);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($updated !== 1) {
            sc_password_redirect('danger', 'Password could not be changed. Please try again.');
        }

        // Prevent reusing the successful form token.
        $_SESSION['societycare_password_csrf'] = bin2hex(random_bytes(32));
        sc_password_redirect('success', 'Password changed successfully.');
    } catch (Throwable $e) {
        error_log('SocietyCare change password error: ' . $e->getMessage());
        sc_password_redirect('danger', 'Unable to change password right now. Please try again later.');
    }
}

$flash = $_SESSION['societycare_password_flash'] ?? null;
unset($_SESSION['societycare_password_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-primary px-4">
    <span class="navbar-brand fs-4">🏠 SocietyCare</span>
    <div>
        <span class="text-white me-3">
            Welcome, <?php echo sc_password_escape($_SESSION['resident_name'] ?? 'Resident'); ?>
        </span>
        <a href="dashboard.php" class="btn btn-light btn-sm me-2">Dashboard</a>
        <a href="profile.php" class="btn btn-light btn-sm me-2">My Profile</a>
        <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
    </div>
</nav>
<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white"><h3 class="mb-0">Change Password</h3></div>
        <div class="card-body">
            <?php if (is_array($flash) && isset($flash['text'])) { ?>
                <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'danger'; ?>" role="alert">
                    <?php echo sc_password_escape($flash['text']); ?>
                </div>
            <?php } ?>
            <form method="POST" action="change-password.php">
                <input type="hidden" name="csrf_token" value="<?php echo sc_password_escape($_SESSION['societycare_password_csrf']); ?>">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="8" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                <a href="profile.php" class="btn btn-secondary">Back to Profile</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
