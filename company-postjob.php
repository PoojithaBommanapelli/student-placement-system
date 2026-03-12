<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not a company
if (!isLoggedIn() || !isCompany()) {
    header("Location: ../index.html");
    exit();
}

$company_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if company is approved
$company_query = "SELECT status FROM companies WHERE id = ?";
$stmt = $conn->prepare($company_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company_status = $stmt->get_result()->fetch_assoc()['status'];

if ($company_status !== 'approved') {
    $error = 'Your account is pending approval. You can post jobs once approved by admin.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $company_status === 'approved') {
    $title = trim($_POST['title']);
    $vacancies = intval($_POST['vacancies']);
    $salary = trim($_POST['salary']);
    $work_mode = $_POST['work_mode'];
    $must_have_skills = trim($_POST['must_have_skills']);
    $responsibilities = trim($_POST['responsibilities']);
    $education = trim($_POST['education']);
    $department = trim($_POST['department']);
    $role = trim($_POST['role']);
    $employment_type = $_POST['employment_type'];
    $location = trim($_POST['location']);
    // Validate inputs
    if (empty($title) || empty($vacancies) || empty($work_mode) || empty($employment_type) || empty($location)) {
    $error = 'Required fields must be filled';
    } elseif ($vacancies < 1) {
        $error = 'Vacancies must be at least 1';
    } else {
        // Insert job
        $insert_query = "INSERT INTO jobs (company_id, title, vacancies, salary, work_mode, location,
                     must_have_skills, responsibilities, education, department, role, employment_type) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isisssssssss", $company_id, $title, $vacancies, $salary, $work_mode, $location,
                 $must_have_skills, $responsibilities, $education, $department, $role, $employment_type);

        
        if ($stmt->execute()) {
            $success = 'Job posted successfully!';
            
            // Clear form
            $_POST = array();
        } else {
            $error = 'Error posting job. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job - Placement Portal</title>
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
                                <i class="bi bi-person me-2"></i>
                                Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="company-postjob.php">
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
                    <h1 class="h2">Post New Job</h1>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Job Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo $_POST['title'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Please enter a job title</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="vacancies" class="form-label">Number of Vacancies *</label>
                                    <input type="number" class="form-control" id="vacancies" name="vacancies" 
                                           value="<?php echo $_POST['vacancies'] ?? ''; ?>" min="1" required>
                                    <div class="invalid-feedback">Please enter number of vacancies</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department" 
                                           value="<?php echo $_POST['department'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <input type="text" class="form-control" id="role" name="role" 
                                           value="<?php echo $_POST['role'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="work_mode" class="form-label">Work Mode *</label>
                                    <select class="form-select" id="work_mode" name="work_mode" required>
                                        <option value="">Select Work Mode</option>
                                        <option value="on-site" <?php echo (isset($_POST['work_mode']) && $_POST['work_mode'] == 'on-site') ? 'selected' : ''; ?>>On-Site</option>
                                        <option value="remote" <?php echo (isset($_POST['work_mode']) && $_POST['work_mode'] == 'remote') ? 'selected' : ''; ?>>Remote</option>
                                        <option value="hybrid" <?php echo (isset($_POST['work_mode']) && $_POST['work_mode'] == 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                                    </select>
                                    <div class="invalid-feedback">Please select work mode</div>
                                </div>
                                <div class="col-md-4 mb-3">
    <label for="location" class="form-label">Workplace Location *</label>
    <input type="text" class="form-control" id="location" name="location" 
           value="<?php echo $_POST['location'] ?? ''; ?>" 
           placeholder="e.g., Bangalore, India" required>
    <div class="invalid-feedback">Please enter workplace location</div>
</div>
                                <div class="col-md-4 mb-3">
                                    <label for="employment_type" class="form-label">Employment Type *</label>
                                    <select class="form-select" id="employment_type" name="employment_type" required>
                                        <option value="">Select Employment Type</option>
                                        <option value="full-time" <?php echo (isset($_POST['employment_type']) && $_POST['employment_type'] == 'full-time') ? 'selected' : ''; ?>>Full Time</option>
                                        <option value="part-time" <?php echo (isset($_POST['employment_type']) && $_POST['employment_type'] == 'part-time') ? 'selected' : ''; ?>>Part Time</option>
                                        <option value="internship" <?php echo (isset($_POST['employment_type']) && $_POST['employment_type'] == 'internship') ? 'selected' : ''; ?>>Internship</option>
                                    </select>
                                    <div class="invalid-feedback">Please select employment type</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="salary" class="form-label">Salary/Stipend</label>
                                    <input type="text" class="form-control" id="salary" name="salary" 
                                           value="<?php echo $_POST['salary'] ?? ''; ?>" placeholder="e.g., ₹5-8 LPA or ₹25,000/month">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="education" class="form-label">Education Requirements</label>
                                <input type="text" class="form-control" id="education" name="education" 
                                       value="<?php echo $_POST['education'] ?? ''; ?>" placeholder="e.g., B.Tech in Computer Science">
                            </div>
                            
                            <div class="mb-3">
                                <label for="must_have_skills" class="form-label">Must-Have Skills</label>
                                <textarea class="form-control" id="must_have_skills" name="must_have_skills" rows="2" 
                                          placeholder="Enter comma-separated skills"><?php echo $_POST['must_have_skills'] ?? ''; ?></textarea>
                                <div class="form-text">Separate skills with commas (e.g., Java, Python, SQL)</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="responsibilities" class="form-label">Responsibilities</label>
                                <textarea class="form-control" id="responsibilities" name="responsibilities" rows="4"><?php echo $_POST['responsibilities'] ?? ''; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" <?php echo $company_status !== 'approved' ? 'disabled' : ''; ?>>Post Job</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.needs-validation');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html>