/* ======================================
   DEPARTMENTS JS - EUFILE PROFESSIONAL
   ====================================== */

let staffList = [];
let currentDept = "";
let currentPage = 1;
const itemsPerPage = 5;
let rfidBuffer = "";
let isListeningRFID = false;
let port;

const ICONS = {
  files: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#03acfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>`,
  storage: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#03acfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>`,
};

// XSS-safe interpolation helper. Use whenever rendering user-supplied
// fields into innerHTML.
function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => (
    {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]
  ));
}

document.addEventListener("DOMContentLoaded", () => {
  // URL params must be read BEFORE loadStaffData() so renderCards() filters
  // by the right department on first paint.
  checkUrlParams();
  initSidebar();
  setupPasscodeLogic();
  loadStaffData();
  
  document.getElementById("staffSearch")?.addEventListener("input", () => {
    currentPage = 1;
    renderCards();
  });
});

/* ======================================
   CORE UTILITIES
   ====================================== */

function showToast(message, type = "success") {
    const container = document.getElementById("toastContainer");
    if (!container) return;
    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span> <div>${message}</div>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = "0";
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

function formatBytes(bytes, decimals = 2) {
  if (!+bytes) return "0 Bytes";
  const k = 1024, dm = decimals < 0 ? 0 : decimals,
    sizes = ["Bytes", "KiB", "MiB", "GiB", "TiB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
}

/* ======================================
   DATA RENDERING & PAGINATION
   ====================================== */

function renderCards() {
  const container = document.getElementById("staffContainer");
  if (!container) return;
  container.innerHTML = "";
  const searchTerm = document.getElementById("staffSearch").value.toLowerCase();

  const filtered = staffList.filter(
    (s) =>
      s.department_name === currentDept &&
      `${s.first_name} ${s.last_name}`.toLowerCase().includes(searchTerm)
  );

  const totalPages = Math.ceil(filtered.length / itemsPerPage);
  const paginatedItems = filtered.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  // dept.php now returns the SAME file_count / storage_bytes on every row
  // of a given department (it's a per-dept total joined back), so we read it
  // off the first matching row instead of summing across users.
  const deptRow      = filtered[0] || staffList.find(s => s.department_name === currentDept);
  const deptFiles    = deptRow ? (parseInt(deptRow.file_count)    || 0) : 0;
  const deptStorageB = deptRow ? (parseInt(deptRow.storage_bytes) || 0) : 0;

  document.getElementById("countTotal").innerText = filtered.length;
  document.getElementById("deptFiles").innerText  = deptFiles;
  document.getElementById("deptStorage").innerText = formatBytes(deptStorageB);
  document.getElementById("pageInfo").innerText   = `Page ${currentPage} of ${totalPages || 1}`;

  document.getElementById("prevBtn").disabled = currentPage === 1;
  document.getElementById("nextBtn").disabled = currentPage === totalPages || totalPages === 0;

  paginatedItems.forEach((s) => {
    const fullName = `${s.first_name ?? ''} ${s.last_name ?? ''}`.trim();
    const safeName = escapeHtml(fullName || 'Unnamed');
    const safeRole = escapeHtml(s.role || 'Staff');
    const roleClass = (s.role || 'staff').toLowerCase();
    const img = s.profile_picture
      ? escapeHtml(s.profile_picture)
      : `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName || 'User')}`;
    container.innerHTML += `
            <div class="staff-card">
                <div class="staff-avatar"><img src="${img}" alt="profile" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=' + encodeURIComponent('${escapeHtml(fullName || 'User')}')"></div>
                <div style="flex:2">
                    <strong style="font-size: 1.1rem; color: var(--text-main);">${safeName}</strong>
                    <div><span class="badge ${escapeHtml(roleClass)}">${safeRole}</span></div>
                </div>
                <div style="flex:1; text-align:center; border-left: 1px solid var(--border);">
                    <div style="display:flex; align-items:center; justify-content:center; gap:8px;">${ICONS.files} <strong>${deptFiles}</strong></div>
                    <small style="font-size:0.65rem; color:var(--text-dim); text-transform:uppercase;">Dept Files</small>
                </div>
                <div style="flex:1; text-align:center; border-left: 1px solid var(--border);">
                    <div style="display:flex; align-items:center; justify-content:center; gap:8px;">${ICONS.storage} <strong>${formatBytes(deptStorageB)}</strong></div>
                    <small style="font-size:0.65rem; color:var(--text-dim); text-transform:uppercase;">Dept Storage</small>
                </div>
                <div style="flex:1; text-align:right; display:flex; gap:8px; justify-content: flex-end;">
                    <button class="btn-primary" style="background:transparent; color:var(--primary); border:1px solid var(--primary); padding: 5px 12px;" onclick="openEdit(${s.id})">Manage</button>
                    <button class="btn-primary" style="background:transparent; color:var(--danger); border:1px solid var(--danger); padding: 5px 12px;" onclick="deletePersonnel(${s.id})">Delete</button>
                </div>
            </div>`;
  });
}

function changePage(direction) {
  currentPage += direction;
  renderCards();
}

/* ======================================
   MODAL & PROFILE MANAGEMENT
   ====================================== */
   function openAddModal() {
    const modal = document.getElementById("editModal");
    const title = modal.querySelector("h3");
    
    // Hanapin ang MFA section (yung div na may border-top)
    const mfaSection = modal.querySelector("div[style*='border-top']");

    // 1. Linisin ang ID at Form
    document.getElementById("editId").value = "";
    document.getElementById("editForm").reset();
    
    // 2. I-reset ang UI Labels
    title.innerText = "Add New Personnel";
    document.getElementById("preview").innerHTML = `<img src="https://ui-avatars.com/api/?name=New+Staff&background=03acfa&color=fff" style="width:100%;height:100%;object-fit:cover;">`;
    
    // 3. Itago ang MFA section (dahil wala pang ID ang bagong staff)
    if (mfaSection) mfaSection.style.display = "none";

    // 4. Auto-select ang kasalukuyang department
    if (currentDept) {
        const deptSelect = document.getElementById("deptEdit");
        Array.from(deptSelect.options).forEach(opt => {
            if (opt.text === currentDept) deptSelect.value = opt.value;
        });
    }

    modal.style.display = "flex";
}

function openEdit(id) {
    const staff = staffList.find((s) => s.id == id);
    if (!staff) return;

    document.getElementById("editId").value = staff.id;
    document.getElementById("fName").value = staff.first_name;
    document.getElementById("lName").value = staff.last_name;
    document.getElementById("email").value = staff.email;
    document.getElementById("deptEdit").value = staff.dept_id;

    // Sync button labels and styles based on flags
    updateMFAInterface(staff);

    const img = staff.profile_picture || `https://ui-avatars.com/api/?name=${staff.first_name}+${staff.last_name}`;
    document.getElementById("preview").innerHTML = `<img src="${img}" style="width:100%;height:100%;object-fit:cover;">`;
    document.getElementById("editModal").style.display = "flex";
}

function updateMFAInterface(staff) {
    const passBtn = document.getElementById("passBtn");
    const passStatus = document.getElementById("passStatus");
    const rfidBtn = document.getElementById("rfidBtn");
    const rfidStatus = document.getElementById("rfidStatus");
    const fingerBtn = document.getElementById("fingerBtn");
    const fingerStatus = document.getElementById("fingerStatus");

    // Passcode Logic
    if (Number(staff.has_passcode) === 1) {
        passBtn.innerText = "Edit Passcode";
        passBtn.style.background = "#e2e8f0";
        passBtn.style.color = "var(--text-main)";
        passStatus.innerText = "Status: Active";
        passStatus.style.color = "#10b981";
    } else {
        passBtn.innerText = "Setup";
        passBtn.style.background = "var(--primary)";
        passBtn.style.color = "white";
        passStatus.innerText = "Status: Not Set";
        passStatus.style.color = "var(--text-dim)";
    }

    // RFID Logic
    if (Number(staff.has_rfid) === 1) {
        rfidBtn.innerText = "Edit Card";
        rfidBtn.style.background = "#e2e8f0";
        rfidStatus.innerText = "LINKED";
        rfidStatus.className = "badge admin";
    } else {
        rfidBtn.innerText = "Link Card";
        rfidBtn.style.background = "var(--primary)";
        rfidStatus.innerText = "NOT LINKED";
        rfidStatus.className = "badge staff";
    }

    // Fingerprint Logic
    if (Number(staff.has_fingerprint) === 1) {
        fingerBtn.innerText = "Edit Finger";
        fingerBtn.style.background = "#e2e8f0";
        fingerStatus.innerText = "ENROLLED";
        fingerStatus.className = "badge admin";
    } else {
        fingerBtn.innerText = "Scan Finger";
        fingerBtn.style.background = "var(--primary)";
        fingerStatus.innerText = "NOT ENROLLED";
        fingerStatus.className = "badge staff";
    }
}

async function saveData() {
  const id = document.getElementById("editId").value;
  const fName = document.getElementById("fName").value.trim(),
        lName = document.getElementById("lName").value.trim(),
        email = document.getElementById("email").value.trim(),
        deptId = document.getElementById("deptEdit").value;

  if (!fName || !lName || !email || !deptId) return showToast("Punan ang lahat ng fields.", "error");

  const formData = new FormData();
  formData.append("first_name", fName);
  formData.append("last_name", lName);
  formData.append("email", email);
  formData.append("dept_id", deptId);
  if (document.getElementById("fileInput").files[0]) formData.append("profile_picture", document.getElementById("fileInput").files[0]);

  const endpoint = id ? "../Departments/update_staff.php" : "../Departments/add_staff.php";
  if (id) formData.append("id", id);

  try {
    const res = await fetch(endpoint, { method: "POST", body: formData }),
          result = await res.json();
    if (result.success) {
      showToast(id ? "Profile updated." : "Personnel added. Pass: EuFile2026");
      closeModal();
      loadStaffData();
    } else {
      showToast(result.message || "Operation failed.", "error");
    }
  } catch (err) { showToast("Server error.", "error"); }
}

async function deletePersonnel(id) {
    if (!confirm("Delete this personnel record?")) return;

    try {
        const formData = new FormData();
        formData.append("id", id);

        const res = await fetch("../Departments/delete_staff.php", {
            method: "POST",
            body: formData
        });
        const result = await res.json();

        if (result.success) {
            showToast("Personnel deleted.");
            loadStaffData(); 
        } else {
            showToast(result.message || "Deletion failed.", "error");
        }
    } catch (err) {
        showToast("Server connection error.", "error");
    }
}

/* ======================================
   SECURITY & MFA LOGIC
   ====================================== */

async function submitPasscode() {
    const inputs = document.querySelectorAll('.p-digit');
    let passcode = "";
    inputs.forEach(input => passcode += input.value);
    
    if (passcode.length < 4) return showToast("Minimum 4 digits required.", "error");

    const userId = document.getElementById("editId").value;
    const btn = document.getElementById("confirmPassBtn");
    btn.disabled = true;

    try {
        const res = await fetch("../Departments/update_passcode.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `id=${userId}&passcode=${passcode}`
        });
        const result = await res.json();
        if (result.success) {
            showToast("Security Passcode updated.");
            const staff = staffList.find(s => s.id == userId);
            if (staff) { staff.has_passcode = 1; updateMFAInterface(staff); }
            closePasscodeModal();
            renderCards();
        }
    } catch (err) { showToast("Server error.", "error"); }
    finally { btn.disabled = false; }
}

function registerRFID() {
    const userId = document.getElementById("editId").value;
    if (!userId) return showToast("Select Staff First.", "error");
    isListeningRFID = true;
    rfidBuffer = "";
    showToast("Tap RFID card on reader.");
}

document.addEventListener("keydown", (e) => {
    if (!isListeningRFID) return;
    if (e.key === "Enter") {
        if (rfidBuffer.length > 0) { saveMFAData('rfid', rfidBuffer); isListeningRFID = false; }
    } else if (e.key.length === 1) { rfidBuffer += e.key; }
});

function registerFingerprint() {
    const userId = document.getElementById("editId").value;
    if (!userId) return showToast("Select Staff First.", "error");
    document.getElementById("fingerModal").style.display = "flex";
}

async function connectAndScan() {
    try {
        port = await navigator.serial.requestPort();
        await port.open({ baudRate: 57600 });
        document.getElementById("fingerProgress").innerText = "SCANNING...";
        // Simulate extraction (AS608 communication sequence)
        setTimeout(() => saveMFAData('fingerprint', 'HEX_AS608_TEMPLATE'), 2000);
    } catch (err) { showToast("Connection failed.", "error"); }
}

async function saveMFAData(type, value) {
    const userId = document.getElementById("editId").value;
    try {
        const res = await fetch("../Departments/update_mfa.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `id=${userId}&type=${type}&value=${value}`
        });
        const result = await res.json();
        if (result.success) {
            showToast(`${type.toUpperCase()} enrolled.`);
            const staff = staffList.find(s => s.id == userId);
            if (staff) {
                if (type === 'rfid') staff.has_rfid = 1;
                if (type === 'fingerprint') staff.has_fingerprint = 1;
                updateMFAInterface(staff);
            }
            if (type === 'fingerprint') closeFingerModal();
            renderCards();
        }
    } catch (err) { showToast("Save failed.", "error"); }
}

/* ======================================
   INITIALIZATION & SIDEBAR
   ====================================== */

async function loadStaffData() {
  try {
    const res = await fetch("../Departments/dept.php");
    staffList = await res.json();
    renderCards();
  } catch (err) { console.error(err); }
}

async function initSidebar() {
  const dropdown = document.getElementById("deptDropdown"),
        deptSelect = document.getElementById("deptEdit");
  try {
    const res = await fetch("../Backend/get_Departments.php"), 
          depts = await res.json();
    dropdown.innerHTML = "";
    deptSelect.innerHTML = '<option value="" disabled selected>Select Department</option>';
    depts.forEach((dept) => {
      const a = document.createElement("a");
      a.className = "sub-item" + (currentDept === dept.name ? " active-link" : "");
      a.innerText = dept.name;
      a.href = `../Departments/Departments.html?dept=${encodeURIComponent(dept.name)}`;
      dropdown.appendChild(a);
      const opt = document.createElement("option");
      opt.value = dept.id; opt.innerText = dept.name;
      deptSelect.appendChild(opt);
    });
  } catch (err) { console.error(err); }
}

function setupPasscodeLogic() {
  const inputs = document.querySelectorAll('.p-digit');
  inputs.forEach((input, index) => {
    input.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/[^0-9]/g, '');
      if (e.target.value.length === 1 && index < inputs.length - 1) inputs[index + 1].focus();
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && e.target.value === '' && index > 0) inputs[index - 1].focus();
    });
  });
}

// UI Helpers
function resetPasscode() { document.getElementById('passcodeModal').style.display = 'flex'; }
function closeFingerModal() { document.getElementById('fingerModal').style.display = 'none'; if(port) port.close(); }
function closePasscodeModal() { document.getElementById('passcodeModal').style.display = 'none'; }
function closeModal() { document.getElementById('editModal').style.display = 'none'; }
function toggleDepartments() {
  const isShowing = document.getElementById("deptDropdown").classList.toggle("show");
  document.getElementById("dropArrow").style.transform = isShowing ? "rotate(0deg)" : "rotate(-90deg)";
}
function checkUrlParams() {
  const dept = new URLSearchParams(window.location.search).get("dept");
  if (dept) { currentDept = dept; document.getElementById("deptTitle").innerText = dept; }
}