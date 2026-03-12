<?php
include '../includes/db.php';
include '../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debugging: Check authentication status
$isLoggedIn = isLoggedIn();
$isAdmin = isAdmin();

// Redirect if not logged in or not an admin
if (!$isLoggedIn || !$isAdmin) {
    header("Location: ../index.html");
    exit();
}

// Determine which company profile to show
if (isset($_GET['company_id']) && isAdmin()) {
    // Viewing a specific company profile as admin
    $profile_company_id = intval($_GET['company_id']);
    
    // Verify the company exists
    $company_check = "SELECT * FROM companies WHERE id = ?";
    $stmt = $conn->prepare($company_check);
    $stmt->bind_param("i", $profile_company_id);
    $stmt->execute();
    $company_result = $stmt->get_result();
    
    if ($company_result->num_rows === 1) {
        $company = $company_result->fetch_assoc();
        $is_viewing_other_company = true;
    } else {
        // Company not found, redirect back
        header("Location: ../admin/admin-companies.php");
        exit();
    }
} else {
    // For company users viewing their own profile
    if (!isCompany() && !isAdmin()) {
        header("Location: ../index.html");
        exit();
    }
    
    // If admin is not viewing a specific company, use their own ID (if company)
    if (isCompany()) {
        $profile_company_id = $_SESSION['user_id'];
    }
    $is_viewing_other_company = false;
}


// Handle company approval/rejection
if (isset($_GET['approve'])) {
    $company_id = intval($_GET['approve']);
    $update_query = "UPDATE companies SET status = 'approved' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $company_id);
    
    if ($stmt->execute()) {
        // Get company email to send notification
        $email_query = "SELECT email FROM companies WHERE id = ?";
        $stmt2 = $conn->prepare($email_query);
        $stmt2->bind_param("i", $company_id);
        $stmt2->execute();
        $company_email = $stmt2->get_result()->fetch_assoc()['email'];
        
        // Send approval email (placeholder)
        // sendEmail($company_email, 'Account Approved', 'company_approved.html');
        
        $_SESSION['success'] = 'Company approved successfully';
    } else {
        $_SESSION['error'] = 'Error approving company';
    }
    header("Location: admin-companies.php");
    exit();
}

if (isset($_GET['reject'])) {
    $company_id = intval($_GET['reject']);
    $update_query = "UPDATE companies SET status = 'rejected' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $company_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Company rejected successfully';
    } else {
        $_SESSION['error'] = 'Error rejecting company';
    }
    header("Location: admin-companies.php");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['company_ids'])) {
    $action = $_POST['bulk_action'];
    $company_ids = implode(",", $_POST['company_ids']);
    
    if ($action === 'approve') {
        $update_query = "UPDATE companies SET status = 'approved' WHERE id IN ($company_ids)";
    } elseif ($action === 'reject') {
        $update_query = "UPDATE companies SET status = 'rejected' WHERE id IN ($company_ids)";
    } elseif ($action === 'delete') {
        $update_query = "DELETE FROM companies WHERE id IN ($company_ids)";
    }
    
    if ($conn->query($update_query)) {
        $_SESSION['success'] = 'Bulk action completed successfully';
    } else {
        $_SESSION['error'] = 'Error performing bulk action';
    }
    header("Location: admin-companies.php");
    exit();
}

// Build query based on filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$industry = isset($_GET['industry']) ? $_GET['industry'] : '';

$query = "SELECT * FROM companies WHERE 1=1";
$params = array();

if ($filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR location LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($industry)) {
    $query .= " AND industry_type = ?";
    $params[] = $industry;
}

$query .= " ORDER BY created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$companies = $stmt->get_result();

// Get unique industry types for filter
$industry_query = "SELECT DISTINCT industry_type FROM companies WHERE industry_type IS NOT NULL ORDER BY industry_type";
$industry_result = $conn->query($industry_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Companies - Placement Portal</title>
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
        
        .student-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;

        
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
                            <a class="nav-link active" href="admin-companies.php">
                                <i class="bi bi-buildings me-2"></i>
                                Companies
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-jobs.php">
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
                    <h1 class="h2">Manage Companies</h1>
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
                                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Companies</option>
                                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                        <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value 'rejected' <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="industry" class="form-label">Industry</label>
                                    <select class="form-select" id="industry" name="industry">
                                        <option value="">All Industries</option>
                                        <?php while ($industry_row = $industry_result->fetch_assoc()): ?>
                                            <option value="<?php echo $industry_row['industry_type']; ?>" 
                                                <?php echo $industry === $industry_row['industry_type'] ? 'selected' : ''; ?>>
                                                <?php echo $industry_row['industry_type']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Search companies...">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Companies Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Company List</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="bulkForm">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <select class="form-select" name="bulk_action" id="bulkAction">
                                        <option value="">Bulk Actions</option>
                                        <option value="approve">Approve Selected</option>
                                        <option value="reject">Reject Selected</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary" onclick="applyBulkAction()">Apply</button>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <!-- Replace the table header in admin-companies.php -->
<thead>
    <tr>
        <th width="30">
            <input type="checkbox" id="selectAll">
        </th>
        <th>Logo</th>
        <th>Company Name</th>
        <th>Email</th>
        <th>Industry</th>
        <th>Location</th>
        <th>Status</th>
        <th>Registered</th>
        <th>Actions</th>
    </tr>
</thead>

<!-- Replace the table body in admin-companies.php -->
<tbody>
    <?php if ($companies->num_rows > 0): ?>
        <?php while ($company = $companies->fetch_assoc()): ?>
        <tr>
            <td>
                <input type="checkbox" name="company_ids[]" value="<?php echo $company['id']; ?>">
            </td>
            <td>
                <img src="../uploads/companies/logos/<?php echo !empty($company['logo']) ? htmlspecialchars($company['logo']) : 'default.png'; ?>" 
                     alt="<?php echo htmlspecialchars($company['name']); ?>" 
                     class="student-avatar">
            </td>
            <td><?php echo htmlspecialchars($company['name']); ?></td>
            <td><?php echo htmlspecialchars($company['email']); ?></td>
            <td><?php echo htmlspecialchars($company['industry_type']); ?></td>
            <td><?php echo htmlspecialchars($company['location']); ?></td>
            <td>
                <span class="badge bg-<?php echo $company['status'] == 'approved' ? 'success' : ($company['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                    <?php echo ucfirst($company['status']); ?>
                </span>
            </td>
            <td><?php echo date('M j, Y', strtotime($company['created_at'])); ?></td>
            <td class="action-buttons">
                <div class="btn-group btn-group-sm">
                    <a href="../company/company-profile.php?company_id=<?php echo $company['id']; ?>" class="btn btn-info" target="_blank">
                    <i class="bi bi-eye me-1"></i>View Profile
                    </a>
                    <!-- Add this new button for viewing company jobs -->
                    <a href="admin-jobs.php?company=<?php echo $company['id']; ?>" class="btn btn-primary">
                    <i class="bi bi-briefcase me-1"></i>View Jobs
                    </a>
                    <?php if ($company['status'] != 'approved'): ?>
                        <a href="admin-companies.php?approve=<?php echo $company['id']; ?>" class="btn btn-success">
                            <i class="bi bi-check-lg"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($company['status'] != 'rejected'): ?>
                        <a href="admin-companies.php?reject=<?php echo $company['id']; ?>" class="btn btn-danger">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="9" class="text-center">No companies found</td>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="company_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Apply bulk action
        function applyBulkAction() {
            const action = document.getElementById('bulkAction').value;
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            const selected = document.querySelectorAll('input[name="company_ids[]"]:checked');
            if (selected.length === 0) {
                alert('Please select at least one company');
                return;
            }
            
            if (confirm(`Are you sure you want to ${action} ${selected.length} company(s)?`)) {
                document.getElementById('bulkForm').submit();
            }
        }
    </script>
</body>
</html>