/* ======================================
   REQUEST JS - UPDATED MODAL & LOGIC
   ====================================== */

let requestItems = [];
let filteredItems = [];
let currentPage = 1;
const itemsPerPage = 8;
let selectedItems = new Set();

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    renderRequests();
});

// Populates only the department dropdown content
async function initSidebar() {
    const dropdown = document.getElementById("deptDropdown");
    try {
        const res = await fetch("../Backend/get_Departments.php");
        const departments = await res.json();
        dropdown.innerHTML = "";
        departments.forEach((dept) => {
            const a = document.createElement("a");
            a.className = "sub-item"; a.innerText = dept.name;
            a.href = `../Departments/Departments.html?dept=${encodeURIComponent(dept.name)}`;
            dropdown.appendChild(a);
        });
    } catch (err) { console.error(err); }
}

async function renderRequests() {
    try {
        const res = await fetch('../Request/get_requests.php');
        requestItems = await res.json();
        filteredItems = [...requestItems];
        const badge = document.getElementById('reqBadge');
        if (badge) {
            badge.innerText = requestItems.length;
            badge.style.display = requestItems.length > 0 ? 'block' : 'none';
        }
        renderTable();
    } catch (err) { console.error(err); }
}

function renderTable() {
    const tableBody = document.getElementById('requestsTableBody');
    const paginated = filteredItems.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

    const bulkBar = document.getElementById("bulkActionsBar");
    if (selectedItems.size > 0) {
        bulkBar.classList.add("active");
        document.getElementById("selectedCount").innerText = selectedItems.size;
    } else { bulkBar.classList.remove("active"); }

    if (paginated.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-dim); padding:40px;">No pending requests found.</td></tr>`;
        return;
    }

    tableBody.innerHTML = paginated.map(req => {
        const isSelected = selectedItems.has(req.id);
        return `
            <tr class="${isSelected ? 'selected' : ''}" onclick="toggleSelect(${req.id}, event)">
                <td><input type="checkbox" class="list-checkbox" ${isSelected ? 'checked' : ''}></td>
                <td>
                    <div class="user-info" style="display:flex; align-items:center; gap:12px;">
                        <div class="user-avatar" style="width:35px; height:35px; border-radius:50%; background:#e0f2fe; color:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:700;">${req.first_name.charAt(0)}</div>
                        <div>
                            <strong>${req.first_name} ${req.last_name}</strong><br>
                            <small style="color:var(--text-dim)">${req.role}</small>
                        </div>
                    </div>
                </td>
                <td><strong>${req.folder}</strong></td>
                <td style="font-style: italic; color:var(--text-dim)">"${req.reason}"</td>
                <td>${req.request_date}</td>
                <td><span style="background:#fff7ed; color:#c2410c; padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:600;">Pending</span></td>
                <td>
                    <div class="action-btns" style="display:flex; gap:8px;">
                        <button class="btn btn-approve" style="background:var(--success); color:white; border:none; padding:8px 15px; border-radius:8px; cursor:pointer;" onclick="event.stopPropagation(); processRequest(${req.id}, 'approved')">Approve</button>
                        <button class="btn btn-deny" style="background:#fee2e2; color:var(--danger); border:none; padding:8px 15px; border-radius:8px; cursor:pointer;" onclick="event.stopPropagation(); processRequest(${req.id}, 'denied')">Deny</button>
                    </div>
                </td>
            </tr>`;
    }).join('');
    renderPagination();
}

/**
 * FIXED MODAL TITLE LOGIC
 */
async function processRequest(id, status) {
    const fd = new FormData();
    fd.append('id', id); fd.append('status', status);
    try {
        const res = await fetch('../Request/update_request.php', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.status === 'success') {
            const titleEl = document.getElementById('modalTitle');
            const bodyEl = document.getElementById('modalBody');
            
            // Dynamic Header Fix
            if (status === 'approved') {
                titleEl.innerText = 'Access Granted';
                titleEl.style.color = 'var(--success)';
            } else {
                titleEl.innerText = 'Access Denied';
                titleEl.style.color = 'var(--danger)';
            }
            
            bodyEl.innerText = `The request has been successfully ${status} and logged.`;
            document.getElementById('modalOverlay').style.display = 'flex';
            renderRequests(); 
        }
    } catch (err) { console.error(err); }
}

/**
 * PROFESSIONAL BOTTOM PAGINATION
 */
function renderPagination() {
    const container = document.getElementById("paginationContainer");
    if (!container) return;
    const total = filteredItems.length;
    const totalPages = Math.ceil(total / itemsPerPage);
    const startIdx = total === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const endIdx = Math.min(currentPage * itemsPerPage, total);

    if (total === 0) { container.innerHTML = ""; return; }

    container.innerHTML = `
        <div class="pagination-info" style="font-size:0.85rem; color:var(--text-dim); font-weight:600;">Showing <b>${startIdx}-${endIdx}</b> of <b>${total}</b> results</div>
        <div class="pagination-btns" style="display:flex; gap:6px;">
            <button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">Prev</button>
            ${Array.from({length: totalPages}, (_, i) => i + 1).map(num => `
                <button class="page-btn ${num === currentPage ? 'active' : ''}" onclick="changePage(${num})">${num}</button>
            `).join("")}
            <button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">Next</button>
        </div>`;
}

async function bulkAction(status) {
    if (selectedItems.size === 0) return;
    if (!confirm(`${status === 'approved' ? 'Approve' : 'Deny'} ${selectedItems.size} requests?`)) return;
    for (let id of selectedItems) {
        const fd = new FormData();
        fd.append('id', id); fd.append('status', status);
        await fetch('../Request/update_request.php', { method: 'POST', body: fd });
    }
    selectedItems.clear();
    renderRequests();
    alert(`Bulk ${status} successful.`);
}

function toggleSelect(id, e) {
    if (e.target.closest('.action-btns')) return;
    selectedItems.has(id) ? selectedItems.delete(id) : selectedItems.add(id);
    renderTable();
}

function toggleDepartments() {
    const isShowing = document.getElementById("deptDropdown").classList.toggle("show");
    document.getElementById("dropArrow").style.transform = isShowing ? "rotate(0deg)" : "rotate(-90deg)";
}
function changePage(p) { if(p < 1) return; currentPage = p; renderTable(); }
function closeModal() { document.getElementById('modalOverlay').style.display = 'none'; }
function clearSelection() { selectedItems.clear(); renderTable(); }
function handleLogout() { if(confirm("Confirm Logout?")) window.location.href = "../Backend/logout.php"; }