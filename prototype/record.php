<?php
/* session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
} */

/* ---------- DATABASE ---------- */

$host = "localhost";
$dbname = "parish_db";
$user = "postgres";
$password = "password";

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

            <?php if (!$records): ?>
                <p class="text-gray-500">No records found.</p>
            <?php endif; ?>

            <?php foreach ($records as $r): ?>

                <div class="border-b py-4 flex justify-between">

                    <div>

                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">
                            <?= $r["record_type"] ?>
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
                        ID: <?= $r["id"] ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </main>

</body>
</html>
