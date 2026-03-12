<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not a company
if (!isLoggedIn() || !isCompany()) {
    header("Location: ../index.html");
    exit();
}

$company_id = $_SESSION['user_id'];

// Get company details
$company_query = "SELECT * FROM companies WHERE id = ?";
$stmt = $conn->prepare($company_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

// Get job count
$job_count_query = "SELECT COUNT(*) as count FROM jobs WHERE company_id = ?";
$stmt = $conn->prepare($job_count_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$job_count = $stmt->get_result()->fetch_assoc()['count'];

// Get application count
$app_count_query = "SELECT COUNT(*) as count FROM applications a 
                   JOIN jobs j ON a.job_id = j.id 
                   WHERE j.company_id = ?";
$stmt = $conn->prepare($app_count_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$app_count = $stmt->get_result()->fetch_assoc()['count'];

// Get shortlisted count
$shortlisted_query = "SELECT COUNT(*) as count FROM applications a 
                     JOIN jobs j ON a.job_id = j.id 
                     WHERE j.company_id = ? AND a.status = 'shortlisted'";
$stmt = $conn->prepare($shortlisted_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$shortlisted_count = $stmt->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - Placement Portal</title>
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
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="company-dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="company-profile.php">
                                <i class="bi bi-person me-2"></i>
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
                                <i class="bi bi-list-check me-2"></i>
                                Posted Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="company-applicants.php">
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
                    <h1 class="h2">Company Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="company-postjob.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Post New Job
                        </a>
                    </div>
                </div>
                
                <?php if ($company['status'] != 'approved'): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Your account is pending approval. You will be able to post jobs and view applications once approved by admin.
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Posted Jobs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $job_count; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-briefcase fs-1 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="company-jobs.php" class="text-decoration-none">
                                    View Jobs <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $app_count; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-list-check fs-1 text-success"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="company-applicants.php" class="text-decoration-none">
                                    View Applications <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Shortlisted</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $shortlisted_count; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-star fs-1 text-info"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="" class="text-decoration-none">
                                    View Shortlisted <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Account Status</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo ucfirst($company['status']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-shield-check fs-1 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="company-profile.php" class="text-decoration-none">
                                    View Profile <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Recent Applications</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $recent_apps_query = "SELECT a.*, j.title, s.first_name, s.last_name 
                                                     FROM applications a 
                                                     JOIN jobs j ON a.job_id = j.id 
                                                     JOIN students s ON a.student_id = s.id 
                                                     WHERE j.company_id = ? 
                                                     ORDER BY a.applied_at DESC 
                                                     LIMIT 5";
                                $stmt = $conn->prepare($recent_apps_query);
                                $stmt->bind_param("i", $company_id);
                                $stmt->execute();
                                $recent_apps = $stmt->get_result();
                                
                                if ($recent_apps->num_rows > 0) {
                                    while ($app = $recent_apps->fetch_assoc()) {
                                        $status_class = '';
                                        if ($app['status'] == 'applied') $status_class = 'bg-info';
                                        elseif ($app['status'] == 'viewed') $status_class = 'bg-primary';
                                        elseif ($app['status'] == 'shortlisted') $status_class = 'bg-success';
                                        elseif ($app['status'] == 'rejected') $status_class = 'bg-danger';
                                        
                                        echo '<div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">';
                                        echo '<div>';
                                        echo '<h6 class="mb-0">' . $app['title'] . '</h6>';
                                        echo '<small class="text-muted">' . $app['first_name'] . ' ' . $app['last_name'] . '</small>';
                                        echo '</div>';
                                        echo '<span class="badge ' . $status_class . '">' . ucfirst($app['status']) . '</span>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<p class="text-muted">No applications yet.</p>';
                                }
                                ?>
                            </div>
                            <div class="card-footer bg-transparent text-center">
                                <a href="company-applicants.php" class="text-decoration-none">View All Applications</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Recent Job Postings</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $recent_jobs_query = "SELECT * FROM jobs 
                                                     WHERE company_id = ? 
                                                     ORDER BY created_at DESC 
                                                     LIMIT 5";
                                $stmt = $conn->prepare($recent_jobs_query);
                                $stmt->bind_param("i", $company_id);
                                $stmt->execute();
                                $recent_jobs = $stmt->get_result();
                                
                                if ($recent_jobs->num_rows > 0) {
                                    while ($job = $recent_jobs->fetch_assoc()) {
                                        echo '<div class="mb-3 pb-2 border-bottom">';
                                        echo '<h6 class="mb-0">' . $job['title'] . '</h6>';
                                        echo '<small class="text-muted">' . $job['department'] . ' • ' . $job['work_mode'] . '</small>';
                                        echo '<div class="mt-1">';
                                        echo '<span class="badge bg-light text-dark">' . $job['employment_type'] . '</span>';
                                        echo '<span class="badge bg-light text-dark ms-1">' . $job['vacancies'] . ' vacancies</span>';
                                        echo '<span class="badge bg-' . ($job['status'] == 'active' ? 'success' : 'secondary') . ' ms-1">' . ucfirst($job['status']) . '</span>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<p class="text-muted">No job postings yet.</p>';
                                }
                                ?>
                            </div>
                            <div class="card-footer bg-transparent text-center">
                                <a href="company-jobs.php" class="text-decoration-none">View All Jobs</a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>