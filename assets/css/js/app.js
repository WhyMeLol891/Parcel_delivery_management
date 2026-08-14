// assets/js/app.js
let compressedBlob = null;

document.addEventListener('DOMContentLoaded', () => {
  loadParcels();

  // 1. Create Parcel AJAX Form
  document.getElementById('createParcelForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
      recipient_name: document.getElementById('recipient').value,
      phone: document.getElementById('phone').value,
      address: document.getElementById('address').value
    };

    const res = await fetch('api/parcels.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });

    const result = await res.json();
    if (result.success) {
      alert(`✅ Created! Tracking Number: ${result.tracking_number}`);
      e.target.reset();
      loadParcels();
    }
  });
});

// 2. Fetch and Render Parcel Table via AJAX
async function loadParcels() {
  const res = await fetch('api/parcels.php');
  const result = await res.json();
  const tbody = document.getElementById('parcelTableBody');
  tbody.innerHTML = '';

  result.data.forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><strong>${p.tracking_number}</strong></td>
      <td>${p.recipient_name}<br><small>${p.phone}</small></td>
      <td><span class="badge badge-${p.status.toLowerCase()}">${p.status}</span></td>
      <td>
        ${p.proof_photo_path ? `<a href="${p.proof_photo_path}" target="_blank">View POD</a>` : 'None'}
      </td>
      <td>
        <button class="btn btn-success" onclick="openCameraModal(${p.id})">📷 Upload POD</button>
        <button class="btn btn-danger" onclick="deleteParcel(${p.id})">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

// 3. Mobile Camera File Input & Client-Side Canvas Compression
function handleCameraCapture(input) {
  const file = input.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    const img = new Image();
    img.src = e.target.result;
    img.onload = () => {
      const canvas = document.createElement('canvas');
      const maxWidth = 1200;
      let width = img.width;
      let height = img.height;

      if (width > maxWidth) {
        height = Math.round((height * maxWidth) / width);
        width = maxWidth;
      }

      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, width, height);

      // Compress to 70% JPEG
      canvas.toBlob((blob) => {
        compressedBlob = blob;
        const preview = document.getElementById('previewImg');
        preview.src = URL.createObjectURL(blob);
        preview.style.display = 'block';
      }, 'image/jpeg', 0.7);
    };
  };
  reader.readAsDataURL(file);
}

// 4. Submit Proof Photo via AJAX FormData
async function uploadPOD(parcelId) {
  if (!compressedBlob) return alert('Please capture a photo first.');

  const formData = new FormData();
  formData.append('parcel_id', parcelId);
  formData.append('proofImage', compressedBlob, `pod-${parcelId}.jpg`);
  formData.append('remarks', document.getElementById('podRemarks').value);

  const res = await fetch('api/upload_proof.php', {
    method: 'POST',
    body: formData
  });

  const result = await res.json();
  if (result.success) {
    alert('✅ Photo proof uploaded successfully!');
    location.reload();
  } else {
    alert(`Error: ${result.message}`);
  }
}

// 5. Delete Parcel AJAX
async function deleteParcel(id) {
  if (!confirm('Are you sure you want to delete this parcel?')) return;
  await fetch('api/parcels.php', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  loadParcels();
}