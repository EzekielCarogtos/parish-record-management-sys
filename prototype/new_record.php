<?php
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
    die("DB connection failed.");
}

/* ---------- SAVE RECORD ---------- */

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $type = $_POST["record_type"] ?? "";
    $name = $_POST["full_name"] ?? "";
    $date = $_POST["record_date"] ?? null;
    $details = json_encode($_POST);

    if ($type && $name) {

        $stmt = $pdo->prepare("
            INSERT INTO records
                (record_type, full_name, record_date, details)
            VALUES
                (:type, :name, :date, :details)
        ");

        $stmt->execute([
            ":type"    => $type,
            ":name"    => $name,
            ":date"    => $date,
            ":details" => $details
        ]);

        $message = "Record saved successfully.";
    }
}

$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Record</title>
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
            "home.php"      => "Dashboard",
            "record.php"    => "Records",
            "new_record.php"=> "New Record",
            "cert_req.php"  => "Certificate Request",
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

    <!-- MAIN -->

    <main class="ml-64 p-8">

        <div class="bg-white rounded-xl shadow-sm p-6 max-w-4xl mx-auto">

            <h3 class="text-lg font-semibold mb-4">
                Add New Sacramental Record
            </h3>

            <?php if ($message): ?>
                <div class="mb-4 bg-green-100 text-green-700 p-3 rounded">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="recordForm" class="space-y-4">

                <label class="font-semibold text-sm">
                    Sacrament Type
                </label>

                <select
                    name="record_type"
                    class="w-full border rounded p-2"
                    onchange="toggleForm(this.value)"
                    required
                >
                    <option value="">Select type</option>
                    <option value="Baptism">Baptism</option>
                    <option value="Confirmation">Confirmation</option>
                    <option value="Marriage">Marriage</option>
                    <option value="Funeral">Funeral</option>
                </select>

                <!-- COMMON FIELDS -->

                <div id="commonFields" class="hidden space-y-3">

                    <label class="text-sm font-semibold">
                        Full Name
                    </label>
                    <input
                        type="text"
                        name="full_name"
                        class="w-full border rounded p-2"
                    >

                    <label class="text-sm font-semibold">
                        Record Date
                    </label>
                    <input
                        type="date"
                        name="record_date"
                        class="w-full border rounded p-2"
                    >

                </div>

                <!-- BAPTISM -->

                <div id="form-Baptism" class="hidden space-y-2">

                    <label class="text-sm">Place of Birth</label>
                    <input
                        name="birth_place"
                        class="w-full border rounded p-2"
                    >

                    <label class="text-sm">Parents</label>
                    <input
                        name="parents"
                        class="w-full border rounded p-2"
                    >

                </div>

                <!-- CONFIRMATION -->

                <div id="form-Confirmation" class="hidden space-y-2">

                    <label class="text-sm">Baptized Parish</label>
                    <input
                        name="baptized_parish"
                        class="w-full border rounded p-2"
                    >

                </div>

                <!-- MARRIAGE -->

                <div id="form-Marriage" class="hidden space-y-2">

                    <label class="text-sm">Spouse Name</label>
                    <input
                        name="spouse"
                        class="w-full border rounded p-2"
                    >

                </div>

                <!-- FUNERAL -->

                <div id="form-Funeral" class="hidden space-y-2">

                    <label class="text-sm">Cause of Death</label>
                    <input
                        name="cause"
                        class="w-full border rounded p-2"
                    >

                </div>

                <div class="pt-4 border-t">

                    <button class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700">
                        Save Record
                    </button>

                    <button
                        type="reset"
                        class="border px-5 py-2 rounded ml-2"
                    >
                        Clear
                    </button>

                </div>

            </form>

        </div>

    </main>

    <script>
        function toggleForm(type) {

            document
                .getElementById("commonFields")
                .classList.toggle("hidden", !type);

            const forms = ["Baptism", "Confirmation", "Marriage", "Funeral"];

            forms.forEach(f => {
                document
                    .getElementById("form-" + f)
                    .classList.add("hidden");
            });

            if (type) {
                document
                    .getElementById("form-" + type)
                    .classList.remove("hidden");
            }
        }
    </script>

</body>
</html>
