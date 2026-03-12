<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not a student
if (!isLoggedIn() || !isStudent()) {
    header("Location: ../index.html");
    exit();
}

$student_id = $_SESSION['user_id'];

// Check if student is approved
$student_query = "SELECT status FROM students WHERE id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_status = $stmt->get_result()->fetch_assoc()['status'];

// Build query based on filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$company = isset($_GET['company']) ? $_GET['company'] : '';
$work_mode = isset($_GET['work_mode']) ? $_GET['work_mode'] : '';
$employment_type = isset($_GET['employment_type']) ? $_GET['employment_type'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';


$query = "SELECT j.*, c.name as company_name, c.logo as company_logo 
          FROM jobs j 
          JOIN companies c ON j.company_id = c.id 
          WHERE j.status = 'active' AND c.status = 'approved'";
$params = array();
$types = '';

if (!empty($search)) {
    $query .= " AND (j.title LIKE ? OR j.department LIKE ? OR j.role LIKE ? OR c.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ssss';
}

if (!empty($company)) {
    $query .= " AND c.id = ?";
    $params[] = $company;
    $types .= 's';
}

if (!empty($work_mode)) {
    $query .= " AND j.work_mode = ?";
    $params[] = $work_mode;
    $types .= 's';
}

if (!empty($employment_type)) {
    $query .= " AND j.employment_type = ?";
    $params[] = $employment_type;
    $types .= 's';
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
$companies_query = "SELECT id, name FROM companies WHERE status = 'approved' ORDER BY name";
$companies_result = $conn->query($companies_query);

// Get applied job IDs for this student
$applied_query = "SELECT job_id FROM applications WHERE student_id = ?";
$stmt = $conn->prepare($applied_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$applied_jobs = $stmt->get_result();
$applied_job_ids = array();
while ($row = $applied_jobs->fetch_assoc()) {
    $applied_job_ids[] = $row['job_id'];
}

// Handle job application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $student_status === 'approved') {
    $job_id = intval($_POST['job_id']);
    
    // Check if already applied
    if (in_array($job_id, $applied_job_ids)) {
        $error = 'You have already applied for this job';
    } else {
        // Insert application
        $insert_query = "INSERT INTO applications (job_id, student_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ii", $job_id, $student_id);
        
        if ($stmt->execute()) {
            $success = 'Application submitted successfully!';
            
            // Send application confirmation email (placeholder)
            // sendEmail($student_email, 'Job Application Submitted', 'job_applied.html');
            
            // Refresh applied jobs list
            $applied_job_ids[] = $job_id;
        } else {
            $error = 'Error submitting application. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Listings - Placement Portal</title>
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
                            <a class="nav-link" href="student-applied.php">
                                <i class="bi bi-list-check me-2"></i>
                                Applied Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="student-jobs.php">
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
                    <h1 class="h2">Job Listings</h1>
                    <span class="badge bg-primary"><?php echo $jobs->num_rows; ?> Jobs</span>
                </div>
                
                <?php if ($student_status != 'approved'): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Your account is pending approval. You will be able to apply for jobs once approved by admin.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Job title, company, etc.">
                                </div>
                                <div class="col-md-3">
                                    <label for="company" class="form-label">Company</label>
                                    <select class="form-select" id="company" name="company">
                                        <option value="">All Companies</option>
                                        <?php while ($company_row = $companies_result->fetch_assoc()): ?>
                                            <option value="<?php echo $company_row['id']; ?>" 
                                                <?php echo $company == $company_row['id'] ? 'selected' : ''; ?>>
                                                <?php echo $company_row['name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="work_mode" class="form-label">Work Mode</label>
                                    <select class="form-select" id="work_mode" name="work_mode">
                                        <option value="">All Modes</option>
                                        <option value="on-site" <?php echo $work_mode === 'on-site' ? 'selected' : ''; ?>>On-Site</option>
                                        <option value="remote" <?php echo $work_mode === 'remote' ? 'selected' : ''; ?>>Remote</option>
                                        <option value="hybrid" <?php echo $work_mode === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
    <label for="location" class="form-label">Location</label>
    <select class="form-select" id="location" name="location">
        <option value="">All Locations</option>
        <?php 
        // Fetch distinct locations from jobs table
        $locations_result = $conn->query("SELECT DISTINCT location FROM jobs ORDER BY location ASC");
        while ($loc_row = $locations_result->fetch_assoc()): 
        ?>
            <option value="<?php echo htmlspecialchars($loc_row['location']); ?>" 
                <?php echo (isset($location) && $location === $loc_row['location']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($loc_row['location']); ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>

                                <div class="col-md-2">
                                    <label for="employment_type" class="form-label">Employment Type</label>
                                    <select class="form-select" id="employment_type" name="employment_type">
                                        <option value="">All Types</option>
                                        <option value="full-time" <?php echo $employment_type === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                                        <option value="part-time" <?php echo $employment_type === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                                        <option value="internship" <?php echo $employment_type === 'internship' ? 'selected' : ''; ?>>Internship</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Jobs List -->
                <?php if ($jobs->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($job = $jobs->fetch_assoc()): ?>
                            <div class="col-md-6 mb-4" id="job-<?php echo $job['id']; ?>">
                                <div class="card shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title"><?php echo $job['title']; ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted"><?php echo $job['company_name']; ?></h6>
                                            </div>
                                            <img src="../uploads/companies/logos/<?php echo $job['company_logo'] ?? 'default.png'; ?>" 
                                                 alt="Company Logo" class="company-logo" style="width: 50px; height: 50px;">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="badge bg-light text-dark"><?php echo $job['employment_type']; ?></span>
                                            <span class="badge bg-light text-dark ms-1"><?php echo $job['work_mode']; ?></span>
                                            <?php if (!empty($job['vacancies'])): ?>
                                                <span class="badge bg-info ms-1"><?php echo $job['vacancies']; ?> Vacancies</span>
                                            <?php endif; ?>
                                            <?php if (!empty($job['location'])): ?>
                                                <span class="badge bg-info ms-1"><?php echo $job['location']; ?> Location</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($job['department'])): ?>
                                            <p class="card-text"><strong>Department:</strong> <?php echo $job['department']; ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($job['role'])): ?>
                                            <p class="card-text"><strong>Role:</strong> <?php echo $job['role']; ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($job['salary'])): ?>
                                            <p class="card-text"><strong>Salary:</strong> <?php echo $job['salary']; ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($job['education'])): ?>
                                            <p class="card-text"><strong>Education:</strong> <?php echo $job['education']; ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($job['must_have_skills'])): ?>
                                            <p class="card-text"><strong>Skills:</strong> <?php echo $job['must_have_skills']; ?></p>
                                        <?php endif; ?>
                                        
                                        <p class="card-text"><small class="text-muted">Posted on <?php echo date('M j, Y', strtotime($job['created_at'])); ?></small></p>
                                        
                                        <div class="mt-3">
                                            <?php if (in_array($job['id'], $applied_job_ids)): ?>
                                                <button class="btn btn-success" disabled>
                                                    <i class="bi bi-check-circle me-1"></i> Applied
                                                </button>
                                            <?php else: ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-primary" <?php echo $student_status !== 'approved' ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-send me-1"></i> Apply Now
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-outline-secondary ms-1" data-bs-toggle="modal" data-bs-target="#jobDetailsModal<?php echo $job['id']; ?>">
                                                View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Job Details Modal -->
                            <div class="modal fade" id="jobDetailsModal<?php echo $job['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Job Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-4">
                                                <div class="col-md-8">
                                                    <h4><?php echo $job['title']; ?></h4>
                                                    <h5 class="text-muted"><?php echo $job['company_name']; ?></h5>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <img src="../uploads/companies/logos/<?php echo $job['company_logo'] ?? 'default.png'; ?>" 
                                                         alt="Company Logo" class="company-logo" style="width: 80px; height: 80px;">
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Employment Type:</strong> 
                                                    <span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('-', ' ', $job['employment_type'])); ?></span>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Work Mode:</strong> 
                                                    <span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('-', ' ', $job['work_mode'])); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Department:</strong> <?php echo $job['department'] ?? 'Not specified'; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Role:</strong> <?php echo $job['role'] ?? 'Not specified'; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Vacancies:</strong> <?php echo $job['vacancies']; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Locations:</strong> <?php echo $job['location']; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Salary:</strong> <?php echo $job['salary'] ?? 'Not specified'; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Education:</strong> <?php echo $job['education'] ?? 'Not specified'; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Posted on:</strong> <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
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
                                                            ?>
                                                                <span class="badge bg-primary me-1"><?php echo trim($skill); ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($job['responsibilities'])): ?>
                                                <div class="row mb-3">
                                                    <div class="col-12">
                                                        <strong>Responsibilities:</strong>
                                                        <div class="mt-1"><?php echo nl2br($job['responsibilities']); ?></div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <?php if (!in_array($job['id'], $applied_job_ids)): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-primary" <?php echo $student_status !== 'approved' ? 'disabled' : ''; ?>>
                                                        Apply Now
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-success" disabled>Already Applied</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-briefcase display-1 text-muted"></i>
                        <h3 class="text-muted">No Jobs Available</h3>
                        <p>There are currently no job openings matching your criteria. Please try different filters.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>