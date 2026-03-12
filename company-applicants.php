<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not a company
if (!isLoggedIn() || !isCompany()) {
    header("Location: ../index.html");
    exit();
}

$company_id = $_SESSION['user_id'];

// Handle status updates
if (isset($_GET['update_status']) && isset($_GET['status'])) {
    $application_id = intval($_GET['update_status']);
    $new_status = $_GET['status'];
    
    // Verify the application belongs to this company
    $verify_query = "SELECT a.* FROM applications a 
                     JOIN jobs j ON a.job_id = j.id 
                     WHERE a.id = ? AND j.company_id = ?";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("ii", $application_id, $company_id);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    
    if ($application) {
        $update_query = "UPDATE applications SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $application_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Application status updated successfully';
        } else {
            $_SESSION['error'] = 'Error updating application status';
        }
    } else {
        $_SESSION['error'] = 'Application not found or access denied';
    }
    
    // Redirect back to avoid form resubmission
    header("Location: company-applicants.php" . (isset($_GET['job_id']) ? "?job_id=" . $_GET['job_id'] : ""));
    exit();
}

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$job_filter = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT a.*, s.first_name, s.last_name, s.email, s.branch, s.year, 
                 s.resume, s.photo, j.title as job_title, j.id as job_id
          FROM applications a
          JOIN students s ON a.student_id = s.id
          JOIN jobs j ON a.job_id = j.id
          WHERE j.company_id = ?";
$params = array($company_id);
$types = "i";

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($job_filter > 0) {
    $query .= " AND a.job_id = ?";
    $params[] = $job_filter;
    $types .= "i";
}

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR j.title LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$query .= " ORDER BY a.applied_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applicants = $stmt->get_result();

// Get company's jobs for filter
$jobs_query = "SELECT id, title FROM jobs WHERE company_id = ? ORDER BY created_at DESC";
$stmt_jobs = $conn->prepare($jobs_query);
$stmt_jobs->bind_param("i", $company_id);
$stmt_jobs->execute();
$jobs = $stmt_jobs->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicants - Placement Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!--<link rel="stylesheet" href="../assets/css/style.css">-->
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
        
        /* Fixed table responsive styling */
        .table-responsive {
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        /* Horizontal scroll for tables on mobile */
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            min-width: 800px; /* Minimum width to ensure proper display */
            width: 100%;
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem 0.75rem;
            white-space: nowrap;
        }
        
        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-color: #e9ecef;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
            transition: all 0.2s ease;
        }
        
        /* Fixed student avatar size */
        .applicant-photo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: nowrap;
        }
        
        .btn-group .btn {
            border-radius: 0.5rem;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
            border-radius: 0.5rem;
            white-space: nowrap;
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
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
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
            height: 8px;
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
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .table th, .table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .applicant-photo {
                width: 40px;
                height: 40px;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            /* Better mobile filter layout */
            .filter-card .row > div {
                margin-bottom: 1rem;
            }
            
            .filter-card .row > div:last-child {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="company-dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="company-profile.php">
                                <i class="bi bi-building me-2"></i>
                                Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="company-postjob.php">
                                <i class="bi bi-plus-circle me-2"></i>
                                Post Job
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="company-jobs.php">
                                <i class="bi bi-briefcase me-2"></i>
                                Posted Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="company-applicants.php">
                                <i class="bi bi-people me-2"></i>
                                Applicants
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="company-logout.php">
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
                    <h1 class="h2">Applicants</h1>
                    <span class="badge bg-primary"><?php echo $applicants->num_rows; ?> Applicants</span>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                        <option value="applied" <?php echo $status_filter === 'applied' ? 'selected' : ''; ?>>Applied</option>
                                        <option value="viewed" <?php echo $status_filter === 'viewed' ? 'selected' : ''; ?>>Viewed</option>
                                        <option value="shortlisted" <?php echo $status_filter === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="hired" <?php echo $status_filter === 'hired' ? 'selected' : ''; ?>>Hired</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="job_id" class="form-label">Job</label>
                                    <select class="form-select" id="job_id" name="job_id">
                                        <option value="0">All Jobs</option>
                                        <?php while ($job = $jobs->fetch_assoc()): ?>
                                            <option value="<?php echo $job['id']; ?>" <?php echo $job_filter == $job['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Search applicants...">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Applicants Table -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-container">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Applicant</th>
                                        <th>Job Title</th>
                                        <th>Branch</th>
                                        <th>Year</th>
                                        <th>Applied On</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($applicants->num_rows > 0): ?>
                                        <?php while ($applicant = $applicants->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="../uploads/students/photos/<?php echo $applicant['photo'] ?? 'default.png'; ?>" 
                                                         alt="Profile" class="applicant-photo me-3"
                                                         onerror="this.src='../uploads/students/photos/default.png'">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></strong>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($applicant['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($applicant['job_title']); ?></td>
                                            <td><?php echo htmlspecialchars($applicant['branch']); ?></td>
                                            <td>Year <?php echo htmlspecialchars($applicant['year']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($applicant['applied_at'])); ?></td>
                                            <td>
                                                <span class="badge status-badge bg-<?php 
                                                    echo $applicant['status'] == 'applied' ? 'info' : 
                                                    ($applicant['status'] == 'viewed' ? 'primary' : 
                                                    ($applicant['status'] == 'shortlisted' ? 'success' : 
                                                    ($applicant['status'] == 'rejected' ? 'danger' : 'warning'))); 
                                                ?>">
                                                    <?php echo ucfirst($applicant['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="../student/student-profile.php?student_id=<?php echo $applicant['student_id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" target="_blank" data-bs-toggle="tooltip" title="View Profile">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (!empty($applicant['resume'])): ?>
                                                    <a href="../uploads/students/resumes/<?php echo $applicant['resume']; ?>" 
                                                       class="btn btn-outline-success btn-sm" target="_blank" data-bs-toggle="tooltip" title="View Resume">
                                                        <i class="bi bi-file-earmark-text"></i>
                                                    </a>
                                                    <?php else: ?>
                                                    <button class="btn btn-outline-secondary btn-sm" disabled data-bs-toggle="tooltip" title="No Resume">
                                                        <i class="bi bi-file-earmark-x"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" 
                                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bi bi-gear"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="?update_status=<?php echo $applicant['id']; ?>&status=viewed<?php echo $job_filter > 0 ? '&job_id=' . $job_filter : ''; ?>">
                                                                <i class="bi bi-eye me-1"></i> Mark as Viewed
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="?update_status=<?php echo $applicant['id']; ?>&status=shortlisted<?php echo $job_filter > 0 ? '&job_id=' . $job_filter : ''; ?>">
                                                                <i class="bi bi-star me-1"></i> Shortlist
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="?update_status=<?php echo $applicant['id']; ?>&status=rejected<?php echo $job_filter > 0 ? '&job_id=' . $job_filter : ''; ?>">
                                                                <i class="bi bi-x-circle me-1"></i> Reject
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="?update_status=<?php echo $applicant['id']; ?>&status=hired<?php echo $job_filter > 0 ? '&job_id=' . $job_filter : ''; ?>">
                                                                <i class="bi bi-check-circle me-1"></i> Mark as Hired
                                                            </a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <i class="bi bi-people display-1 text-muted"></i>
                                                <h4 class="text-muted mt-3">No Applicants Found</h4>
                                                <p class="text-muted">There are no applicants matching your current filters.</p>
                                                <?php if ($status_filter !== 'all' || $job_filter > 0 || !empty($search)): ?>
                                                <a href="company-applicants.php" class="btn btn-primary mt-2">
                                                    <i class="bi bi-arrow-clockwise me-1"></i> Clear Filters
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Add image error handling
            document.querySelectorAll('.applicant-photo').forEach(img => {
                img.onerror = function() {
                    this.src = '../uploads/students/photos/default.png';
                };
            });
        });
    </script>
</body>
</html>