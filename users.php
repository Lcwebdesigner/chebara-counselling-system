<?php
require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("admin");

$message = "";
$error = "";

// Update user status
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $user_id = (int) $_POST["user_id"];
    $new_status = $_POST["status"];

    $allowed_statuses = ["active", "inactive", "suspended"];

    if (!in_array($new_status, $allowed_statuses, true)) {
        $error = "Invalid user status.";
    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param("si", $new_status, $user_id);

        if ($stmt->execute()) {
            $message = "User status updated successfully.";
        } else {
            $error = "Unable to update user status.";
        }

        $stmt->close();
    }
}

// Search
$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $search_term = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT id, full_name, email, phone, role, status, created_at
        FROM users
        WHERE full_name LIKE ?
           OR email LIKE ?
           OR phone LIKE ?
        ORDER BY created_at DESC
    ");

    $stmt->bind_param(
        "sss",
        $search_term,
        $search_term,
        $search_term
    );

    $stmt->execute();
    $users = $stmt->get_result();

} else {

    $users = $conn->query("
        SELECT id, full_name, email, phone, role, status, created_at
        FROM users
        ORDER BY created_at DESC
    ");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>User Management | Chebara Counselling System</title>

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

        <h1>User Management</h1>

        <p>
            View and manage students, counsellors and administrators.
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


    <!-- Search -->

    <section class="card">

        <h2>Search Users</h2>

        <form method="GET">

            <div class="form-group">

                <label for="search">
                    Name, Email or Phone
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search users..."
                >

            </div>

            <div class="button-group">

                <button type="submit" class="btn">
                    Search
                </button>

                <a href="users.php" class="btn btn-secondary">
                    Clear
                </a>

            </div>

        </form>

    </section>


    <!-- Users Table -->

    <section class="card">

        <h2>System Users</h2>

        <?php if ($users && $users->num_rows > 0): ?>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>

                            <th>Name</th>

                            <th>Email</th>

                            <th>Phone</th>

                            <th>Role</th>

                            <th>Status</th>

                            <th>Registered</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php while ($user = $users->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $user["full_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $user["email"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $user["phone"] ?? "-"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    ucfirst($user["role"])
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    ucfirst($user["status"])
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "d M Y",
                                        strtotime(
                                            $user["created_at"]
                                        )
                                    )
                                );
                                ?>
                            </td>

                            <td>

                                <?php if ((int)$user["id"] !== (int)currentUserId()): ?>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?php
                                            echo (int)$user["id"];
                                            ?>"
                                        >

                                        <select
                                            name="status"
                                            onchange="this.form.submit()"
                                        >

                                            <option value="active"
                                                <?php
                                                echo $user["status"] === "active"
                                                    ? "selected"
                                                    : "";
                                                ?>>
                                                Active
                                            </option>

                                            <option value="inactive"
                                                <?php
                                                echo $user["status"] === "inactive"
                                                    ? "selected"
                                                    : "";
                                                ?>>
                                                Inactive
                                            </option>

                                            <option value="suspended"
                                                <?php
                                                echo $user["status"] === "suspended"
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

                                <?php else: ?>

                                    <strong>
                                        Current Admin
                                    </strong>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p>
                No users found.
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
