<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not a student
if (!isLoggedIn() || !isStudent()) {
    header("Location: ../index.html");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student details
$student_query = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get student skills
$skills_query = "SELECT id, skill FROM student_skills WHERE student_id = ?";
$stmt = $conn->prepare($skills_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$skills_result = $stmt->get_result();
$skills = [];
while ($row = $skills_result->fetch_assoc()) {
    $skills[] = $row;
}

// Get student education
$education_query = "SELECT * FROM student_education WHERE student_id = ? ORDER BY end_date DESC";
$stmt = $conn->prepare($education_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$education = $stmt->get_result();

// Get field of study
$field_of_study = '';
$education->data_seek(0);
if ($edu = $education->fetch_assoc()) {
    $field_of_study = $edu['field_of_study'] ?? '';
}

// Include the categorizeSkills function
// You can either include it here or create a separate functions file
function categorizeSkills($skills, $field_of_study = '') {
    // ... (paste the full categorizeSkills function here)
}

// Categorize skills
$categorized_skills = categorizeSkills($skills, $field_of_study);

// Handle skill deletion
if (isset($_GET['delete_skill'])) {
    $skill_id = $_GET['delete_skill'];
    $delete_query = "DELETE FROM student_skills WHERE id = ? AND student_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $skill_id, $student_id);
    if ($stmt->execute()) {
        header("Location: student-skills.php?success=Skill deleted successfully");
        exit();
    } else {
        $error = "Error deleting skill: " . $conn->error;
    }
}

// Handle skill addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_skill'])) {
    $skill = $_POST['skill'];
    
    $insert_query = "INSERT INTO student_skills (student_id, skill) VALUES (?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("is", $student_id, $skill);
    if ($stmt->execute()) {
        header("Location: student-skills.php?success=Skill added successfully");
        exit();
    } else {
        $error = "Error saving skill: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills - Placement Portal</title>
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
                            <a class="nav-link" href="student-jobs.php">
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
                    <h1 class="h2">Skills Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                            <i class="bi bi-plus me-1"></i> Add Skill
                        </button>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?php echo $_GET['success']; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Skills Section with Field-Specific Categorization -->

                <!-- All Skills List with Delete Option -->
                <div class="card shadow mt-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Manage Your Skills</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($skills)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Skill</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($skills as $skill): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($skill['skill']); ?></td>
                                                <td>
                                                    <a href="student-skills.php?delete_skill=<?php echo $skill['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this skill?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No skills added yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Skill Modal -->
    <div class="modal fade" id="addSkillModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Skill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="skill" class="form-label">Skill</label>
                            <input type="text" class="form-control" id="skill" name="skill" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_skill">Save Skill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>