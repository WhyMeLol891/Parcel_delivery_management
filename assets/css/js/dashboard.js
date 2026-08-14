let allParcels = [];
let map = null;
let riderMarkers = {};

document.addEventListener('DOMContentLoaded', () => {
  initMobileSidebar();
  initMap();
  fetchParcelsData();
});

// 1. Mobile Sidebar Toggle
function initMobileSidebar() {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  }
}

// 2. Interactive Leaflet.js Map Initialization
function initMap() {
  // Default coordinates (Centered on city level)
  map = L.map('deliveryMap').setView([3.1390, 101.6869], 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  // Example: Plot active rider marker
  updateRiderLocationOnMap(1, 3.1420, 101.6910, 'Dave Rider (Motorcycle)');
}

function updateRiderLocationOnMap(riderId, lat, lng, title) {
  if (riderMarkers[riderId]) {
    riderMarkers[riderId].setLatLng([lat, lng]);
  } else {
    riderMarkers[riderId] = L.marker([lat, lng])
      .addTo(map)
      .bindPopup(`<b>${title}</b><br>Live GPS Active`);
  }
}

// 3. Fetch Parcels via Native Fetch API with Security Headers
async function fetchParcelsData() {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  try {
    const res = await fetch('api/parcels.php', {
      method: 'GET',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      }
    });

    const result = await res.json();
    if (result.success) {
      allParcels = result.data;
      updateMetricsCards(allParcels);
      renderParcelTable(allParcels);
    }
  } catch (err) {
    console.error('Failed to load parcel data:', err);
  }
}

// 4. Client-side Search and Status Filter
function filterParcels() {
  const query = document.getElementById('searchInput').value.toLowerCase();
  const selectedStatus = document.getElementById('statusFilter').value;

  const filtered = allParcels.filter(p => {
    const matchesSearch = p.tracking_number.toLowerCase().includes(query) || 
                          p.recipient_name.toLowerCase().includes(query);
    const matchesStatus = selectedStatus === 'ALL' || p.status === selectedStatus;

    return matchesSearch && matchesStatus;
  });

  renderParcelTable(filtered);
}

// 5. Render Data Table
function renderParcelTable(data) {
  const tbody = document.getElementById('parcelTableBody');
  tbody.innerHTML = '';

  if (data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No parcel records found.</td></tr>';
    return;
  }

  data.forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><strong>${escapeHtml(p.tracking_number)}</strong></td>
      <td>${escapeHtml(p.recipient_name)}</td>
      <td>${escapeHtml(p.recipient_phone)}</td>
      <td><span class="badge badge-${p.status}">${p.status.replace('_', ' ')}</span></td>
      <td>${p.assigned_rider_name ? escapeHtml(p.assigned_rider_name) : 'Unassigned'}</td>
      <td>
        <button class="btn" style="padding: 4px 8px; font-size: 0.8rem;" onclick="viewDetails(${p.id})">Inspect</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

// 6. Update Metric Counters
function updateMetricsCards(parcels) {
  document.getElementById('cardTotalParcels').innerText = parcels.length;
  document.getElementById('cardInTransit').innerText    = parcels.filter(p => p.status === 'in_transit').length;
  document.getElementById('cardDelivered').innerText    = parcels.filter(p => p.status === 'delivered').length;
  document.getElementById('cardPending').innerText      = parcels.filter(p => p.status === 'pending').length;
}

// Utility: Escape HTML to prevent XSS
function escapeHtml(str) {
  return str ? str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;") : '';
}

// Logout Action
async function logout() {
  await fetch('api/auth.php?action=logout');
  window.location.href = 'index.php';
}