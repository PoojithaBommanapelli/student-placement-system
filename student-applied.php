<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not a student
if (!isLoggedIn() || !isStudent()) {
    header("Location: ../index.html");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get applications
$query = "SELECT a.*, j.title, j.department, j.employment_type, j.work_mode, j.location,
                 c.name as company_name, c.logo as company_logo 
          FROM applications a 
          JOIN jobs j ON a.job_id = j.id 
          JOIN companies c ON j.company_id = c.id 
          WHERE a.student_id = ? 
          ORDER BY a.applied_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applied Jobs - Placement Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
        <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --light-bg: #f8f9fa;
            --card-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            box-shadow: var(--card-shadow);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            border-radius: 0.5rem;
            margin: 0.25rem 0.5rem;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white !important;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 24px;
            text-align: center;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .table-responsive {
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem 0.75rem;
        }
        
        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-color: #e9ecef;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }
        
        .student-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .student-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        
        .btn-group .btn {
            border-radius: 0.5rem;
            margin: 0 2px;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
            border-radius: 0.5rem;
        }
        
        .filter-card {
            background: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);
        }
        
        .stats-card {
            background: linear-gradient(120deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .pagination .page-link {
            border-radius: 0.5rem;
            margin: 0 0.25rem;
            border: none;
            color: var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .main-header {
            background: linear-gradient(90deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
        }
        
        /* Modern checkbox styling */
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.15em;
            border-radius: 0.35em;
            border: 2px solid #dee2e6;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* Animation for alerts */
        .alert {
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Hover effects for buttons */
        .btn {
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(90deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body data-user-role="<?php echo $_SESSION['role'] ?? ''; ?>">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="student-dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student-profile.php">
                                <i class="bi bi-person me-2"></i>
                                Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student-resume.php">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                Resume Builder
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student-skills.php">
                                <i class="bi bi-tools me-2"></i>
                                Skills Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="student-applied.php">
                                <i class="bi bi-list-check me-2"></i>
                                Applied Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student-jobs.php">
                                <i class="bi bi-briefcase me-2"></i>
                                Job Listings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student-logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Applied Jobs</h1>
                    <span class="badge bg-primary"><?php echo $applications->num_rows; ?> Applications</span>
                </div>
                
                <?php if ($applications->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($application = $applications->fetch_assoc()): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title"><?php echo $application['title']; ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted"><?php echo $application['company_name']; ?></h6>
                                            </div>
                                            <img src="../uploads/companies/logos/<?php echo $application['company_logo'] ?? 'default.png'; ?>" 
                                                 alt="Company Logo" class="company-logo" style="width: 50px; height: 50px;">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="badge bg-<?php 
                                                echo $application['status'] == 'applied' ? 'info' : 
                                                ($application['status'] == 'viewed' ? 'primary' : 
                                                ($application['status'] == 'shortlisted' ? 'success' : 
                                                ($application['status'] == 'rejected' ? 'danger' : 'warning'))); 
                                            ?>">
                                                <?php echo ucfirst($application['status']); ?>
                                            </span>
                                            <span class="badge bg-light text-dark ms-1"><?php echo $application['employment_type']; ?></span>
                                            <span class="badge bg-light text-dark ms-1"><?php echo $application['work_mode']; ?></span>
                                            <span class="badge bg-light text-dark ms-1"><?php echo $application['location']; ?></span>
                                        </div>
                                        
                                        <?php if (!empty($application['department'])): ?>
                                            <p class="card-text"><strong>Department:</strong> <?php echo $application['department']; ?></p>
                                        <?php endif; ?>
                                        
                                        <p class="card-text"><strong>Applied on:</strong> <?php echo date('M j, Y g:i A', strtotime($application['applied_at'])); ?></p>
                                        
                                        <?php if ($application['status_updated_at'] && $application['status'] != 'applied'): ?>
                                            <p class="card-text"><strong>Status updated:</strong> <?php echo date('M j, Y g:i A', strtotime($application['status_updated_at'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statusTimelineModal<?php echo $application['id']; ?>">
                                                View Status Timeline
                                            </button>
                                            <a href="student-jobs.php#job-<?php echo $application['job_id']; ?>" class="btn btn-sm btn-outline-secondary ms-1">
                                                View Job Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Status Timeline Modal -->
                            <div class="modal fade" id="statusTimelineModal<?php echo $application['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Application Status Timeline</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="timeline">
                                                <div class="timeline-item active">
                                                    <h6>Application Submitted</h6>
                                                    <p class="text-muted"><?php echo date('M j, Y g:i A', strtotime($application['applied_at'])); ?></p>
                                                </div>
                                                
                                                <?php if ($application['status'] == 'viewed' || $application['status'] == 'shortlisted' || $application['status'] == 'rejected' || $application['status'] == 'hired'): ?>
                                                    <div class="timeline-item <?php echo $application['status'] != 'applied' ? 'active' : ''; ?>">
                                                        <h6>Application Viewed</h6>
                                                        <p class="text-muted">
                                                            <?php if ($application['status_updated_at'] && $application['status'] != 'applied'): ?>
                                                                <?php echo date('M j, Y g:i A', strtotime($application['status_updated_at'])); ?>
                                                            <?php else: ?>
                                                                Pending
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($application['status'] == 'shortlisted' || $application['status'] == 'rejected' || $application['status'] == 'hired'): ?>
                                                    <div class="timeline-item <?php echo $application['status'] == 'shortlisted' || $application['status'] == 'rejected' || $application['status'] == 'hired' ? 'active' : ''; ?>">
                                                        <h6>
                                                            <?php if ($application['status'] == 'shortlisted'): ?>
                                                                Shortlisted for Next Round
                                                            <?php elseif ($application['status'] == 'rejected'): ?>
                                                                Application Rejected
                                                            <?php elseif ($application['status'] == 'hired'): ?>
                                                                Hired
                                                            <?php else: ?>
                                                                Under Review
                                                            <?php endif; ?>
                                                        </h6>
                                                        <p class="text-muted">
                                                            <?php if ($application['status_updated_at'] && $application['status'] != 'applied' && $application['status'] != 'viewed'): ?>
                                                                <?php echo date('M j, Y g:i A', strtotime($application['status_updated_at'])); ?>
                                                            <?php else: ?>
                                                                Pending
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-briefcase display-1 text-muted"></i>
                        <h3 class="text-muted">No Applications Yet</h3>
                        <p>You haven't applied to any jobs yet. Browse available jobs and apply now!</p>
                        <a href="student-jobs.php" class="btn btn-primary">Browse Jobs</a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>