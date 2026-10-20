<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include("../config/database.php");

try {
// Complaint statistics
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM complaints"))['total'];

$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM complaints WHERE status='Pending'"))['total'];

$resolved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM complaints WHERE status='Resolved'"))['total'];

$inprogress = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM complaints WHERE status='In Progress'"))['total'];

$closed = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS total FROM complaints WHERE status='Closed'"))['total'];

$feedback_count = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS total FROM feedback")
)['total'];

$average_rating = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT AVG(rating) AS avg_rating FROM feedback")
)['avg_rating'];

if ($average_rating == NULL) {
    $average_rating = 0;
}
} catch (Throwable $error) {
    error_log('SocietyCare admin dashboard statistics error: ' . $error->getMessage());
    http_response_code(500);
    exit('Unable to load dashboard statistics right now. Please try again later.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | SocietyCare</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<nav class="navbar navbar-dark bg-primary px-4">
    <span class="navbar-brand fs-3">🏠 SocietyCare Admin</span>

    <div>
        <span class="text-white me-3">
            Welcome, <?php echo htmlspecialchars((string) ($_SESSION['admin_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </span>

        <a href="../logout.php" class="btn btn-light">
            Logout
        </a>
    </div>
</nav>

<div class="container mt-5">

<div class="card shadow p-4">

<h2 class="mb-3">Admin Dashboard</h2>

<p class="text-muted">
Manage all complaints submitted by residents.
</p>

<hr>

<div class="row text-center">

<div class="col">
    <a href="manage-complaints.php" class="text-decoration-none text-dark">
        <div class="card p-3 h-100 shadow-sm">
            <h4>Total</h4>
            <h1><?php echo $total; ?></h1>
        </div>
    </a>
</div>

<div class="col">
    <a href="manage-complaints.php?status=Pending" class="text-decoration-none text-dark">
        <div class="card p-3 h-100 shadow-sm">
            <h4>Pending</h4>
            <h1><?php echo $pending; ?></h1>
        </div>
    </a>
</div>

<div class="col">
    <a href="manage-complaints.php?status=In%20Progress" class="text-decoration-none text-dark">
        <div class="card p-3 h-100 shadow-sm">
            <h4>In Progress</h4>
            <h1><?php echo $inprogress; ?></h1>
        </div>
    </a>
</div>

<div class="col">
    <a href="manage-complaints.php?status=Resolved" class="text-decoration-none text-dark">
        <div class="card p-3 h-100 shadow-sm">
            <h4>Resolved</h4>
            <h1><?php echo $resolved; ?></h1>
        </div>
    </a>
</div>

<div class="col">
    <a href="manage-complaints.php?status=Closed" class="text-decoration-none text-dark">
        <div class="card p-3 h-100 shadow-sm">
            <h4>Closed</h4>
            <h1><?php echo $closed; ?></h1>
        </div>
    </a>
</div>

<div class="col">
    <a href="manage-feedback.php" class="text-decoration-none text-dark">
        <div class="card p-3 h-100 shadow-sm">
            <h4>Feedback</h4>
            <h1><?php echo $feedback_count; ?></h1>
        </div>
    </a>
</div>

<div class="col">
    <a href="manage-feedback.php" class="text-decoration-none text-dark">
        <div class="card p-3 h-100 shadow-sm">
            <h4>Avg Rating</h4>
            <h1><?php echo number_format($average_rating, 1); ?>/5</h1>
        </div>
    </a>
</div>

<div class="text-center mt-4">

<a href="manage-complaints.php" class="btn btn-primary btn-lg me-2">
    Manage Complaints
</a>

<a href="manage-feedback.php" class="btn btn-success btn-lg">
    Manage Feedback
</a>

</div>

</div>

</div>

</body>
</html>