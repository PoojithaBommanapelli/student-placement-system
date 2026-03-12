<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is student
function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

// Check if user is company
function isCompany() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'company';
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../index.html");
        exit();
    }
}

// Redirect based on role
function redirectBasedOnRole() {
    if (isLoggedIn()) {
        if (isStudent()) {
            header("Location: ../student/student-dashboard.php");
        } elseif (isCompany()) {
            header("Location: ../company/company-dashboard.php");
        } elseif (isAdmin()) {
            header("Location: ../admin/admin-dashboard.php");
        }
        exit();
    }
}
?>
