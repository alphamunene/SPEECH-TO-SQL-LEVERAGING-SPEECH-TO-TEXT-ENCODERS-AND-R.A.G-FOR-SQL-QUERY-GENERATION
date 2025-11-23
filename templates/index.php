<?php
session_start();
$mysql = new mysqli("localhost", "root", "", "speech2sql");
$mysql->set_charset("utf8mb4");

// Handle AJAX requests only
$action = $_GET['action'] ?? '';
if ($action === 'save_history' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['query'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        exit;
    }

    $query = $mysql->real_escape_string($input['query']);
    $result_json = isset($input['result']) ? $mysql->real_escape_string(json_encode($input['result'])) : '[]';
    $mysql->query("INSERT INTO history (query, result, created_at) VALUES ('$query', '$result_json', NOW())");
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'load_history') {
    $res = $mysql->query("SELECT * FROM history ORDER BY created_at DESC");
    $history = [];
    while ($row = $res->fetch_assoc()) {
        $history[] = $row;
    }
    echo json_encode($history);
    exit;
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Speech2SQL</title>

  <!-- Bootstrap -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    body {
      background: #ffffff;
      font-family: "Segoe UI", Arial, sans-serif;
      margin: 0;
      padding-top: 90px; /* space for fixed header */
      color: #222;
    }

    /* HEADER STYLING */
    .navbar {
      background: linear-gradient(90deg, #011f42 0%, #012a63 100%);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
      height: 80px;
    }

    .navbar-brand {
      color: #ffffff !important;
      font-weight: 700;
      font-size: 1.5rem;
      letter-spacing: 0.5px;
    }

    .nav-link {
      position: relative;
      color: #f5f5f5 !important;
      font-weight: 500;
      margin-right: 18px;
      transition: all 0.3s ease-in-out;
    }

    /* Underline animation */
    .nav-link::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -3px;
      width: 0;
      height: 2px;
      background: #f1f107;
      transition: width 0.3s ease-in-out;
    }

    .nav-link:hover {
      color: #f1f107 !important;
      transform: scale(1.05);
    }

    .nav-link:hover::after {
      width: 100%;
    }

    .navbar-toggler {
      border-color: #fff;
    }

    .navbar-toggler-icon {
      filter: invert(100%);
    }

    .navbar-collapse {
      background-color: #011f42;
    }

    /* BODY STYLING */
    .container {
      max-width: 1000px;
      margin: auto;
      padding: 20px;
    }

    .tabs {
      display: flex;
      gap: 20px;
      justify-content: center;
      margin-bottom: 20px;
    }

    .tab {
      cursor: pointer;
      padding: 10px 20px;
      border-radius: 5px;
      background: #eee;
      font-weight: 500;
      transition: 0.3s;
    }

    .tab.active {
      background: #011f42;
      color: #fff;
    }

    .card {
      border: 1px solid #ccc;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    textarea {
      width: 100%;
      height: 80px;
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #ccc;
      resize: vertical;
    }

    .controls {
      margin-top: 10px;
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    button {
      padding: 8px 12px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    #queryBtn {
      background: #011f42;
      color: #fff;
    }

    #micBtn {
      background: #28a745;
      color: #fff;
    }

    #stopMicBtn {
      background: #dc3545;
      color: #fff;
    }

    #uploadBtn {
      background: #666;
      color: #fff;
    }

    #dbSection {
      display: none;
    }

    table {
      border-collapse: collapse;
      margin-top: 10px;
      width: 100%;
    }

    th,
    td {
      border: 1px solid #ccc;
      padding: 5px;
      text-align: left;
    }

    #micStatus {
      font-style: italic;
      color: #555;
    }

    footer {
      text-align: center;
      padding: 15px;
      font-size: 0.95rem;
      color: #555;
      border-top: 1px solid #ddd;
      margin-top: 30px;
    }
  </style>
 
</head>

<body>
  <!-- ====== NAVBAR ====== -->
  <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid px-4">
     
      <!-- LEFT MENU DROPDOWN -->
      <div class="dropdown me-3">
        <a href="#" class="d-flex align-items-center link-light text-decoration-none dropdown-toggle"
           data-bs-toggle="dropdown" aria-expanded="false">
          <span style="font-size: 1.5rem;">☰</span>
        </a>
        <ul class="dropdown-menu">
          <li class="dropdown-header text-muted">👤 Logged in as</li>
          <li><span class="dropdown-item-text fw-bold" id="userEmailMenu">
  <?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'No user'; ?>
</span></li>

          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="http://localhost/SPEECH2SQL_fixed_fixed/templates/login.php" id="logoutBtn">Logout</a></li>
        </ul>
      </div>
      <a class="navbar-brand" href="#">🎙️ Speech2SQL</a>
      <button
        class="navbar-toggler"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#navbarNav"
      > 
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-lg-center text-center">
          <li class="nav-item"><a class="nav-link" href="#" id="navQuery">Query</a></li>
          <li class="nav-item"><a class="nav-link" href="#" id="navhist">History</a></li>
          <li class="nav-item"><a class="nav-link" href="#" id="navDB">Database</a></li>
          <li class="nav-item"><a class="nav-link" href="#" id="navHelp">Help</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- ====== MAIN CONTENT ====== -->
  <div class="container">
    <!-- Tabs -->
    <div class="tabs">
      <div id="tabQuery" class="tab active">Query</div>
      <div id="tabDB" class="tab">Database</div>
    </div>

    <!-- Query Section -->
    <main id="querySection">
      <section class="card">
        <h2>Speak or Type Your Question</h2>
        <textarea id="nlquery" placeholder="e.g., List all employees in Marketing"></textarea>
        <div class="controls">
          <button id="queryBtn">Send Query</button>
          <button id="micBtn">🎤 Start Mic</button>
          <button id="stopMicBtn">⛔ Stop Mic</button>
          <span id="micStatus"></span>
          <input type="file" id="audioFile" accept="audio/*">
          <button id="uploadBtn">Transcribe Audio</button>
        </div>
      </section>

      <section class="card">
  <h2>Results</h2>
  <pre id="sql"></pre>
  <table id="resultTable"></table>
  <canvas id="chart" style="max-width:600px;"></canvas>

  <!-- Buttons for dropdowns -->
  <div class="mt-3">
    <button class="btn btn-info mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#schemaCollapse" aria-expanded="false">
      🗄️ Show Schema
    </button>
    <div class="collapse" id="schemaCollapse">
      <div class="card card-body" id="schemaContent">
        <!-- Schema will be injected here -->
      </div>
    </div>

    <button id="showSqlBtn" class="btn btn-secondary mb-2" type="button">💻 Show Generated SQL</button>

    <div class="collapse" id="sqlCollapse">
      <div class="card card-body">
        <pre id="generatedSQL"></pre>
      </div>
    </div>
  </div>
  
</section>

    </main>


  <!-- ====== DATABASE SECTION ====== -->
  <main id="dbSection">
    <section class="card text-center">
      <h2>🗄️ Database Operations</h2>

      <!-- Connect Button -->
      <button id="showDbOptionsBtn" class="btn btn-primary mb-3">
        🔗 Connect Database
      </button>

      <!-- Hidden Database Options -->
      <div id="dbOptions" style="display: none;">
        <p>Upload a database file or connect via connection string:</p>

        <div class="mb-3">
          <input type="file" id="dbFile" accept=".db,.sqlite" class="form-control">
        </div>

        <button id="uploadDbBtn" class="btn btn-primary mb-2 w-100">📤 Upload DB</button>

        <div class="mt-3">
          <input
            type="text"
            id="dbConn"
            placeholder="e.g., postgresql://user:pass@host/dbname"
            class="form-control mb-2"
          >
          <button id="connectDbBtn" class="btn btn-primary w-100">🔌 Connect DB</button>
        </div>

        <p id="dbStatus" class="mt-3 text-muted"></p>
        <div id="tableList" class="mt-3"></div>
      </div>

      <button id="showTablesBtn" class="btn btn-primary mb-3">
        📊 View Tables
      </button>
    </section>
  </main>


  <!-- ====== HELP SECTION ====== -->
  <main id="helpSection" style="display:none;">
    <section class="card text-center">
      <h2>🆘 Help & Support</h2>

      <!-- Search bar -->
      <div class="input-group mb-3 mt-3">
        <span class="input-group-text">🔍</span>
        <input
          type="text"
          id="helpSearch"
          class="form-control"
          placeholder="Search help topics..."
        />
      </div>

      <!-- Button to show help options -->
      <button id="showHelpOptionsBtn" class="btn btn-primary mb-3">
        📚 Show Help Topics
      </button>

      <!-- Hidden help topics -->
      <div id="helpOptions" style="display:none; text-align:left;">
        <p>Select a topic below or search above:</p>

        <!-- Accordion for help topics -->
        <div class="accordion" id="helpAccordion">

          <!-- Query Help -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpQuery">
                💬 How to Use the Query Section
              </button>
            </h2>
            <div id="helpQuery" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
              <div class="accordion-body">
                Type or speak your question into the text area, then click <b>Send Query</b>.
                The system will automatically convert it into an SQL statement and display the results.
              </div>
            </div>
          </div>

          <!-- Database Help -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpDB">
                🗄️ Managing the Database
              </button>
            </h2>
            <div id="helpDB" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
              <div class="accordion-body">
                Use <b>Connect Database</b> to upload a SQLite file or connect with a database string.
                You can then view or reload tables to explore data in your database.
              </div>
            </div>
          </div>

          <!-- Voice Help -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpVoice">
                🎤 Using Voice Commands
              </button>
            </h2>
            <div id="helpVoice" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
              <div class="accordion-body">
                Click <b>Start Mic</b> to capture your voice and automatically convert it to text.
                Use <b>Stop Mic</b> when you finish speaking.
              </div>
            </div>
          </div>

          <!-- Troubleshooting Help -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#helpTrouble">
                ⚙️ Troubleshooting Common Issues
              </button>
            </h2>
            <div id="helpTrouble" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
              <div class="accordion-body">
                <ul>
                  <li>🎙️ Check your microphone permissions if voice input doesn’t work.</li>
                  <li>🗄️ Make sure your database file is valid and uploaded correctly.</li>
                  <li>🌐 Ensure the backend server is running before sending queries.</li>
                  <li>🔄 Try refreshing the page to reload any lost connections.</li>
                </ul>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  </main>
<!-- History Main Section -->
<main id="historyPage" style="display:none; margin-top: 20px;">
  <section class="card shadow p-3">
    <h3 class="mb-3">History</h3>
    <table class="table table-bordered">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Query</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody id="historyBody"></tbody>
    </table>
  </section>
</main>

  <footer>
    &copy; Speech2SQL
  </footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Elements
  const navQuery = document.getElementById('navQuery');
  const navDB = document.getElementById('navDB');
  const navHelp = document.getElementById('navHelp');
  const tabQuery = document.getElementById('tabQuery');
  const tabDB = document.getElementById('tabDB');
  const querySection = document.getElementById('querySection');
  const dbSection = document.getElementById('dbSection');
  const helpSection = document.getElementById('helpSection');

  const queryBtn = document.getElementById('queryBtn');
  const micBtn = document.getElementById('micBtn');
  const stopMicBtn = document.getElementById('stopMicBtn');
  const micStatus = document.getElementById('micStatus');
  const nlquery = document.getElementById('nlquery');
  const uploadBtn = document.getElementById('uploadBtn');
  const audioFile = document.getElementById('audioFile');

  const showDbOptionsBtn = document.getElementById('showDbOptionsBtn');
  const dbOptions = document.getElementById('dbOptions');
  const connectDbBtn = document.getElementById('connectDbBtn');
  const uploadDbBtn = document.getElementById('uploadDbBtn');
  const dbFile = document.getElementById('dbFile');
  const dbConn = document.getElementById('dbConn');
  const dbStatus = document.getElementById('dbStatus');
  const showTablesBtn = document.getElementById('showTablesBtn');
  const tableList = document.getElementById('tableList');

  const showHelpOptionsBtn = document.getElementById('showHelpOptionsBtn');
  const helpOptions = document.getElementById('helpOptions');

  const sqlPre = document.getElementById('sql');
  const resultTable = document.getElementById('resultTable');
  const chartCanvas = document.getElementById('chart');

  document.querySelector('[data-bs-target="#schemaCollapse"]')?.addEventListener('click', async () => {
  const schemaContentEl = document.getElementById('schemaContent');
  try {
    const res = await fetch('/api/show-tables?limit=3'); // Calls main.py endpoint
    const tablesJson = await res.json();

    if (tablesJson.tables && tablesJson.tables.length > 0) {
      let html = '';
      tablesJson.tables.forEach(tbl => {
        html += `<h5>${tbl.table}</h5>`;
        if (tbl.error) {
          html += `<p style="color:red">${tbl.error}</p>`;
          return;
        }
        html += `<p><b>Columns:</b> ${tbl.columns.join(', ')}</p>`;
        if (tbl.rows && tbl.rows.length > 0) {
          html += '<table class="table table-sm table-bordered"><thead><tr>';
          tbl.columns.forEach(c => html += `<th>${c}</th>`);
          html += '</tr></thead><tbody>';
          tbl.rows.forEach(r => {
            html += '<tr>';
            tbl.columns.forEach(c => html += `<td>${r[c] !== undefined ? r[c] : ''}</td>`);
            html += '</tr>';
          });
          html += '</tbody></table>';
        }
      });
      schemaContentEl.innerHTML = html;
    } else {
      schemaContentEl.innerHTML = '<em>No tables found.</em>';
    }

    const schemaCollapseEl = document.getElementById('schemaCollapse');
    const schemaCollapse = new bootstrap.Collapse(schemaCollapseEl, { toggle: false });
    schemaCollapse.show();
  } catch (err) {
    schemaContentEl.innerHTML = `<em>Error fetching schema: ${err.message}</em>`;
  }
});

document.getElementById("showSqlBtn")?.addEventListener("click", async () => {
  const nlQuery = document.getElementById("nlquery").value.trim();
  const generatedSQLPre = document.getElementById("generatedSQL");

  if (!nlQuery) {
    alert("Please enter a query first!");
    return;
  }

  try {
    const res = await fetch("/api/view_sql", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ query: nlQuery }),
    });

    const data = await res.json();

    if (!res.ok) {
      generatedSQLPre.textContent = `Error: ${data.detail || res.status}`;
      return;
    }

    generatedSQLPre.textContent = data.sql_preview || "No SQL returned.";
    // Show collapse
    const sqlCollapseEl = document.getElementById("sqlCollapse");
    const sqlCollapse = new bootstrap.Collapse(sqlCollapseEl, { toggle: false });
    sqlCollapse.show();

  } catch (err) {
    generatedSQLPre.textContent = `Fetch failed: ${err.message}`;
  }
});


  // Navigation / tabs
  function showSection(id) {
    querySection.style.display = id === 'querySection' ? 'block' : 'none';
    dbSection.style.display = id === 'dbSection' ? 'block' : 'none';
    helpSection.style.display = id === 'helpSection' ? 'block' : 'none';

    tabQuery.classList.toggle('active', id === 'querySection');
    tabDB.classList.toggle('active', id === 'dbSection');
  }

  navQuery?.addEventListener('click', (e) => { e.preventDefault(); showSection('querySection'); });
  navDB?.addEventListener('click', (e) => { e.preventDefault(); showSection('dbSection'); });
  navHelp?.addEventListener('click', (e) => { e.preventDefault(); showSection('helpSection'); });

  tabQuery?.addEventListener('click', () => showSection('querySection'));
  tabDB?.addEventListener('click', () => showSection('dbSection'));

  // Toggle DB options
  showDbOptionsBtn?.addEventListener('click', () => {
    dbOptions.style.display = dbOptions.style.display === 'none' ? 'block' : 'none';
  });

  // Help options toggle
  showHelpOptionsBtn?.addEventListener('click', () => {
    helpOptions.style.display = helpOptions.style.display === 'none' ? 'block' : 'none';
  });

  // POST JSON helper (keeps original Authorization behavior)
 async function postJSON(url, data) {
    const token = localStorage.getItem('token') || '';
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
        body: JSON.stringify(data)
    });

    let json = {};
    try {
        json = await res.json();
    } catch (err) {
        // Handle non-JSON response
        const text = await res.text();
        json = { detail: 'Invalid JSON response', raw: text };
    }

    return { ok: res.ok, status: res.status, json };
}


  // Query -> backend
 queryBtn?.addEventListener('click', async () => {
    const q = nlquery.value.trim();
    if (!q) { alert('Please enter a query'); return; }

    try {
        const { ok, json } = await postJSON('/api/query_nl', { query: q });

        if (!ok) {
            sqlPre.innerText = json.detail || json.error || 'Error';
            renderResults([], []);
            return;
        }

        sqlPre.innerText = json.sql || '';
        renderResults(json.rows || [], json.columns || json.cols || []);

          // === Save history in index.php/MySQL ===
        await fetch('index.php?action=save_history', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                query: q,
                result: json.rows || []
            })
        });

    } catch (err) {
        alert('Error sending query: ' + err.message);
    }
});

  // Audio upload -> transcribe (keeps original endpoint /api/upload_audio)
  uploadBtn?.addEventListener('click', async () => {
    const f = audioFile.files[0];
    if (!f) { alert('Choose file'); return; }
    const fd = new FormData();
    fd.append('file', f);
    try {
      const token = localStorage.getItem('token') || '';
      const res = await fetch('/api/upload_audio', { method: 'POST', body: fd, headers: { 'Authorization': 'Bearer ' + token } });
      const j = await res.json();
      if (!res.ok) {
        alert('Upload error: ' + (j.detail || j.error || res.status));
        return;
      }
      nlquery.value = j.transcript || '';
    } catch (err) {
      alert('Upload failed: ' + err.message);
    }
  });

  // --------------------
  // Speech recognition (robust)
  // --------------------
  let recognition = null;
  const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  if (SpeechRec) {
    recognition = new SpeechRec();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    recognition.continuous = false;

    recognition.onstart = () => {
      micStatus.innerText = '🎤 Listening...';
      micBtn.disabled = true;
      stopMicBtn.disabled = false;
      micBtn.style.opacity = '0.6';
      stopMicBtn.style.opacity = '1';
    };

    recognition.onresult = (event) => {
      // event.results is a LiveSpeechRecognitionResultList
      let transcript = '';
      for (let i = event.resultIndex; i < event.results.length; i++) {
        transcript += event.results[i][0].transcript;
      }
      // Append or replace? keep original behavior: replace field
      nlquery.value = transcript;
    };

    recognition.onerror = (ev) => {
      micStatus.innerText = '⚠️ ' + (ev.error || 'Speech error');
      console.error('Speech recognition error', ev);
    };

    recognition.onend = () => {
      micStatus.innerText = '';
      micBtn.disabled = false;
      stopMicBtn.disabled = true;
      micBtn.style.opacity = '1';
      stopMicBtn.style.opacity = '0.6';
    };
  } else {
    // Not supported
    micBtn.disabled = true;
    micBtn.title = 'Speech recognition not supported in this browser';
    micStatus.innerText = 'Speech recognition not supported in this browser.';
  }

  micBtn?.addEventListener('click', () => {
    if (!recognition) { alert('Speech recognition not supported. Use Chrome or Edge.'); return; }
    try {
      recognition.start();
    } catch (err) {
      console.warn('recognition.start error', err);
    }
  });

  stopMicBtn?.addEventListener('click', () => {
    if (recognition) recognition.stop();
    micStatus.innerText = '⛔ Stopped.';
    setTimeout(() => { if (micStatus.innerText === '⛔ Stopped.') micStatus.innerText = ''; }, 1200);
  });

  // Render results (keeps original behavior)
  function renderResults(rows, cols) {
    // clear table
    resultTable.innerHTML = '';
    if (!cols || cols.length === 0) return;

    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    cols.forEach(c => { const th = document.createElement('th'); th.innerText = c; headRow.appendChild(th); });
    thead.appendChild(headRow);
    resultTable.appendChild(thead);

    const tbody = document.createElement('tbody');
    rows.forEach(r => {
      const tr = document.createElement('tr');
      r.forEach(cell => { const td = document.createElement('td'); td.innerText = (cell === null ? '' : cell); tr.appendChild(td); });
      tbody.appendChild(tr);
    });
    resultTable.appendChild(tbody);

    // Chart: try to plot numeric columns (keeps original simple behavior)
    try {
      const nums = cols.map((c,i) => rows.map(r => parseFloat(r[i])).filter(v => !isNaN(v)));
      const firstNum = nums.find(a => a.length > 0);
      if (!firstNum) return;
      // destroy previous chart if any - keep simple by replacing canvas
      if (chartCanvas) {
        const ctx = chartCanvas.getContext('2d');
        // create new chart
        new Chart(ctx, { type: 'bar', data: { labels: rows.map((r,i)=> i+1), datasets: [{ label: 'Series', data: firstNum }] } });
      }
    } catch (err) {
      console.warn('Chart render failed', err);
    }
  }

  // --------------------
  // DB Management
  // --------------------

  // Upload DB file (keeps original behavior: attempts to POST to /api/upload_db)
  uploadDbBtn?.addEventListener('click', async () => {
    const admin = prompt('Enter admin password:');
    if (!admin) return alert('Admin password required');
    const f = dbFile.files[0];
    if (!f) return alert('Choose DB file');
    const fd = new FormData();
    fd.append('file', f);
    try {
      const res = await fetch('/api/upload_db', { method: 'POST', body: fd, headers: { 'X-Admin-Auth': admin } });
      const j = await res.json().catch(()=>({ detail: 'Invalid JSON response' }));
      if (!res.ok) {
        alert('Upload failed: ' + (j.detail || res.status));
        return;
      }
      dbStatus.textContent = j.message || 'Uploaded';
      // trigger rebuild
      await fetch('/api/rebuild_schema_index', { method: 'POST', headers: { 'X-Admin-Auth': admin } });
    } catch (err) {
      alert('Upload DB failed: ' + err.message);
    }
  });

  // Connect DB via connection string - send JSON { conn_str: <value> } to /api/connect_db
  connectDbBtn?.addEventListener('click', async () => {
    const admin = prompt('Enter admin password:');
    if (!admin) return alert('Admin password required');
    const conn = dbConn.value.trim();
    if (!conn) return alert('Enter connection string');
    try {
      const res = await fetch('/api/connect_db', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Admin-Auth': admin },
        body: JSON.stringify({ conn_str: conn }) // keep backend-compatible key name
      });
      const j = await res.json().catch(()=>({ detail: 'Invalid JSON response' }));
      if (!res.ok) {
        alert('Connection failed: ' + (j.detail || res.status));
        dbStatus.textContent = 'Connection failed';
        return;
      }
      dbStatus.textContent = j.message || 'Connected';
      // try rebuild
      await fetch('/api/rebuild_schema_index', { method: 'POST', headers: { 'X-Admin-Auth': admin } });
    } catch (err) {
      alert('Connect DB failed: ' + err.message);
    }
  });

  showTablesBtn?.addEventListener('click', async () => {
    try {
        const res = await fetch('/api/view_schema');  // Use your schema API
        const j = await res.json();
        
        if (j.schema && Object.keys(j.schema).length > 0) {
            let html = '<h5>Tables & Columns</h5><ul>';
            for (const [table, cols] of Object.entries(j.schema)) {
                html += `<li><b>${table}</b>: ${cols.join(', ')}</li>`;
            }
            html += '</ul>';
            tableList.innerHTML = html;
        } else {
            tableList.innerHTML = '<em>No tables found or not connected.</em>';
        }
    } catch (err) {
        tableList.innerHTML = '<em>Error fetching tables: ' + err.message + '</em>';
    }
});


  // initial section
  showSection('querySection');
});
</script>
 <script>
    const API_BASE = "http://127.0.0.1:8000";

async function connectDB() {
  const connStr = document.getElementById("connStr").value;
  const res = await fetch(`${API_BASE}/api/connect_db`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ conn_str: connStr }),
  });
  const data = await res.json();
  alert(data.message || data.detail);
}


  </script>
 <script>
document.addEventListener('DOMContentLoaded', () => {
  // Section elements
  const querySection = document.getElementById('querySection');
  const dbSection = document.getElementById('dbSection');
  const helpSection = document.getElementById('helpSection');
  const historyPage = document.getElementById('historyPage');

  // Navigation
  const navQuery = document.getElementById('navQuery');
  const navDB = document.getElementById('navDB');
  const navHelp = document.getElementById('navHelp');
  const navhist = document.getElementById('navhist');

  // Tabs
  const tabQuery = document.getElementById('tabQuery');
  const tabDB = document.getElementById('tabDB');

  // Helper: Hide all except selected section
  function showSection(section) {
    querySection.style.display = section === 'querySection' ? 'block' : 'none';
    dbSection.style.display = section === 'dbSection' ? 'block' : 'none';
    helpSection.style.display = section === 'helpSection' ? 'block' : 'none';
    historyPage.style.display = section === 'historyPage' ? 'block' : 'none';

    tabQuery.classList.toggle('active', section === 'querySection');
    tabDB.classList.toggle('active', section === 'dbSection');
  }

  // Navigation events
  navQuery?.addEventListener('click', e => { e.preventDefault(); showSection('querySection'); });
  navDB?.addEventListener('click', e => { e.preventDefault(); showSection('dbSection'); });
  navHelp?.addEventListener('click', e => { e.preventDefault(); showSection('helpSection'); });
  tabQuery?.addEventListener('click', () => showSection('querySection'));
  tabDB?.addEventListener('click', () => showSection('dbSection'));

  // History event
  navhist?.addEventListener('click', e => {
    e.preventDefault();
    showSection('historyPage');
    loadHistory();
  });

  // Load history function
 async function loadHistory() {
    const res = await fetch('index.php?action=load_history');
    const data = await res.json();

    const body = document.getElementById("historyBody");
    body.innerHTML = "";

    data.forEach((row, i) => {
        let prettyResult = '';
        try {
            // Try to parse JSON and pretty-print it
            const parsed = JSON.parse(row.result);
            prettyResult = `<pre>${JSON.stringify(parsed, null, 2)}</pre>`;
        } catch (err) {
            // Fallback if not valid JSON
            prettyResult = row.result;
        }

        body.innerHTML += `
            <tr>
                <td>${i + 1}</td>
                <td>${row.query}<br>${prettyResult}</td>
                <td>${row.created_at}</td>
            </tr>
        `;
    });

    if (data.length === 0) {
        body.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">No history found.</td>
            </tr>
        `;
    }
}


  // Initial state
  showSection('querySection');
});
</script>


</body>
</html>
