<?php
session_start();
include("config/database.php");

// Register form CSRF token; generated once per session and kept across failed attempts.
if (empty($_SESSION['registration_csrf_token'])) {
    $_SESSION['registration_csrf_token'] = bin2hex(random_bytes(32));
}

// Flash messages are shown once, after a POST/Redirect/GET cycle.
$message = $_SESSION['registration_flash_message'] ?? '';
$message_type = $_SESSION['registration_flash_type'] ?? '';
unset($_SESSION['registration_flash_message'], $_SESSION['registration_flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    $message = '';
    $message_type = 'danger';

    if (!is_string($posted_token) || !hash_equals($_SESSION['registration_csrf_token'], $posted_token)) {
        $message = 'Invalid or expired registration form. Please try again.';
    } else {
        // Reject malformed array parameters instead of passing them to trim() or other functions.
        $names = ['full_name', 'email', 'mobile', 'house_number', 'block', 'password', 'confirm_password'];
        $fields_valid = true;
        foreach ($names as $name) {
            if (!isset($_POST[$name]) || !is_string($_POST[$name])) {
                $fields_valid = false;
                break;
            }
        }

        if (!$fields_valid) {
            $message = 'Please complete the registration form.';
        } else {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $mobile = trim($_POST['mobile']);
            $house_number = trim($_POST['house_number']);
            $block = trim($_POST['block']); // Optional, as in the original form.
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            if ($full_name === '' || $house_number === '' || $email === '') {
                $message = 'Please fill in your name, email, and house/flat number.';
            } elseif ($password !== $confirm_password) {
                $message = 'Passwords do not match!';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
            } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
                $message = 'Mobile number must be exactly 10 digits.';
            } elseif (strlen($password) < 8) {
                $message = 'Password must be at least 8 characters long.';
            } else {
                try {
                    $email_stmt = mysqli_prepare($conn, "SELECT resident_id FROM residents WHERE email = ? LIMIT 1");
                    mysqli_stmt_bind_param($email_stmt, 's', $email);
                    mysqli_stmt_execute($email_stmt);
                    mysqli_stmt_store_result($email_stmt);
                    $email_exists = mysqli_stmt_num_rows($email_stmt) > 0;
                    mysqli_stmt_close($email_stmt);

                    $house_stmt = mysqli_prepare($conn,
                        "SELECT resident_id FROM residents WHERE house_number = ? AND block = ? AND status = 'Active' LIMIT 1"
                    );
                    mysqli_stmt_bind_param($house_stmt, 'ss', $house_number, $block);
                    mysqli_stmt_execute($house_stmt);
                    mysqli_stmt_store_result($house_stmt);
                    $house_exists = mysqli_stmt_num_rows($house_stmt) > 0;
                    mysqli_stmt_close($house_stmt);

                    if ($email_exists) {
                        $message = 'This email is already registered.';
                    } elseif ($house_exists) {
                        $message = 'An active resident is already registered for this house/flat.';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $insert_stmt = mysqli_prepare($conn,
                            'INSERT INTO residents (full_name, email, mobile, password, house_number, block) VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        mysqli_stmt_bind_param($insert_stmt, 'ssssss', $full_name, $email, $mobile,
                            $hashed_password, $house_number, $block);
                        mysqli_stmt_execute($insert_stmt);
                        mysqli_stmt_close($insert_stmt);
                        $message = 'Registration successful! You can now login.';
                        $message_type = 'success';
                    }
                } catch (mysqli_sql_exception $e) {
                    error_log('SocietyCare registration database error: ' . $e->getMessage());
                    $message = 'Something went wrong. Please try again.';
                }
            }
        }
    }

    $_SESSION['registration_flash_message'] = $message;
    $_SESSION['registration_flash_type'] = $message_type;
    header('Location: register.php', true, 303);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resident Registration | SocietyCare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="container">
    <div class="auth-card">
        <h2 class="text-center fw-bold mb-3">Resident Registration</h2>
        <p class="text-center text-muted mb-4">Create your SocietyCare account</p>

        <?php if ($message != "") { ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php } ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['registration_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Mobile Number</label>
                <input type="text" name="mobile" class="form-control" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>House/Flat Number</label>
                    <input type="text" name="house_number" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label>Block/Wing</label>
                    <input type="text" name="block" class="form-control">
                </div>
            </div>

            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-4">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" name="register" class="btn btn-primary w-100">Register</button>
        </form>

        <p class="text-center mt-3">
            Already registered? <a href="login.php">Login here</a>
        </p>
    </div>
</div>

</body>
</html>