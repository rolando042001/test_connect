/* ======================================
   ARCHIVE JS - PROFESSIONAL CLEAN LOGIC
   ====================================== */

let archivedItems = [];
let filteredItems = [];
let departmentsMap = {};
let currentPage = 1;
const itemsPerPage = 8;
let selectedItems = new Set();

const ICONS = {
    folder: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#03acfa" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>`,
    file: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#03acfa" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>`,
    ellipsis: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>`
};

document.addEventListener('DOMContentLoaded', () => {
    fetchArchive();
    initSidebar();
    loadDepartments();
    setupListeners();
});

async function fetchArchive() {
    try {
        const res = await fetch('./get_archive.php');
        archivedItems = await res.json();
        applyFilters(); 
    } catch (e) { console.error(e); }
}

async function loadDepartments() {
    try {
        const res = await fetch('../Backend/get_Departments.php');
        const departments = await res.json();
        const filter = document.getElementById("deptFilter");
        departments.forEach(dept => {
            departmentsMap[dept.id] = dept.name;
            const opt = document.createElement("option");
            opt.value = dept.id; opt.textContent = dept.name;
            filter.appendChild(opt);
        });
    } catch (e) { console.error(e); }
}

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

/**
 * 1. ACTION FUNCTIONS
 */
async function restoreFromArchive(id, type) {
    if (!confirm("Restore this item?")) return;
    try {
        const res = await fetch('unarchive.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: [{id, type}] })
        });
        if ((await res.json()).success) { fetchArchive(); alert("Restored successfully."); }
    } catch (e) { console.error(e); }
}

async function moveToTrash(id, type) {
    if (!confirm("Move this item to trash?")) return;
    try {
        const res = await fetch('archive_to_trash.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: [{id, type}] })
        });
        if ((await res.json()).success) { fetchArchive(); alert("Moved to Trash."); }
    } catch (e) { console.error(e); }
}

function applyFilters() {
    const search = document.getElementById("searchInput").value.toLowerCase();
    const type = document.getElementById("typeFilter").value;
    const dept = document.getElementById("deptFilter").value;

    filteredItems = archivedItems.filter(d => {
        const matchSearch = d.name.toLowerCase().includes(search);
        const matchType = !type || d.type === type;
        const matchDept = !dept || d.dept_id == dept;
        return matchSearch && matchType && matchDept;
    });

    currentPage = 1; render();
}

function render() {
    const grid = document.getElementById("gridView");
    const listBody = document.getElementById("listBody");
    const isGridView = document.getElementById("gridBtn").classList.contains("active");
    const paginated = filteredItems.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

    const bulkBar = document.getElementById("bulkActionsBar");
    if (selectedItems.size > 0) {
        bulkBar.classList.add("active");
        document.getElementById("selectedCount").innerText = selectedItems.size;
    } else { bulkBar.classList.remove("active"); }

    if (isGridView) {
        grid.style.display = "grid";
        document.getElementById("listView").style.display = "none";
        grid.innerHTML = paginated.map((d, i) => {
            const isSelected = selectedItems.has(`${d.type}-${d.id}`);
            return `
            <div class="file-card ${isSelected ? 'selected' : ''}" onclick="toggleSelect(${d.id}, '${d.type}', event)">
                <input type="checkbox" class="card-checkbox" ${isSelected ? 'checked' : ''}>
                <div class="ellipsis" onclick="event.stopPropagation(); toggleMenu('menu-${i}')">${ICONS.ellipsis}
                    <div class="ellipsis-menu" id="menu-${i}">
                        <div onclick="restoreFromArchive(${d.id}, '${d.type}')">Restore</div>
                        <div style="color:var(--danger)" onclick="moveToTrash(${d.id}, '${d.type}')">Move to Trash</div>
                    </div>
                </div>
                <div class="file-name">${d.type === 'folder' ? ICONS.folder : ICONS.file} ${d.name}</div>
                <div class="file-meta">${departmentsMap[d.dept_id] || 'General'} • ${d.type}</div>
                <div class="file-meta">Archived: ${d.date}</div>
            </div>`;
        }).join("");
    } else {
        grid.style.display = "none";
        document.getElementById("listView").style.display = "block";
        listBody.innerHTML = paginated.map((d, i) => {
            const isSelected = selectedItems.has(`${d.type}-${d.id}`);
            return `
            <tr class="${isSelected ? 'selected' : ''}" onclick="toggleSelect(${d.id}, '${d.type}', event)">
                <td><input type="checkbox" class="list-checkbox" ${isSelected ? 'checked' : ''}></td>
                <td><div style="display:flex; align-items:center; gap:12px;">${d.type === 'folder' ? ICONS.folder : ICONS.file} ${d.name}</div></td>
                <td>${departmentsMap[d.dept_id] || 'General'}</td>
                <td>${d.type}</td>
                <td>${d.date}</td>
                <td style="position:relative;">
                    <div class="ellipsis" onclick="event.stopPropagation(); toggleMenu('lmenu-${i}')">${ICONS.ellipsis}
                        <div class="ellipsis-menu" id="lmenu-${i}">
                            <div onclick="restoreFromArchive(${d.id}, '${d.type}')">Restore</div>
                            <div style="color:var(--danger)" onclick="moveToTrash(${d.id}, '${d.type}')">Move to Trash</div>
                        </div>
                    </div>
                </td>
            </tr>`;
        }).join("");
    }
    renderPagination();
}

function renderPagination() {
    const container = document.getElementById("paginationContainer");
    const total = filteredItems.length;
    const totalPages = Math.ceil(total / itemsPerPage);
    const startIdx = total === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const endIdx = Math.min(currentPage * itemsPerPage, total);

    if (total === 0) { container.innerHTML = ""; return; }

    container.innerHTML = `
        <div class="pagination-info">Showing <b>${startIdx}-${endIdx}</b> of <b>${total}</b> results</div>
        <div class="pagination-btns">
            <button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">Prev</button>
            ${Array.from({length: totalPages}, (_, i) => i + 1).map(num => `
                <button class="page-btn ${num === currentPage ? 'active' : ''}" onclick="changePage(${num})">${num}</button>
            `).join("")}
            <button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">Next</button>
        </div>`;
}

async function bulkAction(action) {
    if (selectedItems.size === 0) return;
    const items = Array.from(selectedItems).map(key => { const [type, id] = key.split('-'); return { type, id }; });
    if (!confirm(`${action === 'restore' ? 'Restore' : 'Trash'} ${items.length} items?`)) return;
    const endpoint = action === 'restore' ? 'unarchive.php' : 'archive_to_trash.php';
    try {
        const res = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ items }) });
        if ((await res.json()).success) { selectedItems.clear(); fetchArchive(); alert(`Bulk ${action} successful.`); }
    } catch (e) { console.error(e); }
}

function setupListeners() {
    document.getElementById("searchInput").addEventListener("input", applyFilters);
    document.getElementById("deptFilter").addEventListener("change", applyFilters);
    document.getElementById("typeFilter").addEventListener("change", applyFilters);
    document.addEventListener('click', () => document.querySelectorAll(".ellipsis-menu").forEach(m => m.style.display = "none"));
}

function toggleSelect(id, type, e) {
    if (e.target.closest('.ellipsis')) return;
    const key = `${type}-${id}`;
    selectedItems.has(key) ? selectedItems.delete(key) : selectedItems.add(key);
    render();
}

function toggleMenu(id) {
    const menu = document.getElementById(id);
    const isOpen = menu.style.display === "flex";
    document.querySelectorAll(".ellipsis-menu").forEach(m => m.style.display = "none");
    menu.style.display = isOpen ? "none" : "flex";
}

function switchView(mode) {
    document.getElementById("gridBtn").classList.toggle("active", mode === "grid");
    document.getElementById("listBtn").classList.toggle("active", mode === "list");
    render();
}

function changePage(p) { if(p < 1) return; currentPage = p; render(); }
function toggleDepartments() { const isShowing = document.getElementById("deptDropdown").classList.toggle("show"); document.getElementById("dropArrow").style.transform = isShowing ? "rotate(0deg)" : "rotate(-90deg)"; }
function clearSelection() { selectedItems.clear(); render(); }
function handleLogout() { if(confirm("Confirm Logout?")) window.location.href = "../Backend/logout.php"; }