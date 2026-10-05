<?php

require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("counsellor");

$user_id = currentUserId();

$message = "";
$message_type = "";


/*
 * Handle slot actions.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";


    /*
     * CREATE NEW SLOT
     */
    if ($action === "create") {

        $appointment_date =
            trim($_POST["appointment_date"] ?? "");

        $start_time =
            trim($_POST["start_time"] ?? "");

        $end_time =
            trim($_POST["end_time"] ?? "");


        if (
            empty($appointment_date)
            || empty($start_time)
            || empty($end_time)
        ) {

            $message =
                "Please provide the date, start time and end time.";

            $message_type = "danger";

        } elseif ($appointment_date < date("Y-m-d")) {

            $message =
                "You cannot create a slot for a past date.";

            $message_type = "danger";

        } elseif ($start_time >= $end_time) {

            $message =
                "The end time must be later than the start time.";

            $message_type = "danger";

        } else {


            /*
             * Check for an existing slot with
             * the same counsellor, date and time.
             */
            $stmt = $conn->prepare(
                "SELECT id
                 FROM appointment_slots
                 WHERE counsellor_id = ?
                 AND appointment_date = ?
                 AND start_time = ?
                 AND end_time = ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "isss",
                $user_id,
                $appointment_date,
                $start_time,
                $end_time
            );

            $stmt->execute();

            $existing = $stmt->get_result();


            if ($existing->num_rows > 0) {

                $message =
                    "A slot with the same date and time already exists.";

                $message_type = "danger";

                $stmt->close();

            } else {

                $stmt->close();

                $status = "available";

                $stmt = $conn->prepare(
                    "INSERT INTO appointment_slots
                    (
                        counsellor_id,
                        appointment_date,
                        start_time,
                        end_time,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "issss",
                    $user_id,
                    $appointment_date,
                    $start_time,
                    $end_time,
                    $status
                );


                if ($stmt->execute()) {

                    $message =
                        "Appointment slot created successfully.";

                    $message_type = "success";

                } else {

                    $message =
                        "Unable to create the appointment slot.";

                    $message_type = "danger";
                }

                $stmt->close();
            }
        }
    }


    /*
     * BLOCK SLOT
     */
    elseif ($action === "block") {

        $slot_id =
            intval($_POST["slot_id"] ?? 0);


        if ($slot_id <= 0) {

            $message = "Invalid appointment slot.";
            $message_type = "danger";

        } else {

            $new_status = "blocked";

            $stmt = $conn->prepare(
                "UPDATE appointment_slots
                 SET status = ?
                 WHERE id = ?
                 AND counsellor_id = ?
                 AND status = 'available'"
            );

            $stmt->bind_param(
                "sii",
                $new_status,
                $slot_id,
                $user_id
            );


            if ($stmt->execute()) {

                if ($stmt->affected_rows === 1) {

                    $message =
                        "Appointment slot has been blocked.";

                    $message_type = "success";

                } else {

                    $message =
                        "This slot cannot be blocked.";

                    $message_type = "danger";
                }

            } else {

                $message =
                    "Unable to block the appointment slot.";

                $message_type = "danger";
            }

            $stmt->close();
        }
    }


    /*
     * RESTORE BLOCKED SLOT
     */
    elseif ($action === "restore") {

        $slot_id =
            intval($_POST["slot_id"] ?? 0);


        if ($slot_id <= 0) {

            $message = "Invalid appointment slot.";
            $message_type = "danger";

        } else {

            $new_status = "available";

            $stmt = $conn->prepare(
                "UPDATE appointment_slots
                 SET status = ?
                 WHERE id = ?
                 AND counsellor_id = ?
                 AND status = 'blocked'
                 AND appointment_date >= CURDATE()"
            );

            $stmt->bind_param(
                "sii",
                $new_status,
                $slot_id,
                $user_id
            );


            if ($stmt->execute()) {

                if ($stmt->affected_rows === 1) {

                    $message =
                        "Appointment slot is available again.";

                    $message_type = "success";

                } else {

                    $message =
                        "This slot cannot be restored.";

                    $message_type = "danger";
                }

            } else {

                $message =
                    "Unable to restore the appointment slot.";

                $message_type = "danger";
            }

            $stmt->close();
        }
    }
}


/*
 * Get counsellor's slots.
 */
$stmt = $conn->prepare(
    "SELECT
        id,
        appointment_date,
        start_time,
        end_time,
        status,
        created_at
     FROM appointment_slots
     WHERE counsellor_id = ?
     ORDER BY
        appointment_date ASC,
        start_time ASC"
);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$slots = $stmt->get_result();

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
        Manage Slots | Chebara TVC
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


    <!-- Page heading -->

    <div class="card">

        <h1>
            Manage Appointment Slots
        </h1>

        <p>
            Create and manage the counselling
            appointment times available to students.
        </p>

    </div>


    <!-- Messages -->

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


    <!-- Create slot -->

    <div class="card">

        <h2>
            Create New Appointment Slot
        </h2>

        <br>

        <form
            method="POST"
            action="slots.php"
        >

            <input
                type="hidden"
                name="action"
                value="create"
            >


            <div class="form-group">

                <label for="appointment_date">
                    Appointment Date
                </label>

                <input
                    type="date"
                    id="appointment_date"
                    name="appointment_date"
                    min="<?php echo date("Y-m-d"); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="start_time">
                    Start Time
                </label>

                <input
                    type="time"
                    id="start_time"
                    name="start_time"
                    required
                >

            </div>


            <div class="form-group">

                <label for="end_time">
                    End Time
                </label>

                <input
                    type="time"
                    id="end_time"
                    name="end_time"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Create Appointment Slot
            </button>

        </form>

    </div>


    <!-- Existing slots -->

    <div class="card">

        <h2>
            My Appointment Slots
        </h2>

        <br>


        <?php if ($slots->num_rows > 0): ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Date
                            </th>

                            <th>
                                Time
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php while (
                        $slot = $slots->fetch_assoc()
                    ): ?>


                        <tr>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    date(
                                        "d M Y",
                                        strtotime(
                                            $slot[
                                                "appointment_date"
                                            ]
                                        )
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    date(
                                        "H:i",
                                        strtotime(
                                            $slot[
                                                "start_time"
                                            ]
                                        )
                                    )
                                );

                                ?>

                                -

                                <?php

                                echo htmlspecialchars(
                                    date(
                                        "H:i",
                                        strtotime(
                                            $slot[
                                                "end_time"
                                            ]
                                        )
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    ucfirst(
                                        $slot["status"]
                                    )
                                );

                                ?>

                            </td>


                            <td>


                                <?php if (
                                    $slot["status"]
                                    === "available"
                                ): ?>


                                    <form
                                        method="POST"
                                        action="slots.php"
                                        onsubmit="return confirm(
                                            'Are you sure you want to block this slot?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="block"
                                        >

                                        <input
                                            type="hidden"
                                            name="slot_id"
                                            value="<?php
                                                echo $slot["id"];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >
                                            Block Slot
                                        </button>

                                    </form>


                                <?php elseif (
                                    $slot["status"]
                                    === "blocked"
                                ): ?>


                                    <form
                                        method="POST"
                                        action="slots.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="restore"
                                        >

                                        <input
                                            type="hidden"
                                            name="slot_id"
                                            value="<?php
                                                echo $slot["id"];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                        >
                                            Make Available
                                        </button>

                                    </form>


                                <?php elseif (
                                    $slot["status"]
                                    === "booked"
                                ): ?>


                                    <span>
                                        Booked
                                    </span>


                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endwhile; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="alert alert-warning">

                You have not created any appointment
                slots yet.

            </div>


        <?php endif; ?>


    </div>


    <!-- Information -->

    <div class="card">

        <h3>
            Slot Management Guidelines
        </h3>

        <p>
            Create appointment times when you are
            available to provide counselling.
        </p>

        <p>
            A slot that has already been booked cannot
            be blocked from this page.
        </p>

        <p>
            Students will only see active future slots
            that are marked as available.
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
