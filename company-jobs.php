<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not a company
if (!isLoggedIn() || !isCompany()) {
    header("Location: ../index.html");
    exit();
}

$company_id = $_SESSION['user_id'];

// Handle job status toggle
if (isset($_GET['toggle_status'])) {
    $job_id = intval($_GET['toggle_status']);
    
    // Get current status
    $status_query = "SELECT status FROM jobs WHERE id = ? AND company_id = ?";
    $stmt = $conn->prepare($status_query);
    $stmt->bind_param("ii", $job_id, $company_id);
    $stmt->execute();
    $current_status = $stmt->get_result()->fetch_assoc()['status'];
    
    // Toggle status
    $new_status = $current_status === 'active' ? 'inactive' : 'active';
    $update_query = "UPDATE jobs SET status = ? WHERE id = ? AND company_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $new_status, $job_id, $company_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Job status updated successfully';
    } else {
        $_SESSION['error'] = 'Error updating job status';
    }
    header("Location: company-jobs.php");
    exit();
}

// Handle job deletion
if (isset($_GET['delete'])) {
    $job_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM jobs WHERE id = ? AND company_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $job_id, $company_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Job deleted successfully';
    } else {
        $_SESSION['error'] = 'Error deleting job';
    }
    header("Location: company-jobs.php");
    exit();
}

// Get filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT j.*, 
          (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
          FROM jobs j 
          WHERE j.company_id = ?";

$params = array($company_id);
$types = "i";

if ($filter !== 'all') {
    $query .= " AND j.status = ?";
    $params[] = $filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (j.title LIKE ? OR j.department LIKE ? OR j.role LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY j.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$jobs = $stmt->get_result();

// Get stats for cards
$total_jobs_query = "SELECT COUNT(*) as total FROM jobs WHERE company_id = ?";
$stmt_total = $conn->prepare($total_jobs_query);
$stmt_total->bind_param("i", $company_id);
$stmt_total->execute();
$total_jobs = $stmt_total->get_result()->fetch_assoc()['total'];

$active_jobs_query = "SELECT COUNT(*) as active FROM jobs WHERE company_id = ? AND status = 'active'";
$stmt_active = $conn->prepare($active_jobs_query);
$stmt_active->bind_param("i", $company_id);
$stmt_active->execute();
$active_jobs = $stmt_active->get_result()->fetch_assoc()['active'];

$total_applications_query = "SELECT COUNT(*) as total_apps FROM applications a 
                             JOIN jobs j ON a.job_id = j.id 
                             WHERE j.company_id = ?";
$stmt_apps = $conn->prepare($total_applications_query);
$stmt_apps->bind_param("i", $company_id);
$stmt_apps->execute();
$total_applications = $stmt_apps->get_result()->fetch_assoc()['total_apps'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posted Jobs - Placement Portal</title>
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
                            <a class="nav-link active" href="company-jobs.php">
                                <i class="bi bi-briefcase me-2"></i>
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
                    <h1 class="h2">Posted Jobs</h1>
                    <a href="company-postjob.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Post New Job
                    </a>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card bg-primary text-white mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Jobs</h6>
                                        <h3 class="card-text"><?php echo $total_jobs; ?></h3>
                                    </div>
                                    <i class="bi bi-briefcase fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card bg-success text-white mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Active Jobs</h6>
                                        <h3 class="card-text"><?php echo $active_jobs; ?></h3>
                                    </div>
                                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card bg-info text-white mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Applications</h6>
                                        <h3 class="card-text"><?php echo $total_applications; ?></h3>
                                    </div>
                                    <i class="bi bi-people fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="filter" class="form-label">Status Filter</label>
                                    <select class="form-select" id="filter" name="filter">
                                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Jobs</option>
                                        <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label for="search" class="form-label">Search Jobs</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title, department, or role...">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Jobs List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Job Listings</h5>
                        <span class="text-muted"><?php echo $jobs->num_rows; ?> job(s) found</span>
                    </div>
                    <div class="card-body">
                        <?php if ($jobs->num_rows > 0): ?>
                            <div class="row">
                                <?php while ($job = $jobs->fetch_assoc()): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card job-card h-100 <?php echo $job['status'] == 'inactive' ? 'job-inactive' : ''; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h5 class="card-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                                                <span class="badge bg-<?php echo $job['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($job['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <?php if (!empty($job['department'])): ?>
                                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($job['department']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($job['role'])): ?>
                                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($job['role']); ?></span>
                                                <?php endif; ?>
                                                <span class="badge bg-info me-1"><?php echo ucfirst(str_replace('-', ' ', $job['work_mode'])); ?></span>
                                                <span class="badge bg-warning"><?php echo ucfirst($job['employment_type']); ?></span>
                                                <?php if (!empty($job['location'])): ?>
    <span class="badge bg-secondary me-1">
        <?php echo htmlspecialchars($job['location']); ?>
    </span>
<?php endif; ?>

                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Vacancies</small>
                                                    <div class="fw-semibold"><?php echo $job['vacancies']; ?></div>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Applications</small>
                                                    <div class="fw-semibold">
                                                        <span class="badge bg-primary application-badge">
                                                            <?php echo $job['application_count']; ?> applied
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($job['salary'])): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Salary</small>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($job['salary']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">Posted</small>
                                                <div class="fw-semibold"><?php echo date('M d, Y', strtotime($job['created_at'])); ?></div>
                                            </div>
                                            
                                            <?php if (!empty($job['must_have_skills'])): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted">Key Skills</small>
                                                    <div>
                                                        <?php 
                                                        $skills = explode(',', $job['must_have_skills']);
                                                        foreach (array_slice($skills, 0, 3) as $skill): 
                                                        ?>
                                                            <span class="badge bg-light text-dark badge-sm me-1"><?php echo trim($skill); ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($skills) > 3): ?>
                                                            <span class="badge bg-light text-dark badge-sm">+<?php echo count($skills) - 3; ?> more</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <div class="d-flex justify-content-between">
                                                <a href="company-applicants.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-people me-1"></i> View Applications
                                                </a>
                                                <div class="btn-group">
                                                  
                                                    <a href="company-jobs.php?toggle_status=<?php echo $job['id']; ?>" class="btn btn-sm btn-<?php echo $job['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                        <i class="bi bi-<?php echo $job['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                                                    </a>
                                                    <a href="company-jobs.php?delete=<?php echo $job['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this job?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-briefcase display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">No jobs found</h4>
                                <p class="text-muted">Get started by posting your first job opening</p>
                                <a href="company-postjob.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Post a Job
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($jobs->num_rows > 0): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>