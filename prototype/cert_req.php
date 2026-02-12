<?php
/* ---------- DATABASE CONNECTION ---------- */

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

/* ---------- SEARCH PROCESS ---------- */

$results = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $type = $_POST["cert_type"] ?? "";
    $name = "%" . ($_POST["name"] ?? "") . "%";
    $date = $_POST["date"] ?? "";

    $sql = "
        SELECT *
        FROM records
        WHERE record_type = :type
        AND full_name ILIKE :name
    ";

    if (!empty($date)) {
        $sql .= " AND record_date = :date";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(":type", $type);
    $stmt->bindValue(":name", $name);

    if (!empty($date)) {
        $stmt->bindValue(":date", $date);
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Request</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 font-sans">

<!-- SIDEBAR -->
<aside class="fixed top-0 left-0 h-screen w-64 bg-white border-r flex flex-col p-6">

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
                    ? "bg-blue-50 text-blue-600 font-semibold"
                    : "text-gray-600 hover:bg-blue-50 hover:text-blue-600" ?>"
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
            class="block text-center border border-red-500 text-red-500 rounded-md py-1 text-sm hover:bg-red-50"
        >
            Sign Out
        </a>
    </div>

</aside>

<!-- MAIN CONTENT -->
<main class="ml-64 p-8">

    <div class="bg-white rounded-xl shadow-sm p-6 max-w-3xl mx-auto">

        <h3 class="text-lg font-semibold mb-4">Search for Record</h3>

        <form method="POST" class="space-y-4">

            <div>
                <label class="text-sm font-semibold">Certificate Type *</label>
                <select
                    name="cert_type"
                    required
                    class="w-full border rounded p-2"
                >
                    <option value="">Select type</option>
                    <option>Baptism</option>
                    <option>Confirmation</option>
                    <option>Marriage</option>
                    <option>Funeral</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-semibold">Name *</label>
                <input
                    type="text"
                    name="name"
                    required
                    class="w-full border rounded p-2"
                    placeholder="Enter name"
                >
            </div>

            <div>
                <label class="text-sm font-semibold">Date of Sacrament</label>
                <input
                    type="date"
                    name="date"
                    class="w-full border rounded p-2"
                >
            </div>

            <div>
                <label class="text-sm font-semibold">Purpose of Request</label>
                <textarea
                    name="purpose"
                    class="w-full border rounded p-2"
                    rows="3"
                ></textarea>
            </div>

            <button
                type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
            >
                Search Records
            </button>

        </form>

    </div>

    <!-- SEARCH RESULTS -->

    <?php if (!empty($results)): ?>

        <div class="bg-white rounded-xl shadow-sm p-6 mt-6">

            <h4 class="font-semibold mb-3">Matching Records</h4>

            <?php foreach ($results as $row): ?>

                <div class="border-b py-3">

                    <div class="font-semibold">
                        <?= htmlspecialchars($row["full_name"]) ?>
                    </div>

                    <div class="text-sm text-gray-500">
                        <?= $row["record_type"] ?> —
                        <?= date("M d, Y", strtotime($row["record_date"])) ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php elseif ($_SERVER["REQUEST_METHOD"] === "POST"): ?>

        <div class="bg-white rounded-xl shadow-sm p-6 mt-6 text-gray-500">
            No matching records found.
        </div>

    <?php endif; ?>

</main>

</body>
</html>
