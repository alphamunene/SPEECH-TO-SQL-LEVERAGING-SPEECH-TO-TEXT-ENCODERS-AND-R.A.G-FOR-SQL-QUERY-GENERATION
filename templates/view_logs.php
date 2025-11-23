<?php
include('config.php'); // includes both MySQL + SQLite connections

// --- Add status column if not exists ---
$columns = $sqlite->query("PRAGMA table_info(logs)");
$hasStatus = false;
while ($col = $columns->fetchArray(SQLITE3_ASSOC)) {
    if ($col['name'] === 'status') {
        $hasStatus = true;
        break;
    }
}
if (!$hasStatus) {
    $sqlite->exec("ALTER TABLE logs ADD COLUMN status TEXT DEFAULT 'active'");
}

// --- Handle Deactivate Single Log ---
if (isset($_GET['deactivate'])) {
    $id = intval($_GET['deactivate']);
    $sqlite->exec("UPDATE logs SET status='inactive' WHERE id = $id");
    header("Location: view_logs.php");
    exit();
}

// --- Handle Activate Single Log ---
if (isset($_GET['activate'])) {
    $id = intval($_GET['activate']);
    $sqlite->exec("UPDATE logs SET status='active' WHERE id = $id");
    header("Location: view_logs.php");
    exit();
}

// --- Handle Deactivate All Logs ---
if (isset($_GET['deactivate_all'])) {
    $sqlite->exec("UPDATE logs SET status='inactive'");
    header("Location: view_logs.php");
    exit();
}

// --- Handle Export to CSV ---
if (isset($_GET['export'])) {
    $filename = "logs_export_" . date("Y-m-d_H-i-s") . ".csv";
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=$filename");

    $output = fopen("php://output", "w");
    fputcsv($output, ["ID", "Action", "Email", "Timestamp", "Status"]);

    $results = $sqlite->query("SELECT * FROM logs ORDER BY id DESC");
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Activity Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://kit.fontawesome.com/a2e0ad1c6d.js" crossorigin="anonymous"></script>
  <style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    .table-container { max-height: 500px; overflow-y: auto; }
    .badge { font-size: 0.9em; }
  </style>
</head>
<body>

<div class="container mt-5">
  <div class="card">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h4 class="mb-0"><i class="fa-solid fa-clipboard-list"></i> Activity Logs</h4>
      <div>
        <a href="login.php" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-door-open"></i> Back to Login</a>
      </div>
    </div>

    <div class="card-body">
      <!-- Control Buttons -->
      <div class="d-flex justify-content-between mb-3">
        <div>
          <a href="?export=true" class="btn btn-success btn-sm">
            <i class="fa-solid fa-file-export"></i> Export CSV
          </a>
          <a href="?deactivate_all=true" onclick="return confirm('Deactivate ALL logs?')" class="btn btn-warning btn-sm">
            <i class="fa-solid fa-ban"></i> Deactivate All
          </a>
        </div>
        <input type="text" id="searchInput" class="form-control w-25" placeholder="Search..." onkeyup="searchLogs()">
      </div>

      <!-- Logs Table -->
      <div class="table-container">
        <table class="table table-hover align-middle text-center">
          <thead class="table-dark sticky-top">
            <tr>
              <th>ID</th>
              <th>Action</th>
              <th>User Email</th>
              <th>Timestamp</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $results = $sqlite->query("SELECT * FROM logs ORDER BY id DESC");
            if ($results) {
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $statusBadge = $row['status'] === 'active' 
                        ? "<span class='badge bg-success'>Active</span>"
                        : "<span class='badge bg-secondary'>Inactive</span>";

                    $actionBtn = $row['status'] === 'active' 
                        ? "<a href='?deactivate={$row['id']}' class='btn btn-sm btn-outline-warning' onclick='return confirm(\"Deactivate this log?\")'>
                             <i class='fa-solid fa-ban'></i>
                           </a>"
                        : "<a href='?activate={$row['id']}' class='btn btn-sm btn-outline-success' onclick='return confirm(\"Activate this log?\")'>
                             <i class='fa-solid fa-check'></i>
                           </a>";

                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td><span class='badge bg-primary'>{$row['action']}</span></td>
                            <td>{$row['email']}</td>
                            <td>{$row['timestamp']}</td>
                            <td>$statusBadge</td>
                            <td>$actionBtn</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No logs found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function searchLogs() {
  let input = document.getElementById('searchInput').value.toLowerCase();
  let rows = document.querySelectorAll('tbody tr');
  rows.forEach(row => {
    let text = row.textContent.toLowerCase();
    row.style.display = text.includes(input) ? '' : 'none';
  });
}
</script>

</body>
</html>
