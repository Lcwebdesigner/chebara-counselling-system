<?php

require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("counsellor");

$user_id = currentUserId();

$message = "";
$message_type = "";


/*
 * Handle profile update.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $specialization = trim($_POST["specialization"] ?? "");
    $bio = trim($_POST["bio"] ?? "");


    if (empty($full_name)) {

        $message = "Full name is required.";
        $message_type = "danger";

    } else {

        $conn->begin_transaction();

        try {

            /*
             * Update basic user information.
             */
            $stmt = $conn->prepare(
                "UPDATE users
                 SET full_name = ?,
                     phone = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                 AND role = 'counsellor'"
            );

            $stmt->bind_param(
                "ssi",
                $full_name,
                $phone,
                $user_id
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to update your profile."
                );
            }

            $stmt->close();


            /*
             * Check whether counsellor profile exists.
             */
            $stmt = $conn->prepare(
                "SELECT id
                 FROM counsellor_profiles
                 WHERE user_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "i",
                $user_id
            );

            $stmt->execute();

            $profile_result = $stmt->get_result();

            $profile_exists =
                $profile_result->num_rows === 1;

            $stmt->close();


            /*
             * Update existing counsellor profile.
             */
            if ($profile_exists) {

                $stmt = $conn->prepare(
                    "UPDATE counsellor_profiles
                     SET specialization = ?,
                         bio = ?
                     WHERE user_id = ?"
                );

                $stmt->bind_param(
                    "ssi",
                    $specialization,
                    $bio,
                    $user_id
                );

            /*
             * Create counsellor profile if it
             * does not exist yet.
             */
            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO counsellor_profiles
                    (
                        user_id,
                        specialization,
                        bio
                    )
                    VALUES (?, ?, ?)"
                );

                $stmt->bind_param(
                    "iss",
                    $user_id,
                    $specialization,
                    $bio
                );
            }


            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to save counsellor information."
                );
            }

            $stmt->close();


            /*
             * Activity log.
             */
            $action = "profile_updated";

            $description =
                "Counsellor updated their profile information.";

            $stmt = $conn->prepare(
                "INSERT INTO activity_logs
                (
                    user_id,
                    action,
                    description
                )
                VALUES (?, ?, ?)"
            );

            $stmt->bind_param(
                "iss",
                $user_id,
                $action,
                $description
            );

            $stmt->execute();
            $stmt->close();


            $conn->commit();

            $message =
                "Your profile has been updated successfully.";

            $message_type = "success";

        } catch (Exception $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $message_type = "danger";
        }
    }
}


/*
 * Retrieve current counsellor profile.
 */
$stmt = $conn->prepare(
    "SELECT
        u.full_name,
        u.email,
        u.phone,
        cp.specialization,
        cp.bio

     FROM users u

     LEFT JOIN counsellor_profiles cp
        ON u.id = cp.user_id

     WHERE u.id = ?
     AND u.role = 'counsellor'

     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

$profile = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Counsellor Profile | Chebara TVC
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body>


<nav class="navbar">

    <div class="container">

        <div class="logo">
            Chebara TVC
        </div>

        <div class="nav-links">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="slots.php">
                Manage Slots
            </a>

            <a href="appointments.php">
                Appointments
            </a>

            <a href="profile.php">
                Profile
            </a>

            <a href="../logout.php">
                Logout
            </a>

        </div>

    </div>

</nav>


<main class="container dashboard">


    <div class="card">

        <h1>
            Counsellor Profile
        </h1>

        <p>
            Manage your professional information
            displayed in the counselling system.
        </p>

    </div>


    <?php if ($message): ?>

        <div class="alert
            <?php
            echo $message_type === "success"
                ? "alert-success"
                : "alert-danger";
            ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <div class="card">

        <h2>
            Personal Information
        </h2>

        <br>


        <form
            method="POST"
            action="profile.php"
        >


            <div class="form-group">

                <label for="full_name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?php
                        echo htmlspecialchars(
                            $profile["full_name"] ?? ""
                        );
                    ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    value="<?php
                        echo htmlspecialchars(
                            $profile["email"] ?? ""
                        );
                    ?>"
                    readonly
                >

                <small>
                    Email address cannot be changed
                    from this page.
                </small>

            </div>


            <div class="form-group">

                <label for="phone">
                    Phone Number
                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?php
                        echo htmlspecialchars(
                            $profile["phone"] ?? ""
                        );
                    ?>"
                    placeholder="Enter phone number"
                >

            </div>


            <div class="form-group">

                <label for="specialization">
                    Counselling Specialization
                </label>

                <input
                    type="text"
                    id="specialization"
                    name="specialization"
                    value="<?php
                        echo htmlspecialchars(
                            $profile["specialization"] ?? ""
                        );
                    ?>"
                    placeholder="e.g. Student counselling"
                >

            </div>


            <div class="form-group">

                <label for="bio">
                    Professional Bio
                </label>

                <textarea
                    id="bio"
                    name="bio"
                    rows="6"
                    placeholder="Brief professional description"
                ><?php
                    echo htmlspecialchars(
                        $profile["bio"] ?? ""
                    );
                ?></textarea>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Profile
            </button>


        </form>

    </div>


    <div class="card">

        <h3>
            Confidentiality
        </h3>

        <p>
            Keep your account information secure.
            Counselling records and student information
            should only be accessed for authorized
            counselling and appointment-management
            purposes.
        </p>

    </div>


</main>


<footer>

    <div class="container">

        <p>
            &copy; <?php echo date("Y"); ?>
            Chebara Technical and Vocational College
        </p>

        <p>
            Online Counselling Booking System
        </p>

    </div>

</footer>


</body>

</html>
