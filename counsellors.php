<?php
require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("admin");

$message = "";
$error = "";

// Update counsellor account status
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $user_id = (int) $_POST["user_id"];
    $new_status = $_POST["status"];

    $allowed_statuses = ["active", "inactive", "suspended"];

    if (!in_array($new_status, $allowed_statuses, true)) {

        $error = "Invalid counsellor status.";

    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET status = ?, updated_at = NOW()
            WHERE id = ? AND role = 'counsellor'
        ");

        $stmt->bind_param("si", $new_status, $user_id);

        if ($stmt->execute()) {
            $message = "Counsellor status updated successfully.";
        } else {
            $error = "Unable to update counsellor status.";
        }

        $stmt->close();
    }
}


// Get counsellors
$counsellors = $conn->query("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.status,
        u.created_at,
        cp.specialization,
        cp.bio
    FROM users u
    LEFT JOIN counsellor_profiles cp
        ON u.id = cp.user_id
    WHERE u.role = 'counsellor'
    ORDER BY u.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Counsellors | Chebara Counselling System</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

</head>

<body>

<header class="navbar">

    <div class="container">

        <div class="nav-content">

            <a href="../index.php" class="logo">
                Chebara Counselling
            </a>

            <nav class="nav-links">

                <a href="dashboard.php">
                    Dashboard
                </a>

                <a href="users.php">
                    Users
                </a>

                <a href="counsellors.php">
                    Counsellors
                </a>

                <a href="appointments.php">
                    Appointments
                </a>

                <a href="reports.php">
                    Reports
                </a>

                <a href="settings.php">
                    Settings
                </a>

                <a href="../logout.php">
                    Logout
                </a>

            </nav>

        </div>

    </div>

</header>


<main class="container">

    <section class="dashboard-header">

        <h1>Counsellor Management</h1>

        <p>
            View counsellor profiles and manage counsellor accounts.
        </p>

    </section>


    <?php if ($message !== ""): ?>

        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <section class="card">

        <h2>Registered Counsellors</h2>

        <?php if ($counsellors && $counsellors->num_rows > 0): ?>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>

                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php while ($counsellor = $counsellors->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $counsellor["full_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $counsellor["email"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $counsellor["phone"] ?? "-"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $counsellor["specialization"]
                                    ?? "Not specified"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    ucfirst($counsellor["status"])
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "d M Y",
                                        strtotime(
                                            $counsellor["created_at"]
                                        )
                                    )
                                );
                                ?>
                            </td>

                            <td>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?php
                                        echo (int)$counsellor["id"];
                                        ?>"
                                    >

                                    <select
                                        name="status"
                                        onchange="this.form.submit()"
                                    >

                                        <option value="active"
                                            <?php
                                            echo $counsellor["status"] === "active"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Active
                                        </option>

                                        <option value="inactive"
                                            <?php
                                            echo $counsellor["status"] === "inactive"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Inactive
                                        </option>

                                        <option value="suspended"
                                            <?php
                                            echo $counsellor["status"] === "suspended"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Suspended
                                        </option>

                                    </select>

                                    <input
                                        type="hidden"
                                        name="update_status"
                                        value="1"
                                    >

                                </form>

                            </td>

                        </tr>

                        <?php if (!empty($counsellor["bio"])): ?>

                            <tr>

                                <td colspan="7">

                                    <strong>
                                        Profile:
                                    </strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $counsellor["bio"]
                                    );
                                    ?>

                                </td>

                            </tr>

                        <?php endif; ?>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p>
                No counsellors have been registered yet.
            </p>

        <?php endif; ?>

    </section>

</main>


<footer class="footer">

    <div class="container">

        <p>
            &copy; <?php echo date("Y"); ?>
            Chebara Technical and Vocational College
            Counselling Booking Management System.
        </p>

    </div>

</footer>

</body>

</html>
