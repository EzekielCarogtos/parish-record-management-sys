<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Check if user is admin
if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== 'admin') {
    header("Location: home.php");
    exit;
}

/* ---------- DATABASE CONNECTION ---------- */

$host = "localhost";
$dbname = "parish_db";
$user = "postgres";
$password = "pass";

try {
    $pdo = new PDO(
        "pgsql:host=$host;dbname=$dbname",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed.");
}

/* ---------- CHECK TABLE COLUMNS ---------- */
// Check if reviewed_by column exists in certificate_requests
$hasReviewedBy = false;
try {
    $checkCol = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'certificate_requests' 
        AND column_name = 'reviewed_by'
    ");
    $hasReviewedBy = $checkCol->rowCount() > 0;
} catch (PDOException $e) {
    $hasReviewedBy = false;
}

// Check if reviewed_by column exists in edit_requests
$hasEditReviewedBy = false;
try {
    $checkEditCol = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'edit_requests' 
        AND column_name = 'reviewed_by'
    ");
    $hasEditReviewedBy = $checkEditCol->rowCount() > 0;
} catch (PDOException $e) {
    $hasEditReviewedBy = false;
}

// Check if status column exists in users table
$hasUserStatus = false;
try {
    $checkUserStatus = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'users' 
        AND column_name = 'status'
    ");
    $hasUserStatus = $checkUserStatus->rowCount() > 0;
} catch (PDOException $e) {
    $hasUserStatus = false;
}

/* ---------- MESSAGE HANDLING ---------- */
$message = '';
$messageType = '';

/* ---------- HANDLE USER ACTIONS ---------- */

// Create new user
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create_user'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'secretary';
    
    if ($name && $email && $password) {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $checkStmt->execute([':email' => $email]);
        
        if ($checkStmt->fetch()) {
            $message = 'Email already exists.';
            $messageType = 'error';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Check if status column exists
            if ($hasUserStatus) {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, password, role, status, created_at) 
                     VALUES (:name, :email, :password, :role, 'active', NOW())"
                );
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, password, role, created_at) 
                     VALUES (:name, :email, :password, :role, NOW())"
                );
            }
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':role' => $role
            ]);
            
            $message = 'User created successfully.';
            $messageType = 'success';
        }
    } else {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    }
}

// Edit user
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_user'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'secretary';
    
    if ($userId && $name && $email) {
        // Check if email exists for another user
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $checkStmt->execute([':email' => $email, ':id' => $userId]);
        
        if ($checkStmt->fetch()) {
            $message = 'Email already exists for another user.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare(
                "UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id"
            );
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':id' => $userId
            ]);
            
            $message = 'User updated successfully.';
            $messageType = 'success';
        }
    }
}

// Deactivate/Activate user (only if status column exists)
if ($hasUserStatus && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['toggle_user_status'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($userId && $action) {
        $newStatus = ($action === 'deactivate') ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $userId
        ]);
        
        $message = 'User ' . $action . 'd successfully.';
        $messageType = 'success';
    }
}

/* ---------- HANDLE CERTIFICATE REQUESTS ---------- */

// Approve certificate request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['approve_certificate'])) {
    $requestId = intval($_POST['request_id'] ?? 0);
    
    // Check if already processed
    $checkStmt = $pdo->prepare("SELECT status FROM certificate_requests WHERE id = :id");
    $checkStmt->execute([':id' => $requestId]);
    $current = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current && $current['status'] === 'pending') {
        if ($hasReviewedBy) {
            $stmt = $pdo->prepare(
                "UPDATE certificate_requests 
                 SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW() 
                 WHERE id = :id"
            );
            $stmt->execute([
                ':admin_id' => $_SESSION['user_id'],
                ':id' => $requestId
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE certificate_requests 
                 SET status = 'approved', reviewed_at = NOW() 
                 WHERE id = :id"
            );
            $stmt->execute([':id' => $requestId]);
        }
        
        $message = 'Certificate request approved successfully.';
        $messageType = 'success';
    } else {
        $message = 'This request has already been processed.';
        $messageType = 'warning';
    }
}

// Reject certificate request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reject_certificate'])) {
    $requestId = intval($_POST['request_id'] ?? 0);
    
    // Check if already processed
    $checkStmt = $pdo->prepare("SELECT status FROM certificate_requests WHERE id = :id");
    $checkStmt->execute([':id' => $requestId]);
    $current = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current && $current['status'] === 'pending') {
        if ($hasReviewedBy) {
            $stmt = $pdo->prepare(
                "UPDATE certificate_requests 
                 SET status = 'rejected', reviewed_by = :admin_id, reviewed_at = NOW() 
                 WHERE id = :id"
            );
            $stmt->execute([
                ':admin_id' => $_SESSION['user_id'],
                ':id' => $requestId
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE certificate_requests 
                 SET status = 'rejected', reviewed_at = NOW() 
                 WHERE id = :id"
            );
            $stmt->execute([':id' => $requestId]);
        }
        
        $message = 'Certificate request rejected.';
        $messageType = 'success';
    } else {
        $message = 'This request has already been processed.';
        $messageType = 'warning';
    }
}

/* ---------- HANDLE EDIT REQUESTS ---------- */

// Approve edit request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['approve_edit'])) {
    $editId = intval($_POST['edit_id'] ?? 0);
    
    // Check if already processed
    $checkStmt = $pdo->prepare("SELECT status, record_id, requested_changes FROM edit_requests WHERE id = :id");
    $checkStmt->execute([':id' => $editId]);
    $edit = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit && $edit['status'] === 'pending') {
        // Update edit request status
        if ($hasEditReviewedBy) {
            $editStmt = $pdo->prepare(
                "UPDATE edit_requests SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id"
            );
            $editStmt->execute([':admin_id' => $_SESSION['user_id'], ':id' => $editId]);
        } else {
            $editStmt = $pdo->prepare(
                "UPDATE edit_requests SET status = 'approved', reviewed_at = NOW() WHERE id = :id"
            );
            $editStmt->execute([':id' => $editId]);
        }
        
        // Apply the changes to the record
        $changes = json_decode($edit['requested_changes'], true);
        $setClause = [];
        $params = [':record_id' => $edit['record_id']];
        
        foreach ($changes as $field => $value) {
            $setClause[] = "$field = :$field";
            $params[":$field"] = $value;
        }
        
        if (!empty($setClause)) {
            $updateSql = "UPDATE records SET " . implode(', ', $setClause) . " WHERE id = :record_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($params);
        }
        
        $message = 'Edit request approved and changes applied.';
        $messageType = 'success';
    } else {
        $message = 'This edit request has already been processed.';
        $messageType = 'warning';
    }
}

// Reject edit request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reject_edit'])) {
    $editId = intval($_POST['edit_id'] ?? 0);
    
    // Check if already processed
    $checkStmt = $pdo->prepare("SELECT status FROM edit_requests WHERE id = :id");
    $checkStmt->execute([':id' => $editId]);
    $edit = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit && $edit['status'] === 'pending') {
        if ($hasEditReviewedBy) {
            $editStmt = $pdo->prepare(
                "UPDATE edit_requests SET status = 'rejected', reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id"
            );
            $editStmt->execute([':admin_id' => $_SESSION['user_id'], ':id' => $editId]);
        } else {
            $editStmt = $pdo->prepare(
                "UPDATE edit_requests SET status = 'rejected', reviewed_at = NOW() WHERE id = :id"
            );
            $editStmt->execute([':id' => $editId]);
        }
        
        $message = 'Edit request rejected.';
        $messageType = 'success';
    } else {
        $message = 'This edit request has already been processed.';
        $messageType = 'warning';
    }
}

/* ---------- FETCH DASHBOARD DATA ---------- */

// Get pending certificate requests count (only pending)
$pendingRequestsCount = 0;
try {
    $countStmt = $pdo->query(
        "SELECT COUNT(*) FROM certificate_requests WHERE status = 'pending'"
    );
    $pendingRequestsCount = $countStmt->fetchColumn();
} catch (PDOException $e) {
    $pendingRequestsCount = 0;
}

// Get total users (ALL non-admin users - secretaries)
$totalUsers = 0;
try {
    // First check if role column exists
    $checkRoleCol = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'users' 
        AND column_name = 'role'
    ");
    
    if ($checkRoleCol->rowCount() > 0) {
        $totalUsers = $pdo->query(
            "SELECT COUNT(*) FROM users WHERE role != 'admin' OR role IS NULL"
        )->fetchColumn();
    } else {
        $totalUsers = $pdo->query(
            "SELECT COUNT(*) FROM users"
        )->fetchColumn();
    }
} catch (PDOException $e) {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

$totalRecords = $pdo->query(
    "SELECT COUNT(*) FROM records"
)->fetchColumn();

$totalCertificates = 0;
try {
    $totalCertificates = $pdo->query(
        "SELECT COUNT(*) FROM certificates"
    )->fetchColumn();
} catch (PDOException $e) {
    $totalCertificates = 0;
}

// Recent records
$stmt = $pdo->query(
    "SELECT full_name, record_type, record_date, created_at
     FROM records
     ORDER BY created_at DESC
     LIMIT 5"
);
$recentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all users (including secretaries)
$allUsers = [];
try {
    // Check what columns exist
    $columns = [];
    $colQuery = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'users'
    ");
    while ($col = $colQuery->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['column_name'];
    }
    
    // Build query based on existing columns
    $selectFields = ['id', 'name', 'email', 'created_at'];
    
    if (in_array('role', $columns)) {
        $selectFields[] = 'role';
    }
    if (in_array('status', $columns)) {
        $selectFields[] = 'status';
    }
    
    $selectClause = implode(', ', $selectFields);
    
    // Get all users, ordered by creation date
    $userStmt = $pdo->query(
        "SELECT $selectClause 
         FROM users 
         ORDER BY created_at DESC"
    );
    $allUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // If error, try simple query
    try {
        $userStmt = $pdo->query(
            "SELECT id, name, email, created_at 
             FROM users 
             ORDER BY created_at DESC"
        );
        $allUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $allUsers = [];
    }
}

// Fetch pending certificate requests with duplicate check
$pendingRequests = [];
try {
    // Check if certificate_requests table exists and has required columns
    $reqStmt = $pdo->prepare(
        "SELECT cr.* 
         FROM certificate_requests cr
         WHERE cr.status = 'pending'
         ORDER BY cr.requested_at DESC
         LIMIT 10"
    );
    $reqStmt->execute();
    $pendingRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add has_approved flag by checking if there are any approved requests for the same record
    foreach ($pendingRequests as &$req) {
        $checkStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM certificate_requests 
             WHERE record_id = :record_id 
             AND status = 'approved'"
        );
        $checkStmt->execute([':record_id' => $req['record_id'] ?? 0]);
        $req['has_approved'] = $checkStmt->fetchColumn();
    }
} catch (PDOException $e) {
    $pendingRequests = [];
}

// Fetch pending edit requests
$pendingEdits = [];
try {
    // Check if edit_requests table exists
    $editStmt = $pdo->query(
        "SELECT er.id, er.record_id, r.full_name, r.record_type, 
                er.reason, er.requested_changes, er.requested_at, u.name as requested_by
         FROM edit_requests er
         JOIN records r ON er.record_id = r.id
         JOIN users u ON er.requested_by = u.id
         WHERE er.status = 'pending'
         ORDER BY er.requested_at DESC
         LIMIT 10"
    );
    $pendingEdits = $editStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add has_approved flag
    foreach ($pendingEdits as &$edit) {
        $checkStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM edit_requests 
             WHERE record_id = :record_id 
             AND status = 'approved'"
        );
        $checkStmt->execute([':record_id' => $edit['record_id']]);
        $edit['has_approved'] = $checkStmt->fetchColumn();
    }
} catch (PDOException $e) {
    $pendingEdits = [];
}

$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Parish Records Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            background: white;
            border-right: 1px solid #e5e7eb;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 2rem;
        }
        
        .nav-link {
            color: #6b7280;
            border-radius: 8px;
            margin-bottom: 5px;
            padding: 10px 15px;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: #eef4ff;
            color: #3b82f6;
        }
        
        .nav-link.active {
            font-weight: 600;
        }
        
        .stat-card {
            padding: 1.5rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .status-badge-online {
            background-color: #d1e7dd;
            color: #0f5132;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-badge-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge-approved {
            background-color: #d1e7dd;
            color: #0f5132;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-badge-rejected {
            background-color: #f8d7da;
            color: #721c24;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="flex items-center mb-8 px-2">
            <div class="bg-blue-600 text-white rounded-lg p-2 mr-3">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-3h12v3H4z"></path>
                </svg>
            </div>
            <div>
                <h6 class="font-bold text-gray-900">Admin Panel</h6>
                <small class="text-gray-500 text-xs">Management</small>
            </div>
        </div>

        <nav class="flex flex-col flex-1">
            <a href="#" class="nav-link active" onclick="showSection('overview'); return false;">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                    <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                </svg>
                System Overview
            </a>
            <a href="#" class="nav-link" onclick="showSection('manage-users'); return false;">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                </svg>
                Manage Staff
            </a>
            <a href="#" class="nav-link" onclick="showSection('pending-approvals'); return false;">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Pending Approvals
                <?php if ($pendingRequestsCount > 0): ?>
                    <span class="ml-auto bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-0.5 rounded-full">
                        <?= $pendingRequestsCount ?>
                    </span>
                <?php endif; ?>
            </a>
        </nav>

        <div class="border-t border-gray-200 pt-4">
            <div class="flex items-center mb-3 px-2">
                <div class="bg-gray-200 rounded-full p-2 mr-3">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900">Administrator</div>
                    <div class="text-xs text-gray-500">Admin</div>
                </div>
            </div>
            <a href="login.php" class="block text-center border border-red-500 text-red-500 rounded-md py-2 text-sm hover:bg-red-50 transition duration-200">
                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V7.414l-5-5H3zm7 10a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"></path>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($message): ?>
            <div class="mb-4 p-4 rounded-lg <?= 
                $messageType === 'success' ? 'bg-green-100 text-green-700' : 
                ($messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') 
            ?> flex justify-between items-center">
                <span><?= htmlspecialchars($message) ?></span>
                <button onclick="this.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- Overview Section -->
        <section id="admin-dashboard">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">System Status</h3>
                    <p class="text-gray-500">Admin overview and system management</p>
                </div>
                <div class="status-badge-online">
                    <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <circle cx="10" cy="10" r="8"/>
                    </svg>
                    System Online
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="stat-card">
                    <div class="flex justify-between">
                        <div>
                            <div class="stat-number"><?= $totalUsers ?></div>
                            <small class="text-gray-500">Total Staff Users</small>
                        </div>
                        <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex justify-between">
                        <div>
                            <div class="stat-number text-green-600"><?= $totalRecords ?></div>
                            <small class="text-gray-500">Total Records</small>
                        </div>
                        <svg class="w-8 h-8 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex justify-between">
                        <div>
                            <div class="stat-number text-blue-600"><?= $totalCertificates ?></div>
                            <small class="text-gray-500">Certificates Issued</small>
                        </div>
                        <svg class="w-8 h-8 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex justify-between">
                        <div>
                            <div class="stat-number text-yellow-600"><?= $pendingRequestsCount ?></div>
                            <small class="text-gray-500">Pending Requests</small>
                        </div>
                        <svg class="w-8 h-8 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h6 class="font-semibold text-gray-900 mb-4">Recent Records Created</h6>
                <div class="space-y-3">
                    <?php if ($recentRecords): ?>
                        <?php foreach ($recentRecords as $record): ?>
                        <div class="border-b border-gray-100 pb-3 last:border-0">
                            <div class="text-xs text-gray-400 mb-1"><?= date("F d, Y - h:i A", strtotime($record["created_at"])) ?></div>
                            <div class="font-medium"><?= htmlspecialchars($record["full_name"]) ?> 
                                <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-800 text-xs rounded-full"><?= $record["record_type"] ?></span>
                            </div>
                            <div class="text-xs text-gray-400">Record Date: <?= $record["record_date"] ? date("M d, Y", strtotime($record["record_date"])) : "N/A" ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-gray-400 text-center py-4">No records created yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Manage Users Section -->
        <section id="manage-users" style="display:none;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-900">Staff Management</h3>
                <button onclick="openCreateUserModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 text-sm flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                    </svg>
                    Add New Staff
                </button>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($allUsers)): ?>
                            <?php foreach ($allUsers as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap font-medium"><?= htmlspecialchars($user['name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?= htmlspecialchars($user['email']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?= ($user['role'] ?? 'secretary') === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                                        <?= ucfirst(htmlspecialchars($user['role'] ?? 'secretary')) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?= ($user['status'] ?? 'active') === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= ucfirst($user['status'] ?? 'active') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                    <button onclick='editUser(<?= htmlspecialchars(json_encode($user)) ?>)' class="text-blue-600 hover:text-blue-800">
                                        <svg class="w-5 h-5 inline" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                                        </svg>
                                    </button>
                                    <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" name="toggle_user_status" class="text-red-600 hover:text-red-800">
                                            <svg class="w-5 h-5 inline" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path>
                                            </svg>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" name="toggle_user_status" class="text-green-600 hover:text-green-800">
                                            <svg class="w-5 h-5 inline" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Pending Approvals Section -->
        <section id="pending-approvals" style="display:none;">
            <div class="mb-4">
                <h3 class="text-xl font-semibold text-gray-900">Pending Approvals</h3>
                <p class="text-gray-500">Certificate requests and record edits awaiting review</p>
            </div>

            <!-- Certificate Requests -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h6 class="font-semibold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path>
                    </svg>
                    Pending Certificate Requests
                </h6>
                <?php if (!empty($pendingRequests)): ?>
                    <div class="space-y-4">
                        <?php foreach ($pendingRequests as $req): ?>
                        <div class="flex justify-between items-start border-b border-gray-100 pb-4">
                            <div class="flex-1">
                                <div class="font-semibold text-lg"><?= htmlspecialchars($req['full_name']) ?></div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-xs rounded-full"><?= htmlspecialchars($req['cert_type']) ?></span>
                                    <span class="ml-2 text-gray-400">Requested: <?= date('M d, Y', strtotime($req['requested_at'])) ?></span>
                                    <?php if ($req['has_approved'] > 0): ?>
                                        <span class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded-full">Previously Approved</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($req['purpose'])): ?>
                                    <div class="text-sm text-gray-500">Purpose: <?= htmlspecialchars($req['purpose']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex space-x-2 ml-4">
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <button type="submit" name="approve_certificate" 
                                            class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm <?= $req['has_approved'] > 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                            <?= $req['has_approved'] > 0 ? 'disabled' : '' ?>>
                                        Approve
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <button type="submit" name="reject_certificate" class="px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-gray-400 text-center py-4">No pending certificate requests.</div>
                <?php endif; ?>
            </div>

            <!-- Edit Requests -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h6 class="font-semibold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                    </svg>
                    Pending Record Edits
                </h6>
                <?php if (!empty($pendingEdits)): ?>
                    <div class="space-y-4">
                        <?php foreach ($pendingEdits as $edit): ?>
                        <div class="flex justify-between items-start border-b border-gray-100 pb-4">
                            <div class="flex-1">
                                <div class="font-semibold text-lg"><?= htmlspecialchars($edit['full_name']) ?></div>
                                <div class="text-sm text-gray-600 mb-1">
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded-full"><?= htmlspecialchars($edit['record_type']) ?> Record</span>
                                    <span class="ml-2 text-gray-400">Requested: <?= date('M d, Y', strtotime($edit['requested_at'])) ?></span>
                                    <?php if ($edit['has_approved'] > 0): ?>
                                        <span class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded-full">Previously Edited</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-500 mb-1">By: <?= htmlspecialchars($edit['requested_by']) ?></div>
                                <?php if (!empty($edit['reason'])): ?>
                                    <div class="text-sm text-gray-500">Reason: <?= htmlspecialchars($edit['reason']) ?></div>
                                <?php endif; ?>
                                <details class="mt-2">
                                    <summary class="text-blue-600 text-sm cursor-pointer hover:text-blue-800">View Changes</summary>
                                    <div class="bg-gray-50 p-3 mt-2 rounded text-sm">
                                        <?php 
                                            $changes = json_decode($edit['requested_changes'], true);
                                            if (is_array($changes)) {
                                                foreach ($changes as $field => $value) {
                                                    echo "<div><span class='font-medium'>" . htmlspecialchars($field) . ":</span> " . htmlspecialchars($value) . "</div>";
                                                }
                                            }
                                        ?>
                                    </div>
                                </details>
                            </div>
                            <div class="flex space-x-2 ml-4">
                                <form method="POST">
                                    <input type="hidden" name="edit_id" value="<?= $edit['id'] ?>">
                                    <button type="submit" name="approve_edit" 
                                            class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm <?= $edit['has_approved'] > 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                            <?= $edit['has_approved'] > 0 ? 'disabled' : '' ?>>
                                        Approve
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="edit_id" value="<?= $edit['id'] ?>">
                                    <button type="submit" name="reject_edit" class="px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-gray-400 text-center py-4">No pending record edits.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Create User Modal -->
    <div id="createUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-full max-w-md">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-semibold">Create New Staff Account</h3>
                    <button onclick="closeCreateUserModal()" class="text-gray-600 hover:text-gray-800 text-2xl">&times;</button>
                </div>
                <form method="POST">
                    <div class="p-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" name="name" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" name="password" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="secretary">Secretary</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end p-4 border-t space-x-2">
                        <button type="button" onclick="closeCreateUserModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="create_user" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-full max-w-md">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-semibold">Edit Staff Account</h3>
                    <button onclick="closeEditUserModal()" class="text-gray-600 hover:text-gray-800 text-2xl">&times;</button>
                </div>
                <form method="POST">
                    <div class="p-4 space-y-4">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" name="name" id="edit_name" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" id="edit_email" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" id="edit_role" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="secretary">Secretary</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end p-4 border-t space-x-2">
                        <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="edit_user" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show/hide sections
        function showSection(sectionId) {
            // Hide all sections
            document.getElementById('admin-dashboard').style.display = 'none';
            document.getElementById('manage-users').style.display = 'none';
            document.getElementById('pending-approvals').style.display = 'none';

            // Update Nav Links
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));

            // Show target section
            if (sectionId === 'overview') {
                document.getElementById('admin-dashboard').style.display = 'block';
                document.querySelector('[onclick*="overview"]').classList.add('active');
            } else if (sectionId === 'manage-users') {
                document.getElementById('manage-users').style.display = 'block';
                document.querySelector('[onclick*="manage-users"]').classList.add('active');
            } else if (sectionId === 'pending-approvals') {
                document.getElementById('pending-approvals').style.display = 'block';
                document.querySelector('[onclick*="pending-approvals"]').classList.add('active');
            }
        }

        // Modal functions
        function openCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('hidden');
        }

        function closeCreateUserModal() {
            document.getElementById('createUserModal').classList.add('hidden');
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role || 'secretary';
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100').forEach(function(alert) {
                if (alert.classList.contains('mb-4')) {
                    alert.style.display = 'none';
                }
            });
        }, 5000);

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('bg-opacity-50')) {
                event.target.classList.add('hidden');
            }
        }
    </script>
</body>
</html>