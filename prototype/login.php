<?php
session_start();

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

$error = "";

/* ---------- LOGIN HANDLER ---------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $pass  = $_POST["password"];

    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE email = :email"
    );

    $stmt->execute([
        ":email" => $email
    ]);

    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userData && password_verify($pass, $userData["password"])) {

        $_SESSION["user_id"]   = $userData["id"];
        $_SESSION["user_name"] = $userData["name"];
        $_SESSION["user_role"] = $userData["role"];

        // Redirect based on role column
        if ($userData["role"] === "admin") {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: home.php");
        }
        exit;

    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center font-sans">

    <div class="bg-white w-full max-w-md p-10 rounded-2xl shadow-xl shadow-gray-200">

        <div class="text-center mb-6">
            <h4 class="text-xl font-bold">Parish Records</h4>
            <p class="text-gray-500 text-sm">Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 bg-red-100 text-red-700 px-4 py-2 rounded">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Password
                </label>

                <div class="flex">

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >

                    <button
                        type="button"
                        onclick="togglePassword()"
                        class="px-3 border border-l-0 border-gray-300 rounded-r-lg bg-gray-50 hover:bg-gray-100"
                    >
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>

                </div>
            </div>

            <button
                class="w-full bg-blue-600 text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition"
            >
                Sign In
            </button>

        </form>

    </div>

    <script>
        function togglePassword() {

            const p    = document.getElementById("password");
            const icon = document.getElementById("toggleIcon");

            if (p.type === "password") {
                p.type = "text";
                icon.classList.replace("bi-eye", "bi-eye-slash");
            } else {
                p.type = "password";
                icon.classList.replace("bi-eye-slash", "bi-eye");
            }
        }
    </script>

</body>
</html>
