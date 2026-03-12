<?php
include '../includes/db.php';
include '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

if (!isset($_GET['job_id'])) {
    header("HTTP/1.1 400 Bad Request");
    exit();
}

$job_id = intval($_GET['job_id']);

// Get applicants for the job
$applicants_query = "
    SELECT 
        a.*, 
        s.first_name, 
        s.last_name, 
        s.email, 
        s.hall_ticket, 
        s.branch,
        s.year,
        j.title as job_title,
        c.name as company_name
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN jobs j ON a.job_id = j.id
    JOIN companies c ON j.company_id = c.id
    WHERE a.job_id = ?
    ORDER BY a.applied_at DESC
";

$stmt = $conn->prepare($applicants_query);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$applicants = $stmt->get_result();

if ($applicants->num_rows > 0) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-hover">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Name</th>';
    echo '<th>Hall Ticket</th>';
    echo '<th>Email</th>';
    echo '<th>Branch</th>';
    echo '<th>Year</th>';
    echo '<th>Applied Date</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    while ($applicant = $applicants->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $applicant['first_name'] . ' ' . $applicant['last_name'] . '</td>';
        echo '<td>' . $applicant['hall_ticket'] . '</td>';
        echo '<td>' . $applicant['email'] . '</td>';
        echo '<td>' . $applicant['branch'] . '</td>';
        echo '<td>' . $applicant['year'] . '</td>';
        echo '<td>' . date('M j, Y', strtotime($applicant['applied_at'])) . '</td>';
        echo '<td><span class="badge bg-' . 
            ($applicant['status'] == 'hired' ? 'success' : 
             ($applicant['status'] == 'rejected' ? 'danger' : 
             ($applicant['status'] == 'shortlisted' ? 'warning' : 
             ($applicant['status'] == 'viewed' ? 'info' : 'secondary')))) . 
            '">' . ucfirst($applicant['status']) . '</span></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo '<div class="alert alert-info">No applicants found for this job.</div>';
}
?>