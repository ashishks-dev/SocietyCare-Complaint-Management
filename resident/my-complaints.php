<?php
session_start();

// Only a signed-in resident can view their own complaints.
if (!isset($_SESSION['resident_id']) || (int) $_SESSION['resident_id'] < 1) {
    header('Location: ../login.php');
    exit();
}
require_once __DIR__ . '/../config/database.php';

$resident_id = (int) $_SESSION['resident_id'];

function sc_escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Generate a token once per session. Only POST requests will use this token.
if (!isset($_SESSION['societycare_withdraw_csrf']) || !is_string($_SESSION['societycare_withdraw_csrf'])) {
    $_SESSION['societycare_withdraw_csrf'] = bin2hex(random_bytes(32));
}

// Show withdrawal results only once, after a redirect from the POST endpoint.
$withdraw_flash = $_SESSION['societycare_withdraw_flash'] ?? null;
unset($_SESSION['societycare_withdraw_flash']);

// Accept ordinary text URL parameters only (not arrays).
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '';
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$sql = 'SELECT complaints.*, categories.category_name, feedback.feedback_id
        FROM complaints
        INNER JOIN categories ON complaints.category_id = categories.category_id
        LEFT JOIN feedback ON complaints.complaint_id = feedback.complaint_id
        WHERE complaints.resident_id = ?';
$types = 'i';
$params = [$resident_id];

if ($search !== '') {
    $sql .= ' AND (complaints.complaint_id LIKE ? OR complaints.title LIKE ?)';
    $search_pattern = '%' . $search . '%';
    $types .= 'ss';
    $params[] = $search_pattern;
    $params[] = $search_pattern;
}
if ($status_filter !== '') {
    $sql .= ' AND complaints.status = ?';
    $types .= 's';
    $params[] = $status_filter;
}
$sql .= ' ORDER BY complaints.created_at DESC, complaints.complaint_id DESC';

try {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $complaints = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $e) {
    error_log('SocietyCare resident complaint listing error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load complaints. Please try again later.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Complaints | SocietyCare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
</head>
<body>

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand">🏠 SocietyCare</span>
        <div>
            <a href="dashboard.php" class="btn btn-light btn-sm me-2">Dashboard</a>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <div class="card shadow p-4">
        <?php if (is_array($withdraw_flash) && isset($withdraw_flash['text'])) { ?>
            <div class="alert alert-<?php echo ($withdraw_flash['type'] ?? '') === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo sc_escape($withdraw_flash['text']); ?>
            </div>
        <?php } ?>
        <h2 class="fw-bold mb-3">My Complaints</h2>
        <p class="text-muted">View all complaints submitted by you.</p>

        <form method="GET" class="mb-4">
    <div class="row g-2">

        <div class="col-md-7">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by Complaint ID or Title"
                value="<?php echo sc_escape($search); ?>"
                maxlength="100">
        </div>

        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="Pending" <?php if($status_filter=="Pending") echo "selected"; ?>>Pending</option>
                <option value="In Progress" <?php if($status_filter=="In Progress") echo "selected"; ?>>In Progress</option>
                <option value="Resolved" <?php if($status_filter=="Resolved") echo "selected"; ?>>Resolved</option>
                <option value="Closed" <?php if($status_filter=="Closed") echo "selected"; ?>>Closed</option>
            </select>
        </div>

        <div class="col-md-1">
    <button type="submit" class="btn btn-primary w-100">
        Search
    </button>
       </div>

       <div class="col-md-1">
       <a href="my-complaints.php" class="btn btn-secondary w-100">
        Reset
        </a>
</div>

    </div>
</form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Title</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Admin Remark</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($complaints) > 0) { ?>
                        <?php foreach ($complaints as $row) { ?>
                            <tr>
                                <td>#<?php echo (int) $row['complaint_id']; ?></td>
                                <td><?php echo sc_escape($row['category_name']); ?></td>
                                <td><?php echo sc_escape($row['title']); ?></td>
                                <td><?php echo sc_escape($row['location']); ?></td>
                                <td>
                                <?php
                                if($row['priority']=="High"){
                                echo '<span class="priority-badge priority-high">High</span>';
                                }
                                elseif($row['priority']=="Medium"){
                                echo '<span class="priority-badge priority-medium">Medium</span>';
                                }
                                else{
                                echo '<span class="priority-badge priority-low">Low</span>';
                                }
                                ?>
                                </td>
                                <td>

                                <?php
                                if($row['status']=="Pending"){
                                echo '<span class="status-badge status-pending">Pending</span>';
                                }
                                elseif($row['status']=="In Progress"){
                                echo '<span class="status-badge status-progress">In Progress</span>';
                                }
                                elseif($row['status']=="Resolved"){
                                echo '<span class="status-badge status-resolved">Resolved</span>';
                                }
                                else{
                                echo '<span class="status-badge status-closed">Closed</span>';
                                }
                                ?>
                                </td>

                                <td>
				<?php
				$remark = trim((string)($row['admin_remark'] ?? ''));

				if ($remark !== '') {

    				$maxLength = 70;

    				if (mb_strlen($remark, 'UTF-8') > $maxLength) {
        			$shortRemark = mb_substr($remark, 0, $maxLength, 'UTF-8') . '...';
    				} else {
        			$shortRemark = $remark;
    				}

    				echo '<span title="' .
         			htmlspecialchars($remark, ENT_QUOTES, 'UTF-8') .
         			'">' .
         			htmlspecialchars($shortRemark, ENT_QUOTES, 'UTF-8') .
         			'</span>';

				} else {
    				echo "No remark yet";
				}
				?>
				</td>
                                
                                <td><?php echo sc_escape($row['created_at']); ?></td>
                                <td>

                                <?php

                                if($row['status']=="Pending"){
                                ?>

                                <form method="POST" action="delete-complaint.php" class="d-inline"
                                      onsubmit="return confirm('Are you sure you want to withdraw this complaint?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo sc_escape($_SESSION['societycare_withdraw_csrf']); ?>">
                                    <input type="hidden" name="complaint_id" value="<?php echo (int) $row['complaint_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Withdraw</button>
                                </form>

                                <?php
                                }
                                elseif($row['status']=="Resolved" || $row['status']=="Closed"){

                                if($row['feedback_id'] == NULL){
                                echo '<a href="feedback.php?id='.(int) $row['complaint_id'].'" class="btn btn-success btn-sm">Give Feedback</a>';
                                } else {
                                echo '<a href="view-complaint.php?id='.(int) $row['complaint_id'].'#resident-feedback" class="btn btn-info btn-sm">View Feedback</a>';
                                }

                                }
                                else{
                                echo '<a href="view-complaint.php?id='.(int) $row['complaint_id'].'" class="btn btn-info btn-sm">View Complaint</a>';
                                }
                                ?>

                                </td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                No complaints found.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <a href="raise-complaint.php" class="btn btn-primary mt-3">
            Raise New Complaint
        </a>
    </div>
</div>

</body>
</html>