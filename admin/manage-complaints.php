<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// This page is only for a signed-in administrator.
if (!isset($_SESSION['admin_id']) || (int) $_SESSION['admin_id'] < 1) {
    header('Location: login.php');
    exit();
}

// HTML encoding is for display. Prepared statements below are for SQL safety.
function sc_escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// The query parameter may be missing or even be an array: accept scalar strings only.
function sc_get_text_param($name) {
    if (!isset($_GET[$name]) || !is_string($_GET[$name])) {
        return '';
    }
    return trim($_GET[$name]);
}

// Reuse one parameter-binding routine for the count and data queries.
function sc_run_prepared_query($conn, $sql, $types, $params) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        throw new RuntimeException('Unable to prepare complaint query');
    }
    if ($types !== '') {
        if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('Unable to bind complaint query parameters');
        }
    }
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Unable to execute complaint query');
    }
    return $stmt;
}

$limit = 5; // Preserve the original five complaints per page.
$search = sc_get_text_param('search');
$status_filter = sc_get_text_param('status');
$priority_filter = sc_get_text_param('priority');

// Bound search size to avoid needless work on unusually large inputs.
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$page_input = $_GET['page'] ?? '1';
$page = 1;
if (is_string($page_input) && ctype_digit($page_input)) {
    // Avoid integer overflow from arbitrarily long URL parameters.
    $page = (int) min((float) $page_input, 1000000000);
    $page = max(1, $page);
} elseif (is_int($page_input) && $page_input > 0) {
    $page = min($page_input, 1000000000);
}

$from_clause = ' FROM complaints
JOIN residents ON complaints.resident_id = residents.resident_id
JOIN categories ON complaints.category_id = categories.category_id';
$where = [];
$types = '';
$params = [];

if ($search !== '') {
    // Search across complaint ID, resident name, or complaint title.
    // All three values are bound as data, never interpolated into SQL.
    $where[] = '(complaints.complaint_id LIKE ? OR residents.full_name LIKE ? OR complaints.title LIKE ?)';
    $search_pattern = '%' . $search . '%';
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $types .= 'sss';
}
if ($status_filter !== '') {
    $where[] = 'complaints.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}
if ($priority_filter !== '') {
    $where[] = 'complaints.priority = ?';
    $params[] = $priority_filter;
    $types .= 's';
}
$where_clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Execute the same safely bound filters for BOTH total count and page rows.
try {
    $count_sql = 'SELECT COUNT(*) AS total' . $from_clause . $where_clause;
    $count_stmt = sc_run_prepared_query($conn, $count_sql, $types, $params);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_records = (int) mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);

    $total_pages = (int) ceil($total_records / $limit);
    $page = min($page, max(1, $total_pages));
    $offset = ($page - 1) * $limit;
    $start_record = $total_records === 0 ? 0 : $offset + 1;
    $end_record = min($offset + $limit, $total_records);

    $query = 'SELECT complaints.*, residents.full_name AS resident_name, categories.category_name'
        . $from_clause . $where_clause . " ORDER BY
CASE
    WHEN complaints.status IN ('Pending', 'In Progress') THEN 1
    WHEN complaints.status IN ('Resolved', 'Closed') THEN 2
    ELSE 3
END,
CASE complaints.priority
    WHEN 'High' THEN 1
    WHEN 'Medium' THEN 2
    WHEN 'Low' THEN 3
    ELSE 4
END,
complaints.created_at DESC,
complaints.complaint_id DESC
LIMIT ? OFFSET ?";
    $data_params = $params;
    $data_params[] = $limit;
    $data_params[] = $offset;
    $data_stmt = sc_run_prepared_query($conn, $query, $types . 'ii', $data_params);
    $result = mysqli_stmt_get_result($data_stmt);
    mysqli_stmt_close($data_stmt);
} catch (Throwable $e) {
    error_log('SocietyCare manage complaints query error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load complaints. Please try again later.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Complaints</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
table{
width:100%;
border-collapse:collapse;
margin-top:20px;
}

th,td{
padding:12px;
border:1px solid #ddd;
text-align:left;
}

th{
background:#2563eb;
color:white;
}

.container{
width:90%;
max-width:1200px;
margin:40px auto;
background:white;
padding:30px;
border-radius:10px;
box-shadow:0 0 15px rgba(0,0,0,.08);
}

.status{
padding:5px 12px;
border-radius:20px;
font-weight:bold;
white-space: nowrap;
}

.pending{
background:#facc15;
color:black;
}

.in-progress{
background:#60a5fa;
color:white;
}

.resolved{
background:#22c55e;
color:white;
}

.priority-badge{
    padding:5px 12px;
    border-radius:20px;
    font-weight:bold;
    display:inline-block;
}

.priority-high{
    background:#991b1b;
    color:white;
}

.priority-medium{
    background:#f97316;
    color:white;
}

.priority-low{
    background:#facc15;
    color:black;
}

.closed{
    background:#374151;
    color:white;
}
</style>

</head>

<body>

<div class="container">

<h2>Manage Complaints</h2>

<form method="GET" class="mb-4">
    <div class="row g-2 align-items-center">

        <div class="col-md-4">
            <input
                type="text"
                name="search"
                maxlength="100"
                placeholder="Search by ID, resident name, or title"
                value="<?php echo sc_escape($search); ?>"
                class="form-control"
            >
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="Pending" <?php if($status_filter=="Pending") echo "selected"; ?>>Pending</option>
                <option value="In Progress" <?php if($status_filter=="In Progress") echo "selected"; ?>>In Progress</option>
                <option value="Resolved" <?php if($status_filter=="Resolved") echo "selected"; ?>>Resolved</option>
                <option value="Closed" <?php if($status_filter=="Closed") echo "selected"; ?>>Closed</option>
            </select>
        </div>

        <div class="col-md-3">
            <select name="priority" class="form-select">
                <option value="">All Priority</option>
                <option value="High" <?php if($priority_filter=="High") echo "selected"; ?>>High</option>
                <option value="Medium" <?php if($priority_filter=="Medium") echo "selected"; ?>>Medium</option>
                <option value="Low" <?php if($priority_filter=="Low") echo "selected"; ?>>Low</option>
            </select>
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>

    </div>

    <a href="manage-complaints.php" class="btn btn-secondary mt-2">Reset</a>
</form>


<div class="mb-3">
    <a href="dashboard.php" class="btn btn-secondary">
        ← Back to Dashboard
    </a>
</div>

<p class="text-muted mb-3">
    Showing <strong><?php echo (int) $start_record; ?></strong>
    to
    <strong><?php echo (int) $end_record; ?></strong>
    of
    <strong><?php echo (int) $total_records; ?></strong>
    complaints
</p>

<table>

<tr>
<th>ID</th>
<th>Resident</th>
<th>Category</th>
<th>Title</th>
<th>Priority</th>
<th>Status</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo (int) $row['complaint_id']; ?></td>

<td><?php echo sc_escape($row['resident_name']); ?></td>

<td><?php echo sc_escape($row['category_name']); ?></td>

<td class="text-break" style="max-width:250px;">
    <?php echo sc_escape($row['title']); ?>
</td>

<td>

<?php
if($row['priority']=="High"){
    echo '<span class="priority-badge priority-high">High</span>';
}
elseif($row['priority']=="Medium"){
    echo '<span class="priority-badge priority-medium">Medium</span>';
}
elseif($row['priority']=="Low"){
    echo '<span class="priority-badge priority-low">Low</span>';
}
else{
    echo '<span class="priority-badge">' . sc_escape($row['priority']) . '</span>';
}
?>

</td>

<td>

<span class="status
<?php
if($row['status']=="Pending") echo "pending";
elseif($row['status']=="In Progress") echo "in-progress";
elseif($row['status']=="Resolved") echo "resolved";
elseif($row['status']=="Closed") echo "closed";
?>
">

<?php echo sc_escape($row['status']); ?>

</span>

</td>

<td><?php echo sc_escape($row['created_at']); ?></td>
<td>
    <a href="view-complaint.php?id=<?php echo (int) $row['complaint_id']; ?>" class="btn btn-info btn-sm">
    View
</a>

<a href="update-complaint.php?id=<?php echo (int) $row['complaint_id']; ?>" class="btn btn-primary btn-sm">
    Update
</a>
</td>

</tr>

<?php } ?>

</table>

<?php
$query_params = [
    'search' => $search,
    'status' => $status_filter,
    'priority' => $priority_filter,
];
?>

<nav class="mt-4">
    <ul class="pagination justify-content-center">

        <?php if($page > 1){ 
            $query_params['page'] = $page - 1;
        ?>
            <li class="page-item">
                <a class="page-link" href="?<?php echo sc_escape(http_build_query($query_params)); ?>">Previous</a>
            </li>
        <?php } ?>

        <?php for($i = 1; $i <= $total_pages; $i++){ 
            $query_params['page'] = $i;
        ?>
            <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                <a class="page-link" href="?<?php echo sc_escape(http_build_query($query_params)); ?>">
                    <?php echo (int) $i; ?>
                </a>
            </li>
        <?php } ?>

        <?php if($page < $total_pages){ 
            $query_params['page'] = $page + 1;
        ?>
            <li class="page-item">
                <a class="page-link" href="?<?php echo sc_escape(http_build_query($query_params)); ?>">Next</a>
            </li>
        <?php } ?>

    </ul>
</nav>

</div>

</body>
</html>