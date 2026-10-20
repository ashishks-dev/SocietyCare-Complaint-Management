<?php
session_start();
include("../config/database.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
$total_feedback_query = "SELECT COUNT(*) AS total FROM feedback";
$total_feedback_result = mysqli_query($conn, $total_feedback_query);
$total_feedback = mysqli_fetch_assoc($total_feedback_result)['total'];

$avg_query = "SELECT AVG(rating) AS average_rating FROM feedback";
$avg_result = mysqli_query($conn, $avg_query);
$average_rating = mysqli_fetch_assoc($avg_result)['average_rating'];

$query = "SELECT feedback.*,
          complaints.title,
          complaints.status,
          residents.full_name AS resident_name
          FROM feedback
          JOIN complaints ON feedback.complaint_id = complaints.complaint_id
          JOIN residents ON feedback.resident_id = residents.resident_id
          ORDER BY feedback.submitted_at DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Feedback</title>

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

<h2>Manage Feedback</h2>

<div class="row mb-4">

    <div class="col-md-6">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h5>Total Feedback</h5>
                <h2><?php echo $total_feedback; ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h5>Average Rating</h5>
                <h2>
                    <?php 
                    if($average_rating){
                        echo number_format($average_rating, 1) . " / 5";
                    } else {
                        echo "0 / 5";
                    }
                    ?>
                </h2>
            </div>
        </div>
    </div>

</div>

<table>
    <tr>
        <th>Feedback ID</th>
        <th>Complaint ID</th>
        <th>Resident</th>
        <th>Complaint Title</th>
        <th>Status</th>
        <th>Rating</th>
        <th>Comments</th>
        <th>Date</th>
        <th>Action</th>
    </tr>

    <?php if(mysqli_num_rows($result) > 0){ ?>

        <?php while($row = mysqli_fetch_assoc($result)){ ?>

        <tr>
            <td><?php echo $row['feedback_id']; ?></td>

            <td>#<?php echo $row['complaint_id']; ?></td>

            <td><?php echo htmlspecialchars($row['resident_name']); ?></td>

            <td><?php echo htmlspecialchars($row['title']); ?></td>

            <td>
                <span class="status
                <?php
                if($row['status']=="Pending") echo "pending";
                elseif($row['status']=="In Progress") echo "in-progress";
                elseif($row['status']=="Resolved") echo "resolved";
                else echo "closed";
                ?>
                ">
                <?php echo $row['status']; ?>
                </span>
            </td>

            <td>
                <?php echo $row['rating']; ?> / 5
            </td>

            <td>
	    <?php
	    $comment = trim((string)($row['comments'] ?? ''));

	    if ($comment !== '') {

    		$maxLength = 70;

    		if (mb_strlen($comment, 'UTF-8') > $maxLength) {
        	    $shortComment = mb_substr($comment, 0, $maxLength, 'UTF-8') . '...';
    		} else {
        	    $shortComment = $comment;
    		}

    		echo '<span title="' .
         	     htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') .
         	     '">' .
         	     htmlspecialchars($shortComment, ENT_QUOTES, 'UTF-8') .
         	     '</span>';

	  } else {
    	      echo "No comment provided";
	  }
	  ?>
	  </td>

            <td><?php echo $row['submitted_at']; ?></td>

            <td>
                <a href="view-complaint.php?id=<?php echo $row['complaint_id']; ?>&from=feedback" class="btn btn-info btn-sm">
                    View Complaint
                </a>
            </td>
        </tr>

        <?php } ?>

    <?php } else { ?>

        <tr>
            <td colspan="9" class="text-center text-muted">
                No feedback found.
            </td>
        </tr>

    <?php } ?>

</table>

<a href="dashboard.php" class="btn btn-secondary mt-3">
    Back to Dashboard
</a>

</div>

</body>
</html>