<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not a student
if (!isLoggedIn() || !isStudent()) {
    header("Location: ../index.html");
    exit();
}

$student_id = $_SESSION['user_id'];
$error = '';

// Function to categorize skills based on field of study
function categorizeSkills($skills, $field_of_study = '') {
    $categories = [
        'Programming Languages' => [],
        'Web Technologies' => [],
        'Database Technologies' => [],
        'Tools & Platforms' => [],
        'Operating Systems' => [],
        'Core Engineering Skills' => [],
        'Specialized Skills' => [],
        'Soft Skills' => []
    ];
    
    // Field-specific skill patterns
    $field_patterns = [
        // Computer Science & IT
        'computer science' => ['algorithms', 'data structures', 'compiler design', 'computer architecture', 'operating systems'],
        'information technology' => ['networking', 'system administration', 'it infrastructure', 'cloud computing', 'virtualization'],
        
        // Electronics & Electrical
        'electronics' => ['embedded systems', 'vlsi', 'digital design', 'analog circuits', 'pcb design', 'arduino', 'raspberry pi'],
        'electrical' => ['power systems', 'control systems', 'signal processing', 'electric machines', 'power electronics'],
        
        // Mechanical & Civil
        'mechanical' => ['cad', 'cam', 'solidworks', 'ansys', 'thermodynamics', 'fluid mechanics', 'finite element analysis'],
        'civil' => ['structural analysis', 'construction management', 'surveying', 'geotechnical engineering', 'transportation engineering'],
        
        // AI & Data Science
        'artificial intelligence' => ['machine learning', 'deep learning', 'neural networks', 'natural language processing', 'computer vision'],
        'data science' => ['data analysis', 'statistics', 'data visualization', 'big data', 'data mining', 'predictive modeling'],
        
        // Emerging Technologies
        'cybersecurity' => ['network security', 'ethical hacking', 'cryptography', 'penetration testing', 'security analysis'],
        'iot' => ['sensor networks', 'wireless communication', 'edge computing', 'iot protocols', 'smart devices'],
        'robotics' => ['robot programming', 'kinematics', 'control theory', 'sensor integration', 'automation systems'],
        
        // Other Fields
        'chemical' => ['process engineering', 'reaction engineering', 'transport phenomena', 'process control'],
        'biotechnology' => ['bioprocessing', 'genetic engineering', 'biochemistry', 'microbiology', 'bioinformatics']
    ];
    
    // General skill patterns (common across all fields)
    $general_patterns = [
        'Programming Languages' => [
            'Python', 'Java', 'C\\+\\+', 'C#', 'C', 'JavaScript', 'TypeScript', 'PHP', 
            'Ruby', 'Swift', 'Kotlin', 'Go', 'Rust', 'Scala', 'R ', 'Perl', 'Dart',
            'Matlab', 'Assembly', 'Fortran'
        ],
        'Web Technologies' => [
            'HTML', 'CSS', 'Javascript', 'Node\\.js', 'React', 'Angular', 'Vue', 'Express', 
            'Django', 'Flask', 'Spring', 'jQuery', 'Bootstrap', 'SASS', 'Less', 'Webpack'
        ],
        'Database Technologies' => [
            'MYSQL', 'SQL Server', 'postgreSQL', 'mongodb', 'Oracle', 'SQLite', 'Redis', 'SQL',
            'cassandra', 'dynamodb', 'mariadb', 'firebase', 'Bigquery'
        ],
        'Tools & Platforms' => [
            'git', 'docker', 'Kubernetes', 'jenkins', 'AWS', 'Azure', 'GCP', 'heroku', 
            'github', 'gitlab', 'jira', 'ansible', 'terraform', 'postman'
        ],
        'Operating Systems' => [
            'Windows', 'Linux', 'Unix', 'MACos', 'Ubuntu', 'centos', 'debian', 'android', 'iOS'
        ],
        'Soft Skills' => [
            'communication', 'Teamwork', 'Leadership', 'Problem solving', 'Time management',
            'Critical thinking', 'Creativity', 'Adaptability', 'Project management'
        ]
    ];
    
    foreach ($skills as $skill) {
        $skill_name = trim($skill['skill']);
        $skill_lower = strtolower($skill_name);
        $categorized = false;
        
        // First, check if skill matches field-specific patterns
        if (!empty($field_of_study)) {
            $field_lower = strtolower($field_of_study);
            foreach ($field_patterns as $field => $patterns) {
                if (strpos($field_lower, $field) !== false) {
                    foreach ($patterns as $pattern) {
                        if (preg_match("/\b$pattern\b/i", $skill_lower)) {
                            $categories['Specialized Skills'][] = $skill_name;
                            $categorized = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        // If not field-specific, check general patterns
        if (!$categorized) {
            foreach ($general_patterns as $category => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match("/\b$pattern\b/i", $skill_lower)) {
                        $categories[$category][] = $skill_name;
                        $categorized = true;
                        break 2;
                    }
                }
            }
        }
        
        // If still not categorized, check for engineering core skills
        if (!$categorized) {
            $engineering_core = ['mathematics', 'physics', 'calculus', 'linear algebra', 'statistics', 
                               'engineering drawing', 'technical writing', 'research methodology'];
            foreach ($engineering_core as $core_skill) {
                if (preg_match("/\b$core_skill\b/i", $skill_lower)) {
                    $categories['Core Engineering Skills'][] = $skill_name;
                    $categorized = true;
                    break;
                }
            }
        }
        
        // If still not categorized, add to Specialized Skills
        if (!$categorized) {
            $categories['Specialized Skills'][] = $skill_name;
        }
    }
    
    // Remove empty categories
    foreach ($categories as $category => $skills_list) {
        if (empty($skills_list)) {
            unset($categories[$category]);
        } else {
            // Remove duplicates and sort
            $categories[$category] = array_unique($skills_list);
            sort($categories[$category]);
        }
    }
    
    return $categories;
}

// Handle personal information update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $bio = $_POST['bio'];
    $address = $_POST['address'];
    $github = $_POST['github'];
    $linkedin = $_POST['linkedin'];
    $field_of_study_personal = $_POST['field_of_study_personal'];
    
    $update_query = "UPDATE students SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ?, address = ?, github = ?, linkedin = ?, field_of_study = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssssssssi", $first_name, $last_name, $email, $phone, $bio, $address, $github, $linkedin, $field_of_study_personal, $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Personal information updated successfully';
        header("Location: student-resume.php");
        exit();
    } else {
        $error = "Error updating personal information: " . $conn->error;
    }
}

// Get student details
$student_query = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Check if student exists
if (!$student) {
    die("Student not found");
}

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

// Get student projects
$projects_query = "SELECT * FROM student_projects WHERE student_id = ? ORDER BY id DESC";
$stmt = $conn->prepare($projects_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$projects = $stmt->get_result();

// Get student languages
$languages_query = "SELECT * FROM student_languages WHERE student_id = ?";
$stmt = $conn->prepare($languages_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$languages = $stmt->get_result();

// Get student experience
$experience_query = "SELECT * FROM student_experience WHERE student_id = ? ORDER BY end_date DESC";
$stmt = $conn->prepare($experience_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$experience = $stmt->get_result();

// Get student achievements
$achievements_query = "SELECT * FROM student_achievements WHERE student_id = ? ORDER BY id DESC";
$stmt = $conn->prepare($achievements_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$achievements = $stmt->get_result();

// Get student certifications
$certifications_query = "SELECT * FROM student_certifications WHERE student_id = ? ORDER BY date DESC";
$stmt = $conn->prepare($certifications_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$certifications = $stmt->get_result();

// Get student's field of study (from education)
$field_of_study = '';
$education->data_seek(0); // Reset pointer
if ($edu = $education->fetch_assoc()) {
    $field_of_study = $edu['field_of_study'] ?? '';
}

// Get categorized skills based on field of study
$categorized_skills = categorizeSkills($skills, $field_of_study);

// Handle form submissions for other sections
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_education'])) {
        $degree = $_POST['degree'];
        $institution = $_POST['institution'];
        $field_of_study = $_POST['field_of_study'];
        $grade = $_POST['grade'];

        // Convert month picker inputs to proper DATE
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] . '-01' : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] . '-01' : null;

        $insert_query = "INSERT INTO student_education 
                         (student_id, degree, institution, start_date, end_date, grade, field_of_study) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("issssss", $student_id, $degree, $institution, $start_date, $end_date, $grade, $field_of_study);

        if ($stmt->execute()) {
            header("Location: student-resume.php");
            exit();
        } else {
            $error = "Error saving education: " . $conn->error;
        }
    }
    
if (isset($_POST['add_skill'])) {
    $skills_input = $_POST['skill'];

    // Split by comma, trim spaces, remove empty values
    $skills = array_filter(array_map('trim', explode(',', $skills_input)));

    $insert_query = "INSERT INTO student_skills (student_id, skill) VALUES (?, ?)";
    $stmt = $conn->prepare($insert_query);

    foreach ($skills as $skill) {
        $stmt->bind_param("is", $student_id, $skill);
        if (!$stmt->execute()) {
            $error = "Error saving skill: " . $conn->error;
            break; // stop if any error occurs
        }
    }

    if (!isset($error)) {
        header("Location: student-resume.php");
        exit();
    }
}

    
    if (isset($_POST['add_project'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $technologies = $_POST['technologies'];
        $duration = $_POST['duration'];
        
        $insert_query = "INSERT INTO student_projects (student_id, title, description, technologies, duration) 
                         VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("issss", $student_id, $title, $description, $technologies, $duration);
        if ($stmt->execute()) {
            header("Location: student-resume.php");
            exit();
        } else {
            $error = "Error saving project: " . $conn->error;
        }
    }
    
    if (isset($_POST['add_language'])) {
        $language = $_POST['language'];
        $proficiency = $_POST['proficiency'];
        
        $insert_query = "INSERT INTO student_languages (student_id, language, proficiency) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iss", $student_id, $language, $proficiency);
        if ($stmt->execute()) {
            header("Location: student-resume.php");
            exit();
        } else {
            $error = "Error saving language: " . $conn->error;
        }
    }
    
if (isset($_POST['add_experience'])) {
        $company = $_POST['company'];
        $position = $_POST['position'];
        $description = $_POST['description'];
        $location = $_POST['location'];

        // Convert month picker inputs to proper DATE
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] . '-01' : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] . '-01' : null;

        $insert_query = "INSERT INTO student_experience 
                         (student_id, company, position, description, start_date, end_date, location) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("issssss", $student_id, $company, $position, $description, $start_date, $end_date, $location);

        if ($stmt->execute()) {
            header("Location: student-resume.php");
            exit();
        } else {
            $error = "Error saving experience: " . $conn->error;
        }
    }
    
    if (isset($_POST['add_certification'])) {
        $title = $_POST['title'];
        $organization = $_POST['organization'];
        $description = $_POST['description'];
        $date = !empty($_POST['date']) ? $_POST['date'] : null;
        
        $insert_query = "INSERT INTO student_certifications (student_id, title, organization, description, date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("issss", $student_id, $title, $organization, $description, $date);
        
        if ($stmt->execute()) {
            header("Location: student-resume.php");
            exit();
        } else {
            $error = "Error saving certification: " . $conn->error;
        }
    }
    
    if (isset($_POST['add_achievement'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $date = !empty($_POST['date']) ? $_POST['date'] : null;
        
        $insert_query = "INSERT INTO student_achievements (student_id, title, description, date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isss", $student_id, $title, $description, $date);
        if ($stmt->execute()) {
            header("Location: student-resume.php");
            exit();
        } else {
            $error = "Error saving achievement: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Builder - Placement Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!--<link rel="stylesheet" href="../assets/css/style.css">-->
<style>
/* -------------------- Resume Preview (A4 Layout) -------------------- */
.resume-preview {
    width: 210mm;
    min-height: 297mm;
    height: auto;
    margin: 0 auto;
    padding: 15mm;
    background: white;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    font-family: 'Arial', sans-serif;
    box-sizing: border-box;
    line-height: 1.4;
}

/* Print-specific styles */
@media print {
    body * {
        visibility: hidden;
    }
    .resume-preview, .resume-preview * {
        visibility: visible;
    }
    .resume-preview {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: auto;
        box-shadow: none;
        padding: 15mm;
        margin: 0;
    }

    @page {
        margin: 15mm;
        size: A4 portrait;
    }

    .resume-section {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .resume-section-title {
        page-break-after: avoid;
        break-after: avoid;
    }

    .btn-toolbar, .card-header, .sidebar, .navbar, .btn {
        display: none !important;
    }
}

/* Resume header */
.resume-header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #4e73df;
    padding-bottom: 15px;
}
.resume-header h1 {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 8px;
    color: #2e59d9;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.resume-header p {
    margin: 4px 0;
    font-size: 14px;
    color: #555;
}

/* Resume sections */
.resume-section {
    margin-bottom: 16px;
    page-break-inside: avoid;
    break-inside: avoid;
}
.resume-section-title {
    font-weight: bold;
    border-bottom: 1px solid #4e73df;
    padding-bottom: 6px;
    margin-bottom: 12px;
    font-size: 16px;
    color: #2e59d9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Resume content */
.resume-content {
    font-size: 14px;
    line-height: 1.5;
}
.resume-content ul {
    padding-left: 20px;
    margin-bottom: 8px;
}
.resume-content li {
    margin-bottom: 6px;
    page-break-inside: avoid;
}
.resume-content p {
    margin-bottom: 10px;
    page-break-inside: avoid;
    text-align: justify;
}

/* Education & Experience */
.education-item, .experience-item {
    margin-bottom: 16px;
    page-break-inside: avoid;
}
.education-item:last-child, .experience-item:last-child {
    margin-bottom: 0;
}

/* Skills */
.skills-category {
    margin-bottom: 12px;
}
.skills-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.skill-badge {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 13px;
    color: #495057;
}

/* Projects */
.project-item {
    margin-bottom: 16px;
}
.project-title {
    font-weight: bold;
    margin-bottom: 4px;
}
.project-details {
    font-style: italic;
    color: #6c757d;
    margin-bottom: 6px;
}

/* Date styling */
.date-range {
    font-style: italic;
    color: #6c757d;
    margin-bottom: 5px;
}

/* -------------------- Global & UI Theme -------------------- */
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
.sidebar .nav-link:hover, .sidebar .nav-link.active {
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
    transform: translateY(-5px);
}

/* Tables */
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

/* Student avatar */
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

/* Buttons & badges */
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

/* Filter & Stats Cards */
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

/* Pagination */
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

/* Main header */
.main-header {
    background: linear-gradient(90deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--card-shadow);
}

/* Modern checkbox */
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

/* Alerts */
.alert {
    border: none;
    border-radius: 0.75rem;
    box-shadow: var(--card-shadow);
    animation: slideIn 0.5s ease;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
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

/* Scrollbar */
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
                            <a class="nav-link active" href="student-resume.php">
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
                    <h1 class="h2">Resume Builder</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                            <i class="bi bi-plus-circle me-1"></i> Add Section
                        </button>
                        <button class="btn btn-success" onclick="generatePDF()">
                            <i class="bi bi-download me-1"></i> Download PDF
                        </button>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-5">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Resume Sections</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#editPersonalModal">
                                        <i class="bi bi-person me-2"></i> Personal Information
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addEducationModal">
                                        <i class="bi bi-book me-2"></i> Education
                                        <span class="badge bg-primary rounded-pill float-end"><?php echo $education->num_rows; ?></span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                                        <i class="bi bi-tools me-2"></i> Skills
                                        <span class="badge bg-primary rounded-pill float-end"><?php echo count($skills); ?></span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                                        <i class="bi bi-kanban me-2"></i> Projects
                                        <span class="badge bg-primary rounded-pill float-end"><?php echo $projects->num_rows; ?></span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addExperienceModal">
                                        <i class="bi bi-briefcase me-2"></i> Experience
                                        <span class="badge bg-primary rounded-pill float-end"><?php echo $experience->num_rows; ?></span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addLanguageModal">
                                        <i class="bi bi-translate me-2"></i> Languages
                                        <span class="badge bg-primary rounded-pill float-end"><?php echo $languages->num_rows; ?></span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addAchievementModal">
                                        <i class="bi bi-trophy me-2"></i> Achievements
                                        <span class="badge bg-primary rounded-pill float-end"><?php echo $achievements->num_rows; ?></span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                                        <i class="bi bi-award me-2"></i> Certifications
                                        <span class="badge bg-primary rounded-pill float-end"><?php echo $certifications->num_rows; ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Resume Preview</h5>
                            </div>
                            <div class="card-body">
                                <div class="resume-preview" id="resumePreview">
                                    <!-- Header Section -->
                                    <div class="resume-header">
                                        <h1><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
                                        <?php if (!empty($student['field_of_study'])): ?>
                                            <p><strong><?php echo htmlspecialchars($student['field_of_study']); ?></strong></p>
                                        <?php endif; ?>
                                        <p>
                                            <?php if (!empty($student['phone'])): ?>
                                                Mobile: <?php echo htmlspecialchars($student['phone']); ?> | 
                                            <?php endif; ?>
                                            Email: <?php echo htmlspecialchars($student['email']); ?>
                                        </p>
                                        <?php if (!empty($student['address'])): ?>
                                            <p><?php echo htmlspecialchars($student['address']); ?></p>
                                        <?php endif; ?>
                                        <p>
                                            <?php if (!empty($student['github'])): ?>
                                                GitHub: <?php echo htmlspecialchars($student['github']); ?> | 
                                            <?php endif; ?>
                                            <?php if (!empty($student['linkedin'])): ?>
                                                LinkedIn: <?php echo htmlspecialchars($student['linkedin']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <?php if (!empty($student['bio'])): ?>
                                    <div class="resume-section">
                                        <div class="resume-section-title">Career Objective</div>
                                        <div class="resume-content">
                                            <p><?php echo htmlspecialchars($student['bio']); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Education Section -->
                                    <?php if ($education->num_rows > 0): ?>
                                        <div class="resume-section">
                                            <div class="resume-section-title">Education</div>
                                            <div class="resume-content">
                                                <?php 
                                                $education->data_seek(0);
                                                while ($edu = $education->fetch_assoc()): ?>
                                                    <div class="keep-together">
                                                        <p>
                                                            <strong><?php echo htmlspecialchars($edu['institution']); ?></strong><br>
                                                            <?php echo htmlspecialchars($edu['degree']); ?>
                                                            <?php if (!empty($edu['field_of_study'])): ?>
                                                                in <?php echo htmlspecialchars($edu['field_of_study']); ?>
                                                            <?php endif; ?>
                                                            <?php if (!empty($edu['grade'])): ?>
                                                                ; Grade: <?php echo htmlspecialchars($edu['grade']); ?>
                                                            <?php endif; ?>
                                                            <br>
                                                            <?php echo date('M Y', strtotime($edu['start_date'])); ?> - 
                                                            <?php echo $edu['end_date'] ? date('M Y', strtotime($edu['end_date'])) : 'Present'; ?>
                                                        </p>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Technical Skills Section with Categorization -->
                                    <?php if (!empty($categorized_skills)): ?>
                                        <div class="resume-section">
                                            <div class="resume-section-title">Skills (Technical + Soft Skills)</div>
                                            <div class="resume-content">
                                                <?php foreach ($categorized_skills as $category => $skills_list): ?>
                                                    <?php if (!empty($skills_list)): ?>
                                                        <div class="keep-together">
                                                            <p><strong><?php echo $category; ?>:</strong> 
                                                            <?php echo implode(', ', $skills_list); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Projects Section -->
                                    <?php if ($projects->num_rows > 0): ?>
                                        <div class="resume-section">
                                            <div class="resume-section-title">Projects / Portfolio</div>
                                            <div class="resume-content">
                                                <?php 
                                                $projects->data_seek(0);
                                                while ($project = $projects->fetch_assoc()): ?>
                                                    <div class="keep-together">
                                                        <p>
                                                            <strong><?php echo htmlspecialchars($project['title']); ?></strong>: 
                                                            <?php echo htmlspecialchars($project['description']); ?>
                                                            <?php if (!empty($project['technologies'])): ?>
                                                                <br>Technologies: <?php echo htmlspecialchars($project['technologies']); ?>
                                                            <?php endif; ?>
                                                            <?php if (!empty($project['duration'])): ?>
                                                                <br>Duration: <?php echo htmlspecialchars($project['duration']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Experience Section -->
                                    <?php if ($experience->num_rows > 0): ?>
                                        <div class="resume-section">
                                            <div class="resume-section-title">Work Experience / Internships</div>
                                            <div class="resume-content">
                                                <?php 
                                                $experience->data_seek(0);
                                                while ($exp = $experience->fetch_assoc()): ?>
                                                    <div class="keep-together">
                                                        <p>
                                                            <strong><?php echo htmlspecialchars($exp['company']); ?></strong><br>
                                                            <?php echo htmlspecialchars($exp['position']); ?><br>
                                                            <?php echo htmlspecialchars($exp['description']); ?><br>
                                                            <?php echo date('M Y', strtotime($exp['start_date'])); ?> - 
                                                            <?php echo $exp['end_date'] ? date('M Y', strtotime($exp['end_date'])) : 'Present'; ?>
                                                            <?php if (!empty($exp['location'])): ?>
                                                                | <?php echo htmlspecialchars($exp['location']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Certifications Section -->
                                    <?php if ($certifications->num_rows > 0): ?>
                                        <div class="resume-section">
                                            <div class="resume-section-title">Certifications</div>
                                            <div class="resume-content">
                                                <ul>
                                                    <?php 
                                                    $certifications->data_seek(0);
                                                    while ($certification = $certifications->fetch_assoc()): ?>
                                                        <li class="keep-together">
                                                            <strong><?php echo htmlspecialchars($certification['title']); ?></strong>
                                                            <?php if (!empty($certification['organization'])): ?>
                                                                from <?php echo htmlspecialchars($certification['organization']); ?>
                                                            <?php endif; ?>
                                                            <?php if (!empty($certification['date'])): ?>
                                                                (<?php echo date('M Y', strtotime($certification['date'])); ?>)
                                                            <?php endif; ?>
                                                            <?php if (!empty($certification['description'])): ?>
                                                                : <?php echo htmlspecialchars($certification['description']); ?>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endwhile; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Achievements Section -->
                                    <?php if ($achievements->num_rows > 0): ?>
                                        <div class="resume-section">
                                            <div class="resume-section-title">Achievements / Awards</div>
                                            <div class="resume-content">
                                                <ul>
                                                    <?php 
                                                    $achievements->data_seek(0);
                                                    while ($achievement = $achievements->fetch_assoc()): ?>
                                                        <li class="keep-together">
                                                            <strong><?php echo htmlspecialchars($achievement['title']); ?></strong>
                                                            <?php if (!empty($achievement['date'])): ?>
                                                                (<?php echo date('M Y', strtotime($achievement['date'])); ?>)
                                                            <?php endif; ?>
                                                            <?php if (!empty($achievement['description'])): ?>
                                                                : <?php echo htmlspecialchars($achievement['description']); ?>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endwhile; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Languages Section -->
                                    <?php if ($languages->num_rows > 0): ?>
                                        <div class="resume-section">
                                            <div class="resume-section-title">Languages</div>
                                            <div class="resume-content">
                                                <ul>
                                                    <?php 
                                                    $languages->data_seek(0);
                                                    while ($language = $languages->fetch_assoc()): ?>
                                                        <li class="keep-together">
                                                            <strong><?php echo htmlspecialchars($language['language']); ?></strong>: 
                                                            <?php echo ucfirst($language['proficiency']); ?>
                                                        </li>
                                                    <?php endwhile; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Declaration Section -->
                                    <div class="resume-section">
                                        <div class="resume-section-title">Declaration</div>
                                        <div class="resume-content">
                                            <p>I hereby declare that the above-mentioned information is accurate and true to the best of my knowledge.</p>
                                            <p>Place: </p>
                                            <p>Signature: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Resume Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addEducationModal" data-bs-dismiss="modal">
                            <i class="bi bi-book me-2"></i> Education
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addSkillModal" data-bs-dismiss="modal">
                            <i class="bi bi-tools me-2"></i> Skills
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addProjectModal" data-bs-dismiss="modal">
                            <i class="bi bi-kanban me-2"></i> Projects
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addExperienceModal" data-bs-dismiss="modal">
                            <i class="bi bi-briefcase me-2"></i> Work Experience
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addLanguageModal" data-bs-dismiss="modal">
                            <i class="bi bi-translate me-2"></i> Languages
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addAchievementModal" data-bs-dismiss="modal">
                            <i class="bi bi-trophy me-2"></i> Achievements
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#addCertificationModal" data-bs-dismiss="modal">
                            <i class="bi bi-award me-2"></i> Certifications
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Personal Info Modal -->
    <div class="modal fade" id="editPersonalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Personal Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="update_personal" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="field_of_study_personal" class="form-label">Field of Study</label>
                            <input type="text" class="form-control" id="field_of_study_personal" name="field_of_study_personal" 
                                   value="<?php echo htmlspecialchars($student['field_of_study'] ?? ''); ?>" 
                                   placeholder="e.g., Bachelor of Technology in Artificial Intelligence and Machine Learning">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="github" class="form-label">GitHub URL</label>
                            <input type="url" class="form-control" id="github" name="github" value="<?php echo htmlspecialchars($student['github'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="linkedin" class="form-label">LinkedIn URL</label>
                            <input type="url" class="form-control" id="linkedin" name="linkedin" value="<?php echo htmlspecialchars($student['linkedin'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Career Objective/Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($student['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Education Modal -->
    <div class="modal fade" id="addEducationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Education</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="degree" class="form-label">Degree/Certificate</label>
                            <input type="text" class="form-control" id="degree" name="degree" required placeholder="B.TECH, Intermediate, SSC, B.SC">
                        </div>
                        <div class="mb-3">
                            <label for="institution" class="form-label">Institution</label>
                            <input type="text" class="form-control" id="institution" name="institution" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="month" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date (or expected)</label>
                                <input type="month" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="grade" class="form-label">Grade/Percentage</label>
                            <input type="text" class="form-control" id="grade" name="grade" placeholder="e.g., 8.5 CGPA or 85%">
                        </div>
                        <div class="mb-3">
                            <label for="field_of_study" class="form-label">Field of Study</label>
                            <input type="text" class="form-control" id="field_of_study" name="field_of_study" placeholder="Bachelor of Technology in Artificial Intelligence and Machine Learning">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_education">Save Education</button>
                    </div>
                </form>
            </div>
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
                        <label for="skill" class="form-label">Skills</label>
                        <input type="text" class="form-control" id="skill" name="skill" 
                               placeholder="Enter skills separated by commas (e.g. Python, Java, SQL)" required>
                        <small class="text-muted">Separate multiple skills with commas.</small>
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

    
    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Project Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="technologies" class="form-label">Technologies Used</label>
                            <input type="text" class="form-control" id="technologies" name="technologies" placeholder="e.g., Python, Django, React, MySQL">
                        </div>
                        <div class="mb-3">
                            <label for="duration" class="form-label">Duration</label>
                            <input type="text" class="form-control" id="duration" name="duration" placeholder="e.g., 3 months or Jan 2023 - Mar 2023">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_project">Save Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Language Modal -->
    <div class="modal fade" id="addLanguageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Language</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="language" class="form-label">Language</label>
                            <input type="text" class="form-control" id="language" name="language" required>
                        </div>
                        <div class="mb-3">
                            <label for="proficiency" class="form-label">Proficiency Level</label>
                            <select class="form-select" id="proficiency" name="proficiency" required>
                                <option value="">Select Proficiency</option>
                                <option value="basic">Basic</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="native">Native</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_language">Save Language</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Experience Modal -->
    <div class="modal fade" id="addExperienceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Work Experience</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="company" class="form-label">Company</label>
                            <input type="text" class="form-control" id="company" name="company" required>
                        </div>
                        <div class="mb-3">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="position" name="position" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="month" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="month" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" placeholder="e.g., City, Country">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_experience">Save Experience</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Certification Modal -->
    <div class="modal fade" id="addCertificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Certification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="cert_title" class="form-label">Certification Title</label>
                            <input type="text" class="form-control" id="cert_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="organization" class="form-label">Issuing Organization</label>
                            <input type="text" class="form-control" id="organization" name="organization" required>
                        </div>
                        <div class="mb-3">
                            <label for="cert_date" class="form-label">Date Received</label>
                            <input type="month" class="form-control" id="cert_date" name="date">
                        </div>
                        <div class="mb-3">
                            <label for="cert_description" class="form-label">Description</label>
                            <textarea class="form-control" id="cert_description" name="description" rows="3" placeholder="Optional description or skills learned"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_certification">Save Certification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Achievement Modal -->
    <div class="modal fade" id="addAchievementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Achievement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="achievement_title" class="form-label">Achievement Title</label>
                            <input type="text" class="form-control" id="achievement_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="achievement_description" class="form-label">Description</label>
                            <textarea class="form-control" id="achievement_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="achievement_date" class="form-label">Date</label>
                            <input type="month" class="form-control" id="achievement_date" name="date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_achievement">Save Achievement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    // Function to generate PDF
    function generatePDF() {
        // Create a clone of the resume preview
        const resumeElement = document.getElementById('resumePreview');
        const resumeClone = resumeElement.cloneNode(true);
        
        // Create a container for PDF generation
        const pdfContainer = document.createElement('div');
        pdfContainer.className = 'pdf-container';
        pdfContainer.appendChild(resumeClone);
        
        // Hide the original element temporarily
        resumeElement.style.visibility = 'hidden';
        
        // Append the container to the body
        document.body.appendChild(pdfContainer);
        
        // PDF options with standard margins
        const opt = {
            margin: [0, 0, 10, 10],
            filename: 'my_resume.pdf',
            image: { 
                type: 'jpeg', 
                quality: 0.98 
            },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#FFFFFF'
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            }
        };
        
        // Generate PDF
        html2pdf().set(opt).from(pdfContainer).save().then(() => {
            // Remove the container and show the original element again
            document.body.removeChild(pdfContainer);
            resumeElement.style.visibility = 'visible';
        }).catch(error => {
            console.error('PDF generation error:', error);
            document.body.removeChild(pdfContainer);
            resumeElement.style.visibility = 'visible';
            alert('Error generating PDF. Please try again.');
        });
    }
</script>
</body>
</html>