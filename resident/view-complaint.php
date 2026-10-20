<?php
session_start();

// Residents must be authenticated before complaint data is queried.
if (!isset($_SESSION['resident_id']) || (int) $_SESSION['resident_id'] < 1) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

function sc_view_escape($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Reject missing, malformed, negative, and non-integer complaint IDs.
$raw_id = $_GET['id'] ?? null;
$complaint_id = is_string($raw_id)
    ? filter_var($raw_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($complaint_id === false) {
    header('Location: my-complaints.php');
    exit();
}

$resident_id = (int) $_SESSION['resident_id'];

try {
    // Ownership check is part of the database query, not just the UI.
    $stmt = mysqli_prepare($conn, 'SELECT complaints.*,
                                        residents.full_name AS resident_name,
                                        categories.category_name
                                 FROM complaints
                                 JOIN residents ON complaints.resident_id = residents.resident_id
                                 JOIN categories ON complaints.category_id = categories.category_id
                                 WHERE complaints.complaint_id = ? AND complaints.resident_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $complaint_id, $resident_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: my-complaints.php');
        exit();
    }

    // Load history only after confirming that the resident owns this complaint.
    $history_stmt = mysqli_prepare($conn, 'SELECT updated_at, status, remark, updated_by
                                          FROM complaint_history
                                          WHERE complaint_id = ?
                                          ORDER BY updated_at ASC');
    mysqli_stmt_bind_param($history_stmt, 'i', $complaint_id);
    mysqli_stmt_execute($history_stmt);
    $history_result = mysqli_stmt_get_result($history_stmt);
    $histories = mysqli_fetch_all($history_result, MYSQLI_ASSOC);
    mysqli_stmt_close($history_stmt);

    // Feedback is private to the logged-in resident and the verified complaint.
    // Only load it after the complaint ownership check has succeeded.
    $feedback_stmt = mysqli_prepare($conn, 'SELECT rating, comments, submitted_at
                                           FROM feedback
                                           WHERE complaint_id = ? AND resident_id = ?
                                           ORDER BY feedback_id DESC LIMIT 1');
    mysqli_stmt_bind_param($feedback_stmt, 'ii', $complaint_id, $resident_id);
    mysqli_stmt_execute($feedback_stmt);
    $feedback_result = mysqli_stmt_get_result($feedback_stmt);
    $feedback = mysqli_fetch_assoc($feedback_result) ?: null;
    mysqli_stmt_close($feedback_stmt);
} catch (Throwable $e) {
    error_log('SocietyCare resident view complaint error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load complaint details. Please try again later.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>View Complaint</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary px-4">

<span class="navbar-brand fs-4">
🏠 SocietyCare
</span>

<div>
<a href="my-complaints.php" class="btn btn-light">
    ← Back
</a>
</div>

</nav>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-primary text-white">
<h3 class="mb-0">Complaint Details</h3>
</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
    <th width="25%">Complaint ID</th>
    <td>#<?php echo (int) $row['complaint_id']; ?></td>
</tr>

<tr>
    <th>Resident Name</th>
    <td><?php echo sc_view_escape($row['resident_name']); ?></td>
</tr>

<tr>
    <th>Category</th>
    <td><?php echo sc_view_escape($row['category_name']); ?></td>
</tr>

<tr>
    <th>Title</th>
    <td><?php echo sc_view_escape($row['title']); ?></td>
</tr>

<tr>
    <th>Location</th>
    <td><?php echo sc_view_escape($row['location']); ?></td>
</tr>

<tr>
    <th>Priority</th>
    <td><?php echo sc_view_escape($row['priority']); ?></td>
</tr>

<tr>
    <th>Status</th>
    <td><?php echo sc_view_escape($row['status']); ?></td>
</tr>

<tr>
    <th>Admin Remark</th>
    <td>
        <?php
        if (trim((string) ($row['admin_remark'] ?? '')) !== '') {
            echo sc_view_escape($row['admin_remark']);
        } else {
            echo "No remark yet";
        }
        ?>
    </td>
</tr>

<tr>
    <th>Date</th>
    <td><?php echo sc_view_escape($row['created_at']); ?></td>
</tr>

</table>

<div class="card mt-4">

    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Complaint History</h5>
    </div>

    <div class="card-body">

      <div class="table-responsive">

        <table class="table table-bordered table-striped">

            <thead>

                <tr>
                    <th>Date & Time</th>
                    <th>Status</th>
                    <th>Remark</th>
                    <th>Updated By</th>
                </tr>

            </thead>

            <tbody>

            <?php foreach ($histories as $history){ ?>

                <tr>

                    <td><?php echo sc_view_escape($history['updated_at']); ?></td>

                    <td>
                        <span class="badge bg-primary">
                            <?php echo sc_view_escape($history['status']); ?>
                        </span>
                    </td>

                    <td>
                        <?php echo sc_view_escape($history['remark']); ?>
                    </td>

                    <td>
                        <?php echo sc_view_escape($history['updated_by']); ?>
                    </td>

                </tr>

            <?php } ?>

            </tbody>

        </table>

       </div> 

    </div>

</div>

<?php if ($feedback !== null) { ?>
<div class="card mt-4" id="resident-feedback">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Resident Feedback</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered mb-0">
            <tr>
                <th style="width: 25%;">Rating</th>
                <td><?php echo (int) $feedback['rating']; ?> / 5</td>
            </tr>
            <tr>
                <th>Comments</th>
                <td><?php echo nl2br(sc_view_escape($feedback['comments'])); ?></td>
            </tr>
            <tr>
                <th>Submitted At</th>
                <td><?php echo sc_view_escape($feedback['submitted_at']); ?></td>
            </tr>
        </table>
    </div>
</div>
<?php } ?>

<div class="mt-3">
    <a href="my-complaints.php" class="btn btn-secondary">
        Back to My Complaints
    </a>
</div>

</div>
</div>
</div>

</body>
</html>