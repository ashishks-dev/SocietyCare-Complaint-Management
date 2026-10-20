<?php
session_start();

// Only a signed-in resident can submit or view the feedback form.
if (!isset($_SESSION['resident_id']) || (int) $_SESSION['resident_id'] < 1) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

function sc_feedback_escape($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// All feedback messages use Post/Redirect/Get so a refresh won't repeat POST.
function sc_feedback_redirect($type, $message, $complaint_id) {
    $_SESSION['societycare_feedback_flash'] = [
        'type' => in_array($type, ['success', 'warning'], true) ? $type : 'danger',
        'text' => $message,
    ];
    header('Location: feedback.php?id=' . (int) $complaint_id, true, 303);
    exit();
}

// Strictly validate the complaint ID before querying the database.
$raw_id = $_GET['id'] ?? null;
$complaint_id = is_string($raw_id)
    ? filter_var($raw_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($complaint_id === false) {
    header('Location: my-complaints.php');
    exit();
}

$resident_id = (int) $_SESSION['resident_id'];

if (!isset($_SESSION['societycare_feedback_csrf']) || !is_string($_SESSION['societycare_feedback_csrf'])) {
    $_SESSION['societycare_feedback_csrf'] = bin2hex(random_bytes(32));
}

// A page reload should display a message only once.
$flash = $_SESSION['societycare_feedback_flash'] ?? null;
unset($_SESSION['societycare_feedback_flash']);

try {
    // Both ownership and feedback-eligible status are checked on the server.
    $stmt = mysqli_prepare($conn, 'SELECT complaints.complaint_id, complaints.title,
                                          complaints.status, categories.category_name
                                   FROM complaints
                                   JOIN categories ON complaints.category_id = categories.category_id
                                   WHERE complaints.complaint_id = ?
                                     AND complaints.resident_id = ?
                                     AND complaints.status IN (\'Closed\', \'Resolved\')');
    mysqli_stmt_bind_param($stmt, 'ii', $complaint_id, $resident_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $complaint = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$complaint) {
        header('Location: my-complaints.php');
        exit();
    }

    // An existing feedback record disables the submission form.
    $stmt = mysqli_prepare($conn, 'SELECT feedback_id FROM feedback WHERE complaint_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $complaint_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $feedback_exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
} catch (Throwable $e) {
    error_log('SocietyCare feedback page error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load the feedback form. Please try again later.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saved_token = $_SESSION['societycare_feedback_csrf'];
    $posted_token = $_POST['csrf_token'] ?? null;

    if (!is_string($posted_token) || !hash_equals($saved_token, $posted_token)) {
        sc_feedback_redirect('danger', 'Invalid or expired feedback form. Please open the form and try again.', $complaint_id);
    }

    // One complaint can receive at most one feedback submission.
    if ($feedback_exists) {
        sc_feedback_redirect('warning', 'Feedback already submitted for this complaint.', $complaint_id);
    }

    $rating_input = $_POST['rating'] ?? null;
    $comments_input = $_POST['comments'] ?? null;
    $rating = is_string($rating_input)
        ? filter_var($rating_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]])
        : false;

    if ($rating === false) {
        sc_feedback_redirect('danger', 'Please select a valid rating.', $complaint_id);
    }
    if (!is_string($comments_input) || trim($comments_input) === '') {
        sc_feedback_redirect('danger', 'Please enter your feedback comment.', $complaint_id);
    }
    $comments = trim($comments_input);
    if (mb_strlen($comments, 'UTF-8') > 5000) {
        sc_feedback_redirect('danger', 'Feedback comment is too long.', $complaint_id);
    }

    $transaction_started = false;
    try {
        mysqli_begin_transaction($conn);
        $transaction_started = true;

        // Recheck ownership and status while locking the complaint during insertion.
        $stmt = mysqli_prepare($conn, "SELECT complaint_id FROM complaints
                                      WHERE complaint_id = ? AND resident_id = ?
                                        AND status IN ('Closed', 'Resolved') FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'ii', $complaint_id, $resident_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $eligible = mysqli_stmt_num_rows($stmt) === 1;
        mysqli_stmt_close($stmt);

        if (!$eligible) {
            mysqli_rollback($conn);
            $transaction_started = false;
            header('Location: my-complaints.php', true, 303);
            exit();
        }

        // The UNIQUE index on feedback.complaint_id is the final duplicate safeguard.
        $stmt = mysqli_prepare($conn, 'INSERT INTO feedback (complaint_id, resident_id, rating, comments)
                                       VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iiis', $complaint_id, $resident_id, $rating, $comments);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        $transaction_started = false;
        $_SESSION['societycare_feedback_csrf'] = bin2hex(random_bytes(32));
        sc_feedback_redirect('success', 'Feedback submitted successfully.', $complaint_id);
    } catch (Throwable $e) {
        if ($transaction_started) {
            try {
                mysqli_rollback($conn);
            } catch (Throwable $rollback_error) {
                error_log('SocietyCare feedback rollback error: ' . $rollback_error->getMessage());
            }
        }
        if ($e instanceof mysqli_sql_exception && (int) $e->getCode() === 1062) {
            sc_feedback_redirect('warning', 'Feedback already submitted for this complaint.', $complaint_id);
        }
        error_log('SocietyCare feedback submission error: ' . $e->getMessage());
        sc_feedback_redirect('danger', 'Unable to submit feedback right now. Please try again later.', $complaint_id);
    }
}

// On an ordinary GET, retain the existing yellow warning for previously submitted feedback.
if ($feedback_exists && !is_array($flash)) {
    $flash = ['type' => 'warning', 'text' => 'Feedback already submitted for this complaint.'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Give Feedback</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-primary px-4">
    <span class="navbar-brand fs-4">🏠 SocietyCare</span>
    <div>
        <span class="text-white me-3">Welcome, <?php echo sc_feedback_escape($_SESSION['resident_name'] ?? 'Resident'); ?></span>
        <a href="dashboard.php" class="btn btn-light btn-sm me-2">Dashboard</a>
        <a href="profile.php" class="btn btn-light btn-sm me-2">My Profile</a>
        <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
    </div>
</nav>
<div class="container mt-5">
<div class="card shadow">
<div class="card-header bg-primary text-white"><h3 class="mb-0">Give Feedback</h3></div>
<div class="card-body">
    <?php if (is_array($flash) && isset($flash['text'])) { ?>
        <div class="alert alert-<?php echo in_array(($flash['type'] ?? ''), ['success', 'warning'], true) ? $flash['type'] : 'danger'; ?>" role="alert">
            <?php echo sc_feedback_escape($flash['text']); ?>
        </div>
    <?php } ?>

    <table class="table table-bordered mb-4">
        <tr><th>Complaint ID</th><td>#<?php echo (int) $complaint['complaint_id']; ?></td></tr>
        <tr><th>Category</th><td><?php echo sc_feedback_escape($complaint['category_name']); ?></td></tr>
        <tr><th>Title</th><td><?php echo sc_feedback_escape($complaint['title']); ?></td></tr>
        <tr><th>Status</th><td><?php echo sc_feedback_escape($complaint['status']); ?></td></tr>
    </table>

    <?php if (!$feedback_exists) { ?>
        <form method="POST" action="feedback.php?id=<?php echo (int) $complaint_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo sc_feedback_escape($_SESSION['societycare_feedback_csrf']); ?>">
            <div class="mb-3">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select" required>
                    <option value="">Select Rating</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Good</option>
                    <option value="3">3 - Average</option>
                    <option value="2">2 - Poor</option>
                    <option value="1">1 - Very Poor</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Comments</label>
                <textarea name="comments" class="form-control" rows="4" maxlength="5000" required></textarea>
            </div>
            <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
            <a href="my-complaints.php" class="btn btn-secondary">Back to My Complaints</a>
        </form>
    <?php } else { ?>
        <a href="my-complaints.php" class="btn btn-secondary">Back to My Complaints</a>
    <?php } ?>
</div>
</div>
</div>
</body>
</html>
