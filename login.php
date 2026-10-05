<?php
require_once "includes/config.php";
require_once "includes/auth.php";

$error = "";

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error = "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, full_name, password, role, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if ($user["status"] !== "active") {

                $error = "Your account is not active. Please contact the administrator.";

            } elseif (password_verify($password, $user["password"])) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];

                /*
                 * Redirect according to user role.
                 */

                if ($user["role"] === "student") {

                    header("Location: student/dashboard.php");

                } elseif ($user["role"] === "counsellor") {

                    header("Location: counsellor/dashboard.php");

                } elseif ($user["role"] === "admin") {

                    header("Location: admin/dashboard.php");

                } else {

                    header("Location: index.php");
                }

                exit;

            } else {

                $error = "Invalid email or password.";
            }

        } else {

            $error = "Invalid email or password.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | Chebara TVC</title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

</head>

<body>

<nav class="navbar">

    <div class="container">

        <div class="logo">
            Chebara TVC
        </div>

        <div class="nav-links">

            <a href="index.php">
                Home
            </a>

            <a href="register.php">
                Register
            </a>

        </div>

    </div>

</nav>


<main class="container">

    <div class="form-container">

        <div class="card">

            <h2>Login</h2>

            <p style="margin-bottom: 20px;">
                Login to the Chebara TVC Online
                Counselling Booking System.
            </p>


            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?php
                    echo htmlspecialchars($error);
                    ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="login.php"
            >

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter your email"
                        required
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["email"] ?? ""
                            );
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                    style="width: 100%;"
                >
                    Login
                </button>

            </form>


            <p style="margin-top: 20px; text-align: center;">

                Don't have an account?

                <a
                    href="register.php"
                    style="color: var(--primary); font-weight: bold;"
                >
                    Register as Student
                </a>

            </p>

        </div>

    </div>

</main>


<footer>

    <div class="container">

        <p>
            &copy; <?php echo date("Y"); ?>
            Chebara Technical and Vocational College
        </p>

    </div>

</footer>

</body>

</html>
