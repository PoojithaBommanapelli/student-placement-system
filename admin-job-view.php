<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get job ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Job ID not specified';
    header("Location: admin-jobs.php");
    exit();
}

$job_id = intval($_GET['id']);

// Validate job ID
if ($job_id <= 0) {
    $_SESSION['error'] = 'Invalid Job ID';
    header("Location: admin-jobs.php");
    exit();
}

// Get job details with company information
$job_query = "SELECT j.*, c.name as company_name, c.logo as company_logo, c.location as company_location,
                     c.industry_type, c.website, c.contact_email, c.phone, c.status as company_status
              FROM jobs j 
              JOIN companies c ON j.company_id = c.id 
              WHERE j.id = ?";
              
$stmt = $conn->prepare($job_query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Job not found';
    header("Location: admin-jobs.php");
    exit();
}

$job = $result->fetch_assoc();
$stmt->close();

// Handle status change actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'activate' || $action === 'deactivate') {
        $new_status = $action === 'activate' ? 'active' : 'inactive';
        $update_query = "UPDATE jobs SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $job_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Job {$action}d successfully";
            // Refresh the job data
            $stmt = $conn->prepare($job_query);
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $job = $result->fetch_assoc();
        } else {
            $_SESSION['error'] = "Error {$action}ing job";
        }
        $stmt->close();
        
        // Redirect to avoid form resubmission
        header("Location: admin-job-view.php?id=" . $job_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Job - Placement Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .company-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #f8f9fa;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .job-detail {
            margin-bottom: 1rem;
        }
        .job-detail-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        .job-detail-value {
            font-size: 1.1rem;
        }
        .skill-badge {
            margin: 0.2rem;
            padding: 0.35em 0.65em;
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
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Job Details</h1>
                    <div>
                        <a href="admin-jobs.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Jobs
                        </a>
                        <?php if ($job['status'] == 'active'): ?>
                            <a href="admin-job-view.php?id=<?php echo $job_id; ?>&action=deactivate" class="btn btn-warning">
                                <i class="bi bi-pause me-1"></i> Deactivate
                            </a>
                        <?php else: ?>
                            <a href="admin-job-view.php?id=<?php echo $job_id; ?>&action=activate" class="btn btn-success">
                                <i class="bi bi-play me-1"></i> Activate
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Job Information</h5>
                                <span class="badge bg-<?php echo $job['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($job['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h4 class="text-primary"><?php echo htmlspecialchars($job['title']); ?></h4>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6 job-detail">
                                        <div class="job-detail-label">Department</div>
                                        <div class="job-detail-value"><?php echo !empty($job['department']) ? htmlspecialchars($job['department']) : 'Not specified'; ?></div>
                                    </div>
                                    <div class="col-md-6 job-detail">
                                        <div class="job-detail-label">Role</div>
                                        <div class="job-detail-value"><?php echo !empty($job['role']) ? htmlspecialchars($job['role']) : 'Not specified'; ?></div>
                                    </div>
                                    <div class="col-md-6 job-detail">
                                        <div class="job-detail-label">Vacancies</div>
                                        <div class="job-detail-value"><?php echo $job['vacancies']; ?></div>
                                    </div>
                                    <div class="col-md-6 job-detail">
                                        <div class="job-detail-label">Work Mode</div>
                                        <div class="job-detail-value text-capitalize"><?php echo htmlspecialchars($job['work_mode']); ?></div>
                                    </div>
                                    <div class="col-md-6 job-detail">
                                        <div class="job-detail-label">Employment Type</div>
                                        <div class="job-detail-value text-capitalize"><?php echo htmlspecialchars($job['employment_type']); ?></div>
                                    </div>
                                    <div class="col-md-6 job-detail">
                                        <div class="job-detail-label">Salary/Stipend</div>
                                        <div class="job-detail-value"><?php echo !empty($job['salary']) ? htmlspecialchars($job['salary']) : 'Not specified'; ?></div>
                                    </div>
                                    <div class="col-md-6 job-detail">
                                        <div class="job-detail-label">Posted On</div>
                                        <div class="job-detail-value"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($job['education'])): ?>
                                <div class="job-detail mt-4">
                                    <div class="job-detail-label">Education Requirements</div>
                                    <div class="job-detail-value"><?php echo htmlspecialchars($job['education']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['must_have_skills'])): ?>
                                <div class="job-detail mt-4">
                                    <div class="job-detail-label">Must-Have Skills</div>
                                    <div class="job-detail-value">
                                        <?php 
                                        $skills = explode(',', $job['must_have_skills']);
                                        foreach ($skills as $skill): 
                                            if (!empty(trim($skill))): ?>
                                                <span class="badge bg-primary skill-badge"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                            <?php endif;
                                        endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['responsibilities'])): ?>
                                <div class="job-detail mt-4">
                                    <div class="job-detail-label">Responsibilities</div>
                                    <div class="job-detail-value"><?php echo nl2br(htmlspecialchars($job['responsibilities'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body text-center">
                                <img src="../uploads/companies/logos/<?php echo !empty($job['company_logo']) ? htmlspecialchars($job['company_logo']) : 'default.png'; ?>" 
                                     alt="Company Logo" class="company-logo mb-3"
                                     onerror="this.src='../uploads/companies/logos/default.png'">
                                <h5><?php echo htmlspecialchars($job['company_name']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($job['industry_type']); ?></p>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    <?php echo htmlspecialchars($job['company_location']); ?>
                                </p>
                                <p class="mb-3">
                                    <span class="badge bg-<?php echo $job['company_status'] == 'approved' ? 'success' : ($job['company_status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($job['company_status']); ?>
                                    </span>
                                </p>
                                
                                <div class="d-grid gap-2">
                                    <a href="../company/company-profile.php?company_id=<?php echo $job['company_id']; ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-building me-1"></i> View Company Profile
                                    </a>
                                    <a href="admin-companies.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-list me-1"></i> All Companies
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Company Contact</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($job['contact_email'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-envelope me-2 text-primary"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($job['contact_email']); ?>">
                                            <?php echo htmlspecialchars($job['contact_email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['phone'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-telephone me-2 text-primary"></i>
                                        <a href="tel:<?php echo htmlspecialchars($job['phone']); ?>">
                                            <?php echo htmlspecialchars($job['phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['website'])): ?>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-globe me-2 text-primary"></i>
                                        <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank">
                                            Visit Website
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card shadow-sm mt-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="admin-jobs.php?company=<?php echo $job['company_id']; ?>" class="btn btn-outline-info">
                                        <i class="bi bi-briefcase me-1"></i> View All Jobs from This Company
                                    </a>
                                    <?php if ($job['status'] == 'active'): ?>
                                        <a href="admin-job-view.php?id=<?php echo $job_id; ?>&action=deactivate" class="btn btn-outline-warning">
                                            <i class="bi bi-pause me-1"></i> Deactivate Job
                                        </a>
                                    <?php else: ?>
                                        <a href="admin-job-view.php?id=<?php echo $job_id; ?>&action=activate" class="btn btn-outline-success">
                                            <i class="bi bi-play me-1"></i> Activate Job
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add error handling for images
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                img.onerror = function() {
                    this.src = '../uploads/companies/logos/default.png';
                };
            });
        });
    </script>
</body>
</html>