<?php
require_once "includes/config.php";

session_start();

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Validate full name
    if ($full_name === "") {
        $errors[] = "Full name is required.";
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Validate password
    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    // Confirm password
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check whether email already exists
    if (empty($errors)) {

        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "An account with this email already exists.";
        }

        $check->close();
    }

    // Create account
    if (empty($errors)) {

        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $role = "student";
        $status = "active";

        $stmt = $conn->prepare(
            "INSERT INTO users
            (full_name, email, phone, password, role, status)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssss",
            $full_name,
            $email,
            $phone,
            $hashed_password,
            $role,
            $status
        );

        if ($stmt->execute()) {

            $success =
                "Registration successful. You can now log in.";

        } else {

            $errors[] =
                "Registration failed. Please try again.";
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

    <title>Student Registration | Chebara TVC</title>

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

            <a href="login.php">
                Login
            </a>

        </div>

    </div>

</nav>


<main class="container">

    <div class="form-container">

        <div class="card">

            <h2>Student Registration</h2>

            <p style="margin-bottom: 20px;">
                Create your Chebara TVC counselling
                booking account.
            </p>


            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <?php foreach ($errors as $error): ?>

                        <div>
                            <?php echo htmlspecialchars($error); ?>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


            <?php if ($success): ?>

                <div class="alert alert-success">

                    <?php echo htmlspecialchars($success); ?>

                    <br><br>

                    <a
                        href="login.php"
                        class="btn btn-primary"
                    >
                        Go to Login
                    </a>

                </div>

            <?php endif; ?>


            <?php if (!$success): ?>

            <form
                method="POST"
                action="register.php"
            >

                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control"
                        placeholder="Enter your full name"
                        required
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["full_name"] ?? ""
                            );
                        ?>"
                    >

                </div>


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

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        class="form-control"
                        placeholder="Enter your phone number"
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
                        placeholder="Minimum 8 characters"
                        minlength="8"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        placeholder="Re-enter your password"
                        minlength="8"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                    style="width: 100%;"
                >
                    Create Student Account
                </button>

            </form>

            <?php endif; ?>


            <p style="margin-top: 20px; text-align: center;">

                Already have an account?

                <a
                    href="login.php"
                    style="color: var(--primary); font-weight: bold;"
                >
                    Login
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
