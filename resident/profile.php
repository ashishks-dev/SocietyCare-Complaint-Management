<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// This route belongs to an authenticated resident, never an administrator.
if (!isset($_SESSION['resident_id']) || (int) $_SESSION['resident_id'] < 1) {
    header('Location: ../login.php');
    exit();
}

$resident_id = (int) $_SESSION['resident_id'];
$message = '';
$message_type = '';

// Display one-time messages only after the redirect's fresh GET request.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['societycare_profile_error_flash'])) {
        $message = (string) $_SESSION['societycare_profile_error_flash'];
        $message_type = 'danger';
        unset($_SESSION['societycare_profile_error_flash']);
    } elseif (isset($_SESSION['societycare_profile_flash'])) {
        $message = 'Profile updated successfully.';
        $message_type = 'success';
        unset($_SESSION['societycare_profile_flash']);
    }
}

// A per-session CSRF token protects profile changes initiated by the browser.
if (!isset($_SESSION['societycare_profile_csrf'])) {
    $_SESSION['societycare_profile_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $house_number = trim((string) ($_POST['house_number'] ?? ''));
    $block = trim((string) ($_POST['block'] ?? ''));
    $form_token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['societycare_profile_csrf'], $form_token)) {
        $message = 'Your form session expired. Please refresh the page and try again.';
        $message_type = 'danger';
    } elseif ($full_name === '' || $email === '' || $mobile === '' || $house_number === '' || $block === '') {
        $message = 'All fields are required.';
        $message_type = 'danger';
    } elseif (strlen($full_name) > 100 || strlen($email) > 100 || strlen($house_number) > 50 || strlen($block) > 50) {
        // Conservative byte-based caps avoid attempting to store overlong values.
        $message = 'One or more fields are too long.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $message = 'Mobile number must be exactly 10 digits.';
        $message_type = 'danger';
    } else {
        try {
            // Email and active-flat checks mirror the registration rules.
            $email_stmt = mysqli_prepare($conn, 'SELECT resident_id FROM residents WHERE email = ? AND resident_id <> ? LIMIT 1');
            mysqli_stmt_bind_param($email_stmt, 'si', $email, $resident_id);
            mysqli_stmt_execute($email_stmt);
            mysqli_stmt_store_result($email_stmt);
            $email_in_use = mysqli_stmt_num_rows($email_stmt) > 0;
            mysqli_stmt_close($email_stmt);

            $house_stmt = mysqli_prepare($conn, "SELECT resident_id FROM residents WHERE house_number = ? AND block = ? AND status = 'Active' AND resident_id <> ? LIMIT 1");
            mysqli_stmt_bind_param($house_stmt, 'ssi', $house_number, $block, $resident_id);
            mysqli_stmt_execute($house_stmt);
            mysqli_stmt_store_result($house_stmt);
            $house_in_use = mysqli_stmt_num_rows($house_stmt) > 0;
            mysqli_stmt_close($house_stmt);

            if ($email_in_use) {
                $message = 'This email is already registered.';
                $message_type = 'danger';
            } elseif ($house_in_use) {
                $message = 'An active resident is already registered for this house/flat.';
                $message_type = 'danger';
            } else {
                $update_stmt = mysqli_prepare(
                    $conn,
                    'UPDATE residents SET full_name = ?, email = ?, mobile = ?, house_number = ?, block = ? WHERE resident_id = ?'
                );
                mysqli_stmt_bind_param($update_stmt, 'sssssi', $full_name, $email, $mobile, $house_number, $block, $resident_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);

                $_SESSION['resident_name'] = $full_name;
                // Rotate token after a successful update to prevent replay.
                $_SESSION['societycare_profile_csrf'] = bin2hex(random_bytes(32));
                // Redirect after a successful POST, so refreshing cannot resubmit it.
                $_SESSION['societycare_profile_flash'] = true;
                header('Location: profile.php', true, 303);
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            error_log('SocietyCare profile update database error: ' . $e->getMessage());
            $message = 'Something went wrong. Please try again.';
            $message_type = 'danger';
        }
    }

    // Invalid submissions also use PRG: refreshing the error page is a GET,
    // not a repeat of the rejected POST. Flash only server-generated messages.
    if ($message_type === 'danger') {
        $_SESSION['societycare_profile_error_flash'] = $message;
        header('Location: profile.php', true, 303);
        exit();
    }
}

try {
    $profile_stmt = mysqli_prepare($conn, 'SELECT full_name, email, mobile, house_number, block FROM residents WHERE resident_id = ? LIMIT 1');
    mysqli_stmt_bind_param($profile_stmt, 'i', $resident_id);
    mysqli_stmt_execute($profile_stmt);
    $profile_result = mysqli_stmt_get_result($profile_stmt);
    $resident = mysqli_fetch_assoc($profile_result);
    mysqli_stmt_close($profile_stmt);
} catch (mysqli_sql_exception $e) {
    error_log('SocietyCare profile read database error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load your profile. Please try again later.');
}

if (!$resident) {
    // A stale session must not attempt updates on a nonexistent account.
    unset($_SESSION['resident_id'], $_SESSION['resident_name']);
    header('Location: ../login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Profile</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary px-4">

<span class="navbar-brand fs-4">
🏠 SocietyCare
</span>

<div>

<span class="text-white me-3">
Welcome, <?php echo htmlspecialchars((string) ($_SESSION['resident_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
</span>

<a href="dashboard.php" class="btn btn-light btn-sm me-2">
Dashboard
</a>

<a href="../logout.php" class="btn btn-light btn-sm">
Logout
</a>

</div>

</nav>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-primary text-white">
<h3 class="mb-0">My Profile</h3>
</div>

<div class="card-body">

<?php if($message != ""){ ?>

<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>

<?php } ?>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['societycare_profile_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

<div class="row">

<div class="col-md-6 mb-3">
<label class="form-label">Full Name</label>
<input
type="text"
name="full_name"
class="form-control"
value="<?php echo htmlspecialchars((string) $resident['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Email Address</label>
<input
type="email"
name="email"
class="form-control"
value="<?php echo htmlspecialchars((string) $resident['email'], ENT_QUOTES, 'UTF-8'); ?>"
required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Mobile Number</label>
<input
type="text"
name="mobile"
class="form-control"
value="<?php echo htmlspecialchars((string) $resident['mobile'], ENT_QUOTES, 'UTF-8'); ?>"
required>
</div>

<div class="col-md-3 mb-3">
<label class="form-label">House/Flat Number</label>
<input
type="text"
name="house_number"
class="form-control"
value="<?php echo htmlspecialchars((string) $resident['house_number'], ENT_QUOTES, 'UTF-8'); ?>"
required>
</div>

<div class="col-md-3 mb-3">
<label class="form-label">Block/Wing</label>
<input
type="text"
name="block"
class="form-control"
value="<?php echo htmlspecialchars((string) $resident['block'], ENT_QUOTES, 'UTF-8'); ?>"
required>
</div>

</div>

<div class="d-flex gap-2">

<button
type="submit"
name="update_profile"
class="btn btn-primary">
Update Profile
</button>

<a href="change-password.php" class="btn btn-warning">
Change Password
</a>

<a href="dashboard.php" class="btn btn-secondary">
Back to Dashboard
</a>

</div>

</form>

</div>
</div>
</div>

</body>
</html>