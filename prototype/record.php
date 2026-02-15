<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

/* ---------- DATABASE ---------- */

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

/* ---------- FILTER LOGIC ---------- */

$name = $_GET["name"] ?? "";
$type = $_GET["type"] ?? "";
$year = $_GET["year"] ?? "";

$sql = "SELECT * FROM records WHERE 1=1";
$params = [];

if ($name) {
    $sql .= " AND full_name ILIKE :name";
    $params[":name"] = "%$name%";
}

if ($type && $type !== "All") {
    $sql .= " AND record_type = :type";
    $params[":type"] = $type;
}

if ($year) {
    $sql .= " AND EXTRACT(YEAR FROM record_date) = :year";
    $params[":year"] = $year;
}

$sql .= " ORDER BY record_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle approval request submission
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['request_approval'])) {
    $recordId = intval($_POST['record_id'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $requesterId = $_SESSION['user_id'];

    // get record info
    $rstmt = $pdo->prepare("SELECT id, full_name, record_type FROM records WHERE id = :id");
    $rstmt->execute([':id' => $recordId]);
    $rec = $rstmt->fetch(PDO::FETCH_ASSOC);

    if ($rec) {
        $ins = $pdo->prepare(
            "INSERT INTO certificate_requests
                (requester_id, record_id, full_name, cert_type, purpose, status, requested_at)
             VALUES
                (:requester_id, :record_id, :full_name, :cert_type, :purpose, 'pending', now())"
        );

        $ins->execute([
            ':requester_id' => $requesterId,
            ':record_id' => $rec['id'],
            ':full_name' => $rec['full_name'],
            ':cert_type' => $rec['record_type'],
            ':purpose' => $purpose
        ]);

        $message = 'Approval requested successfully.';
    } else {
        $message = 'Record not found.';
    }

    // Refresh records list
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle edit request submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['request_edit'])) {
    $recordId = intval($_POST['record_id'] ?? 0);
    $requesterId = $_SESSION['user_id'];
    $reason = trim($_POST['edit_reason'] ?? '');
    
    // Get current record data
    $rstmt = $pdo->prepare("SELECT * FROM records WHERE id = :id");
    $rstmt->execute([':id' => $recordId]);
    $record = $rstmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        // Only secretary (creator) can request edits
        if ($record['created_by'] != $requesterId && $_SESSION['user_role'] !== 'admin') {
            $message = 'You can only edit your own records.';
        } else {
            // Collect the changes
            $changes = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'edit_') === 0) {
                    $fieldName = str_replace('edit_', '', $key);
                    if (!isset($record[$fieldName]) || $record[$fieldName] != $value) {
                        $changes[$fieldName] = $value;
                    }
                }
            }
            
            if (empty($changes)) {
                $message = 'No changes detected.';
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO edit_requests
                        (record_id, requested_by, previous_data, requested_changes, reason, status, requested_at)
                     VALUES
                        (:record_id, :requested_by, :previous_data, :requested_changes, :reason, 'pending', NOW())"
                );
                
                $ins->execute([
                    ':record_id' => $recordId,
                    ':requested_by' => $requesterId,
                    ':previous_data' => json_encode(array_intersect_key($record, array_flip(array_keys($changes)))),
                    ':requested_changes' => json_encode($changes),
                    ':reason' => $reason
                ]);
                
                $message = 'Edit request submitted for admin approval.';
            }
        }
    }
    
    // Refresh records list
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 font-sans">

    <!-- SIDEBAR -->

    <aside class="fixed left-0 top-0 h-screen w-64 bg-white border-r flex flex-col p-6">

        <div class="flex items-center mb-8">
            <div class="bg-blue-600 text-white rounded-lg p-2 mr-3">⛪</div>
            <div>
                <h6 class="font-bold">Parish Records</h6>
                <small class="text-gray-500 text-xs">Management System</small>
            </div>
        </div>

        <?php
        $navItems = [
            "home.php" => "Dashboard",
            "record.php" => "Records",
            "new_record.php" => "New Record",
            "cert_req.php" => "Certificate Request",
            "cert_hist.php" => "Certificate History"
        ];
        ?>

        <nav class="flex flex-col space-y-1 flex-1">
            <?php foreach ($navItems as $file => $label): ?>
                <?php $active = ($currentPage === $file); ?>

                <a
                    href="<?= $file ?>"
                    class="px-4 py-2 rounded-lg transition <?= $active
                        ? 'bg-blue-50 text-blue-600 font-semibold'
                        : 'text-gray-600 hover:bg-blue-50 hover:text-blue-600' ?>"
                >
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="border-t pt-4">

            <div class="flex items-center mb-3">
                <div class="bg-gray-200 rounded-full p-2 mr-3">👤</div>
                <div>
                    <div class="text-sm font-bold">Parish Secretary</div>
                    <div class="text-xs text-gray-500">Secretary</div>
                </div>
            </div>

            <a
                href="login.php"
                class="block text-center border border-red-500 text-red-500 rounded py-1 text-sm hover:bg-red-50"
            >
                Sign Out
            </a>

        </div>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="ml-64 p-8">

        <h3 class="text-xl font-semibold mb-1">Sacramental Records</h3>
        <p class="text-gray-500 mb-6">Search and view parish records</p>

        <!-- FILTER FORM -->

        <form
            method="GET"
            class="bg-white p-6 rounded-xl shadow-sm mb-6 grid md:grid-cols-4 gap-4"
        >

            <div>
                <label class="text-sm font-semibold">Name</label>
                <input
                    type="text"
                    name="name"
                    value="<?= htmlspecialchars($name) ?>"
                    class="w-full border rounded p-2"
                >
            </div>

            <div>
                <label class="text-sm font-semibold">Type</label>
                <select name="type" class="w-full border rounded p-2">
                    <option value="All">All</option>

                    <?php foreach (["Baptism", "Confirmation", "Marriage", "Funeral"] as $t): ?>
                        <option <?= $type === $t ? "selected" : "" ?>>
                            <?= $t ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label class="text-sm font-semibold">Year</label>
                <input
                    type="number"
                    name="year"
                    value="<?= htmlspecialchars($year) ?>"
                    class="w-full border rounded p-2"
                >
            </div>

            <div class="flex items-end gap-2">

                <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Search
                </button>

                <a href="record.php" class="border px-4 py-2 rounded">
                    Reset
                </a>

            </div>

        </form>

        <!-- RECORD LIST -->

        <div class="bg-white rounded-xl shadow-sm p-6">

            <?php if ($message): ?>
                <div class="mb-4 bg-green-100 text-green-700 p-3 rounded"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (empty($records)): ?>
                <p class="text-gray-500">No records found.</p>
            <?php else: ?>

                <?php foreach ($records as $r): ?>

                    <div class="border-b py-4">

                        <div class="flex justify-between">

                            <div>

                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">
                                    <?= htmlspecialchars($r["record_type"]) ?>
                                </span>

                                <h4 class="font-semibold mt-2">
                                    <?= htmlspecialchars($r["full_name"]) ?>
                                </h4>

                                <p class="text-sm text-gray-500">
                                    Date:
                                    <?= $r["record_date"]
                                        ? date("M d, Y", strtotime($r["record_date"]))
                                        : "N/A" ?>
                                </p>

                            </div>

                            <div class="text-right text-sm text-gray-500">
                                ID: <?= (int)$r["id"] ?>
                            </div>

                        </div>

                        <div class="mt-3 flex items-center gap-3">
                            <?php if (!empty($r["digitized"])): ?>
                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs">Digitized</span>
                            <?php endif; ?>

                            <?php if (!empty($r["verified"])): ?>
                                <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs">Verified</span>
                            <?php endif; ?>

                            <form method="POST" class="d-inline-block ms-auto">
                                <input type="hidden" name="record_id" value="<?= (int)$r['id'] ?>">
                                <input type="text" name="purpose" placeholder="Reason (optional)" class="border rounded px-2 py-1 text-sm">
                                <button type="submit" name="request_approval" class="bg-yellow-500 text-white px-3 py-1 rounded ms-2 text-sm">Request Admin Approval</button>
                            </form>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </main>

</body>
</html>
