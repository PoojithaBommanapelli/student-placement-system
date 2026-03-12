<?php
include '../includes/db.php';
include '../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in or not an admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../index.html");
    exit();
}

// Handle job status changes
if (isset($_GET['activate'])) {
    $job_id = intval($_GET['activate']);
    $update_query = "UPDATE jobs SET status = 'active' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Job activated successfully';
    } else {
        $_SESSION['error'] = 'Error activating job';
    }
    header("Location: admin-jobs.php");
    exit();
}

if (isset($_GET['deactivate'])) {
    $job_id = intval($_GET['deactivate']);
    $update_query = "UPDATE jobs SET status = 'inactive' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Job deactivated successfully';
    } else {
        $_SESSION['error'] = 'Error deactivating job';
    }
    header("Location: admin-jobs.php");
    exit();
}

if (isset($_GET['delete'])) {
    $job_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM jobs WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Job deleted successfully';
    } else {
        $_SESSION['error'] = 'Error deleting job';
    }
    header("Location: admin-jobs.php");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['job_ids'])) {
    $action = $_POST['bulk_action'];
    $job_ids = array_map('intval', $_POST['job_ids']); // Sanitize IDs
    $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
    
    if ($action === 'activate') {
        $update_query = "UPDATE jobs SET status = 'active' WHERE id IN ($placeholders)";
    } elseif ($action === 'deactivate') {
        $update_query = "UPDATE jobs SET status = 'inactive' WHERE id IN ($placeholders)";
    } elseif ($action === 'delete') {
        $update_query = "DELETE FROM jobs WHERE id IN ($placeholders)";
    }
    
    if (isset($update_query)) {
        $stmt = $conn->prepare($update_query);
        $types = str_repeat('i', count($job_ids));
        $stmt->bind_param($types, ...$job_ids);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Bulk action completed successfully';
        } else {
            $_SESSION['error'] = 'Error performing bulk action';
        }
    }
    header("Location: admin-jobs.php");
    exit();
}

// Build query based on filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$company_filter = isset($_GET['company']) ? $_GET['company'] : '';

$query = "SELECT j.*, c.name as company_name, c.logo as company_logo 
          FROM jobs j 
          JOIN companies c ON j.company_id = c.id 
          WHERE 1=1";
$params = array();
$types = '';

if ($filter !== 'all') {
    $query .= " AND j.status = ?";
    $params[] = $filter;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (j.title LIKE ? OR j.department LIKE ? OR j.role LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

if (!empty($company_filter)) {
    $query .= " AND j.company_id = ?";
    $params[] = $company_filter;
    $types .= 'i';
}

$query .= " ORDER BY j.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$jobs = $stmt->get_result();

// Get companies for filter
$companies_query = "SELECT id, name FROM companies ORDER BY name";
$companies_result = $conn->query($companies_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Placement Portal</title>
    <!--<link rel="stylesheet" href="../assets/css/style.css">-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
        
        .company-logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .company-logo:hover {
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
                            <a class="nav-link" href="admin-dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-students.php">
                                <i class="bi bi-people me-2"></i>
                                Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-companies.php">
                                <i class="bi bi-buildings me-2"></i>
                                Companies
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin-jobs.php">
                                <i class="bi bi-briefcase me-2"></i>
                                Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-reports.php">
                                <i class="bi bi-bar-chart me-2"></i>
                                Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-logout.php">
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
                    <h1 class="h2">Manage Jobs</h1>
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
                                    <label for="filter" class="form-label">Status Filter</label>
                                    <select class="form-select" id="filter" name="filter">
                                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Jobs</option>
                                        <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="company" class="form-label">Company</label>
                                    <select class="form-select" id="company" name="company">
                                        <option value="">All Companies</option>
                                        <?php while ($company = $companies_result->fetch_assoc()): ?>
                                            <option value="<?php echo $company['id']; ?>" 
                                                <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($company['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Search jobs...">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Jobs Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Job Listings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="bulkForm">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <select class="form-select" name="bulk_action" id="bulkAction">
                                        <option value="">Bulk Actions</option>
                                        <option value="activate">Activate Selected</option>
                                        <option value="deactivate">Deactivate Selected</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary" onclick="applyBulkAction()">Apply</button>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th width="30">
                                                <input type="checkbox" id="selectAll">
                                            </th>
                                            <th>Company</th>
                                            <th>Job Title</th>
                                            <th>Department</th>
                                            <th>Vacancies</th>
                                            <th>Work Mode</th>
                                            <th>Status</th>
                                            <th>Posted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($jobs->num_rows > 0): ?>
                                            <?php while ($job = $jobs->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="job_ids[]" value="<?php echo $job['id']; ?>">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="../uploads/companies/logos/<?php echo !empty($job['company_logo']) ? htmlspecialchars($job['company_logo']) : 'default.png'; ?>" 
                                                             alt="<?php echo htmlspecialchars($job['company_name']); ?>" 
                                                             class="company-logo me-2"
                                                             onerror="this.src='../uploads/companies/logos/default.png'">
                                                        <span><?php echo htmlspecialchars($job['company_name']); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($job['title']); ?></td>
                                                <td><?php echo htmlspecialchars($job['department']); ?></td>
                                                <td><?php echo $job['vacancies']; ?></td>
                                                <td>
                                                    <span class="badge bg-info text-capitalize">
                                                        <?php echo htmlspecialchars($job['work_mode']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $job['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($job['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                                <td class="action-buttons">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#jobDetailsModal<?php echo $job['id']; ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($job['status'] != 'active'): ?>
                                                            <a href="admin-jobs.php?activate=<?php echo $job['id']; ?>" class="btn btn-success">
                                                                <i class="bi bi-check-lg"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($job['status'] != 'inactive'): ?>
                                                            <a href="admin-jobs.php?deactivate=<?php echo $job['id']; ?>" class="btn btn-warning">
                                                                <i class="bi bi-pause"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="admin-jobs.php?delete=<?php echo $job['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this job?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center">No jobs found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                        
                        <!-- Pagination -->
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
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
                </div>
            </main>
        </div>
    </div>
    
    <!-- Job Details Modals -->
    <?php 
    // Reset the pointer and recreate modals for each job
    $jobs->data_seek(0);
    while ($job = $jobs->fetch_assoc()): 
    ?>
    <div class="modal fade" id="jobDetailsModal<?php echo $job['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['company_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                            <h5 class="text-muted"><?php echo htmlspecialchars($job['company_name']); ?></h5>
                        </div>
                        <div class="col-md-4 text-end">
                            <img src="../uploads/companies/logos/<?php echo !empty($job['company_logo']) ? htmlspecialchars($job['company_logo']) : 'default.png'; ?>" 
                                 alt="Company Logo" class="company-logo" style="width: 80px; height: 80px;"
                                 onerror="this.src='../uploads/companies/logos/default.png'">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Employment Type:</strong> 
                            <span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('-', ' ', $job['employment_type'] ?? 'Not specified')); ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Work Mode:</strong> 
                            <span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('-', ' ', $job['work_mode'] ?? 'Not specified')); ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Department:</strong> <?php echo !empty($job['department']) ? htmlspecialchars($job['department']) : 'Not specified'; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Role:</strong> <?php echo !empty($job['role']) ? htmlspecialchars($job['role']) : 'Not specified'; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Vacancies:</strong> <?php echo $job['vacancies']; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Location:</strong> <?php echo !empty($job['location']) ? htmlspecialchars($job['location']) : 'Not specified'; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Salary:</strong> <?php echo !empty($job['salary']) ? htmlspecialchars($job['salary']) : 'Not specified'; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Posted on:</strong> <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Education:</strong> <?php echo !empty($job['education']) ? htmlspecialchars($job['education']) : 'Not specified'; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> 
                            <span class="badge bg-<?php echo $job['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($job['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($job['must_have_skills'])): ?>
                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>Must-Have Skills:</strong>
                                <div class="mt-1">
                                    <?php 
                                    $skills = explode(',', $job['must_have_skills']);
                                    foreach ($skills as $skill): 
                                        if (!empty(trim($skill))): ?>
                                            <span class="badge bg-primary me-1 mb-1"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                        <?php endif;
                                    endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($job['responsibilities'])): ?>
                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>Responsibilities:</strong>
                                <div class="mt-1 p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($job['responsibilities'])); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="../company/company-profile.php?company_id=<?php echo $job['company_id']; ?>" class="btn btn-primary" target="_blank">
                        View Company Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Select all checkbox functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="job_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Apply bulk action function
        function applyBulkAction() {
            const action = document.getElementById('bulkAction').value;
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            const selected = document.querySelectorAll('input[name="job_ids[]"]:checked');
            if (selected.length === 0) {
                alert('Please select at least one job');
                return;
            }
            
            if (confirm(`Are you sure you want to ${action} ${selected.length} job(s)?`)) {
                document.getElementById('bulkForm').submit();
            }
        }
        
        // Prevent form submission on modal button clicks
        document.addEventListener('DOMContentLoaded', function() {
            // Handle image loading errors
            const images = document.querySelectorAll('.company-logo');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    this.src = '../uploads/companies/logos/default.png';
                });
            });
            
            // Ensure modal buttons don't submit forms
            const modalButtons = document.querySelectorAll('[data-bs-toggle="modal"]');
            modalButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });
        });
        
        // Handle URL hash for direct modal opening
        if (window.location.hash.startsWith('#jobDetailsModal')) {
            const modalId = window.location.hash.substring(1);
            const modalElement = document.getElementById(modalId);
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        }
    </script>
</body>
</html>