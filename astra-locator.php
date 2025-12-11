<?php
// astra-locator.php
session_start();

// Must be logged in
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}

$cid = (int)$_SESSION['cid'];

/* ---------- DB CONNECTION ---------- */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "my_bank";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

/* ---------- Fetch Locations ---------- */
$selected_city = trim($_GET['city'] ?? 'all');

if ($selected_city === '' || $selected_city === 'all') {
    // No filter → show all
    $sql = "SELECT * FROM astra_locations ORDER BY city, type";
    $stmt = $conn->prepare($sql);
} else {
    // Filter by city
    $sql = "SELECT * FROM astra_locations WHERE city = ? ORDER BY type";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_city);
}

$stmt->execute();
$result = $stmt->get_result();

$locations = [];
while ($row = $result->fetch_assoc()) {
    $locations[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Astra Locator — Branch &amp; ATM Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="transfer.css">
    <style>
        :root { --primary:#00416A; }

        /* Dashboard-style background + centering */
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            background: linear-gradient(135deg, #00416A, #E4E5E6);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 24px 12px;
        }

        .app {
            width: 100%;
            max-width: 960px;
        }

        .card {
            background:#ffffff;
            border-radius:12px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
        }

        .h1 {
            color:#ffffff;
            text-shadow:0 1px 2px rgba(0,0,0,0.25);
        }

        .note {
            color: var(--muted);
            font-size: 13px;
        }

        /* Make location list nicely spaced in a single-column grid */
        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        @media (min-width: 720px) {
            .grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .opt {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            border:1px solid var(--border);
            border-radius:12px;
            padding:14px 16px;
            background:#fbfbfc;
        }
    </style>
</head>
<body>
<div class="app">

    <!-- Top Bar -->
    <div class="topbar">
        <a href="dashboard.php" class="linkish">&larr; Back to Dashboard</a>
        <span class="step">Astra Locator</span>
    </div>

    <h1 class="h1">Astra Branch &amp; ATM Locator</h1>

    <!-- Search / Filter Card -->
    <div class="card" style="margin-bottom:18px;">
        <div class="section">
            <p class="note" style="margin-top:0; margin-bottom:14px;">
                Find the nearest AstraBank branches and ATM booths. Select a city to filter locations.
            </p>

            <form method="get" action="astra-locator.php">
                <div class="row">
                    <label class="label" for="city">City</label>
                    <select id="city" name="city">
                        <option value="all" <?php if ($selected_city === 'all') echo 'selected'; ?>>All Cities</option>

                        <option value="Dhaka" <?php if ($selected_city === 'Dhaka') echo 'selected'; ?>>Dhaka</option>
                        <option value="Chattogram" <?php if ($selected_city === 'Chattogram') echo 'selected'; ?>>Chattogram</option>
                        <option value="Sylhet" <?php if ($selected_city === 'Sylhet') echo 'selected'; ?>>Sylhet</option>
                    </select>
                </div>

                <div class="footerbar">
                    <button type="submit" class="btn">Search Locations</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="section">
            <h2 style="margin-top:0; margin-bottom:10px;">Available Locations</h2>

            <?php if (empty($locations)): ?>
                <p class="note">No locations found for the selected city.</p>
            <?php else: ?>
                <div class="grid">
                    <?php foreach ($locations as $loc): ?>
                        <div class="opt" style="align-items:flex-start; flex-direction:column;">
                            <div>
                                <div class="title">
                                    <?php echo htmlspecialchars($loc['name']); ?>
                                    <small style="margin-left:6px; font-size:12px; color:#70737a;">
                                        (<?php echo htmlspecialchars($loc['type']); ?>)
                                    </small>
                                </div>

                                <small style="display:block; margin-top:4px;">
                                    <b>City:</b> <?php echo htmlspecialchars($loc['city']); ?>
                                </small>

                                <small style="display:block; margin-top:2px;">
                                    <b>Address:</b> <?php echo htmlspecialchars($loc['address']); ?>
                                </small>

                                <small style="display:block; margin-top:2px;">
                                    <b>Hours:</b> <?php echo htmlspecialchars($loc['hours']); ?>
                                </small>
                            </div>

                            <div class="linkrow" style="margin-top:8px;">
                                <?php if (!empty($loc['map_url'])): ?>
                                    <a class="linkish" href="<?php echo htmlspecialchars($loc['map_url']); ?>" target="_blank">
                                        View on Map &rarr;
                                    </a>
                                <?php else: ?>
                                    <span class="note">No map link</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
