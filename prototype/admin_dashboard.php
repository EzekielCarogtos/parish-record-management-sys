<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

/* ---------- DATABASE CONNECTION ---------- */

$host = "localhost";
$dbname = "parish_db";
$user = "postgres";
$password = "123456";

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

/* ---------- ADMIN DASHBOARD DATA ---------- */

$totalUsers = $pdo->query(
    "SELECT COUNT(*) FROM users"
)->fetchColumn();

$totalRecords = $pdo->query(
    "SELECT COUNT(*) FROM records"
)->fetchColumn();

$totaCertificates = $pdo->query(
    "SELECT COUNT(*) FROM certificates"
)->fetchColumn();

$stmt = $pdo->query(
    "SELECT full_name, record_type, record_date, created_at
     FROM records
     ORDER BY created_at DESC
     LIMIT 5"
);
$recentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending certificate requests (if the table exists)
$pendingRequests = [];
try {
    $reqStmt = $pdo->query(
        "SELECT id, full_name, cert_type, purpose, requested_at
         FROM certificate_requests
         WHERE status = 'pending'
         ORDER BY requested_at DESC
         LIMIT 5"
    );
    $pendingRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // table may not exist yet — treat as no pending requests
    $pendingRequests = [];
}

$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Parish Records Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-blue: #0d6efd;
            --bg-light: #f8f9fa;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar Styling */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            background: white;
            border-right: 1px solid #dee2e6;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
        }

        .nav-link {
            color: #6c757d;
            border-radius: 8px;
            margin-bottom: 5px;
            padding: 10px 15px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            background-color: #eef4ff;
            color: var(--primary-blue);
        }

        .nav-link.active { font-weight: 600; }

        /* Content Area */
        #main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card {
            padding: 1.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-online {
            background-color: #d1e7dd;
            color: #0f5132;
        }
    </style>
</head>
<body>

    <nav id="sidebar">
        <div class="d-flex align-items-center mb-4 px-2">
            <div class="bg-primary text-white rounded p-2 me-2">
                <i class="bi bi-shield-lock fs-4"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold">Admin Panel</h6>
                <small class="text-muted">Management</small>
            </div>
        </div>

        <div class="nav flex-column mb-auto">
            <a href="#" class="nav-link active" onclick="showSection('overview'); return false;">
                <i class="bi bi-speedometer2 me-2"></i> System Overview
            </a>
            <a href="#" class="nav-link" onclick="showSection('manage-users'); return false;">
                <i class="bi bi-people me-2"></i> Manage Staff
            </a>
            <a href="cert_hist.php" class="nav-link">
                <i class="bi bi-file-earmark me-2"></i> Certificate History
            </a>
        </div>

        <div class="mt-auto border-top pt-3">
            <div class="d-flex align-items-center mb-3 px-2">
                <div class="bg-light rounded-circle p-2 me-2">
                    <i class="bi bi-person-admin"></i>
                </div>
                <div>
                    <div class="small fw-bold">Administrator</div>
                    <div class="text-muted" style="font-size: 0.75rem;">Admin</div>
                </div>
            </div>
            <a href="login.php" class="btn btn-outline-danger w-100 btn-sm text-decoration-none">
                <i class="bi bi-box-arrow-left me-1"></i> Logout
            </a>
        </div>
    </nav>

    <main id="main-content">
        <section id="admin-dashboard">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>System Status</h3>
                    <p class="text-muted">Admin overview and system management</p>
                </div>
                <div class="status-badge status-online">
                    <i class="bi bi-circle-fill me-1"></i> System Online
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-number"><?= $totalUsers ?></div>
                                <small class="text-muted">Total Registered Users</small>
                            </div>
                            <i class="bi bi-people text-muted fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-number text-success"><?= $totalRecords ?></div>
                                <small class="text-muted">Total Records</small>
                            </div>
                            <i class="bi bi-file-earmark text-success fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-number text-info"><?= $totaCertificates ?></div>
                                <small class="text-muted">Certificates Issued</small>
                            </div>
                            <i class="bi bi-file-text text-info fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-number text-warning">0</div>
                                <small class="text-muted">Pending Requests</small>
                            </div>
                            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($pendingRequests)): ?>
            <div class="card p-4 mb-4">
                <h6 class="mb-4">Pending Approvals</h6>
                <div class="list-group list-group-flush">
                    <?php foreach ($pendingRequests as $req): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start px-0 py-3 border-bottom">
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($req['full_name']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($req['cert_type']) ?> — requested <?= date('M d, Y', strtotime($req['requested_at'])) ?></div>
                            <?php if (!empty($req['purpose'])): ?>
                                <div class="text-sm">Purpose: <?= htmlspecialchars($req['purpose']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <a href="cert_req.php?approve=<?= $req['id'] ?>" class="btn btn-sm btn-success mb-1">Approve</a>
                            <a href="cert_req.php?reject=<?= $req['id'] ?>" class="btn btn-sm btn-danger">Reject</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card p-4">
                <h6 class="mb-4">Recent Records Created</h6>
                <div class="list-group list-group-flush">
                    <?php if ($recentRecords): ?>
                        <?php foreach ($recentRecords as $record): ?>
                        <div class="list-group-item px-0 py-3 border-bottom">
                            <div class="fw-bold small text-muted"><?= date("F d, Y - h:i A", strtotime($record["created_at"])) ?></div>
                            <div><?= htmlspecialchars($record["full_name"]) ?> - <span class="badge bg-info"><?= $record["record_type"] ?></span></div>
                            <div class="text-muted small">Record Date: <?= $record["record_date"] ? date("M d, Y", strtotime($record["record_date"])) : "N/A" ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="text-muted">No records created yet.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="manage-users" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Staff Management</h3>
                <button class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i> Add New Secretary</button>
            </div>
            <div class="card p-0 overflow-hidden">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-4 fw-bold">Parish Secretary</td>
                            <td>secretary@parish.org</td>
                            <td>Feb 12, 2026</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // Show/hide sections
        function showSection(sectionId) {
            // Hide all sections
            document.getElementById('admin-dashboard').style.display = 'none';
            document.getElementById('manage-users').style.display = 'none';

            // Update Nav Links
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));

            // Show target section
            if (sectionId === 'overview') {
                document.getElementById('admin-dashboard').style.display = 'block';
                document.querySelector('[onclick*="overview"]').classList.add('active');
            } else if (sectionId === 'manage-users') {
                document.getElementById('manage-users').style.display = 'block';
                document.querySelector('[onclick*="manage-users"]').classList.add('active');
            }
        }
    </script>

</body>
</html>
