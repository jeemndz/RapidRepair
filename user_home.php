<?php
session_start();
require_once "db.php"; // database connection

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch user info from database if not already in session
if (!isset($_SESSION['firstName']) || !isset($_SESSION['lastName'])) {
    $stmt = $conn->prepare("
        SELECT c.firstName, c.lastName, u.fullName
        FROM users u
        LEFT JOIN client_information c ON u.user_id = c.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $_SESSION['firstName'] = $row['firstName'] ?? "";
        $_SESSION['lastName'] = $row['lastName'] ?? "";
        $_SESSION['fullName'] = $row['fullName'] ?? "";
    }
    $stmt->close();
}

// Safely build full name for display
$firstName = $_SESSION['firstName'] ?? "";
$lastName = $_SESSION['lastName'] ?? "";
$fullName = ($firstName || $lastName) ? trim($firstName . " " . $lastName) : ($_SESSION['fullName'] ?? "Guest");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Home | RapidRepair</title>

    <!-- IMPORTANT: pagelayout.css first -->
    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="user.css">

    <style>
        /* ===============================
           USER HOME (SCROLLABLE PAGE)
        =============================== */

        .content.user-home {
            padding: 0;
            height: calc(100vh - 70px);
            /* adjust if your topbar height differs */
            overflow-y: auto;
            background: #efefef;
        }

        /* HERO */
        .hero {
            position: relative;
            height: 320px;
            background: url("pictures/userbackground.png") center/cover no-repeat;
        }

        .hero-inner {
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            /* STACK VERTICALLY */
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
        }

       .hero-logo {
    width: 600px;        /* bigger default size */
    max-width: 85%;      /* prevents overflow on small screens */
    height: auto;
    display: block;
    margin: 0 auto 16px; /* center + spacing */
    filter: drop-shadow(0 8px 18px rgba(0, 0, 0, 0.45));
}



        .hero-inner h2 {
            font-size: 22px;
            font-style: italic;
            margin: 0;
            text-shadow: 0 3px 8px rgba(0, 0, 0, 0.6);
        }

        /* dark overlay for readability */
        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
        }

        .hero-inner {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
        }

        .hero-inner h2 {
            font-size: 22px;
            font-style: italic;
            text-shadow: 0 3px 8px rgba(0, 0, 0, 0.6);
        }


        /* 3 CARDS */
        .triple-cards {
            margin: 20px auto 0;
            /* no negative margin */
            width: min(1000px, 92%);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .info-card {
            border-radius: 6px;
            padding: 22px 18px;
            color: #fff;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.18);
            min-height: 170px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-blue {
            background: #243f7a;
        }

        .card-red {
            background: #7a0f0f;
        }

        .card-navy {
            background: #243f7a;
        }

        .info-card h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .info-card p,
        .info-card small {
            margin: 0;
            font-size: 12.5px;
            line-height: 1.5;
            opacity: .95;
        }

        .info-card .row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 12.5px;
            margin-top: 10px;
            opacity: .95;
        }

        .info-btn {
            align-self: center;
            margin-top: 12px;
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 5px;
            background: #fff;
            color: #243f7a;
            text-decoration: none;
            display: inline-block;
        }

        .info-btn:hover {
            opacity: .9;
        }

        /* MAIN SECTION */
        .section-wrap {
            width: min(1000px, 92%);
            margin: 18px auto 30px;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
            padding: 18px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            align-items: start;
        }

        .services-photo {
            width: 100%;
            height: 260px;
            border-radius: 6px;
            overflow: hidden;
        }

        .services-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .services-text h2 {
            margin: 0 0 10px;
            color: #243f7a;
            font-style: italic;
        }

        .services-text p {
            margin: 0 0 12px;
            font-size: 13px;
            line-height: 1.6;
            color: #333;
        }

        .service-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px 18px;
            margin-top: 10px;
            font-size: 12.5px;
            font-weight: 700;
            font-style: italic;
            color: #243f7a;
        }

        /* MAP */
        .visit-title {
            text-align: center;
            font-weight: 900;
            letter-spacing: 1px;
            margin: 16px 0 14px;
            color: #111;
        }

        .map-box {
            width: min(1000px, 92%);
            margin: 0 auto 30px;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .map-box iframe {
            width: 100%;
            height: 320px;
            border: 0;
            display: block;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .triple-cards {
                grid-template-columns: 1fr;
                margin-top: -40px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .service-list {
                grid-template-columns: repeat(2, 1fr);
            }

            .hero {
                height: 280px;
            }
        }

        @media (max-width: 520px) {
            .service-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="logo">
            <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
            <small>Commitment is our Passion</small>
        </div>

        <div class="search-box">
            <input type="text" placeholder="Search..." autocomplete="off">
            <div class="search-results" style="display:none;"></div>
        </div>

        <div class="user-info">
            <img src="pictures/user.png" alt="User">
            <div>
                <strong>Welcome!</strong><br>
                <span><?= htmlspecialchars($fullName) ?></span>
            </div>
        </div>
    </header>

    <div class="layout">

        <!-- SIDEBAR (UNCHANGED) -->
        <aside class="sidebar">
            <ul>
                <li class="active"><a href="user_home.php">Home</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="vehicle.php">Vehicle</a></li>
                <li><a href="clientreq.php">Booking</a></li>
                <li><a href="payments.php">Payment</a></li>
            </ul>

            <div class="logout">
                <a href="logout.php">Logout</a>
            </div>
        </aside>

        <!-- CONTENT (SCROLLABLE) -->
        <main class="content user-home">

            <!-- HERO -->
            <section class="hero">
                <div class="hero-inner">
                    <img src="pictures/rapidlogo2.png" alt="Rapid Repair Logo" class="hero-logo">
                    <h2>Commitment is our Passion!</h2>
                </div>
            </section>
            <!-- 3 INFO CARDS -->
            <section class="triple-cards">
                <div class="info-card card-blue">
                    <h3>Contact Details</h3>

                    <div class="row">
                        <span><b>Call us:</b></span>
                        <span>0953 280 7426</span>
                    </div>
                    <div class="row">
                        <span><b>Location:</b></span>
                        <span>DRT Highway, Brgy. Sabang, Baliwag City, Bulacan</span>
                    </div>
                    <div class="row">
                        <span><b>Operating Hours:</b></span>
                        <span>Mon–Sun 8:00AM–8:00PM</span>
                    </div>
                </div>

                <div class="info-card card-red">
                    <h3>Your Trusted Auto Care Starts Here</h3>
                    <p>
                        Sign up today and experience fast, reliable auto repair services you can trust.
                        Let us take care of your vehicle with ease and confidence.
                    </p>
                </div>

                <div class="info-card card-navy">
                    <h3>Featured Services</h3>
                    <p>
                        We offer reliable and professional vehicle repair and maintenance services:
                        diagnostics, oil changes, brake repairs, suspension work, and preventive maintenance.
                    </p>
                </div>
            </section>

            <!-- SERVICES SECTION -->
            <section class="section-wrap">
                <div class="services-grid">
                    <div class="services-photo">
                        <img src="pictures/services-photo.png" alt="Service Photo">
                    </div>

                    <div class="services-text">
                        <h2>Services</h2>
                        <p>
                            We provide reliable auto repair and maintenance services including engine diagnostics,
                            oil changes, brake repairs, suspension work, and preventive maintenance.
                            Our skilled mechanics ensure quality workmanship to keep your vehicle safe,
                            efficient, and road-ready.
                        </p>

                        <div class="service-list">
                            <div>Shock Absorber</div>
                            <div>Transmission</div>
                            <div>Differential</div>

                            <div>Change Oil</div>
                            <div>Belt &amp; Timing</div>
                            <div>Axle Bearings</div>

                            <div>Change Brake Oil</div>
                            <div>Suspension Repair</div>
                            <div>Power Steering / Rack &amp; Pinion</div>

                            <div>Preventive Maintenance Service</div>
                            <div>Underchassis Repair</div>
                            <div>&nbsp;</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- MAP -->
            <h3 class="visit-title">VISIT OUR SHOP</h3>
            <section class="map-box">
                <iframe loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"
                    src="https://www.google.com/maps?q=14.9688835,120.9058685&z=17&output=embed">
                </iframe>
            </section>


        </main>
    </div>

</body>

</html>