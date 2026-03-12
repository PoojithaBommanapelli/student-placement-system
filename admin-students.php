<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not an admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../index.html");
    exit();
}

// Handle student approval/rejection
if (isset($_GET['approve'])) {
    $student_id = intval($_GET['approve']);
    $update_query = "UPDATE students SET status = 'approved' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $student_id);
    
    if ($stmt->execute()) {
        // Get student email to send notification
        $email_query = "SELECT email FROM students WHERE id = ?";
        $stmt2 = $conn->prepare($email_query);
        $stmt2->bind_param("i", $student_id);
        $stmt2->execute();
        $student_email = $stmt2->get_result()->fetch_assoc()['email'];
        
        // Send approval email (placeholder)
        // sendEmail($student_email, 'Account Approved', 'student_approved.html');
        
        $_SESSION['success'] = 'Student approved successfully';
    } else {
        $_SESSION['error'] = 'Error approving student';
    }
    header("Location: admin-students.php");
    exit();
}

if (isset($_GET['reject'])) {
    $student_id = intval($_GET['reject']);
    $update_query = "UPDATE students SET status = 'rejected' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Student rejected successfully';
    } else {
        $_SESSION['error'] = 'Error rejecting student';
    }
    header("Location: admin-students.php");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['student_ids'])) {
    $action = $_POST['bulk_action'];
    $student_ids = implode(",", $_POST['student_ids']);
    
    if ($action === 'approve') {
        $update_query = "UPDATE students SET status = 'approved' WHERE id IN ($student_ids)";
    } elseif ($action === 'reject') {
        $update_query = "UPDATE students SET status = 'rejected' WHERE id IN ($student_ids)";
    } elseif ($action === 'delete') {
        $update_query = "DELETE FROM students WHERE id IN ($student_ids)";
    }
    
    if ($conn->query($update_query)) {
        $_SESSION['success'] = 'Bulk action completed successfully';
    } else {
        $_SESSION['error'] = 'Error performing bulk action';
    }
    header("Location: admin-students.php");
    exit();
}

// Build query based on filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$branch = isset($_GET['branch']) ? $_GET['branch'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : '';

$query = "SELECT * FROM students WHERE 1=1";
$params = array();

if ($filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR hall_ticket LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($branch)) {
    $query .= " AND branch = ?";
    $params[] = $branch;
}

if (!empty($year)) {
    $query .= " AND year = ?";
    $params[] = $year;
}

$query .= " ORDER BY created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Placement Portal</title>
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
                            <a class="nav-link" href="admin-dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin-students.php">
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
                <div class="main-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-1">Manage Students</h1>
                            <p class="text-muted mb-0">View and manage all student accounts</p>
                        </div>
                        <div>
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="bi bi-download me-1"></i>Export Data
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Total Students</h6>
                                    <h3 class="text-white mb-0"><?php echo $students->num_rows; ?></h3>
                                </div>
                                <i class="bi bi-people-fill stats-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(120deg, #4cc9f0 0%, #4895ef 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Approved</h6>
                                    <h3 class="text-white mb-0">
                                        <?php
                                        $approved_query = "SELECT COUNT(*) as count FROM students WHERE status = 'approved'";
                                        $result = $conn->query($approved_query);
                                        echo $result->fetch_assoc()['count'];
                                        ?>
                                    </h3>
                                </div>
                                <i class="bi bi-check-circle-fill stats-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card" style="background: linear-gradient(120deg, #f72585 0%, #b5179e 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Pending</h6>
                                    <h3 class="text-white mb-0">
                                        <?php
                                        $pending_query = "SELECT COUNT(*) as count FROM students WHERE status = 'pending'";
                                        $result = $conn->query($pending_query);
                                        echo $result->fetch_assoc()['count'];
                                        ?>
                                    </h3>
                                </div>
                                <i class="bi bi-clock-history stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="bi bi-funnel me-2"></i>Filter Students</h5>
                        <form method="GET" action="">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="filter" class="form-label">Status</label>
                                    <select class="form-select" id="filter" name="filter">
                                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Students</option>
                                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                        <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="branch" class="form-label">Branch</label>
                                    <select class="form-select" id="branch" name="branch">
                                        <option value="">All Branches</option>
                                        <option value="CSE" <?php echo $branch === 'CSE' ? 'selected' : ''; ?>>CSE</option>
                                        <option value="ECE" <?php echo $branch === 'ECE' ? 'selected' : ''; ?>>ECE</option>
                                        <option value="ME" <?php echo $branch === 'ME' ? 'selected' : ''; ?>>ME</option>
                                        <option value="CE" <?php echo $branch === 'CE' ? 'selected' : ''; ?>>CE</option>
                                        <option value="EEE" <?php echo $branch === 'EEE' ? 'selected' : ''; ?>>EEE</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="year" class="form-label">Year</label>
                                    <select class="form-select" id="year" name="year">
                                        <option value="">All Years</option>
                                        <option value="1" <?php echo $year === '1' ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2" <?php echo $year === '2' ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3" <?php echo $year === '3' ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4" <?php echo $year === '4' ? 'selected' : ''; ?>>4th Year</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo htmlspecialchars($search); ?>" placeholder="Search students...">
                                    </div>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Students Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Student List</h5>
                        <span class="badge bg-primary rounded-pill"><?php echo $students->num_rows; ?> students</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="bulkForm">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <select class="form-select" name="bulk_action" id="bulkAction">
                                        <option value="">Bulk Actions</option>
                                        <option value="approve">Approve Selected</option>
                                        <option value="reject">Reject Selected</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary w-100" onclick="applyBulkAction()">Apply</button>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="form-check form-switch d-inline-block me-3">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                        <label class="form-check-label" for="selectAll">Select All</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                            </th>
                                            <th>Student</th>
                                            <th>Contact Info</th>
                                            <th>Academic</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($students->num_rows > 0): ?>
                                            <?php while ($student = $students->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="form-check-input">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="../uploads/students/photos/<?php echo $student['photo'] ?? 'default.png'; ?>" 
                                                             alt="Student Photo" class="student-avatar me-3">
                                                        <div>
                                                            <h6 class="mb-0"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h6>
                                                            <small class="text-muted"><?php echo $student['hall_ticket']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><?php echo $student['email']; ?></div>
                                                    <?php if (!empty($student['phone'])): ?>
                                                        <small class="text-muted"><?php echo $student['phone']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?php echo $student['branch']; ?></span>
                                                    <span class="badge bg-info">Year <?php echo $student['year']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $student['status'] == 'approved' ? 'success' : ($student['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($student['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                                                    <div class="text-muted small"><?php echo date('g:i A', strtotime($student['created_at'])); ?></div>
                                                </td>
                                                <td class="action-buttons">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../student/student-profile.php?student_id=<?php echo $student['id']; ?>" class="btn btn-info" target="_blank" data-bs-toggle="tooltip" title="View Full Profile">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($student['status'] != 'approved'): ?>
                                                            <a href="admin-students.php?approve=<?php echo $student['id']; ?>" class="btn btn-success" data-bs-toggle="tooltip" title="Approve Student">
                                                                <i class="bi bi-check-lg"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($student['status'] != 'rejected'): ?>
                                                            <a href="admin-students.php?reject=<?php echo $student['id']; ?>" class="btn btn-danger" data-bs-toggle="tooltip" title="Reject Student">
                                                                <i class="bi bi-x-lg"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
                                                    <i class="bi bi-people display-1 text-muted"></i>
                                                    <h4 class="text-muted mt-3">No Students Found</h4>
                                                    <p class="text-muted">Try adjusting your filters to find what you're looking for.</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                        
                        <!-- Pagination -->
                        <nav aria-label="Page navigation" class="mt-4">
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
    
    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Students Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label for="exportFormat" class="form-label">Format</label>
                            <select class="form-select" id="exportFormat">
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="exportColumns" class="form-label">Columns</label>
                            <select multiple class="form-select" id="exportColumns" size="5">
                                <option selected>Name</option>
                                <option selected>Email</option>
                                <option selected>Hall Ticket</option>
                                <option selected>Branch</option>
                                <option selected>Year</option>
                                <option selected>Status</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Export</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="student_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        document.getElementById('selectAllCheckbox').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="student_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            document.getElementById('selectAll').checked = this.checked;
        });
        
        // Apply bulk action
        function applyBulkAction() {
            const action = document.getElementById('bulkAction').value;
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            const selected = document.querySelectorAll('input[name="student_ids[]"]:checked');
            if (selected.length === 0) {
                alert('Please select at least one student');
                return;
            }
            
            if (confirm(`Are you sure you want to ${action} ${selected.length} student(s)?`)) {
                document.getElementById('bulkForm').submit();
            }
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>