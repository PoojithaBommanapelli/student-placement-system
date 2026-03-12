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

// Initialize variables
$is_viewing_other_company = false;
$company_id = null;
$company = null;

// Check if we're viewing a specific company profile
if (isset($_GET['company_id']) && (isAdmin() || isCompany())) {
    $profile_company_id = intval($_GET['company_id']);
    
    // Verify the company exists
    $company_check = "SELECT * FROM companies WHERE id = ?";
    $stmt = $conn->prepare($company_check);
    
    if (!$stmt) {
        die("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $profile_company_id);
    $stmt->execute();
    $company_result = $stmt->get_result();
    
    if ($company_result->num_rows === 1) {
        $company = $company_result->fetch_assoc();
        $is_viewing_other_company = true;
        $company_id = $profile_company_id;
    } else {
        // Company not found, redirect back
        if (isAdmin()) {
            header("Location: ../admin/admin-companies.php");
        } else {
            header("Location: company-profile.php");
        }
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
        $company_id = $_SESSION['user_id'];
        $is_viewing_other_company = false;
    } else {
        // Admin viewing their own profile? Redirect to companies list
        header("Location: ../admin/admin-companies.php");
        exit();
    }
}

$error = '';
$success = '';

// Get company details with job count
if ($company_id) {
    $company_query = "SELECT c.*, COUNT(j.id) as total_jobs 
                      FROM companies c 
                      LEFT JOIN jobs j ON c.id = j.company_id 
                      WHERE c.id = ? 
                      GROUP BY c.id";
    $stmt = $conn->prepare($company_query);
    
    if (!$stmt) {
        die("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $company = $result->fetch_assoc();
    } else {
        die("Company not found");
    }

    // Get recent jobs
    $jobs_query = "SELECT * FROM jobs WHERE company_id = ? ORDER BY created_at DESC LIMIT 5";
    $stmt_jobs = $conn->prepare($jobs_query);
    
    if (!$stmt_jobs) {
        die("Database prepare error: " . $conn->error);
    }
    
    $stmt_jobs->bind_param("i", $company_id);
    $stmt_jobs->execute();
    $recent_jobs = $stmt_jobs->get_result();
}

// Handle form submission (only if editing own profile)
if (!$is_viewing_other_company && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate required fields first
    $required_fields = ['name', 'location', 'industry_type'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $error = 'Required fields must be filled: ' . implode(', ', $missing_fields);
    } else {
        $name = trim($_POST['name']);
        $branch = trim($_POST['branch'] ?? '');
        $location = trim($_POST['location']);
        $industry_type = trim($_POST['industry_type']);
        $about = trim($_POST['about'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $linkedin = trim($_POST['linkedin'] ?? '');
        
        // Handle logo upload
        $logo_path = $company['logo']; // Keep existing logo by default
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $logo = $_FILES['logo'];
            $logo_name = time() . '_' . basename($logo['name']);
            $target_dir = "../uploads/companies/logos/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                if (!mkdir($target_dir, 0777, true)) {
                    $error = "Failed to create upload directory.";
                }
            }
            
            if (empty($error)) {
                $target_file = $target_dir . $logo_name;
                
                // Check if image file is an actual image
                $check = getimagesize($logo["tmp_name"]);
                if ($check !== false) {
                    // Check file size (max 2MB)
                    if ($logo["size"] > 2000000) {
                        $error = "Sorry, your file is too large. Maximum size is 2MB.";
                    } else {
                        // Allow certain file formats
                        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                        } else {
                            // Try to upload file
                            if (move_uploaded_file($logo["tmp_name"], $target_file)) {
                                $logo_path = $logo_name;
                                
                                // Delete old logo if it exists and is not the default
                                if (!empty($company['logo']) && $company['logo'] != 'default.png') {
                                    $old_logo = $target_dir . $company['logo'];
                                    if (file_exists($old_logo)) {
                                        unlink($old_logo);
                                    }
                                }
                            } else {
                                $error = "Sorry, there was an error uploading your file.";
                            }
                        }
                    }
                } else {
                    $error = "File is not an image.";
                }
            }
        }
        
        if (empty($error)) {
            // Check if the new columns exist, if not update without them
            $columns_check = $conn->query("SHOW COLUMNS FROM companies LIKE 'website'");
            $has_new_columns = $columns_check->num_rows > 0;
            
            if ($has_new_columns) {
                // Update company profile with all columns
                $update_query = "UPDATE companies SET name = ?, branch = ?, location = ?, 
                                industry_type = ?, about = ?, logo = ?, website = ?, 
                                contact_email = ?, phone = ?, linkedin = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                
                if (!$stmt) {
                    $error = "Database prepare error: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssssssssi", $name, $branch, $location, $industry_type, 
                                     $about, $logo_path, $website, $contact_email, $phone, $linkedin, $company_id);
                }
            } else {
                // Update company profile with basic columns only
                $update_query = "UPDATE companies SET name = ?, branch = ?, location = ?, 
                                industry_type = ?, about = ?, logo = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                
                if (!$stmt) {
                    $error = "Database prepare error: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssssi", $name, $branch, $location, $industry_type, 
                                     $about, $logo_path, $company_id);
                }
            }
            
            if ($stmt && $stmt->execute()) {
                $success = 'Profile updated successfully';
                
                // Refresh company data
                $stmt_refresh = $conn->prepare($company_query);
                if ($stmt_refresh) {
                    $stmt_refresh->bind_param("i", $company_id);
                    $stmt_refresh->execute();
                    $company = $stmt_refresh->get_result()->fetch_assoc();
                    
                    // Update session name if changed
                    $_SESSION['name'] = $company['name'];
                }
            } else {
                $error = 'Error updating profile: ' . ($stmt ? $stmt->error : $conn->error);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_viewing_other_company ? 'View Company' : 'Company Profile'; ?> - Placement Portal</title>
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

/* Company Logo */
.company-logo {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Social Icons */
.social-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    text-decoration: none;
    margin: 0 0.25rem;
    transition: transform 0.2s ease;
}
.social-icon:hover {
    transform: scale(1.1);
}

/* Feature Badge */
.feature-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Sidebar */
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
.sidebar .nav-link:hover, 
.sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.15);
    color: white !important;
    transform: translateX(5px);
}
.sidebar .nav-link i {
    width: 24px;
    text-align: center;
}

/* Cards */
.card {
    border: none;
    border-radius: 0.75rem;
    box-shadow: var(--card-shadow);
    transition: transform 0.3s ease;
}
.card:hover {
    transform: translateY(-2px);
}

/* Stats Card */
.stats-card {
    background: linear-gradient(120deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: transform 0.2s ease;
}
.stats-card:hover {
    transform: translateY(-5px);
}

/* Forms */
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

/* Buttons */
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

/* Alerts */
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
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar - Only show if viewing own profile -->
            <?php if (!$is_viewing_other_company): ?>
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
                            <a class="nav-link active" href="company-profile.php">
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
            <?php endif; ?>
            
            <!-- Main content -->
            <main class="<?php echo $is_viewing_other_company ? 'col-12' : 'col-md-9 ms-sm-auto col-lg-10'; ?> px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $is_viewing_other_company ? 'View Company Profile' : 'Company Profile'; ?></h1>
                    <?php if (isset($company['status'])): ?>
                    <span class="badge bg-<?php echo $company['status'] == 'approved' ? 'success' : ($company['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                        <?php echo ucfirst($company['status']); ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($company): ?>
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card bg-primary text-white mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Jobs Posted</h6>
                                        <h3 class="card-text"><?php echo isset($company['total_jobs']) ? $company['total_jobs'] : 0; ?></h3>
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
                                        <h3 class="card-text">
                                            <?php 
                                            $active_jobs_query = "SELECT COUNT(*) as active_jobs FROM jobs WHERE company_id = ? AND status = 'active'";
                                            $stmt_active = $conn->prepare($active_jobs_query);
                                            if ($stmt_active) {
                                                $stmt_active->bind_param("i", $company_id);
                                                $stmt_active->execute();
                                                $active_result = $stmt_active->get_result()->fetch_assoc();
                                                echo $active_result['active_jobs'];
                                            } else {
                                                echo "0";
                                            }
                                            ?>
                                        </h3>
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
                                        <h6 class="card-title">Member Since</h6>
                                        <h5 class="card-text"><?php echo isset($company['created_at']) ? date('M Y', strtotime($company['created_at'])) : 'N/A'; ?></h5>
                                    </div>
                                    <i class="bi bi-calendar fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-4">
                        <!-- Company Card -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-body text-center">
                                <img src="../uploads/companies/logos/<?php echo !empty($company['logo']) ? htmlspecialchars($company['logo']) : 'default.png'; ?>" 
                                     alt="Company Logo" class="company-logo mb-3" id="logoPreview"
                                     onerror="this.src='../uploads/companies/logos/default.png';">
                                <h4><?php echo htmlspecialchars($company['name'] ?? 'N/A'); ?></h4>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($company['industry_type'] ?? 'N/A'); ?></p>
                                <p class="text-muted mb-3">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    <?php echo htmlspecialchars($company['location'] ?? 'N/A'); ?>
                                    <?php if (!empty($company['branch'])): ?>
                                        <br><small><?php echo htmlspecialchars($company['branch']); ?></small>
                                    <?php endif; ?>
                                </p>
                                
                                <!-- Social Links -->
                                <div class="d-flex justify-content-center mb-3">
                                    <?php 
                                    // Check if new columns exist
                                    $website = isset($company['website']) ? $company['website'] : '';
                                    $linkedin = isset($company['linkedin']) ? $company['linkedin'] : '';
                                    $contact_email = isset($company['contact_email']) ? $company['contact_email'] : (isset($company['email']) ? $company['email'] : '');
                                    ?>
                                    <?php if (!empty($website)): ?>
                                        <a href="<?php echo htmlspecialchars($website); ?>" target="_blank" class="social-icon bg-primary text-white">
                                            <i class="bi bi-globe"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($linkedin)): ?>
                                        <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" class="social-icon bg-info text-white">
                                            <i class="bi bi-linkedin"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($contact_email)): ?>
                                        
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-grid">
                                    
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Jobs -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Recent Jobs</h6>
                            </div>
                            <div class="card-body">
                                <?php if (isset($recent_jobs) && $recent_jobs->num_rows > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php while ($job = $recent_jobs->fetch_assoc()): ?>
                                            <div class="list-group-item px-0 py-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                                        <span class="badge bg-<?php echo $job['status'] == 'active' ? 'success' : 'secondary'; ?> feature-badge">
                                                            <?php echo ucfirst($job['status']); ?>
                                                        </span>
                                                        <small class="text-muted d-block"><?php echo date('M d, Y', strtotime($job['created_at'])); ?></small>
                                                    </div>
                                                    <span class="badge bg-primary"><?php echo $job['vacancies']; ?> positions</span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No jobs posted yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <!-- Company Profile Form/View -->
                        <div class="card shadow">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo $is_viewing_other_company ? 'Company Information' : 'Edit Company Profile'; ?></h5>
                                <?php if ($is_viewing_other_company && isAdmin()): ?>
                                    <a href="../admin/admin-companies.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-1"></i> Back to Companies
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body <?php echo $is_viewing_other_company ? 'view-mode' : ''; ?>">
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . ($is_viewing_other_company ? '?company_id=' . $company_id : ''); ?>" 
                                      enctype="multipart/form-data" class="needs-validation" novalidate>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="logo" class="form-label">Company Logo</label>
                                            <?php if ($is_viewing_other_company): ?>
                                                <div class="form-control">
                                                    <a href="../uploads/companies/logos/<?php echo !empty($company['logo']) ? htmlspecialchars($company['logo']) : 'default.png'; ?>" 
                                                       target="_blank" class="text-decoration-none">
                                                        View Current Logo
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <input class="form-control" type="file" id="logo" name="logo" accept="image/*">
                                                <div class="form-text">Recommended: 200x200px, Max 2MB (JPG, PNG, GIF)</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Company Name *</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($company['name'] ?? ''); ?>" 
                                                   <?php echo $is_viewing_other_company ? 'readonly' : 'required'; ?>>
                                            <?php if (!$is_viewing_other_company): ?>
                                                <div class="invalid-feedback">Please enter your company name</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="branch" class="form-label">Branch/Division</label>
                                            <input type="text" class="form-control" id="branch" name="branch" 
                                                   value="<?php echo htmlspecialchars($company['branch'] ?? ''); ?>" 
                                                   placeholder="e.g., Headquarters, India Office" 
                                                   <?php echo $is_viewing_other_company ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="location" class="form-label">Location *</label>
                                            <input type="text" class="form-control" id="location" name="location" 
                                                   value="<?php echo htmlspecialchars($company['location'] ?? ''); ?>" 
                                                   placeholder="e.g., Bangalore, India" 
                                                   <?php echo $is_viewing_other_company ? 'readonly' : 'required'; ?>>
                                            <?php if (!$is_viewing_other_company): ?>
                                                <div class="invalid-feedback">Please enter your company location</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="industry_type" class="form-label">Industry Type *</label>
                                        <?php if ($is_viewing_other_company): ?>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($company['industry_type'] ?? ''); ?>" readonly>
                                        <?php else: ?>
                                            <select class="form-select" id="industry_type" name="industry_type" required>
                                                <option value="">Select Industry Type</option>
                                                <option value="IT Services" <?php echo ($company['industry_type'] ?? '') == 'IT Services' ? 'selected' : ''; ?>>IT Services</option>
                                                <option value="Software Development" <?php echo ($company['industry_type'] ?? '') == 'Software Development' ? 'selected' : ''; ?>>Software Development</option>
                                                <option value="Finance" <?php echo ($company['industry_type'] ?? '') == 'Finance' ? 'selected' : ''; ?>>Finance & Banking</option>
                                                <option value="Healthcare" <?php echo ($company['industry_type'] ?? '') == 'Healthcare' ? 'selected' : ''; ?>>Healthcare</option>
                                                <option value="Manufacturing" <?php echo ($company['industry_type'] ?? '') == 'Manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
                                                <option value="Education" <?php echo ($company['industry_type'] ?? '') == 'Education' ? 'selected' : ''; ?>>Education</option>
                                                <option value="E-commerce" <?php echo ($company['industry_type'] ?? '') == 'E-commerce' ? 'selected' : ''; ?>>E-commerce & Retail</option>
                                                <option value="Telecommunications" <?php echo ($company['industry_type'] ?? '') == 'Telecommunications' ? 'selected' : ''; ?>>Telecommunications</option>
                                                <option value="Consulting" <?php echo ($company['industry_type'] ?? '') == 'Consulting' ? 'selected' : ''; ?>>Consulting</option>
                                                <option value="Other" <?php echo ($company['industry_type'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                            <div class="invalid-feedback">Please select an industry type</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="about" class="form-label">About Company</label>
                                        <textarea class="form-control" id="about" name="about" rows="4" 
                                                  placeholder="Describe your company, mission, values, and what makes you unique..." 
                                                  <?php echo $is_viewing_other_company ? 'readonly' : ''; ?>><?php echo htmlspecialchars($company['about'] ?? ''); ?></textarea>
                                        <?php if (!$is_viewing_other_company): ?>
                                            <div class="form-text">This will be displayed on your public profile</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="website" class="form-label">Website</label>
                                            <input type="url" class="form-control" id="website" name="website" 
                                                   value="<?php echo htmlspecialchars(isset($company['website']) ? $company['website'] : ''); ?>" 
                                                   placeholder="https://example.com" 
                                                   <?php echo $is_viewing_other_company ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="contact_email" class="form-label">Contact Email</label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                   value="<?php echo htmlspecialchars(isset($company['contact_email']) ? $company['contact_email'] : (isset($company['email']) ? $company['email'] : '')); ?>" 
                                                   placeholder="contact@company.com" 
                                                   <?php echo $is_viewing_other_company ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars(isset($company['phone']) ? $company['phone'] : ''); ?>" 
                                                   placeholder="+91 1234567890" 
                                                   <?php echo $is_viewing_other_company ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="linkedin" class="form-label">LinkedIn</label>
                                            <input type="url" class="form-control" id="linkedin" name="linkedin" 
                                                   value="<?php echo htmlspecialchars(isset($company['linkedin']) ? $company['linkedin'] : ''); ?>" 
                                                   placeholder="https://linkedin.com/company/your-company" 
                                                   <?php echo $is_viewing_other_company ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    
                                    <?php if (!$is_viewing_other_company): ?>
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="reset" class="btn btn-outline-secondary">Reset</button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle me-1"></i>Update Profile
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Company information not found. Please contact administrator.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.needs-validation');
            if (form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
                
                // Logo preview
                const logoInput = document.getElementById('logo');
                if (logoInput) {
                    logoInput.addEventListener('change', function() {
                        const file = this.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const logoPreview = document.getElementById('logoPreview');
                                if (logoPreview) {
                                    logoPreview.src = e.target.result;
                                }
                            }
                            reader.readAsDataURL(file);
                        }
                    });
                }
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>