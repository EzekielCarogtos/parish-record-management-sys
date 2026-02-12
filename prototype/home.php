<?php
/* session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
} */

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

/* ---------- DASHBOARD DATA ---------- */

$totalRecords = $pdo->query(
    "SELECT COUNT(*) FROM records"
)->fetchColumn();

$digitized = $pdo->query(
    "SELECT COUNT(*) FROM records WHERE digitized = true"
)->fetchColumn();

$verified = $pdo->query(
    "SELECT COUNT(*) FROM records WHERE verified = true"
)->fetchColumn();

$certificates = $pdo->query(
    "SELECT COUNT(*) FROM certificates"
)->fetchColumn();

$stmt = $pdo->query(
    "SELECT full_name, record_type, record_date, digitized, verified
     FROM records
     ORDER BY created_at DESC
     LIMIT 5"
);

$recentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parish Records Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100 font-sans">

    <!-- SIDEBAR -->

    <aside class="fixed top-0 left-0 h-screen w-64 bg-white border-r flex flex-col p-6">

        <div class="flex items-center mb-8">
            <div class="bg-blue-600 text-white rounded-lg p-2 mr-3">⛪</div>

            <div>
                <h6 class="font-bold">Parish Records</h6>
                <small class="text-gray-500 text-xs">
                    Management System
                </small>
            </div>
        </div>

        <?php
        $navItems = [
            "home.php"       => "Dashboard",
            "record.php"     => "Records",
            "new_record.php" => "New Record",
            "cert_req.php"   => "Certificate Request",
            "cert_hist.php"  => "Certificate History"
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
                    <div class="text-sm font-bold">
                        Parish Secretary
                    </div>
                    <div class="text-xs text-gray-500">
                        Secretary
                    </div>
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

        <h3 class="text-xl font-semibold mb-1">
            Dashboard
        </h3>

        <p class="text-gray-500 mb-6">
            Overview of parish sacramental records
        </p>

        <!-- STAT CARDS -->

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

            <div class="bg-white rounded-xl shadow-sm p-6">

                <div class="flex justify-between">

                    <div>
                        <div class="text-2xl font-bold">
                            <?= $totalRecords ?>
                        </div>

                        <div class="text-sm text-gray-500">
                            Total Records
                        </div>
                    </div>

                    📄

                </div>

            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">

                <div class="flex justify-between">

                    <div>
                        <div class="text-2xl font-bold text-green-600">
                            <?= $digitized ?>
                        </div>

                        <div class="text-sm text-gray-500">
                            Digitized
                        </div>
                    </div>

                    ✅

                </div>

            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">

                <div class="flex justify-between">

                    <div>
                        <div class="text-2xl font-bold text-blue-600">
                            <?= $verified ?>
                        </div>

                        <div class="text-sm text-gray-500">
                            Verified
                        </div>
                    </div>

                    🛡️

                </div>

            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">

                <div class="flex justify-between">

                    <div>
                        <div class="text-2xl font-bold text-purple-600">
                            <?= $certificates ?>
                        </div>

                        <div class="text-sm text-gray-500">
                            Certificates Issued
                        </div>
                    </div>

                    📜

                </div>

            </div>

        </div>

        <!-- RECENT RECORDS -->

        <div class="bg-white rounded-xl shadow-sm p-6">

            <h6 class="font-semibold mb-4">
                Recent Records
            </h6>

            <div class="divide-y">

                <?php if ($recentRecords): ?>

                    <?php foreach ($recentRecords as $record): ?>

                        <div class="flex justify-between items-center py-3">

                            <div>

                                <div class="font-semibold">
                                    <?= htmlspecialchars($record["full_name"]) ?>
                                </div>

                                <div class="text-sm text-gray-500">
                                    <?= $record["record_type"] ?> —
                                    <?= date("m/d/Y", strtotime($record["record_date"])) ?>
                                </div>

                            </div>

                            <div class="space-x-2">

                                <?php if ($record["digitized"]): ?>
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs">
                                        Digitized
                                    </span>
                                <?php endif; ?>

                                <?php if ($record["verified"]): ?>
                                    <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs">
                                        Verified
                                    </span>
                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="text-gray-500">
                        No records found.
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </main>

</body>
</html>
