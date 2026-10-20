<?php
session_start();
include("../config/database.php");

$message = "";

if (isset($_POST['login'])) {
    $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    // Parameterized lookup keeps submitted email separate from SQL syntax.
    $stmt = mysqli_prepare(
        $conn,
        "SELECT admin_id, name, password FROM admins WHERE email = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $adminId, $adminName, $passwordHash);
    $accountFound = (mysqli_stmt_fetch($stmt) === true);
    mysqli_stmt_close($stmt);

    if ($accountFound && password_verify($password, $passwordHash)) {
        session_regenerate_id(true);
        unset($_SESSION['resident_id'], $_SESSION['resident_name']);
        $_SESSION['admin_id'] = $adminId;
        $_SESSION['admin_name'] = $adminName;

        header("Location: dashboard.php");
        exit();
    }

    $message = "Invalid email or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | SocietyCare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">

<div class="container">
    <div class="auth-card">
        <h2 class="text-center fw-bold mb-3">Admin Login</h2>
        <p class="text-center text-muted mb-4">Login to manage SocietyCare complaints</p>

        <?php if ($message != "") { ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php } ?>

        <form method="POST">
            <div class="mb-3">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-4">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        </form>

        <p class="text-center mt-3">
            <a href="../index.php">Back to Home</a>
        </p>
    </div>
</div>

</body>
</html>