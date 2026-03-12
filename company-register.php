<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $branch = trim($_POST['branch']);
    $location = trim($_POST['location']);
    $industry_type = trim($_POST['industry_type']);
    $about = trim($_POST['about']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($name) || empty($location) || empty($industry_type) || 
        empty($email) || empty($password)) {
        $error = 'All required fields must be filled';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM companies WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert company
            $insert_query = "INSERT INTO companies (name, branch, location, industry_type, about, email, password) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("sssssss", $name, $branch, $location, $industry_type, $about, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! Please wait for admin approval.';
                
                // Send approval pending email (placeholder)
                // sendEmail($email, 'Registration Pending Approval', 'company_approval_pending.html');
                
                // Clear form
                $_POST = array();
            } else {
                $error = 'Registration failed. Please try again.';
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
    <title>Company Registration - Placement Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-building me-2"></i>Company Registration</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label">Company Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $_POST['name'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Please enter your company name</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="branch" class="form-label">Branch/Division</label>
                                    <input type="text" class="form-control" id="branch" name="branch" 
                                           value="<?php echo $_POST['branch'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Location *</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo $_POST['location'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Please enter your company location</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="industry_type" class="form-label">Industry Type *</label>
                                <select class="form-select" id="industry_type" name="industry_type" required>
                                    <option value="">Select Industry Type</option>
                                    <option value="IT Services" <?php echo (isset($_POST['industry_type']) && $_POST['industry_type'] == 'IT Services') ? 'selected' : ''; ?>>IT Services</option>
                                    <option value="Software Development" <?php echo (isset($_POST['industry_type']) && $_POST['industry_type'] == 'Software Development') ? 'selected' : ''; ?>>Software Development</option>
                                    <option value="Finance" <?php echo (isset($_POST['industry_type']) && $_POST['industry_type'] == 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                    <option value="Healthcare" <?php echo (isset($_POST['industry_type']) && $_POST['industry_type'] == 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                    <option value="Manufacturing" <?php echo (isset($_POST['industry_type']) && $_POST['industry_type'] == 'Manufacturing') ? 'selected' : ''; ?>>Manufacturing</option>
                                    <option value="Education" <?php echo (isset($_POST['industry_type']) && $_POST['industry_type'] == 'Education') ? 'selected' : ''; ?>>Education</option>
                                    <option value="Retail" <?php echo (isset($_POST['industry_type']) && $_POST['industry_type'] == 'Retail') ? 'selected' : ''; ?>>Retail</option>
                                    <option value="Other" <?php echo (isset($_POST['industry_type']) && $_POST['industry_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div class="invalid-feedback">Please select an industry type</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="about" class="form-label">About Company</label>
                                <textarea class="form-control" id="about" name="about" rows="3"><?php echo $_POST['about'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="invalid-feedback">Please enter a password</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback">Please confirm your password</div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#">terms and conditions</a>
                                </label>
                                <div class="invalid-feedback">You must agree before submitting</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2">Register</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="company-login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
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
