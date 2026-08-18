<?php
session_start();

// 1. Protection & Auth
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 2. Database Connection
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=synergy1_derricklim_parcel_delivery_management;charset=utf8mb4", "synergy1_yenping", "R.zb0ZwEuGZ}*fW2");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$message = '';
$message_type = 'success';

// 3. Handle ADD NEW PARCEL Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_parcel'])) {
    $sender_name = trim($_POST['sender_name'] ?? '');
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $recipient_phone = trim($_POST['recipient_phone'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    
    // Auto-generate Unique Tracking Number
    $tracking_number = 'TRK-' . date('Y') . '-' . rand(1000, 9999);

    if ($sender_name && $recipient_name && $delivery_address) {
        try {
            $stmt = $pdo->prepare("INSERT INTO parcels (tracking_number, sender_name, recipient_name, recipient_phone, delivery_address, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$tracking_number, $sender_name, $recipient_name, $recipient_phone, $delivery_address]);
            $message = "🎉 Parcel created successfully! Tracking Number: <strong>$tracking_number</strong>";
        } catch (PDOException $e) {
            $message = "Error creating parcel: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 4. Handle Parcel Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_parcel'])) {
    $parcel_id = $_POST['parcel_id'] ?? '';
    $rider_id = $_POST['rider_id'] ?? '';
    if ($parcel_id && $rider_id) {
        $stmt = $pdo->prepare("UPDATE parcels SET rider_id = ?, status = 'in_transit' WHERE id = ?");
        $stmt->execute([$rider_id, $parcel_id]);
        $message = "Parcel #$parcel_id assigned to Rider successfully!";
    }
}

$userName = $_SESSION['full_name'] ?? 'Admin User';
$userRole = ucfirst($_SESSION['role'] ?? 'admin');
$page = $_GET['page'] ?? 'dashboard';

// Search terms
$search_parcel = $_GET['search_parcel'] ?? '';
$search_rider = $_GET['search_rider'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SwiftCourier Admin - <?= ucfirst($page) ?></title>

  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
    body { display: flex; min-height: 100vh; background: #0f172a; color: #f8fafc; }
    
    /* Sidebar */
    .sidebar { width: 260px; background: #1e293b; padding: 25px 20px; border-right: 1px solid #334155; display: flex; flex-direction: column; justify-content: space-between; }
    .brand { font-size: 1.4rem; font-weight: bold; color: #38bdf8; margin-bottom: 30px; }
    .nav-links { list-style: none; }
    .nav-links li { margin-bottom: 8px; }
    .nav-links a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #94a3b8; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.2s; }
    .nav-links a:hover, .nav-links a.active { background: #3b82f6; color: #ffffff; }
    
    /* Main Content */
    .main-content { flex: 1; padding: 35px; background: #0f172a; overflow-y: auto; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #334155; padding-bottom: 20px; }
    .header h1 { font-size: 1.8rem; font-weight: 600; color: #f8fafc; }
    .badge { background: #3b82f6; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; text-transform: uppercase; }
    .btn-logout { background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; }
    .btn-logout:hover { background: #dc2626; }

    /* Stat Cards Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .card { background: #1e293b; padding: 18px; border-radius: 12px; border: 1px solid #334155; }
    .card-title { color: #94a3b8; font-size: 0.85rem; font-weight: 500; }
    .card-value { font-size: 1.8rem; font-weight: 700; margin-top: 8px; color: #f8fafc; }
    
    /* Section & Forms */
    .section-card { background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 20px; margin-bottom: 25px; }
    .section-card h3 { margin-bottom: 15px; color: #f8fafc; display: flex; justify-content: space-between; align-items: center; }
    
    .toolbar { display: flex; gap: 12px; margin-bottom: 15px; }
    input[type="text"], input[type="tel"], input[type="search"], select, textarea { background: #0f172a; border: 1px solid #334155; padding: 10px 14px; border-radius: 6px; color: #fff; width: 100%; font-size: 0.95rem; }
    button, .btn { background: #2563eb; color: white; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; }
    button:hover, .btn:hover { background: #1d4ed8; }
    .btn-success { background: #16a34a; }
    .btn-success:hover { background: #15803d; }

    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { padding: 12px; color: #94a3b8; border-bottom: 1px solid #334155; font-size: 0.85rem; text-transform: uppercase; }
    td { padding: 14px 12px; border-bottom: 1px solid #334155; color: #e2e8f0; font-size: 0.95rem; }

    .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
    .status-in_transit { background: #0284c7; color: #e0f2fe; }
    .status-pending { background: #d97706; color: #fef3c7; }
    .status-delivered { background: #16a34a; color: #dcfce7; }
    .status-online { background: #16a34a; color: #dcfce7; }
    .status-offline { background: #64748b; color: #f1f5f9; }

    #map { width: 100%; height: 380px; border-radius: 10px; border: 1px solid #334155; }
    .alert-success { background: #064e3b; color: #a7f3d0; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
    .alert-error { background: #7f1d1d; color: #fecaca; padding: 12px; border-radius: 6px; margin-bottom: 20px; }

    /* Modal Pop-up Styling */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); justify-content: center; align-items: center; z-index: 999; }
    .modal-content { background: #1e293b; width: 100%; max-width: 500px; padding: 25px; border-radius: 12px; border: 1px solid #334155; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .close-btn { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
    .form-group { margin-bottom: 15px; position: relative; }
    .form-group label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 5px; }

    /* Auto-complete Suggestions Box */
    .suggestions-box {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: #1e293b;
      border: 1px solid #38bdf8;
      border-radius: 0 0 6px 6px;
      max-height: 180px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
      box-shadow: 0 10px 15px rgba(0,0,0,0.5);
    }
    .suggestion-item {
      padding: 10px;
      font-size: 0.85rem;
      color: #e2e8f0;
      cursor: pointer;
      border-bottom: 1px solid #334155;
    }
    .suggestion-item:hover {
      background: #3b82f6;
      color: #fff;
    }
  </style>
</head>
<body>

  <!-- Navigation Sidebar -->
  <aside class="sidebar">
    <div>
      <div class="brand">⚡ SwiftCourier Admin</div>
      <ul class="nav-links">
        <li><a href="dashboard.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">📊 Overview & Map</a></li>
        <li><a href="dashboard.php?page=parcels" class="<?= $page === 'parcels' ? 'active' : '' ?>">📦 Parcels & Assign</a></li>
        <li><a href="dashboard.php?page=riders" class="<?= $page === 'riders' ? 'active' : '' ?>">🛵 Riders Directory</a></li>
        <li><a href="dashboard.php?page=logs" class="<?= $page === 'logs' ? 'active' : '' ?>">📜 Activity Logs</a></li>
        <li><a href="dashboard.php?page=reports" class="<?= $page === 'reports' ? 'active' : '' ?>">📈 Generate Reports</a></li>
      </ul>
    </div>
    <div>
      <a href="dashboard.php?logout=1" class="btn-logout" style="display: block; text-align: center;">Logout</a>
    </div>
  </aside>

  <!-- Main View Panel -->
  <main class="main-content">
    <header class="header">
      <div>
        <h1><?= ucfirst($page) ?></h1>
        <p style="color: #94a3b8; margin-top: 4px;">Welcome back, <strong><?= htmlspecialchars($userName) ?></strong></p>
      </div>
      <div>
        <button class="btn-success" onclick="openAddModal()">➕ Add New Parcel</button>
        <span class="badge" style="margin-left: 10px;"><?= htmlspecialchars($userRole) ?></span>
      </div>
    </header>

    <?php if ($message): ?>
      <div class="<?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>"><?= $message ?></div>
    <?php endif; ?>

    <!-- STATS OVERVIEW -->
    <?php
      $totRiders = $pdo->query("SELECT COUNT(*) FROM riders")->fetchColumn();
      try {
          $onlineRiders = $pdo->query("SELECT COUNT(*) FROM riders WHERE is_online = 1")->fetchColumn();
      } catch (PDOException $e) { $onlineRiders = 0; }
      $offlineRiders = $totRiders - $onlineRiders;

      $totParcels  = $pdo->query("SELECT COUNT(*) FROM parcels")->fetchColumn();
      $delParcels  = $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'delivered'")->fetchColumn();
      $penParcels  = $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'pending'")->fetchColumn();
    ?>
    <section class="stats-grid">
      <div class="card"><div class="card-title">Total Riders</div><div class="card-value"><?= $totRiders ?></div></div>
      <div class="card"><div class="card-title">Online Riders</div><div class="card-value" style="color:#22c55e;"><?= $onlineRiders ?></div></div>
      <div class="card"><div class="card-title">Offline Riders</div><div class="card-value" style="color:#94a3b8;"><?= $offlineRiders ?></div></div>
      <div class="card"><div class="card-title">Total Parcels</div><div class="card-value" style="color:#38bdf8;"><?= $totParcels ?></div></div>
      <div class="card"><div class="card-title">Delivered</div><div class="card-value" style="color:#22c55e;"><?= $delParcels ?></div></div>
      <div class="card"><div class="card-title">Pending</div><div class="card-value" style="color:#f59e0b;"><?= $penParcels ?></div></div>
    </section>

    <!-- TAB 1: OVERVIEW & MAP -->
    <?php if ($page === 'dashboard'): ?>
      <section class="section-card">
        <h3>📍 Real-Time Rider Location Tracking Map</h3>
        <div id="map" style="height: 350px; width: 100%; border-radius: 8px;"></div>
      </section>

      <!-- Assign Parcel Box -->
      <section class="section-card">
        <h3>⚡ Assign Parcel to Rider</h3>
        <form method="POST" action="dashboard.php?page=dashboard" style="display:grid; grid-template-columns: 1fr 1fr auto; gap: 15px; margin-top: 10px;">
          <input type="hidden" name="assign_parcel" value="1">
          
          <!-- Parcel Dropdown -->
          <div>
            <label style="font-size:0.85rem; color:#94a3b8; display:block; margin-bottom:5px;">Select Parcel</label>
            <select name="parcel_id" required>
              <option value="">-- Choose Parcel --</option>
              <?php 
                // Flexible Query: Shows any parcel that is not delivered yet
                $unassigned = $pdo->query("SELECT id, tracking_number, recipient_name, status FROM parcels WHERE LOWER(status) != 'delivered' ORDER BY id DESC")->fetchAll();
                
                if (empty($unassigned)): 
              ?>
                <option value="" disabled>No available parcels</option>
              <?php else: ?>
                <?php foreach ($unassigned as $u): ?>
                  <option value="<?= $u['id'] ?>">#<?= htmlspecialchars($u['tracking_number']) ?> - To: <?= htmlspecialchars($u['recipient_name']) ?> (<?= ucfirst($u['status']) ?>)</option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <!-- Rider Dropdown -->
          <div>
            <label style="font-size:0.85rem; color:#94a3b8; display:block; margin-bottom:5px;">Select Rider</label>
            <select name="rider_id" required>
              <option value="">-- Choose Rider --</option>
              <?php 
                $availableRiders = $pdo->query("SELECT r.id, u.full_name FROM riders r JOIN users u ON r.user_id = u.id")->fetchAll();
                foreach ($availableRiders as $ar):
              ?>
                <option value="<?= $ar['id'] ?>"><?= htmlspecialchars($ar['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Submit Button -->
          <div style="align-self: flex-end;">
            <button type="submit">Assign Parcel</button>
          </div>
        </form>
      </section>

    <!-- TAB 2: PARCELS LIST & SEARCH -->
    <?php elseif ($page === 'parcels'): ?>
      <section class="section-card">
        <h3>
          <span>📦 Registered Parcels Directory</span>
          <button class="btn-success" onclick="openAddModal()">➕ Add Parcel</button>
        </h3>
        
        <form method="GET" action="dashboard.php" class="toolbar">
          <input type="hidden" name="page" value="parcels">
          <input type="search" name="search_parcel" placeholder="🔍 Search parcels by tracking #, sender or recipient..." value="<?= htmlspecialchars($search_parcel) ?>">
          <button type="submit">Search</button>
        </form>

        <table>
          <thead>
            <tr>
              <th>Tracking #</th>
              <th>Sender</th>
              <th>Recipient</th>
              <th>Address</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php 
              $sql = "SELECT * FROM parcels WHERE 1";
              if ($search_parcel) {
                $sql .= " AND (tracking_number LIKE '%$search_parcel%' OR sender_name LIKE '%$search_parcel%' OR recipient_name LIKE '%$search_parcel%')";
              }
              $sql .= " ORDER BY id DESC";
              $parcelsList = $pdo->query($sql)->fetchAll();
              
              foreach ($parcelsList as $p): 
            ?>
              <tr>
                <td><code><?= htmlspecialchars($p['tracking_number']) ?></code></td>
                <td><?= htmlspecialchars($p['sender_name']) ?></td>
                <td><?= htmlspecialchars($p['recipient_name']) ?><br><small style="color:#94a3b8;"><?= htmlspecialchars($p['recipient_phone']) ?></small></td>
                <td><?= htmlspecialchars($p['recipient_address'] ?? $p['address'] ?? 'N/A') ?></td>
                <td><span class="status-badge status-<?= $p['status'] ?>"><?= str_replace('_', ' ', $p['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($parcelsList)): ?>
              <tr><td colspan="5" style="text-align:center; color:#94a3b8;">No matching parcels found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    <!-- TAB 3: RIDERS DIRECTORY -->
    <?php elseif ($page === 'riders'): ?>
      <section class="section-card">
        <h3>🛵 Registered Delivery Riders</h3>
        <form method="GET" action="dashboard.php" class="toolbar">
          <input type="hidden" name="page" value="riders">
          <input type="search" name="search_rider" placeholder="🔍 Search riders by name, email or vehicle plate..." value="<?= htmlspecialchars($search_rider) ?>">
          <button type="submit">Search</button>
        </form>

        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email & Phone</th>
              <th>Vehicle Details</th>
            </tr>
          </thead>
          <tbody>
            <?php 
              $rSql = "SELECT u.full_name, u.email, u.phone, r.vehicle_type, r.vehicle_plate FROM riders r JOIN users u ON r.user_id = u.id WHERE 1";
              if ($search_rider) {
                $rSql .= " AND (u.full_name LIKE '%$search_rider%' OR u.email LIKE '%$search_rider%' OR r.vehicle_plate LIKE '%$search_rider%')";
              }
              $ridersList = $pdo->query($rSql)->fetchAll();

              foreach ($ridersList as $r): 
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($r['email']) ?><br><small style="color:#94a3b8;"><?= htmlspecialchars($r['phone']) ?></small></td>
                <td><?= htmlspecialchars($r['vehicle_type']) ?> (<code><?= htmlspecialchars($r['vehicle_plate']) ?></code>)</td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($ridersList)): ?>
              <tr><td colspan="3" style="text-align:center; color:#94a3b8;">No matching riders found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    <!-- TAB 4: AUDIT LOGS -->
    <?php elseif ($page === 'logs'): ?>
      <section class="section-card">
        <h3>📜 System Activity Logs</h3>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Action Details</th>
              <th>Date / Time</th>
            </tr>
          </thead>
          <tbody>
            <?php $logs = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 50")->fetchAll(); ?>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td>#<?= $l['id'] ?></td>
                <td><?= htmlspecialchars($l['action']) ?></td>
                <td><?= $l['created_at'] ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
              <tr><td colspan="3" style="text-align:center; color:#94a3b8;">No system activity logs available.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    <!-- TAB 5: REPORTS -->
    <?php elseif ($page === 'reports'): ?>
      <section class="section-card">
        <h3>📈 System Reports Generator</h3>
        <p style="color:#94a3b8; margin-bottom: 20px;">Download delivery summaries and audit reports.</p>
        <button onclick="alert('Exporting Parcel Delivery Report to CSV...')">📥 Export Delivery Report (CSV)</button>
      </section>
    <?php endif; ?>

  </main>

  <!-- ADD PARCEL MODAL FORM WITH ADDRESS AUTOCOMPLETE -->
  <div id="addParcelModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>📦 Register New Parcel</h3>
        <button class="close-btn" onclick="closeAddModal()">&times;</button>
      </div>
      <form method="POST" action="dashboard.php?page=parcels">
        <input type="hidden" name="add_parcel" value="1">
        
        <div class="form-group">
          <label>Sender Name</label>
          <input type="text" name="sender_name" placeholder="e.g. Acme Corp / Wayne Enterprises" required>
        </div>

        <div class="form-group">
          <label>Recipient Name</label>
          <input type="text" name="recipient_name" placeholder="e.g. John Doe" required>
        </div>

        <div class="form-group">
          <label>Recipient Phone Number</label>
          <input type="tel" name="recipient_phone" placeholder="e.g. +1 555-0199" required>
        </div>

        <!-- AUTO-COMPLETE ADDRESS FIELD -->
        <div class="form-group">
          <label>📍 Delivery Address (Type to search location)</label>
          <input type="text" id="addressInput" name="delivery_address" placeholder="Start typing address or street name..." autocomplete="off" required>
          <div id="suggestions" class="suggestions-box"></div>
        </div>

        <div style="display:flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
          <button type="button" onclick="closeAddModal()" style="background:#64748b;">Cancel</button>
          <button type="submit" class="btn-success">Save & Register Parcel</button>
        </div>
      </form>
    </div>
  </div>
  <!-- Loop over assigned parcels -->
<?php 
  // Fetch parcels assigned to riders from database
  $assignedParcels = $pdo->query("SELECT * FROM parcels WHERE rider_id IS NOT NULL AND LOWER(status) != 'delivered' ORDER BY id DESC")->fetchAll();

  foreach ($assignedParcels as $parcel): 
?>
  <div class="parcel-card" style="background: #1e293b; border: 1px solid #334155; padding: 15px; border-radius: 8px; margin-bottom: 15px; color: #f8fafc;">
  
  <h4 style="margin: 0 0 8px 0; color: #38bdf8;">
    📦 Tracking: #<?= htmlspecialchars($parcel['tracking_number'] ?? '') ?>
  </h4>
  
  <?php 
    // Safely detect whichever address column exists in your database
    $address = $parcel['recipient_address'] ?? $parcel['delivery_address'] ?? $parcel['address'] ?? '';
  ?>

  <p style="margin: 4px 0;"><strong>Recipient:</strong> <?= htmlspecialchars($parcel['recipient_name'] ?? 'N/A') ?></p>
  <p style="margin: 4px 0;"><strong>Address:</strong> <?= htmlspecialchars($address ?: 'No address provided') ?></p>
  
  <?php if (!empty($address)): ?>
    <!-- 🚀 GOOGLE MAPS NAVIGATION BUTTON -->
    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($address) ?>&travelmode=driving" 
       target="_blank" 
       rel="noopener noreferrer"
       style="display: block; margin-top: 12px; padding: 12px; background: #22c55e; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; text-align: center;">
      🗺️ Open Google Maps Navigation
    </a>
  <?php endif; ?>

</div>
<?php endforeach; ?>

  <!-- Leaflet Map Library -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // Modal controls
    function openAddModal() {
      document.getElementById('addParcelModal').style.display = 'flex';
    }
    function closeAddModal() {
      document.getElementById('addParcelModal').style.display = 'none';
    }

    // Interactive Leaflet Map Setup
    <?php if ($page === 'dashboard'): ?>
      document.addEventListener("DOMContentLoaded", function() {
        var map = L.map('map').setView([3.1390, 101.6869], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

        L.marker([3.1420, 101.6910]).addTo(map).bindPopup("<b>🛵 Rider: Dave Miller</b><br>Status: Online (In Transit)");
        L.marker([3.1220, 101.6710]).addTo(map).bindPopup("<b>🛵 Rider: Alex Wong</b><br>Status: Online (Available)");
      });
    <?php endif; ?>

    // FREE LIVE ADDRESS AUTOCOMPLETE (Using Nominatim Search Engine)
    let timer;
    const addressInput = document.getElementById('addressInput');
    const suggestionsBox = document.getElementById('suggestions');

    if (addressInput) {
      addressInput.addEventListener('input', function() {
        clearTimeout(timer);
        const query = this.value.trim();

        if (query.length < 3) {
          suggestionsBox.style.display = 'none';
          return;
        }

        // Delay 300ms to prevent excessive API calls while typing
        timer = setTimeout(() => {
          fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
            .then(res => res.json())
            .then(data => {
              suggestionsBox.innerHTML = '';
              if (data.length > 0) {
                data.forEach(item => {
                  const div = document.createElement('div');
                  div.className = 'suggestion-item';
                  div.textContent = item.display_name;
                  div.addEventListener('click', function() {
                    addressInput.value = item.display_name;
                    suggestionsBox.style.display = 'none';
                  });
                  suggestionsBox.appendChild(div);
                });
                suggestionsBox.style.display = 'block';
              } else {
                suggestionsBox.style.display = 'none';
              }
            })
            .catch(() => {
              suggestionsBox.style.display = 'none';
            });
        }, 300);
      });

      // Close suggestion box if user clicks outside
      document.addEventListener('click', function(e) {
        if (e.target !== addressInput && e.target !== suggestionsBox) {
          suggestionsBox.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>