<?php
include '../includes/db.php';
include '../includes/auth.php';

// Redirect if not logged in or not an admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../index.html");
    exit();
}

// Get statistics for reports
$total_students = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'approved'")->fetch_assoc()['count'];
$total_companies = $conn->query("SELECT COUNT(*) as count FROM companies WHERE status = 'approved'")->fetch_assoc()['count'];
$total_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs")->fetch_assoc()['count'];
$total_applications = $conn->query("SELECT COUNT(*) as count FROM applications")->fetch_assoc()['count'];

// Get applications by status
$applications_by_status = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM applications 
    GROUP BY status
");

// Get students by branch
$students_by_branch = $conn->query("
    SELECT branch, COUNT(*) as count 
    FROM students 
    WHERE status = 'approved'
    GROUP BY branch
");

// Get jobs by company
$jobs_by_company = $conn->query("
    SELECT c.name, COUNT(j.id) as count 
    FROM companies c 
    LEFT JOIN jobs j ON c.id = j.company_id 
    WHERE c.status = 'approved'
    GROUP BY c.id 
    ORDER BY count DESC
    LIMIT 10
");

// Get job applications count
$job_applications = $conn->query("
    SELECT j.id, j.title, c.name as company_name, COUNT(a.id) as application_count
    FROM jobs j
    LEFT JOIN applications a ON j.id = a.job_id
    JOIN companies c ON j.company_id = c.id
    GROUP BY j.id
    ORDER BY application_count DESC
    LIMIT 10
");

// Get placement statistics by branch
$placement_stats = $conn->query("
    SELECT 
        s.branch,
        COUNT(s.id) as total_students,
        COUNT(CASE WHEN a.status = 'hired' THEN 1 END) as placements,
        ROUND((COUNT(CASE WHEN a.status = 'hired' THEN 1 END) * 100.0 / COUNT(s.id)), 2) as placement_percentage,
        COALESCE(ROUND(AVG(CASE WHEN a.status = 'hired' THEN j.salary END), 2), 0) as avg_package,
        COALESCE(MAX(CASE WHEN a.status = 'hired' THEN j.salary END), 0) as highest_package
    FROM students s
    LEFT JOIN applications a ON s.id = a.student_id
    LEFT JOIN jobs j ON a.job_id = j.id
    WHERE s.status = 'approved'
    GROUP BY s.branch
");

// Get monthly placements data (actual data from database)
$monthly_placements_data = $conn->query("
    SELECT 
        MONTHNAME(a.applied_at) as month_name,
        COUNT(CASE WHEN a.status = 'hired' THEN 1 END) as placements
    FROM applications a
    WHERE YEAR(a.applied_at) = YEAR(CURDATE())
    GROUP BY MONTH(a.applied_at), month_name
    ORDER BY MONTH(a.applied_at)
");

$monthly_placements = array_fill_keys(['January', 'February', 'March', 'April', 'May', 'June', 
                                      'July', 'August', 'September', 'October', 'November', 'December'], 0);

while ($row = $monthly_placements_data->fetch_assoc()) {
    $monthly_placements[$row['month_name']] = $row['placements'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Placement Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!--<link rel="stylesheet" href="../assets/css/style.css">-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a class="nav-link" href="admin-jobs.php">
                                <i class="bi bi-briefcase me-2"></i>
                                Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin-reports.php">
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Reports & Analytics</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <!-- button type="button" class="btn btn-sm btn-outline-secondary">Export PDF</button -->
                            <!-- button type="button" class="btn btn-sm btn-outline-secondary">Export Excel</button -->
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Students</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_students; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people fs-1 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Companies</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_companies; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-buildings fs-1 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Jobs</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_jobs; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-briefcase fs-1 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Total Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_applications; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-list-check fs-1 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Monthly Placements Overview</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="placementsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Application Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="applicationsChart"></canvas>
                                </div>
                                <div class="mt-4 text-center small">
                                    <?php 
                                    $applications_by_status->data_seek(0);
                                    while ($row = $applications_by_status->fetch_assoc()): 
                                    ?>
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-<?php 
                                                echo $row['status'] == 'applied' ? 'info' : 
                                                ($row['status'] == 'viewed' ? 'primary' : 
                                                ($row['status'] == 'shortlisted' ? 'success' : 
                                                ($row['status'] == 'rejected' ? 'danger' : 'warning'))); 
                                            ?>"></i> 
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Charts -->
                <div class="row">
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Students by Branch</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-bar">
                                    <canvas id="branchChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Jobs by Company</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-bar">
                                    <canvas id="companyJobsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Job Applications Table -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Job Applications</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Job Title</th>
                                                <th>Company</th>
                                                <th>Applications</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($job_applications->num_rows > 0): ?>
                                                <?php 
                                                $job_applications->data_seek(0);
                                                while ($job = $job_applications->fetch_assoc()): 
                                                ?>
                                                <tr>
                                                    <td><?php echo $job['title']; ?></td>
                                                    <td><?php echo $job['company_name']; ?></td>
                                                    <td><?php echo $job['application_count']; ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info view-applicants" data-job-id="<?php echo $job['id']; ?>" data-job-title="<?php echo $job['title']; ?>">
                                                            <i class="bi bi-people me-1"></i> View Applicants
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No job applications found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Data Tables -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Placement Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Branch</th>
                                                <th>Total Students</th>
                                                <th>Placements</th>
                                                <th>Placement %</th>
                                                <th>Average Package</th>
                                                <th>Highest Package</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($placement_stats->num_rows > 0): ?>
                                                <?php while ($stats = $placement_stats->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $stats['branch']; ?></td>
                                                    <td><?php echo $stats['total_students']; ?></td>
                                                    <td><?php echo $stats['placements']; ?></td>
                                                    <td><?php echo $stats['placement_percentage']; ?>%</td>
                                                    <td>₹<?php echo number_format($stats['avg_package'], 2); ?> LPA</td>
                                                    <td>₹<?php echo number_format($stats['highest_package'], 2); ?> LPA</td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No placement statistics available</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Applicants Modal -->
    <div class="modal fade" id="applicantsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="applicantsModalTitle">Job Applicants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="applicantsList">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading applicants...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
        // Monthly Placements Chart
        var placementsChart = new Chart(document.getElementById('placementsChart'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Placements',
                    lineTension: 0.3,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointRadius: 3,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: [
                        <?php 
                        echo $monthly_placements['January'] . ', ' .
                             $monthly_placements['February'] . ', ' .
                             $monthly_placements['March'] . ', ' .
                             $monthly_placements['April'] . ', ' .
                             $monthly_placements['May'] . ', ' .
                             $monthly_placements['June'] . ', ' .
                             $monthly_placements['July'] . ', ' .
                             $monthly_placements['August'] . ', ' .
                             $monthly_placements['September'] . ', ' .
                             $monthly_placements['October'] . ', ' .
                             $monthly_placements['November'] . ', ' .
                             $monthly_placements['December'];
                        ?>
                    ]
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 5
                        }
                    }
                }
            }
        });
        
        // Applications Status Chart
        var applicationsChart = new Chart(document.getElementById('applicationsChart'), {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    $applications_by_status->data_seek(0);
                    while ($row = $applications_by_status->fetch_assoc()): 
                        echo "'" . ucfirst($row['status']) . "',";
                    endwhile; 
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        $applications_by_status->data_seek(0);
                        while ($row = $applications_by_status->fetch_assoc()): 
                            echo $row['count'] . ",";
                        endwhile; 
                        ?>
                    ],
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617'],
                    hoverBorderColor: 'rgba(234, 236, 244, 1)',
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '80%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Students by Branch Chart
        var branchChart = new Chart(document.getElementById('branchChart'), {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    $students_by_branch->data_seek(0);
                    while ($row = $students_by_branch->fetch_assoc()): 
                        echo "'" . $row['branch'] . "',";
                    endwhile; 
                    ?>
                ],
                datasets: [{
                    label: 'Students',
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9',
                    borderColor: '#4e73df',
                    data: [
                        <?php 
                        $students_by_branch->data_seek(0);
                        while ($row = $students_by_branch->fetch_assoc()): 
                            echo $row['count'] . ",";
                        endwhile; 
                        ?>
                    ]
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Jobs by Company Chart
        var companyJobsChart = new Chart(document.getElementById('companyJobsChart'), {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    $jobs_by_company->data_seek(0);
                    while ($row = $jobs_by_company->fetch_assoc()): 
                        echo "'" . $row['name'] . "',";
                    endwhile; 
                    ?>
                ],
                datasets: [{
                    label: 'Jobs',
                    backgroundColor: '#1cc88a',
                    hoverBackgroundColor: '#17a673',
                    borderColor: '#1cc88a',
                    data: [
                        <?php 
                        $jobs_by_company->data_seek(0);
                        while ($row = $jobs_by_company->fetch_assoc()): 
                            echo $row['count'] . ",";
                        endwhile; 
                        ?>
                    ]
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // View applicants functionality
        document.querySelectorAll('.view-applicants').forEach(button => {
            button.addEventListener('click', function() {
                const jobId = this.getAttribute('data-job-id');
                const jobTitle = this.getAttribute('data-job-title');
                
                // Update modal title
                document.getElementById('applicantsModalTitle').textContent = `Applicants for: ${jobTitle}`;
                
                // Show loading state
                document.getElementById('applicantsList').innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading applicants...</p>
                    </div>
                `;
                
                // Load applicants via AJAX
                fetch(`get-applicants.php?job_id=${jobId}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('applicantsList').innerHTML = data;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('applicantsList').innerHTML = '<p class="text-center text-danger">Error loading applicants</p>';
                    });
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('applicantsModal'));
                modal.show();
            });
        });
    </script>
</body>
</html>