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
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $father_name = trim($_POST['father_name']);
    $mother_name = trim($_POST['mother_name']);
    $hall_ticket = trim($_POST['hall_ticket']);
    $email = trim($_POST['email']);
    $branch = $_POST['branch'];
    $year = $_POST['year'];
    $semester = $_POST['semester'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($hall_ticket) || empty($email) || 
        empty($branch) || empty($year) || empty($semester) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        // Check if email or hall ticket already exists
        $check_query = "SELECT id FROM students WHERE email = ? OR hall_ticket = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ss", $email, $hall_ticket);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email or Hall Ticket already exists';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert student
            $insert_query = "INSERT INTO students (first_name, last_name, father_name, mother_name, 
                            hall_ticket, email, branch, year, semester, password) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("sssssssiis", $first_name, $last_name, $father_name, $mother_name, 
                             $hall_ticket, $email, $branch, $year, $semester, $hashed_password);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! Please wait for admin approval.';
                
                // Send approval pending email (placeholder)
                // sendEmail($email, 'Registration Pending Approval', 'student_approval_pending.html');
                
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
    <title>Student Registration - Placement Portal</title>
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
                        <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Student Registration</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Please enter your first name</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
                                    <div class="invalid-feedback">Please enter your last name</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="father_name" class="form-label">Father's Name</label>
                                    <input type="text" class="form-control" id="father_name" name="father_name" 
                                           value="<?php echo $_POST['father_name'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mother_name" class="form-label">Mother's Name</label>
                                    <input type="text" class="form-control" id="mother_name" name="mother_name" 
                                           value="<?php echo $_POST['mother_name'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="hall_ticket" class="form-label">Hall Ticket Number *</label>
                                <input type="text" class="form-control" id="hall_ticket" name="hall_ticket" 
                                       value="<?php echo $_POST['hall_ticket'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Please enter your hall ticket number</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="branch" class="form-label">Branch *</label>
                                    <select class="form-select" id="branch" name="branch" required>
                                        <option value="">Select Branch</option>
                                        <option value="CSE" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'CSE') ? 'selected' : ''; ?>>Computer Science</option>
                                        <option value="ECE" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'ECE') ? 'selected' : ''; ?>>Electronics</option>
                                        <option value="ME" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'ME') ? 'selected' : ''; ?>>Mechanical</option>
                                        <option value="CE" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'CE') ? 'selected' : ''; ?>>Civil</option>
                                        <option value="EEE" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'EEE') ? 'selected' : ''; ?>>Electrical</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your branch</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="year" class="form-label">Year *</label>
                                    <select class="form-select" id="year" name="year" required>
                                        <option value="">Select Year</option>
                                        <option value="1" <?php echo (isset($_POST['year']) && $_POST['year'] == '1') ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2" <?php echo (isset($_POST['year']) && $_POST['year'] == '2') ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3" <?php echo (isset($_POST['year']) && $_POST['year'] == '3') ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4" <?php echo (isset($_POST['year']) && $_POST['year'] == '4') ? 'selected' : ''; ?>>4th Year</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your year</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="semester" class="form-label">Semester *</label>
                                    <select class="form-select" id="semester" name="semester" required>
                                        <option value="">Select Semester</option>
                                        <option value="1" <?php echo (isset($_POST['semester']) && $_POST['semester'] == '1') ? 'selected' : ''; ?>>1st Semester</option>
                                        <option value="2" <?php echo (isset($_POST['semester']) && $_POST['semester'] == '2') ? 'selected' : ''; ?>>2nd Semester</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your semester</div>
                                </div>
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
                            <p>Already have an account? <a href="student-login.php">Login here</a></p>
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