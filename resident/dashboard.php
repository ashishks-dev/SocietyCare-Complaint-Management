<?php
session_start();

if (!isset($_SESSION['resident_id'])) {
    header("Location: ../login.php");
    exit();
}

$resident_id = filter_var($_SESSION['resident_id'], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if ($resident_id === false) {
    header("Location: ../login.php");
    exit();
}

$resident_name = (string) ($_SESSION['resident_name'] ?? 'Resident');
include("../config/database.php");

// Count only complaints belonging to the currently signed-in resident.
// The Pending card includes both Pending and In Progress complaints.
try {
    $query = "SELECT COUNT(*) AS total,
                     COALESCE(SUM(CASE WHEN status IN ('Pending', 'In Progress') THEN 1 ELSE 0 END), 0) AS pending,
                     COALESCE(SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END), 0) AS resolved
              FROM complaints WHERE resident_id = ?";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new RuntimeException('Could not prepare dashboard query.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $resident_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_complaints, $pending_complaints, $resolved_complaints);
    if (!mysqli_stmt_fetch($stmt)) {
        throw new RuntimeException('Could not fetch dashboard counts.');
    }
    mysqli_stmt_close($stmt);
} catch (Throwable $e) {
    error_log('SocietyCare resident dashboard: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load dashboard right now. Please try again later.');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resident Dashboard | SocietyCare</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<nav class="navbar navbar-dark bg-primary px-4">

    <span class="navbar-brand fs-4">
        🏠 SocietyCare
    </span>

    <div>
        <span class="text-white me-3">
            Welcome, <?php echo htmlspecialchars($resident_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </span>

        <a href="dashboard.php" class="btn btn-light btn-sm me-2">
            Dashboard
        </a>

        <a href="profile.php" class="btn btn-light btn-sm me-2">
            My Profile
        </a>

        <a href="../logout.php" class="btn btn-light btn-sm">
            Logout
        </a>
    </div>

</nav>

<div class="container mt-5">

    <div class="card shadow p-4">

        <h2>Welcome, <?php echo htmlspecialchars($resident_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> 👋</h2>

        <p class="text-muted">
            You have successfully logged into SocietyCare.
        </p>

        <hr>

        <div class="row text-center">

            <div class="col-md-4 mb-3">
                <div class="card p-3">
                    <h5>Total Complaints</h5>
                    <h2><?php echo $total_complaints; ?></h2>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card p-3">
                    <h5>Pending / In Progress</h5>
                    <h2><?php echo $pending_complaints; ?></h2>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card p-3">
                    <h5>Resolved</h5>
                    <h2><?php echo $resolved_complaints; ?></h2>
                </div>
            </div>

        </div>

        <div class="text-center mt-4">

            <a href="raise-complaint.php" class="btn btn-primary">
                Register Complaint
            </a>

            <a href="my-complaints.php" class="btn btn-success">
                View Complaints
            </a>

        </div>

    </div>

</div>

</body>
</html>