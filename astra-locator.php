<?php
// sbl-locator.php
session_start();

// Must be logged in
/*if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit;
}*/

$cid = (string)$_SESSION['cid'];

// Simple in-memory list of branches/ATMs
$locations = [
    [
        'name'   => 'SBL Head Office Branch',
        'type'   => 'Branch',
        'city'   => 'Dhaka',
        'address'=> '10 Example Road, Motijheel, Dhaka',
        'hours'  => 'Sun–Thu, 10:00 AM – 4:00 PM',
        'map'    => 'https://maps.google.com'
    ],
    [
        'name'   => 'SBL Gulshan ATM Booth',
        'type'   => 'ATM',
        'city'   => 'Dhaka',
        'address'=> 'Gulshan Avenue, Dhaka',
        'hours'  => '24 / 7',
        'map'    => 'https://maps.google.com'
    ],
    [
        'name'   => 'SBL Chattogram Branch',
        'type'   => 'Branch',
        'city'   => 'Chattogram',
        'address'=> 'Agrabad C/A, Chattogram',
        'hours'  => 'Sun–Thu, 10:00 AM – 4:00 PM',
        'map'    => 'https://maps.google.com'
    ],
    [
        'name'   => 'SBL Sylhet ATM Booth',
        'type'   => 'ATM',
        'city'   => 'Sylhet',
        'address'=> 'Zindabazar, Sylhet',
        'hours'  => '24 / 7',
        'map'    => 'https://maps.google.com'
    ],
];

$selected_city = trim($_GET['city'] ?? '');

// Filter locations by city if selected
$filtered = array_filter($locations, function ($loc) use ($selected_city) {
    if ($selected_city === '' || $selected_city === 'all') return true;
    return strcasecmp($loc['city'], $selected_city) === 0;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Astra Locator — Branch &amp; ATM Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="transfer.css">
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
                        <option value="all" <?php if ($selected_city === '' || $selected_city === 'all') echo 'selected'; ?>>
                            All Cities
                        </option>
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
            <?php if (empty($filtered)): ?>
                <p class="note">No locations found for the selected city.</p>
            <?php else: ?>
                <div class="grid">
                    <?php foreach ($filtered as $loc): ?>
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
                                <a class="linkish" href="<?php echo htmlspecialchars($loc['map']); ?>" target="_blank">
                                    View on Map &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Note -->
    <div class="card" style="margin-top:18px;">
        <div class="section">
            <p class="note" style="margin-top:0;">
                *Location data is for demonstration. You can later load real branch &amp; ATM lists
                from your database table (e.g. <code>sbl_locations</code>) instead of the static array.
            </p>
        </div>
    </div>

</div>
</body>
</html>
