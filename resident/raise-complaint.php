<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Only a signed-in resident can submit a complaint.
if (!isset($_SESSION['resident_id']) || (int) $_SESSION['resident_id'] < 1) {
    header('Location: ../login.php');
    exit();
}
$resident_id = (int) $_SESSION['resident_id'];

function sc_raise_escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sc_raise_redirect($type, $message) {
    $_SESSION['societycare_raise_flash'] = [
        'type' => $type === 'success' ? 'success' : 'danger',
        'text' => $message,
    ];
    header('Location: raise-complaint.php', true, 303);
    exit();
}

// Create a CSRF token for this resident's session.
if (!isset($_SESSION['societycare_raise_csrf']) || !is_string($_SESSION['societycare_raise_csrf'])) {
    $_SESSION['societycare_raise_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saved_token = $_SESSION['societycare_raise_csrf'];
    $posted_token = $_POST['csrf_token'] ?? null;
    if (!is_string($posted_token) || !hash_equals($saved_token, $posted_token)) {
        sc_raise_redirect('danger', 'Invalid or expired complaint form. Please open the form and try again.');
    }

    // Reject arrays and missing fields instead of passing them to string functions.
    $fields = ['category_id', 'title', 'description', 'location', 'issue_started', 'priority'];
    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
            sc_raise_redirect('danger', 'Please complete all required fields.');
        }
    }

    $category_input = trim($_POST['category_id']);
    $category_id = filter_var($category_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $issue_started = trim($_POST['issue_started']);
    $priority = trim($_POST['priority']);

    $allowed_issue_started = ['Today', 'Yesterday', '2-3 Days Ago', 'About a Week Ago', 'More Than a Week Ago'];
    $allowed_priority = ['Low', 'Medium', 'High'];

    if ($category_id === false || $title === '' || $description === '' || $location === '' ||
        !in_array($issue_started, $allowed_issue_started, true) || !in_array($priority, $allowed_priority, true)) {
        sc_raise_redirect('danger', 'Please provide valid values for all complaint fields.');
    }

    // Match the database column lengths and set a reasonable bound for free-form text.
    if (mb_strlen($title, 'UTF-8') > 150 || mb_strlen($location, 'UTF-8') > 100 ||
        mb_strlen($description, 'UTF-8') > 5000) {
        sc_raise_redirect('danger', 'One or more complaint fields are too long.');
    }

    $transaction_started = false;
    try {
        // Only a category that actually exists in this database is valid.
        $category_stmt = mysqli_prepare($conn, 'SELECT category_id FROM categories WHERE category_id = ? LIMIT 1');
        mysqli_stmt_bind_param($category_stmt, 'i', $category_id);
        mysqli_stmt_execute($category_stmt);
        mysqli_stmt_store_result($category_stmt);
        $category_exists = mysqli_stmt_num_rows($category_stmt) === 1;
        mysqli_stmt_close($category_stmt);
        if (!$category_exists) {
            sc_raise_redirect('danger', 'Please select a valid complaint category.');
        }

        // Both the complaint and its initial history must be created together.
        mysqli_begin_transaction($conn);
        $transaction_started = true;
        $stmt = mysqli_prepare($conn, 'INSERT INTO complaints
            (resident_id, category_id, title, description, location, issue_started, priority)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iisssss', $resident_id, $category_id, $title, $description, $location, $issue_started, $priority);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Complaint insert failed');
        }
        $complaint_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $history_stmt = mysqli_prepare($conn, "INSERT INTO complaint_history
            (complaint_id, status, remark, updated_by)
            VALUES (?, 'Pending', 'Complaint submitted.', 'Resident')");
        mysqli_stmt_bind_param($history_stmt, 'i', $complaint_id);
        if (!mysqli_stmt_execute($history_stmt)) {
            throw new RuntimeException('Complaint history insert failed');
        }
        mysqli_stmt_close($history_stmt);

        mysqli_commit($conn);
        $transaction_started = false;
        // Invalidate the successfully used token; PRG prevents refresh resubmissions.
        $_SESSION['societycare_raise_csrf'] = bin2hex(random_bytes(32));
        sc_raise_redirect('success', 'Complaint submitted successfully!');
    } catch (Throwable $e) {
        if ($transaction_started) {
            try {
                mysqli_rollback($conn);
            } catch (Throwable $rollback_error) {
                error_log('SocietyCare complaint submission rollback error: ' . $rollback_error->getMessage());
            }
        }
        error_log('SocietyCare complaint submission error: ' . $e->getMessage());
        sc_raise_redirect('danger', 'Unable to submit complaint right now. Please try again later.');
    }
}

// Show a redirect message once, without retaining POST as the current browser page.
$flash = $_SESSION['societycare_raise_flash'] ?? null;
unset($_SESSION['societycare_raise_flash']);

try {
    $category_result = mysqli_query($conn, 'SELECT category_id, category_name FROM categories ORDER BY category_id');
} catch (Throwable $e) {
    error_log('SocietyCare complaint category query error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load complaint categories. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Raise Complaint | SocietyCare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand">🏠 SocietyCare</span>
        <a href="dashboard.php" class="btn btn-light">Dashboard</a>
    </div>
</nav>
<div class="container mt-5">
    <div class="card shadow p-4">
        <h2 class="fw-bold mb-3">Raise a Complaint</h2>
        <p class="text-muted">Fill the details below to register your complaint.</p>
        <?php if (is_array($flash) && isset($flash['text'])) { ?>
            <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'danger'; ?>">
                <?php echo sc_raise_escape($flash['text']); ?>
            </div>
        <?php } ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo sc_raise_escape($_SESSION['societycare_raise_csrf']); ?>">
            <div class="mb-3">
                <label>Complaint Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php while ($category = mysqli_fetch_assoc($category_result)) { ?>
                        <option value="<?php echo (int) $category['category_id']; ?>">
                            <?php echo sc_raise_escape($category['category_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="mb-3">
                <label>Complaint Title</label>
                <input type="text" name="title" class="form-control" maxlength="150" required>
            </div>
            <div class="mb-3">
                <label>Complaint Description</label>
                <textarea name="description" class="form-control" rows="4" maxlength="5000" required></textarea>
            </div>
            <div class="mb-3">
                <label>Affected Location</label>
                <input type="text" name="location" class="form-control" maxlength="100" placeholder="Example: Kitchen, Bathroom, Parking Area" required>
            </div>
            <div class="mb-3">
                <label>Issue Started</label>
                <select name="issue_started" class="form-control" required>
                    <option value="">Select</option>
                    <option value="Today">Today</option>
                    <option value="Yesterday">Yesterday</option>
                    <option value="2-3 Days Ago">2-3 Days Ago</option>
                    <option value="About a Week Ago">About a Week Ago</option>
                    <option value="More Than a Week Ago">More Than a Week Ago</option>
                </select>
            </div>
            <div class="mb-4">
                <label>Priority</label>
                <select name="priority" class="form-control" required>
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                </select>
            </div>
            <button type="submit" name="submit_complaint" class="btn btn-primary w-100">Submit Complaint</button>
        </form>
    </div>
</div>
</body>
</html>
