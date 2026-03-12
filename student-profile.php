<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: ../index.html");
    exit();
}

$student_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Determine which student profile to show
if (isset($_GET['student_id']) && (isCompany() || isAdmin())) {
    // Viewing a specific student profile as company/admin
    $profile_student_id = intval($_GET['student_id']);
    
    // Verify the student exists
    $student_check = "SELECT * FROM students WHERE id = ?";
    $stmt = $conn->prepare($student_check);
    $stmt->bind_param("i", $profile_student_id);
    $stmt->execute();
    $student_result = $stmt->get_result();
    
    if ($student_result->num_rows === 1) {
        $student = $student_result->fetch_assoc();
        $is_viewing_other_profile = true;
    } else {
        // Student not found, redirect back
        header("Location: " . (isCompany() ? "../company/company-applications.php" : "../admin/admin-students.php"));
        exit();
    }
} else {
    // Viewing own profile (student)
    if (!isStudent()) {
        header("Location: ../index.html");
        exit();
    }
    $profile_student_id = $student_id;
    $is_viewing_other_profile = false;
}

// Check if additional columns exist, if not add them
$check_columns = $conn->query("SHOW COLUMNS FROM students LIKE 'linkedin'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN linkedin VARCHAR(255) AFTER github");
}

$check_columns = $conn->query("SHOW COLUMNS FROM students LIKE 'phone'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN phone VARCHAR(20) AFTER email");
}

$check_columns = $conn->query("SHOW COLUMNS FROM students LIKE 'address'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN address TEXT AFTER phone");
}

$check_columns = $conn->query("SHOW COLUMNS FROM students LIKE 'github'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN github VARCHAR(255) AFTER address");
}

$check_columns = $conn->query("SHOW COLUMNS FROM students LIKE 'field_of_study'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN field_of_study VARCHAR(255) AFTER last_name");
}

// Get student details
$student_query = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $profile_student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get student skills
$skills_query = "SELECT * FROM student_skills WHERE student_id = ?";
$stmt = $conn->prepare($skills_query);
$stmt->bind_param("i", $profile_student_id);
$stmt->execute();
$skills_result = $stmt->get_result();
$skills = [];
while ($row = $skills_result->fetch_assoc()) {
    $skills[] = $row;
}

// Get student education
$education_query = "SELECT * FROM student_education WHERE student_id = ? ORDER BY end_date DESC";
$stmt = $conn->prepare($education_query);
$stmt->bind_param("i", $profile_student_id);
$stmt->execute();
$education = $stmt->get_result();

// Get student projects
$projects_query = "SELECT * FROM student_projects WHERE student_id = ? ORDER BY id DESC";
$stmt = $conn->prepare($projects_query);
$stmt->bind_param("i", $profile_student_id);
$stmt->execute();
$projects = $stmt->get_result();

// Get student languages
$languages_query = "SELECT * FROM student_languages WHERE student_id = ?";
$stmt = $conn->prepare($languages_query);
$stmt->bind_param("i", $profile_student_id);
$stmt->execute();
$languages = $stmt->get_result();

// Get student experience
$experience_query = "SELECT * FROM student_experience WHERE student_id = ? ORDER BY end_date DESC";
$stmt = $conn->prepare($experience_query);
$stmt->bind_param("i", $profile_student_id);
$stmt->execute();
$experience = $stmt->get_result();

// Get student achievements
$achievements_query = "SELECT * FROM student_achievements WHERE student_id = ? ORDER BY id DESC";
$stmt = $conn->prepare($achievements_query);
$stmt->bind_param("i", $profile_student_id);
$stmt->execute();
$achievements = $stmt->get_result();

// Get student certifications
$certifications_query = "SELECT * FROM student_certifications WHERE student_id = ? ORDER BY id DESC";
$stmt = $conn->prepare($certifications_query);
$stmt->bind_param("i", $profile_student_id);
$stmt->execute();
$certifications = $stmt->get_result();

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
        'computer science' => ['Algorithms', 'Data structures', 'Compiler design', 'Computer Architecture', 'Operating Systems'],
        'Information Technology' => ['Networking', 'System Administration', 'IT Infrastructure', 'Cloud Computing', 'Virtualization'],
        
        // Electronics & Electrical
        'electronics' => ['embedded systems', 'vlsi', 'digital design', 'analog circuits', 'pcb design', 'arduino', 'raspberry pi'],
        'electrical' => ['power systems', 'control systems', 'signal processing', 'electric machines', 'power electronics'],
        
        // Mechanical & Civil
        'mechanical' => ['CAD', 'CAM', 'solidworks', 'ansys', 'thermodynamics', 'fluid mechanics', 'finite element analysis'],
        'civil' => ['structural analysis', 'construction management', 'surveying', 'Geotechnical Engineering', 'Transportation Engineering'],
        
        // AI & Data Science
        'artificial intelligence' => ['Machine Learning', 'Deep learning', 'Neural Networks', 'Natural Language Processing', 'Computer Vision'],
        'data science' => ['Data Analysis', 'Statistics', 'Data Visualization', 'Big data', 'Data Mining', 'Predictive Modeling'],
        
        // Emerging Technologies
        'cybersecurity' => ['Network Security', 'Ethical Hacking', 'cryptography', 'Penetration Testing', 'Scurity Analysis'],
        'iot' => ['Sensor Networks', 'Wireless communication', 'Edge computing', 'IOT protocols', 'Smart Devices'],
        'robotics' => ['Robot Programming', 'Kinematics', 'control theory', 'sensor integration', 'automation systems'],
        
        // Other Fields
        'chemical' => ['Process engineering', 'Reaction engineering', 'Transport Phenomena', 'Process Control'],
        'biotechnology' => ['Bioprocessing', 'Genetic Engineering', 'Biochemistry', 'Microbiology', 'Bioinformatics']
    ];
    
    // General skill patterns (common across all fields)
    $general_patterns = [
        'Programming Languages' => [
            'Python', 'Java', 'C\\+\\+', 'C#', 'C', 'Javascript', 'Typescript', 'php', 
            'Ruby', 'Swift', 'Kotlin', 'Go', 'Rust', 'Scala', 'R ', 'Perl', 'Dart',
            'Matlab', 'Assembly', 'Fortran'
        ],
        'Web Technologies' => [
            'HTML', 'CSS', 'Javascript', 'Node\\.js', 'React', 'Angular', 'Vue', 'Express', 
            'Django', 'Flask', 'Spring', 'jQuery', 'Bootstrap', 'SASS', 'Less', 'Webpack'
        ],
        'Database Technologies' => [
            'MYSQL', 'SQL server', 'postgreSQL', 'mongoDB', 'Oracle', 'SQLite', 'Redis', 'SQL', 
            'Cassandra', 'dynamodb', 'mariadb', 'firebase', 'BIGquery'
        ],
        'Tools & Platforms' => [
            'git', 'docker', 'kubernetes', 'jenkins', 'AWS', 'Azure', 'GCP', 'heroku', 
            'github', 'gitlab', 'JIRA', 'Ansible', 'Terraform', 'Postman'
        ],
        'Operating Systems' => [
            'Windows', 'Linux', 'Unix', 'macOS', 'ubuntu', 'centos', 'debian', 'android', 'iOS'
        ],
        'Soft Skills' => [
            'communication', 'Teamwork', 'Leadership', 'Problem Solving', 'Time Management',
            'Critical thinking', 'Creativity', 'Adaptability', 'Project Management'
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

// Get student's field of study (from education)
$field_of_study = '';
$education->data_seek(0); // Reset pointer
if ($edu = $education->fetch_assoc()) {
    $field_of_study = $edu['field_of_study'] ?? '';
}

// Get categorized skills based on field of study
$categorized_skills = categorizeSkills($skills, $field_of_study);

// Handle delete operations
if (isset($_GET['delete'])) {
    $type = $_GET['type'];
    $id = intval($_GET['id']);
    
    if (!$is_viewing_other_profile) {
        switch ($type) {
            case 'skill':
                $delete_query = "DELETE FROM student_skills WHERE id = ? AND student_id = ?";
                break;
            case 'education':
                $delete_query = "DELETE FROM student_education WHERE id = ? AND student_id = ?";
                break;
            case 'project':
                $delete_query = "DELETE FROM student_projects WHERE id = ? AND student_id = ?";
                break;
            case 'language':
                $delete_query = "DELETE FROM student_languages WHERE id = ? AND student_id = ?";
                break;
            case 'experience':
                $delete_query = "DELETE FROM student_experience WHERE id = ? AND student_id = ?";
                break;
            case 'achievement':
                $delete_query = "DELETE FROM student_achievements WHERE id = ? AND student_id = ?";
                break;
            case 'certification':
                $delete_query = "DELETE FROM student_certifications WHERE id = ? AND student_id = ?";
                break;
            default:
                $error = 'Invalid delete type';
                break;
        }
        
        if (isset($delete_query)) {
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("ii", $id, $profile_student_id);
            
            if ($stmt->execute()) {
                $success = ucfirst($type) . ' deleted successfully';
                // Refresh the page to show updated data
                header("Location: student-profile.php");
                exit();
            } else {
                $error = 'Error deleting ' . $type;
            }
        }
    }
}

// Handle form submission for profile update (only for own profile)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_viewing_other_profile) {
    // Check which form was submitted
    if (isset($_POST['update_profile'])) {
        // Profile update code
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $personal_field_of_study = trim($_POST['field_of_study'] ?? '');
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $github = trim($_POST['github']);
        $linkedin = trim($_POST['linkedin']);
        $bio = trim($_POST['bio']);
        $career_preferences = trim($_POST['career_preferences']);
        
        // Validate inputs
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required';
        } else {
            // Handle photo upload
            $photo_path = $student['photo']; // Keep existing photo by default
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                $photo = $_FILES['photo'];
                $photo_name = time() . '_' . basename($photo['name']);
                $target_dir = "../uploads/students/photos/";
                
                // Create directory if it doesn't exist
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $target_file = $target_dir . $photo_name;
                
                // Check if image file is an actual image
                $check = getimagesize($photo["tmp_name"]);
                if ($check !== false) {
                    // Check file size (max 2MB)
                    if ($photo["size"] > 2000000) {
                        $error = "Sorry, your file is too large. Maximum size is 2MB.";
                    } else {
                        // Allow certain file formats
                        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                        } else {
                            // Resize and compress image to 200x200
                            if (resizeImage($photo["tmp_name"], $target_file, 200, 200)) {
                                $photo_path = $photo_name;
                                
                                // Delete old photo if it exists and is not the default
                                if (!empty($student['photo']) && $student['photo'] != 'default.png') {
                                    $old_photo = $target_dir . $student['photo'];
                                    if (file_exists($old_photo)) {
                                        unlink($old_photo);
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
            
            // Handle resume upload
            $resume_path = $student['resume']; // Keep existing resume by default
            
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
                $resume = $_FILES['resume'];
                $resume_name = time() . '_' . basename($resume['name']);
                $target_dir = "../uploads/students/resumes/";
                
                // Create directory if it doesn't exist
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $target_file = $target_dir . $resume_name;
                
                // Check file size (max 5MB)
                if ($resume["size"] > 5000000) {
                    $error = "Sorry, your resume file is too large. Maximum size is 5MB.";
                } else {
                    // Allow certain file formats
                    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    if ($fileType != "pdf" && $fileType != "doc" && $fileType != "docx") {
                        $error = "Sorry, only PDF, DOC & DOCX files are allowed.";
                    } else {
                        // Try to upload file
                        if (move_uploaded_file($resume["tmp_name"], $target_file)) {
                            $resume_path = $resume_name;
                            
                            // Delete old resume if it exists
                            if (!empty($student['resume'])) {
                                $old_resume = $target_dir . $student['resume'];
                                if (file_exists($old_resume)) {
                                    unlink($old_resume);
                                }
                            }
                        } else {
                            $error = "Sorry, there was an error uploading your resume.";
                        }
                    }
                }
            }
            
            if (empty($error)) {
                // Update student profile with all fields including field_of_study
                $update_query = "UPDATE students SET first_name = ?, last_name = ?, field_of_study = ?, phone = ?, address = ?, 
                                github = ?, linkedin = ?, bio = ?, career_preferences = ?, photo = ?, resume = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                
                // Make sure we have exactly 12 parameters: 11 strings and 1 integer
                $stmt->bind_param("sssssssssssi", $first_name, $last_name, $personal_field_of_study, $phone, $address, $github, $linkedin, 
                                 $bio, $career_preferences, $photo_path, $resume_path, $profile_student_id);
                
                if ($stmt->execute()) {
                    $success = 'Profile updated successfully';
                    
                    // Update session name if changed
                    $_SESSION['name'] = $first_name . ' ' . $last_name;
                    
                    // Refresh student data
                    $stmt = $conn->prepare($student_query);
                    $stmt->bind_param("i", $profile_student_id);
                    $stmt->execute();
                    $student = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = 'Error updating profile: ' . $conn->error;
                }
            }
        }
    }
    // Handle adding new skill
elseif (isset($_POST['add_skill'])) {
    $skills_input = trim($_POST['skill']);

    if (!empty($skills_input)) {
        // Split comma-separated input, trim spaces, remove empties
        $skills_array = array_filter(array_map('trim', explode(',', $skills_input)));

        $insert_skill = "INSERT INTO student_skills (student_id, skill) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_skill);

        $all_success = true;
        foreach ($skills_array as $skill) {
            $stmt->bind_param("is", $profile_student_id, $skill);
            if (!$stmt->execute()) {
                $all_success = false;
                break; // stop if error
            }
        }

        if ($all_success) {
            $success = 'Skill(s) added successfully';

            // Refresh skills
            $stmt = $conn->prepare($skills_query);
            $stmt->bind_param("i", $profile_student_id);
            $stmt->execute();
            $skills_result = $stmt->get_result();

            $skills = [];
            while ($row = $skills_result->fetch_assoc()) {
                $skills[] = $row;
            }

            // Re-categorize skills
            $categorized_skills = categorizeSkills($skills, $field_of_study);
        } else {
            $error = 'Error adding skill(s). Please try again.';
        }
    }
}

    // Handle adding new education
    elseif (isset($_POST['add_education'])) {
        $institution = trim($_POST['institution']);
        $degree = trim($_POST['degree']);
        $edu_field_of_study = trim($_POST['field_of_study'] ?? '');
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] . "-01" : null;
        $end_date   = !empty($_POST['end_date'])   ? $_POST['end_date']   . "-01" : null;
        $grade = trim($_POST['grade'] ?? '');
        
        if (!empty($institution) && !empty($degree) && !empty($start_date)) {
            $insert_education = "INSERT INTO student_education (student_id, institution, degree, field_of_study, start_date, end_date, grade) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_education);
            $stmt->bind_param("issssss", $profile_student_id, $institution, $degree, $edu_field_of_study, $start_date, $end_date, $grade);
            
            if ($stmt->execute()) {
                $success = 'Education added successfully';
                // Refresh education
                $stmt = $conn->prepare($education_query);
                $stmt->bind_param("i", $profile_student_id);
                $stmt->execute();
                $education = $stmt->get_result();
                // Update field of study for skill categorization
                $field_of_study = $edu_field_of_study;
                // Re-categorize skills
                $categorized_skills = categorizeSkills($skills, $field_of_study);
            } else {
                $error = 'Error adding education. Please try again.';
            }
        } else {
            $error = 'Institution, degree, and start date are required';
        }
    }
    // Handle adding new project
    elseif (isset($_POST['add_project'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $technologies = trim($_POST['technologies'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        
        if (!empty($title) && !empty($description)) {
            $insert_project = "INSERT INTO student_projects (student_id, title, description, technologies, duration) 
                              VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_project);
            $stmt->bind_param("issss", $profile_student_id, $title, $description, $technologies, $duration);
            
            if ($stmt->execute()) {
                $success = 'Project added successfully';
                // Refresh projects
                $stmt = $conn->prepare($projects_query);
                $stmt->bind_param("i", $profile_student_id);
                $stmt->execute();
                $projects = $stmt->get_result();
            } else {
                $error = 'Error adding project. Please try again.';
            }
        } else {
            $error = 'Title and description are required';
        }
    }
    // Handle adding new language
    elseif (isset($_POST['add_language'])) {
        $language = trim($_POST['language']);
        $proficiency = trim($_POST['proficiency']);
        
        if (!empty($language) && !empty($proficiency)) {
            $insert_language = "INSERT INTO student_languages (student_id, language, proficiency) 
                               VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_language);
            $stmt->bind_param("iss", $profile_student_id, $language, $proficiency);
            
            if ($stmt->execute()) {
                $success = 'Language added successfully';
                // Refresh languages
                $stmt = $conn->prepare($languages_query);
                $stmt->bind_param("i", $profile_student_id);
                $stmt->execute();
                $languages = $stmt->get_result();
            } else {
                $error = 'Error adding language. Please try again.';
            }
        } else {
            $error = 'Language and proficiency are required';
        }
    }
    // Handle adding new experience
    elseif (isset($_POST['add_experience'])) {
        $company = trim($_POST['company']);
        $position = trim($_POST['position']);
        $description = trim($_POST['description']);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] . "-01" : null;
        $end_date   = !empty($_POST['end_date'])   ? $_POST['end_date']   . "-01" : null;
        $location = trim($_POST['location'] ?? '');
        
        if (!empty($company) && !empty($position) && !empty($description) && !empty($start_date)) {
            $insert_experience = "INSERT INTO student_experience (student_id, company, position, description, start_date, end_date, location) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_experience);
            $stmt->bind_param("issssss", $profile_student_id, $company, $position, $description, $start_date, $end_date, $location);
            
            if ($stmt->execute()) {
                $success = 'Work experience added successfully';
                // Refresh experience
                $stmt = $conn->prepare($experience_query);
                $stmt->bind_param("i", $profile_student_id);
                $stmt->execute();
                $experience = $stmt->get_result();
            } else {
                $error = 'Error adding work experience. Please try again.';
            }
        } else {
            $error = 'Company, position, description, and start date are required';
        }
    }
    // Handle adding new certification
    elseif (isset($_POST['add_certification'])) {
        $title        = trim($_POST['title']);
        $organization = trim($_POST['organization']);
        $description  = trim($_POST['description']);
        $date         = !empty($_POST['date']) ? $_POST['date'] . "-01" : null;

        if (!empty($title)) {
            $insert_certification = "INSERT INTO student_certifications 
                (student_id, title, organization, description, date) 
                VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_certification);
            $stmt->bind_param("issss", $profile_student_id, $title, $organization, $description, $date);

            if ($stmt->execute()) {
                $success = 'Certification added successfully';
                // Refresh certifications
                $stmt = $conn->prepare($certifications_query);
                $stmt->bind_param("i", $profile_student_id);
                $stmt->execute();
                $certifications = $stmt->get_result();
            } else {
                $error = 'Error adding certification. Please try again.';
            }
        } else {
            $error = 'Title is required';
        }
    }
    // Handle adding new achievement
    elseif (isset($_POST['add_achievement'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $date = !empty($_POST['date']) ? $_POST['date'] . "-01" : null;
        
        if (!empty($title)) {
            $insert_achievement = "INSERT INTO student_achievements (student_id, title, description, date) 
                                  VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_achievement);
            $stmt->bind_param("isss", $profile_student_id, $title, $description, $date);
            
            if ($stmt->execute()) {
                $success = 'Achievement added successfully';
                // Refresh achievements
                $stmt = $conn->prepare($achievements_query);
                $stmt->bind_param("i", $profile_student_id);
                $stmt->execute();
                $achievements = $stmt->get_result();
            } else {
                $error = 'Error adding achievement. Please try again.';
            }
        } else {
            $error = 'Title is required';
        }
    }
}

// Function to resize and compress image
function resizeImage($sourcePath, $targetPath, $width, $height) {
    $imageInfo = getimagesize($sourcePath);
    $imageType = $imageInfo[2];
    
    // Create image from source
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    // Create a new true color image
    $newImage = imagecreatetruecolor($width, $height);
    
    // Preserve transparency for PNG and GIF
    if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
        imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }
    
    // Resize image
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
    
    // Save image based on type
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $targetPath, 85); // 85% quality
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $targetPath, 9); // 9 is maximum compression
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($newImage, $targetPath);
            break;
        default:
            $result = false;
    }
    
    // Free up memory
    imagedestroy($image);
    imagedestroy($newImage);
    
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - Placement Portal</title>
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

/* Profile & Avatars */
.profile-image {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #f8f9fa;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
    margin-bottom: 20px;
    transition: transform 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
}
.card-header {
    border-radius: 10px 10px 0 0 !important;
}

/* Stats Card */
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

/* Badges */
.badge {
    font-size: 0.8rem;
    font-weight: 500;
    padding: 0.5em 0.75em;
    border-radius: 0.5rem;
}
.bg-purple {
    background-color: #6f42c1 !important;
    color: white;
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

/* Upload */
.upload-preview {
    border: 2px dashed #dee2e6;
    border-radius: 5px;
    padding: 15px;
    text-align: center;
    margin-bottom: 15px;
}
.file-info {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
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
.btn-download {
    transition: all 0.3s;
}
.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.btn-group .btn {
    border-radius: 0.5rem;
    margin: 0 2px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
    white-space: nowrap;
}
.action-buttons .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

/* Filter & Header */
.filter-card {
    background: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);
}
.main-header {
    background: linear-gradient(90deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--card-shadow);
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

/* Custom Scrollbar */
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
            <!-- Sidebar (only show for students viewing their own profile) -->
            <?php if (!$is_viewing_other_profile): ?>
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
                            <a class="nav-link active" href="student-profile.php">
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
            <?php endif; ?>
            
            <!-- Main content -->
            <main class="<?php echo $is_viewing_other_profile ? 'col-12' : 'col-md-9 ms-sm-auto col-lg-10'; ?> px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Student Profile</h1>
                    <?php if ($is_viewing_other_profile): ?>
                        <a href="<?php echo isCompany() ? '../company/company-applications.php' : '../admin/admin-students.php'; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body text-center">
                                <img src="../uploads/students/photos/<?php echo $student['photo'] ?? 'default.png'; ?>" 
                                     alt="Profile Photo" class="profile-image mb-3" id="photoPreview">
                                <h4><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h4>
                                <p class="text-muted"><?php echo $student['branch']; ?> - Year <?php echo $student['year']; ?></p>
                                <span class="badge bg-<?php echo $student['status'] == 'approved' ? 'success' : ($student['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                                
                                <div class="mt-3">
                                    <?php if (!empty($student['resume'])): ?>
                                        <a href="../uploads/students/resumes/<?php echo $student['resume']; ?>" 
                                           class="btn btn-sm btn-outline-primary btn-download" target="_blank">
                                            <i class="bi bi-download me-1"></i> Download Resume
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No resume uploaded</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Contact Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Email:</strong> <?php echo $student['email']; ?>
                                </div>
                                <?php if (!empty($student['phone'])): ?>
                                <div class="mb-2">
                                    <strong>Phone:</strong> <?php echo $student['phone']; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($student['address'])): ?>
                                <div class="mb-2">
                                    <strong>Address:</strong> <?php echo $student['address']; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($student['github'])): ?>
                                <div class="mb-2">
                                    <strong>GitHub:</strong> 
                                    <a href="<?php echo $student['github']; ?>" target="_blank"><?php echo $student['github']; ?></a>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($student['linkedin'])): ?>
                                <div class="mb-2">
                                    <strong>LinkedIn:</strong> 
                                    <a href="<?php echo $student['linkedin']; ?>" target="_blank"><?php echo $student['linkedin']; ?></a>
                                </div>
                                <?php endif; ?>
                                <div class="mb-2">
                                    <strong>Hall Ticket No:</strong> <?php echo $student['hall_ticket']; ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Branch:</strong> <?php echo $student['branch']; ?>
                                </div>
                                <?php if (!empty($student['field_of_study'])): ?>
                                <div class="mb-2">
                                    <strong>Field of Study:</strong> <?php echo htmlspecialchars($student['field_of_study']); ?>
                                </div>
                                <?php endif; ?>
                                <div class="mb-2">
                                    <strong>Year:</strong> <?php echo $student['year']; ?>
                                </div>
                                <div>
                                    <strong>Semester:</strong> <?php echo $student['semester']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <?php if (!$is_viewing_other_profile): ?>
                        <div class="card shadow">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo $student['first_name']; ?>" required>
                                            <div class="invalid-feedback">Please enter your first name</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo $student['last_name']; ?>" required>
                                            <div class="invalid-feedback">Please enter your last name</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="field_of_study" class="form-label">Field of Study</label>
                                        <input type="text" class="form-control" id="field_of_study" name="field_of_study" 
                                               value="<?php echo htmlspecialchars($student['field_of_study'] ?? ''); ?>" 
                                               placeholder="e.g., Bachelor of Technology in Artificial Intelligence and Machine Learning">
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo $student['phone'] ?? ''; ?>" placeholder="e.g., +91-9876543210">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="e.g., H.NO: XXX, XXXX, XXXX, XXX, XXX, India, 123456"><?php echo $student['address'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="github" class="form-label">GitHub URL</label>
                                        <input type="url" class="form-control" id="github" name="github" 
                                               value="<?php echo $student['github'] ?? ''; ?>" placeholder="e.g., https://github.com/username">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="linkedin" class="form-label">LinkedIn URL</label>
                                        <input type="url" class="form-control" id="linkedin" name="linkedin" 
                                               value="<?php echo $student['linkedin'] ?? ''; ?>" placeholder="e.g., https://linkedin.com/in/username">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="photo" class="form-label">Profile Photo</label>
                                        <div class="upload-preview">
                                            <div id="photoPreviewArea">
                                                <?php if (!empty($student['photo']) && $student['photo'] != 'default.png'): ?>
                                                <div class="file-info">
                                                    <strong>Current file:</strong> <?php echo $student['photo']; ?><br>
                                                    <small>Click "Choose File" to change</small>
                                                </div>
                                                <?php else: ?>
                                                <p class="text-muted">No photo selected</p>
                                                <?php endif; ?>
                                            </div>
                                            <input class="form-control mt-2" type="file" id="photo" name="photo" accept="image/*">
                                        </div>
                                        <div class="form-text">Recommended size: 200x200 pixels. Max size: 2MB</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="resume" class="form-label">Resume</label>
                                        <div class="upload-preview">
                                            <div id="resumePreviewArea">
                                                <?php if (!empty($student['resume'])): ?>
                                                <div class="file-info">
                                                    <strong>Current file:</strong> <?php echo $student['resume']; ?><br>
                                                    <small>Click "Choose File" to change</small>
                                                </div>
                                                <?php else: ?>
                                                <p class="text-muted">No resume uploaded yet</p>
                                                <?php endif; ?>
                                            </div>
                                            <input class="form-control mt-2" type="file" id="resume" name="resume" accept=".pdf,.doc,.docx">
                                        </div>
                                        <div class="form-text">Accepted formats: PDF, DOC, DOCX. Max size: 5MB</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="bio" class="form-label">Career Objective</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="e.g., Third-year undergraduate specializing in Artificial Intelligence and Machine Learning, seeking to contribute to innovative projects..."><?php echo $student['bio'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="career_preferences" class="form-label">Career Preferences</label>
                                        <textarea class="form-control" id="career_preferences" name="career_preferences" rows="3"><?php echo $student['career_preferences'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" name="update_profile">Update Profile</button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Career Objective Section -->
                        <?php if (!empty($student['bio'])): ?>
                        <div class="card shadow mt-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Career Objective</h5>
                            </div>
                            <div class="card-body">
                                <p><?php echo $student['bio']; ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Skills Section with Field-Specific Categorization -->
                        <div class="card shadow mt-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Technical Skills</h5>
                                <?php if (!$is_viewing_other_profile): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                                    <i class="bi bi-plus me-1"></i> Add Skill
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($categorized_skills)): ?>
                                    <!-- Programming Languages -->
                                    <?php if (!empty($categorized_skills['Programming Languages'])): ?>
                                    <div class="mb-3">
                                        <h6 class="mb-1">• Programming Languages:</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($categorized_skills['Programming Languages'] as $skill): ?>
                                                <span class="badge bg-primary"><?php echo $skill; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Web Technologies -->
                                    <?php if (!empty($categorized_skills['Web Technologies'])): ?>
                                    <div class="mb-3">
                                        <h6 class="mb-1">• Web Technologies:</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($categorized_skills['Web Technologies'] as $skill): ?>
                                                <span class="badge bg-success"><?php echo $skill; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Database Technologies -->
                                    <?php if (!empty($categorized_skills['Database Technologies'])): ?>
                                    <div class="mb-3">
                                        <h6 class="mb-1">• Database Technologies:</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($categorized_skills['Database Technologies'] as $skill): ?>
                                                <span class="badge bg-info"><?php echo $skill; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Tools & Platforms -->
                                    <?php if (!empty($categorized_skills['Tools & Platforms'])): ?>
                                    <div class="mb-3">
                                        <h6 class="mb-1">• Tools & Platforms:</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($categorized_skills['Tools & Platforms'] as $skill): ?>
                                                <span class="badge bg-warning"><?php echo $skill; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Operating Systems -->
                                    <?php if (!empty($categorized_skills['Operating Systems'])): ?>
                                    <div class="mb-3">
                                        <h6 class="mb-1">• Operating Systems:</h6>
                                                                                    <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($categorized_skills['Operating Systems'] as $skill): ?>
                                                    <span class="badge bg-secondary"><?php echo $skill; ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Core Engineering Skills -->
                                    <?php if (!empty($categorized_skills['Core Engineering Skills'])): ?>
                                        <div class="mb-3">
                                            <h6 class="mb-1">• Core Engineering Skills:</h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($categorized_skills['Core Engineering Skills'] as $skill): ?>
                                                    <span class="badge bg-dark"><?php echo $skill; ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Specialized Skills (Field-specific) -->
                                    <?php if (!empty($categorized_skills['Specialized Skills'])): ?>
                                        <div class="mb-3">
                                            <h6 class="mb-1">• Specialized Skills:</h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($categorized_skills['Specialized Skills'] as $skill): ?>
                                                    <span class="badge bg-danger"><?php echo $skill; ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Soft Skills -->
                                    <?php if (!empty($categorized_skills['Soft Skills'])): ?>
                                        <div class="mb-3">
                                            <h6 class="mb-1">• Soft Skills:</h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($categorized_skills['Soft Skills'] as $skill): ?>
                                                    <span class="badge bg-purple"><?php echo $skill; ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <p class="text-muted">No skills added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Education Section -->
                        <div class="card shadow mt-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Education</h5>
                                <?php if (!$is_viewing_other_profile): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEducationModal">
                                    <i class="bi bi-plus me-1"></i> Add Education
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($education->num_rows > 0): ?>
                                    <?php 
                                    // Reset pointer before looping
                                    $education->data_seek(0);
                                    while ($edu = $education->fetch_assoc()): ?>
                                        <div class="mb-3 pb-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $edu['degree']; ?></h6>
                                                    <p class="mb-1 text-muted"><?php echo $edu['institution']; ?></p>
                                                    <p class="mb-1">
                                                        <?php echo date('M Y', strtotime($edu['start_date'])); ?> - 
                                                        <?php echo $edu['end_date'] ? date('M Y', strtotime($edu['end_date'])) : 'Present'; ?>
                                                        <?php if (!empty($edu['grade'])): ?>
                                                            | Grade: <?php echo $edu['grade']; ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!empty($edu['field_of_study'])): ?>
                                                        <p class="mb-0"><strong>Field of Study:</strong> <?php echo $edu['field_of_study']; ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!$is_viewing_other_profile): ?>
                                                <div class="action-buttons">
                                                    <a href="?delete=1&type=education&id=<?php echo $edu['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this education entry?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">No education information added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Projects Section -->
                        <div class="card shadow mt-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Projects</h5>
                                <?php if (!$is_viewing_other_profile): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                                    <i class="bi bi-plus me-1"></i> Add Project
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($projects->num_rows > 0): ?>
                                    <?php 
                                    // Reset pointer before looping
                                    $projects->data_seek(0);
                                    while ($project = $projects->fetch_assoc()): ?>
                                        <div class="mb-3 pb-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $project['title']; ?></h6>
                                                    <p class="mb-1 text-muted"><?php echo $project['duration']; ?></p>
                                                    <p class="mb-1"><?php echo $project['description']; ?></p>
                                                    <?php if (!empty($project['technologies'])): ?>
                                                        <p class="mb-0"><strong>Technologies:</strong> <?php echo $project['technologies']; ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!$is_viewing_other_profile): ?>
                                                <div class="action-buttons">
                                                    <a href="?delete=1&type=project&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this project?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">No projects added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Experience Section -->
                        <div class="card shadow mt-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Work Experience</h5>
                                <?php if (!$is_viewing_other_profile): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addExperienceModal">
                                    <i class="bi bi-plus me-1"></i> Add Experience
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($experience->num_rows > 0): ?>
                                    <?php 
                                    // Reset pointer before looping
                                    $experience->data_seek(0);
                                    while ($exp = $experience->fetch_assoc()): ?>
                                        <div class="mb-3 pb-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $exp['position']; ?></h6>
                                                    <p class="mb-1 text-muted"><?php echo $exp['company']; ?></p>
                                                    <p class="mb-1">
                                                        <?php echo date('M Y', strtotime($exp['start_date'])); ?> - 
                                                        <?php echo $exp['end_date'] ? date('M Y', strtotime($exp['end_date'])) : 'Present'; ?>
                                                        <?php if (!empty($exp['location'])): ?>
                                                            | <?php echo $exp['location']; ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <p class="mb-0"><?php echo $exp['description']; ?></p>
                                                </div>
                                                <?php if (!$is_viewing_other_profile): ?>
                                                <div class="action-buttons">
                                                    <a href="?delete=1&type=experience&id=<?php echo $exp['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this experience?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">No work experience added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Languages Section -->
                        <div class="card shadow mt-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Languages</h5>
                                <?php if (!$is_viewing_other_profile): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLanguageModal">
                                    <i class="bi bi-plus me-1"></i> Add Language
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($languages->num_rows > 0): ?>
                                    <div class="row">
                                        <?php 
                                        // Reset pointer before looping
                                        $languages->data_seek(0);
                                        while ($language = $languages->fetch_assoc()): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><?php echo $language['language']; ?></span>
                                                    <div>
                                                        <span class="badge bg-info text-capitalize"><?php echo $language['proficiency']; ?></span>
                                                        <?php if (!$is_viewing_other_profile): ?>
                                                        <a href="?delete=1&type=language&id=<?php echo $language['id']; ?>" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Are you sure you want to delete this language?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No languages added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Certifications / Trainings Section -->
                        <div class="card shadow mt-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Certifications / Trainings</h5>
                                <?php if (!$is_viewing_other_profile): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                                    <i class="bi bi-plus me-1"></i> Add Certification
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($certifications->num_rows > 0): ?>
                                    <?php 
                                    // Reset pointer before looping
                                    $certifications->data_seek(0);
                                    while ($cert = $certifications->fetch_assoc()): ?>
                                        <div class="mb-3 pb-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($cert['title']); ?></h6>
                                                    <?php if (!empty($cert['organization'])): ?>
                                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($cert['organization']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($cert['date']) && $cert['date'] != '0000-00-00'): ?>
                                                        <p class="mb-1 text-muted"><?php echo date('M Y', strtotime($cert['date'])); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($cert['description'])): ?>
                                                        <p class="mb-0"><?php echo htmlspecialchars($cert['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!$is_viewing_other_profile): ?>
                                                <div class="action-buttons">
                                                    <a href="?delete=1&type=certification&id=<?php echo $cert['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this certification?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">No certifications added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Achievements Section -->
                        <div class="card shadow mt-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Achievements</h5>
                                <?php if (!$is_viewing_other_profile): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAchievementModal">
                                    <i class="bi bi-plus me-1"></i> Add Achievement
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($achievements->num_rows > 0): ?>
                                    <?php 
                                    // Reset pointer before looping
                                    $achievements->data_seek(0);
                                    while ($achievement = $achievements->fetch_assoc()): ?>
                                        <div class="mb-3 pb-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $achievement['title']; ?></h6>
                                                    <?php if (!empty($achievement['date'])): ?>
                                                        <p class="mb-1 text-muted"><?php echo date('M Y', strtotime($achievement['date'])); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($achievement['description'])): ?>
                                                        <p class="mb-0"><?php echo $achievement['description']; ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!$is_viewing_other_profile): ?>
                                                <div class="action-buttons">
                                                    <a href="?delete=1&type=achievement&id=<?php echo $achievement['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this achievement?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">No achievements added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
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
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
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
    
    <!-- Add Education Modal -->
    <div class="modal fade" id="addEducationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Education</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="degree" class="form-label">Degree/Certificate</label>
                            <input type="text" class="form-control" id="degree" name="degree" required>
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
    
    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
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
                            <input type="text" class="form-control" id="technologies" name="technologies" placeholder="e.g., HTML, CSS, JavaScript, PHP">
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
    
    <!-- Add Experience Modal -->
    <div class="modal fade" id="addExperienceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Work Experience</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
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
                            <input type="text" class="form-control" id="location" name="location" placeholder="e.g., Remote, City, Country">
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
    
    <!-- Add Language Modal -->
    <div class="modal fade" id="addLanguageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Language</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="language" class="form-label">Language</label>
                            <input type="text" class="form-control" id="language" name="language" required>
                        </div>
                        <div class="mb-3">
                            <label for="proficiency" class="form-label">Proficiency</label>
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
    
    <!-- Add Certification Modal -->
    <div class="modal fade" id="addCertificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Certification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
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
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
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
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.needs-validation');
            if (form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropulation();
                    }
                    form.classList.add('was-validated');
                });
            }
            
            // Photo preview
            const photoInput = document.getElementById('photo');
            if (photoInput) {
                photoInput.addEventListener('change', function() {
                    const file = this.files[0];
                    const previewArea = document.getElementById('photoPreviewArea');
                    
                    if (file) {
                        if (file.size > 2 * 1024 * 1024) {
                            alert('File size exceeds 2MB. Please choose a smaller file.');
                            this.value = '';
                            return;
                        }
                        
                        if (!file.type.match('image.*')) {
                            alert('Please select an image file.');
                            this.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('photoPreview').src = e.target.result;
                            previewArea.innerHTML = `
                                <div class="file-info">
                                    <strong>Selected file:</strong> ${file.name}<br>
                                    <small>Size: ${(file.size / 1024).toFixed(2)} KB</small>
                                </div>
                            `;
                        }
                        reader.readAsDataURL(file);
                    } else {
                        previewArea.innerHTML = '<?php echo !empty($student['photo']) && $student['photo'] != 'default.png' ? '<div class="file-info"><strong>Current file:</strong> ' . $student['photo'] . '<br><small>Click "Choose File" to change</small></div>' : '<p class="text-muted">No photo selected</p>'; ?>';
                    }
                });
            }
            
            // Resume preview
            const resumeInput = document.getElementById('resume');
            if (resumeInput) {
                resumeInput.addEventListener('change', function() {
                    const file = this.files[0];
                    const previewArea = document.getElementById('resumePreviewArea');
                    
                    if (file) {
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File size exceeds 5MB. Please choose a smaller file.');
                            this.value = '';
                            return;
                        }
                        
                        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Please select a PDF, DOC, or DOCX file.');
                            this.value = '';
                            return;
                        }
                        
                        previewArea.innerHTML = `
                            <div class="file-info">
                                <strong>Selected file:</strong> ${file.name}<br>
                                <small>Size: ${(file.size / 1024).toFixed(2)} KB</small>
                            </div>
                        `;
                    } else {
                        previewArea.innerHTML = '<?php echo !empty($student['resume']) ? '<div class="file-info"><strong>Current file:</strong> ' . $student['resume'] . '<br><small>Click "Choose File" to change</small></div>' : '<p class="text-muted">No resume uploaded yet</p>'; ?>';
                    }
                });
            }
        });
    </script>
</body>
</html>