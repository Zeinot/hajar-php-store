<?php
/**
 * Admin Header Template
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> | Elegant Drapes</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Toastify JS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- Custom CSS -->
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            background-color: #343a40;
            min-width: 250px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.5rem 1rem;
            margin: 0.2rem 0;
            border-radius: 0.25rem;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 500;
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        
        .content {
            flex: 1;
        }
        
        .navbar-brand {
            font-weight: bold;
        }
        
        .dropdown-menu {
            min-width: 240px;
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px;
                height: 100vh;
                z-index: 1030;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .content {
                margin-left: 0 !important;
            }
        }
        
        /* Card hover effect */
        .dashboard-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        
        /* Status badges */
        .badge.bg-status-pending { background-color: #ffc107; }
        .badge.bg-status-processing { background-color: #0d6efd; }
        .badge.bg-status-shipped { background-color: #0dcaf0; }
        .badge.bg-status-delivered { background-color: #198754; }
        .badge.bg-status-cancelled { background-color: #dc3545; }
        .badge.bg-status-refunded { background-color: #6c757d; }
        
        /* Timeline styling */
        .timeline {
            position: relative;
            padding-left: 1.5rem;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0.75rem;
            height: 100%;
            width: 1px;
            background-color: #e0e0e0;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-item-marker {
            position: absolute;
            left: -1.5rem;
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .timeline-item-marker-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 2rem;
            width: 2rem;
            border-radius: 100%;
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .timeline-item-marker-indicator i {
            font-size: 1rem;
        }
        
        .timeline-item-content {
            padding-left: 0.75rem;
            padding-top: 0.25rem;
        }
        
        .timeline-item-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .timeline-item-subtitle {
            font-size: 0.875rem;
            font-weight: 400;
            margin-bottom: 0.5rem;
            color: #6c757d;
        }
        
        .timeline-item-description {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle -->
    <button class="btn btn-dark d-md-none position-fixed top-0 end-0 mt-2 me-2 z-index-1030" type="button" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>
    
    <!-- Sidebar -->
    <div class="sidebar d-md-flex flex-column flex-shrink-0 p-3 text-white" id="sidebar">
        <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="bi bi-gem me-2"></i>
            <span class="fs-4">Elegant Drapes</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php" class="nav-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bag"></i>
                    Orders
                </a>
            </li>
            <li class="nav-item">
                <a href="products.php" class="nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam"></i>
                    Products
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                    <i class="bi bi-tags"></i>
                    Categories
                </a>
            </li>
            <li class="nav-item">
                <a href="customers.php" class="nav-link <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i>
                    Customers
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-graph-up"></i>
                    Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i>
                    Settings
                </a>
            </li>
        </ul>
        <hr>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="https://via.placeholder.com/32" alt="Admin" width="32" height="32" class="rounded-circle me-2">
                <strong><?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li class="dropdown-item">
                    <small class="text-muted d-block">Logged in as</small>
                    <span class="d-block"><?php echo $_SESSION['admin_email'] ?? 'admin@example.com'; ?></span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><a class="dropdown-item" href="../index.php" target="_blank">View Store</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Content -->
    <div class="content" id="content">
        <!-- Top navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
            <div class="container-fluid">
                <span class="navbar-brand mb-0 h1"><?php echo $page_title ?? 'Dashboard'; ?></span>
                
                <div class="d-flex">
                    <div class="dropdown me-2">
                        <button class="btn btn-light border position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                3
                                <span class="visually-hidden">unread notifications</span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="notificationsDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">New order received</a></li>
                            <li><a class="dropdown-item" href="#">Low stock alert: 5 products</a></li>
                            <li><a class="dropdown-item" href="#">New customer registered</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-primary" href="#">View all notifications</a></li>
                        </ul>
                    </div>
                    
                    <a href="logout.php" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="d-none d-md-inline-block ms-1">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
        
        <!-- Main content -->
        <div class="p-4">
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['flash_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_type']);
                ?>
            <?php endif; ?>
