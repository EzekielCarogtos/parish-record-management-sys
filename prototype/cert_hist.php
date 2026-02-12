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

/* ---------- FETCH CERTIFICATE HISTORY ---------- */

$stmt = $pdo->query(
    "SELECT certificate_type, holder_name, issued_at
     FROM certificates
     ORDER BY issued_at DESC"
);

$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate History</title>

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
            <a href="<?= $file ?>"
               class="px-4 py-2 rounded-lg transition
               <?= $active
                   ? 'bg-blue-50 text-blue-600 font-semibold'
                   : 'text-gray-600 hover:bg-blue-50 hover:text-blue-600' ?>">
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

        <a href="login.php"
           class="block text-center border border-red-500 text-red-500 rounded-md py-1 text-sm hover:bg-red-50">
            Sign Out
        </a>
    </div>

</aside>

<!-- MAIN CONTENT -->
<main class="ml-64 p-8">

    <h3 class="text-xl font-semibold mb-1">Certificate History</h3>
    <p class="text-gray-500 mb-6">Log of all issued certificates</p>

    <div class="bg-white rounded-xl shadow-sm p-6">

        <h6 class="font-semibold mb-4 flex items-center gap-2">
            ⏱ Issued Certificates
        </h6>

        <?php if ($certificates): ?>
            <div class="space-y-4">

                <?php foreach ($certificates as $cert): ?>
                <div class="flex justify-between items-center bg-gray-50 hover:bg-gray-100 p-4 rounded-lg transition">

                    <div>
                        <div class="font-semibold text-blue-600">
                            <?= htmlspecialchars($cert['cert_type']) ?> Certificate
                        </div>
                        <div class="text-sm">
                            <?= htmlspecialchars($cert['holder_name']) ?>
                        </div>
                        <div class="text-sm text-gray-500">
                            Issued: <?= date("F d, Y", strtotime($cert['issued_at'])) ?>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button class="border border-blue-500 text-blue-500 px-3 py-1 rounded-md text-sm hover:bg-blue-50">
                            View
                        </button>
                        <button class="border border-gray-400 text-gray-600 px-3 py-1 rounded-md text-sm hover:bg-gray-200">
                            Print
                        </button>
                    </div>

                </div>
                <?php endforeach; ?>

            </div>
        <?php else: ?>
            <p class="text-gray-500">No certificates issued yet.</p>
        <?php endif; ?>

    </div>

</main>

</body>
</html>
