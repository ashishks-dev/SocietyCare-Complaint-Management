<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Only a signed-in administrator may read complaint details.
if (!isset($_SESSION['admin_id']) || (int) $_SESSION['admin_id'] < 1) {
    header('Location: login.php');
    exit();
}

// Render database text safely in HTML text and quoted attribute contexts.
function sc_escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// A complaint ID must be a single, positive decimal integer, not "1abc" or an array.
// FILTER_VALIDATE_INT also rejects values outside the PHP integer range.
$id_input = $_GET['id'] ?? null;
$complaint_id = is_string($id_input)
    ? filter_var($id_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($complaint_id === false) {
    header('Location: manage-complaints.php');
    exit();
}

// The return destination is selected from two fixed local pages only.
$back_page = (isset($_GET['from']) && $_GET['from'] === 'feedback')
    ? 'manage-feedback.php'
    : 'manage-complaints.php';

try {
    $query = 'SELECT complaints.*, residents.full_name AS resident_name,
                     categories.category_name
              FROM complaints
              JOIN residents ON complaints.resident_id = residents.resident_id
              JOIN categories ON complaints.category_id = categories.category_id
              WHERE complaints.complaint_id = ?';
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $complaint_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: manage-complaints.php');
        exit();
    }

    $history_query = 'SELECT * FROM complaint_history
                      WHERE complaint_id = ?
                      ORDER BY updated_at ASC';
    $history_stmt = mysqli_prepare($conn, $history_query);
    mysqli_stmt_bind_param($history_stmt, 'i', $complaint_id);
    mysqli_stmt_execute($history_stmt);
    $history_result = mysqli_stmt_get_result($history_stmt);
    $history_rows = mysqli_fetch_all($history_result, MYSQLI_ASSOC);
    mysqli_stmt_close($history_stmt);
} catch (mysqli_sql_exception $e) {
    error_log('SocietyCare view complaint database error: ' . $e->getMessage());
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
🏠 SocietyCare Admin
</span>

<div>
<a href="<?php echo sc_escape($back_page); ?>" class="btn btn-light">
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
    <td><?php echo sc_escape($row['resident_name']); ?></td>
</tr>

<tr>
    <th>Category</th>
    <td><?php echo sc_escape($row['category_name']); ?></td>
</tr>

<tr>
    <th>Title</th>
    <td><?php echo sc_escape($row['title']); ?></td>
</tr>

<tr>
    <th>Location</th>
    <td><?php echo sc_escape($row['location']); ?></td>
</tr>

<tr>
    <th>Priority</th>
    <td><?php echo sc_escape($row['priority']); ?></td>
</tr>

<tr>
    <th>Status</th>
    <td><?php echo sc_escape($row['status']); ?></td>
</tr>

<tr>
    <th>Admin Remark</th>
    <td>
        <?php
        if ($row['admin_remark'] !== null && $row['admin_remark'] !== '') {
            echo sc_escape($row['admin_remark']);
        } else {
            echo 'No remark yet';
        }
        ?>
    </td>
</tr>

<tr>
    <th>Date</th>
    <td><?php echo sc_escape($row['created_at']); ?></td>
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
                    <th>Date &amp; Time</th>
                    <th>Status</th>
                    <th>Remark</th>
                    <th>Updated By</th>
                </tr>

            </thead>

            <tbody>

            <?php foreach ($history_rows as $history) { ?>

                <tr>

                    <td><?php echo sc_escape($history['updated_at']); ?></td>

                    <td>
                        <span class="badge bg-primary">
                            <?php echo sc_escape($history['status']); ?>
                        </span>
                    </td>

                    <td>
                        <?php echo sc_escape($history['remark']); ?>
                    </td>

                    <td>
                        <?php echo sc_escape($history['updated_by']); ?>
                    </td>

                </tr>

            <?php } ?>

            </tbody>

        </table>

       </div>

    </div>

</div>

<div class="mt-3">
    <a href="update-complaint.php?id=<?php echo (int) $row['complaint_id']; ?>" class="btn btn-primary">
        Update Complaint
    </a>

    <a href="<?php echo sc_escape($back_page); ?>" class="btn btn-secondary">
        <?php echo $back_page === 'manage-feedback.php' ? 'Back to Manage Feedback' : 'Back to Manage Complaints'; ?>
    </a>
</div>

</div>
</div>
</div>

</body>
</html>
