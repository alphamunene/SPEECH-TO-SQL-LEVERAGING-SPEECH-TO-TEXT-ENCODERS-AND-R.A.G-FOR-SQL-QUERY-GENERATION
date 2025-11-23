<?php
session_start();

// =====================================================================================
// 1. ADMIN LOGIN SECURITY
// =====================================================================================
if (!isset($_SESSION['admin_authenticated'])) {
    if (isset($_POST['admin_pass'])) {
        if ($_POST['admin_pass'] === "admin") {
            $_SESSION['admin_authenticated'] = true;
        } else {
            $error = "Incorrect password!";
        }
    }

    if (!isset($_SESSION['admin_authenticated'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Admin Login</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
        </head>
        <body class="bg-dark d-flex justify-content-center align-items-center vh-100">
            <form method="POST" class="card p-4" style="width:350px;">
                <h4 class="mb-3 text-center">Admin Access</h4>
                <?php if(isset($error)) echo "<p class='text-danger'>$error</p>"; ?>
                <input type="password" class="form-control mb-3" name="admin_pass" placeholder="Enter admin password">
                <button class="btn btn-primary w-100">Login</button>
            </form>
        </body>
        </html>
        <?php
        exit();
    }
}

// =====================================================================================
// 2. LOAD CURRENT SECURITY MODE
// =====================================================================================
$security_file = __DIR__ . "/security_mode.txt";
if (!file_exists($security_file)) file_put_contents($security_file, "medium");
$current_mode = trim(file_get_contents($security_file));

// =====================================================================================
// 3. UPDATE SECURITY MODE
// =====================================================================================
if (isset($_GET['setmode'])) {
    $new = $_GET['setmode'] === "high" ? "high" : "medium";
    file_put_contents($security_file, $new);
    $current_mode = $new;
}

// =====================================================================================
// 4. LOGGED USER DATABASE (text-based)
// =====================================================================================
$data_file = __DIR__ . "/logged_users.txt";
if (!file_exists($data_file)) touch($data_file);

// Parse data
$records = [];
$lines = file($data_file, FILE_IGNORE_NEW_LINES);
foreach ($lines as $line) {
    list($email,$login,$logout,$ip,$agent) = explode("|", $line);
    $records[] = [
        "email" => trim($email),
        "login" => trim($login),
        "logout" => trim($logout),
        "ip" => trim($ip),
        "agent" => trim($agent)
    ];
}

// =====================================================================================
// 5. EXPORT CSV
// =====================================================================================
if (isset($_GET['export'])) {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=users_log.csv");
    $out = fopen("php://output", "w");
    fputcsv($out, ["Email", "Login Time", "Logout Time", "IP Address", "User-Agent"]);
    foreach ($records as $r) fputcsv($out, $r);
    fclose($out);
    exit();
}

// =====================================================================================
// 6. HELPER: Determine "Online" status
// =====================================================================================
function onlineStatus($logout_time) {
    return trim($logout_time) === "-" ? 
        "<span class='badge bg-success'>Online</span>" :
        "<span class='badge bg-secondary'>Offline</span>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Speech2SQL Admin Panel</title>

    <!-- AUTO REFRESH EVERY 10 SECONDS -->
    <meta http-equiv="refresh" content="10">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <style>
        body { background:#011f42; color:white; }
        .card { background:white; border-radius:10px; }
        .dot { height:15px; width:15px; border-radius:50%; display:inline-block; }
    </style>
</head>
<body class="p-4">

<h2 class="mb-4 fw-bold">⚙ Speech2SQL Admin Panel</h2>

<!-- =================================================================================
     SECURITY SETTINGS
===================================================================================== -->
<div class="card p-3 mb-4">
    <h4>Security Mode</h4>
    <p>Only one mode can be active at a time.</p>

    <div class="d-flex justify-content-center mb-3 gap-3">
        <a href="admin.php?setmode=medium" class="btn btn-dark">
            Medium Security
            <?php if($current_mode=="medium") 
                echo '<span class="dot bg-success ms-2"></span>'; 
            else 
                echo '<span class="dot bg-danger ms-2"></span>'; ?>
        </a>

        <a href="admin.php?setmode=high" class="btn btn-dark">
            High Security
            <?php if($current_mode=="high") 
                echo '<span class="dot bg-success ms-2"></span>'; 
            else 
                echo '<span class="dot bg-danger ms-2"></span>'; ?>
        </a>
    </div>

    <hr>
    <p class="mt-2">Current setting:
        <strong class="text-warning"><?= strtoupper($current_mode) ?></strong>
    </p>

    <p class="mt-3">
        <a href="<?= $current_mode=='high' ? 'loginn.php' : 'login.php' ?>" class="btn btn-dark">
            Open Login Page
        </a>

        <a href="view_logs.php" class="btn btn-dark ms-2">
            See or edit Logins
        </a>
    </p>
</div>

</body>
</html>
