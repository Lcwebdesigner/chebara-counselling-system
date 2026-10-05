<?php
session_start();

$userLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Chebara TVC | Online Counselling Booking System</title>

    <meta
        name="description"
        content="Chebara Technical and Vocational College Online Counselling Booking System. Book and manage counselling appointments online."
    >

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<!-- ================= NAVIGATION ================= -->

<nav class="navbar">
    <div class="container">

        <div class="logo">
            Chebara TVC
        </div>

        <div class="nav-links">
            <a href="index.php">Home</a>

            <?php if (!$userLoggedIn): ?>

                <a href="login.php">Login</a>
                <a href="register.php" class="btn btn-secondary">
                    Register
                </a>

            <?php else: ?>

                <?php if ($userRole === 'student'): ?>
                    <a href="student/dashboard.php">Dashboard</a>
                <?php elseif ($userRole === 'counsellor'): ?>
                    <a href="counsellor/dashboard.php">Dashboard</a>
                <?php elseif ($userRole === 'admin'): ?>
                    <a href="admin/dashboard.php">Dashboard</a>
                <?php endif; ?>

                <a href="logout.php">Logout</a>

            <?php endif; ?>
        </div>

    </div>
</nav>


<!-- ================= HERO ================= -->

<section class="hero">

    <div class="container">

        <h1>
            Online Counselling Booking System
        </h1>

        <p>
            A convenient and structured way for Chebara Technical
            and Vocational College students to request and manage
            counselling appointments.
        </p>

        <?php if (!$userLoggedIn): ?>

            <a href="register.php" class="btn btn-secondary">
                Book an Appointment
            </a>

            <a href="login.php" class="btn">
                Login
            </a>

        <?php else: ?>

            <?php if ($userRole === 'student'): ?>

                <a href="student/dashboard.php" class="btn btn-secondary">
                    Go to My Dashboard
                </a>

            <?php elseif ($userRole === 'counsellor'): ?>

                <a href="counsellor/dashboard.php" class="btn btn-secondary">
                    Counsellor Dashboard
                </a>

            <?php elseif ($userRole === 'admin'): ?>

                <a href="admin/dashboard.php" class="btn btn-secondary">
                    Admin Dashboard
                </a>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</section>


<!-- ================= HOW IT WORKS ================= -->

<section class="container" style="padding: 50px 0;">

    <div style="text-align:center; margin-bottom:30px;">

        <h2>How It Works</h2>

        <p>
            Access counselling appointments through a simple
            online process.
        </p>

    </div>

    <div class="cards">

        <div class="card">

            <h3>1. Register</h3>

            <p>
                Create your student account using your basic
                registration information.
            </p>

        </div>


        <div class="card">

            <h3>2. View Available Slots</h3>

            <p>
                View available counselling appointment slots
                provided by counsellors.
            </p>

        </div>


        <div class="card">

            <h3>3. Book Appointment</h3>

            <p>
                Select a suitable available slot and submit
                your appointment request.
            </p>

        </div>


        <div class="card">

            <h3>4. Manage Appointment</h3>

            <p>
                Check appointment status and manage cancellation
                or rescheduling requests.
            </p>

        </div>

    </div>

</section>


<!-- ================= FEATURES ================= -->

<section class="container" style="padding: 20px 0 50px;">

    <div style="text-align:center; margin-bottom:30px;">

        <h2>System Features</h2>

    </div>

    <div class="cards">

        <div class="card">

            <h3>Secure Authentication</h3>

            <p>
                Role-based access for students, counsellors
                and administrators.
            </p>

        </div>


        <div class="card">

            <h3>Appointment Management</h3>

            <p>
                Manage available slots, bookings,
                confirmations and appointment status.
            </p>

        </div>


        <div class="card">

            <h3>Notifications</h3>

            <p>
                Receive appointment-related status and
                system notifications.
            </p>

        </div>


        <div class="card">

            <h3>Privacy & Security</h3>

            <p>
                Access to counselling-related records is
                restricted according to user roles.
            </p>

        </div>

    </div>

</section>


<!-- ================= ABOUT ================= -->

<section class="container" style="padding: 20px 0 50px;">

    <div class="card">

        <h2>About the System</h2>

        <p>
            The Chebara TVC Online Counselling Booking System
            supports the administrative process of requesting
            and managing counselling appointments.
        </p>

        <br>

        <p>
            The system is designed to improve access to
            counselling appointments while helping counselling
            staff organize schedules and appointment records.
        </p>

        <br>

        <p>
            This system supports counselling appointment
            management and does not replace professional
            counselling services.
        </p>

    </div>

</section>


<!-- ================= FOOTER ================= -->

<footer>

    <div class="container">

        <p>
            &copy; <?php echo date('Y'); ?>
            Chebara Technical and Vocational College
        </p>

        <p>
            Online Counselling Booking System
        </p>

    </div>

</footer>

</body>
</html>
