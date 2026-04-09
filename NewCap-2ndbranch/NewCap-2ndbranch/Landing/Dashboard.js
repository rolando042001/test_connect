// ======================================
// E-VAULT JS - PRO SYSTEM
// ======================================

// 1. STATE & GLOBAL VARIABLES
let vaultState = { 
    items: [],
    filtered: [],
    currentPage: 1,
    limit: 12
}; 
let currentParentId = null;
let currentDeptId = null;
let currentPath = []; 
let viewMode = "grid";
let currentModalType = null;
let selectedItems = new Set();

// 2. INITIALIZATION
document.addEventListener("DOMContentLoaded", () => {
    initUI();
    renderVaultFiles();
});

function initUI() {
    document.getElementById("vaultSearch")?.addEventListener("keyup", () => {
        vaultState.currentPage = 1;
        applyState();
    });

    document.querySelector("main").addEventListener("contextmenu", (e) => {
        if (e.target.closest(".item-card") || e.target.closest("tr")) return;
        e.preventDefault();
        showGlobalContextMenu(e.clientX, e.clientY);
    });

    document.addEventListener("click", () => {
        const menu = document.getElementById("screenContextMenu");
        if (menu) menu.style.display = "none";
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closePreview();
            clearSelection();
        }
    });

    // DRAG AND DROP LISTENERS
    const dragOverlay = document.getElementById("dragOverlay");
    let dragCounter = 0; // Prevent flickering when dragging over child elements

    window.addEventListener("dragenter", (e) => {
        e.preventDefault();
        if (e.dataTransfer.types.includes("Files")) {
            dragCounter++;
            dragOverlay.classList.add("active");
        }
    });
    
    window.addEventListener("dragleave", (e) => {
        e.preventDefault();
        dragCounter--;
        if (dragCounter === 0) {
            dragOverlay.classList.remove("active");
        }
    });

    window.addEventListener("dragover", (e) => {
        e.preventDefault();
    });

    window.addEventListener("drop", (e) => {
        e.preventDefault();
        dragCounter = 0;
        dragOverlay.classList.remove("active");
        if (e.dataTransfer.files.length > 0) handleDropUpload(e.dataTransfer.files);
    });
}

// ==========================================
// 1. MASTER ACTION CONTROLLER
// ==========================================
async function executeVaultAction(actionType, itemsArray, targetFolderId = null) {
    const fd = new FormData();
    fd.append("action", actionType);
    fd.append("items", JSON.stringify(itemsArray)); 
    
    let target = targetFolderId !== null ? targetFolderId : (currentParentId || "");
    fd.append("target_folder_id", target);
    fd.append("dept_id", currentDeptId || ""); 

    try {
        const endpoint = (actionType === 'archive') ? "../Archive/archive_item.php" : "manage_vault.php";
        const res = await fetch(endpoint, { method: "POST", body: fd });
        const rawText = await res.text();
        
        let data;
        try { data = JSON.parse(rawText); } 
        catch (parseError) {
            console.error("PHP ERROR:", rawText);
            showToast("Server output error. Check Console (F12).", "error");
            return false;
        }

        if (data.status === "success" || data.success) {
            showToast(`Operation successful.`);
            clearSelection();
            renderVaultFiles();
            return true;
        } else {
            showToast(data.message || "Operation failed on server.", "error");
            return false;
        }
    } catch (err) {
        showToast("Server connection error.", "error");
        return false;
    }
}

function getSelectedItemsData() {
    const items = [];
    selectedItems.forEach(id => {
        const item = vaultState.items.find(i => Number(i.id) === Number(id));
        if (item) items.push({ id: item.id, type: item.type });
    });
    return items;
}

function batchCopy(mode) { 
    const items = getSelectedItemsData();
    if (items.length === 0) return showToast("No items selected.", "error");
    sessionStorage.setItem("eufile_clipboard", JSON.stringify({ mode: mode, items: items }));
    showToast(`${items.length} item(s) ${mode === 'copy' ? 'copied' : 'cut'} to clipboard.`);
    clearSelection();
    renderVaultFiles(); 
}

function batchDelete() {
    const items = getSelectedItemsData();
    if (items.length === 0) return;
    if (confirm(`Move ${items.length} item(s) to Trash?`)) { executeVaultAction('delete', items); }
}

function batchArchive() {
    const items = getSelectedItemsData();
    if (items.length === 0) return;
    if (confirm(`Archive ${items.length} item(s)?`)) { executeVaultAction('archive', items); }
}

function copyAction(id, type, mode) {
    sessionStorage.setItem("eufile_clipboard", JSON.stringify({ mode: mode, items: [{id: id, type: type}] }));
    showToast(`Item ${mode === 'copy' ? 'copied' : 'cut'} to clipboard.`);
    renderVaultFiles();
}

function deleteItem(id, type) {
    if (confirm(`Move this ${type} to Trash?`)) { executeVaultAction('delete', [{id: id, type: type}]); }
}

function archiveItem(id, type) {
    if (confirm(`Move this ${type} to Archive?`)) { executeVaultAction('archive', [{id: id, type: type}]); }
}

async function performPaste(targetFolderId = null) {
    const clip = JSON.parse(sessionStorage.getItem("eufile_clipboard"));
    if (!clip || !clip.items || clip.items.length === 0) return showToast("Clipboard is empty.", "error");
    const actionType = `paste_${clip.mode}`; 
    const success = await executeVaultAction(actionType, clip.items, targetFolderId);
    if (success && clip.mode === 'cut') sessionStorage.removeItem("eufile_clipboard"); 
}

// 3. RENDER LOGIC & PAGINATION
function showSkeletonLoaders() {
    const grid = document.getElementById("vaultGrid");
    grid.innerHTML = "";
    for (let i = 0; i < vaultState.limit; i++) {
        grid.innerHTML += `<div class="skeleton-card"><div class="skeleton-icon"></div><div class="skeleton-text"></div><div class="skeleton-text" style="width: 40%; height: 8px;"></div></div>`;
    }
}

async function renderVaultFiles() {
    const grid = document.getElementById("vaultGrid");
    const tableBody = document.getElementById("vaultTableBody");
    showSkeletonLoaders();

    try {
        const parent = currentParentId ?? "";
        const dept = currentDeptId ?? "";
        const res = await fetch(`get_vault.php?parent_id=${parent}&dept_id=${dept}`);
        const data = await res.json();

        vaultState.items = [
            ...data.folders.map(f => ({ ...f, type: 'folder' })),
            ...data.files.map(f => ({ ...f, type: 'file' }))
        ];

        applyState();
    } catch (err) { 
        showToast("Error loading storage.", "error");
    }
}

function applyState() {
    const searchTerm = document.getElementById("vaultSearch")?.value.toLowerCase() || "";
    vaultState.filtered = vaultState.items.filter(item => item.name.toLowerCase().includes(searchTerm));
    
    // Sort Folders First
    vaultState.filtered.sort((a, b) => {
        if (a.type === 'folder' && b.type !== 'folder') return -1;
        if (a.type !== 'folder' && b.type === 'folder') return 1;
        return a.name.localeCompare(b.name);
    });

    displayResults();
    updateBreadcrumbs();
    updateSelectionUI();
    
    const selectAllCb = document.getElementById('selectAllCheckbox');
    if (selectAllCb) selectAllCb.checked = (selectedItems.size === vaultState.filtered.length && vaultState.filtered.length > 0);
}

function displayResults() {
    const grid = document.getElementById("vaultGrid");
    const tableBody = document.getElementById("vaultTableBody");
    grid.innerHTML = "";
    tableBody.innerHTML = "";

    const total = vaultState.filtered.length;
    const start = (vaultState.currentPage - 1) * vaultState.limit;
    const paginatedItems = vaultState.filtered.slice(start, start + vaultState.limit);

    if (total === 0) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--text-dim);padding:50px;">Folder is empty or no search results.</p>';
    } else {
        paginatedItems.forEach(item => renderItem(item, item.type, grid, tableBody));
    }
    
    toggleViewMode(viewMode);
    renderPaginationUI();
}

// ------------------------------------------------
// PROFESSIONAL ICONS LOGIC (UNIFIED SLATE DESIGN)
// ------------------------------------------------
function getProfessionalIcon(type, ext) {
    const strokeColor = "#64748b"; // Uniform Slate
    
    if (type === "folder") {
        return `<svg class="item-icon folder-icon" width="46" height="46" viewBox="0 0 24 24" fill="rgba(3,172,250,0.1)" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>`;
    }
    
    if (ext === "pdf") {
        return `<svg class="item-icon file-icon" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path></svg>`;
    } else if (["jpg", "jpeg", "png", "webp", "gif"].includes(ext)) {
        return `<svg class="item-icon file-icon" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>`;
    } else if (["xlsx", "xls", "csv"].includes(ext)) {
        return `<svg class="item-icon file-icon" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M8 13h2"></path><path d="M8 17h2"></path><path d="M14 13h2"></path><path d="M14 17h2"></path></svg>`;
    } else {
        return `<svg class="item-icon file-icon" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>`;
    }
}

function renderItem(item, type, grid, tableBody) {
    const name = item.name;
    const ext = type === "file" ? name.split(".").pop().toLowerCase() : "";
    const clipboard = JSON.parse(sessionStorage.getItem("eufile_clipboard") || "null");
    
    const isCutting = clipboard && clipboard.mode === "cut" && clipboard.items && clipboard.items.some(i => Number(i.id) === Number(item.id));
    const isSelected = selectedItems.has(item.id);
    
    const iconSVG = getProfessionalIcon(type, ext);

    const card = document.createElement("div");
    card.className = `item-card ${isCutting ? 'is-cutting' : ''} ${isSelected ? 'selected' : ''}`;
    card.setAttribute('data-id', item.id);
    card.innerHTML = `
        <div class="selection-dot" onclick="toggleSelect(event, ${item.id})">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <button class="ellipsis-btn" onclick="event.stopPropagation(); showContextMenu(event, ${JSON.stringify(item).replace(/"/g, '&quot;')})">⋮</button>
        <div class="item-icon-wrapper">${iconSVG}</div>
        <span class="item-name" title="${name}">${name}</span>
    `;
    card.onclick = (e) => { e.ctrlKey ? toggleSelect(e, item.id) : handleItemClick(item, type, name); };
    card.oncontextmenu = (e) => showContextMenu(e, item);
    grid.appendChild(card);

    const tr = document.createElement("tr");
    tr.className = isSelected ? 'selected' : '';
    tr.innerHTML = `
        <td style="text-align:center;"><input type="checkbox" class="list-checkbox" ${isSelected ? 'checked' : ''} onclick="toggleSelect(event, ${item.id})"></td>
        <td style="display:flex; align-items:center; gap:12px; cursor:pointer;" onclick="handleItemClick(${JSON.stringify(item).replace(/"/g, '&quot;')}, '${type}', '${name}')">
            <div style="width:24px; display:flex; align-items:center;">${iconSVG.replace(/46/g, '24')}</div> 
            <span style="font-weight:600;">${name}</span>
        </td>
        <td style="font-size:0.8rem; color:var(--text-dim);">${type.toUpperCase()}</td>
        <td>${item.size ? formatSize(item.size) : "-"}</td>
        <td>${item.date || "-"}</td>
        <td style="text-align: right;"><button class="ellipsis-btn" style="position:static;" onclick="showContextMenu(event, ${JSON.stringify(item).replace(/"/g, '&quot;')})">⋮</button></td>
    `;
    tableBody.appendChild(tr);
}

// PROFESSIONAL PAGINATION
function renderPaginationUI() {
    const container = document.getElementById("paginationContainer");
    const total = vaultState.filtered.length;
    
    if (total === 0) { container.innerHTML = ""; return; }

    const totalPages = Math.ceil(total / vaultState.limit);
    const start = ((vaultState.currentPage - 1) * vaultState.limit) + 1;
    const end = Math.min(vaultState.currentPage * vaultState.limit, total);

    let buttons = '';
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= vaultState.currentPage - 1 && i <= vaultState.currentPage + 1)) {
            buttons += `<button class="page-btn ${i === vaultState.currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
        } else if (i === vaultState.currentPage - 2 || i === vaultState.currentPage + 2) {
            buttons += `<span style="color:var(--text-dim); padding:0 5px;">...</span>`;
        }
    }

    container.className = "pagination-container";
    container.innerHTML = `
        <div class="pagination-info">
            Showing <strong>${start}</strong> to <strong>${end}</strong> of <strong>${total}</strong> files
            <select class="limit-select" style="margin-left: 15px;" onchange="changeLimit(this.value)">
                <option value="12" ${vaultState.limit == 12 ? 'selected' : ''}>12 / page</option>
                <option value="24" ${vaultState.limit == 24 ? 'selected' : ''}>24 / page</option>
                <option value="48" ${vaultState.limit == 48 ? 'selected' : ''}>48 / page</option>
            </select>
        </div>
        <div class="pagination-controls">
            <button class="page-btn" onclick="goToPage(${vaultState.currentPage - 1})" ${vaultState.currentPage === 1 ? 'disabled' : ''}>Prev</button>
            ${buttons}
            <button class="page-btn" onclick="goToPage(${vaultState.currentPage + 1})" ${vaultState.currentPage === totalPages ? 'disabled' : ''}>Next</button>
        </div>
    `;
}

function goToPage(p) {
    const totalPages = Math.ceil(vaultState.filtered.length / vaultState.limit);
    if (p < 1 || p > totalPages) return;
    vaultState.currentPage = p;
    displayResults();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function changeLimit(l) {
    vaultState.limit = parseInt(l);
    vaultState.currentPage = 1;
    displayResults();
}

// 4. NAVIGATION LOGIC
function handleItemClick(item, type, name) {
    if (type === "folder") {
        let nextParentId = currentDeptId === null ? null : item.id;
        let nextDeptId = currentDeptId === null ? item.id : currentDeptId;
        currentPath.push({ id: item.id, deptId: nextDeptId, name: name });
        currentParentId = nextParentId;
        currentDeptId = nextDeptId;
        vaultState.currentPage = 1; 
        renderVaultFiles();
    } else {
        previewFile(item);
    }
}

function goBack() {
    if (currentPath.length > 0) {
        currentPath.pop();
        if (currentPath.length === 0) {
            currentParentId = null;
            currentDeptId = null;
        } else {
            const last = currentPath[currentPath.length - 1];
            currentParentId = (currentPath.length === 1) ? null : last.id;
            currentDeptId = last.deptId;
        }
        vaultState.currentPage = 1;
        renderVaultFiles();
    }
}

function updateBreadcrumbs() {
    const bc = document.getElementById("breadcrumb");
    const navActions = document.getElementById("navActions");
    if (!bc) return;

    if (currentPath.length === 0) {
        // Kapag nasa pinaka-labas (nagpipili ng Department)
        bc.innerHTML = "<span>Departments Overview</span>";
        if (navActions) navActions.style.display = "none";
    } else {
        // Kapag nasa loob na ng Department
        if (navActions) navActions.style.display = "block";
        bc.innerHTML = ''; // Tinanggal na ang "Root Storage"

        currentPath.forEach((p, i) => {
            // Maglagay ng '/' separator kung hindi ito ang unang item
            if (i > 0) {
                bc.innerHTML += ` <span style="margin:0 8px; opacity:0.3;">/</span> `;
            }
            
            // I-highlight kung ito ang kasalukuyang folder na bukas
            const isCurrentFolder = i === currentPath.length - 1;
            const style = isCurrentFolder ? 'color:var(--primary); font-weight:800;' : 'color:var(--text-dim);';
            
            bc.innerHTML += `<span onclick="jumpToPath(${i})" style="cursor:pointer; transition:0.2s; ${style}">${p.name}</span>`;
        });
    }
}

function jumpToPath(index) {
    if (index === -1) {
        currentPath = [];
        currentParentId = null;
        currentDeptId = null;
    } else {
        currentPath = currentPath.slice(0, index + 1);
        const last = currentPath[index];
        currentParentId = (index === 0) ? null : last.id;
        currentDeptId = last.deptId;
    }
    vaultState.currentPage = 1;
    renderVaultFiles();
}

// 5. SELECTION & BATCH LOGIC
function toggleSelect(e, itemId) {
    e.stopPropagation();
    const id = Number(itemId);
    if (selectedItems.has(id)) selectedItems.delete(id);
    else selectedItems.add(id);
    updateSelectionUI();
}

function toggleSelectAll() {
    const visibleItems = vaultState.filtered; 
    if (selectedItems.size === visibleItems.length && visibleItems.length > 0) {
        selectedItems.clear(); 
    } else {
        visibleItems.forEach(item => selectedItems.add(item.id)); 
    }
    updateSelectionUI();
    displayResults(); 
}

function updateSelectionUI() {
    const bar = document.getElementById("bulkActionsBar");
    const countSpan = document.getElementById("selectedCount");
    
    document.querySelectorAll('.item-card').forEach(card => {
        const id = Number(card.getAttribute('data-id'));
        card.classList.toggle('selected', selectedItems.has(id));
    });

    if (selectedItems.size > 0) {
        countSpan.innerText = selectedItems.size;
        bar.classList.add("active");
    } else {
        bar.classList.remove("active");
    }
}

function clearSelection() {
    selectedItems.clear();
    updateSelectionUI();
    displayResults();
}

// 6. CONTEXT MENUS
function showContextMenu(e, item) {
    e.preventDefault(); e.stopPropagation();
    const menu = document.getElementById("screenContextMenu");
    const hasClipboard = !!sessionStorage.getItem("eufile_clipboard");

    menu.innerHTML = `
        <div class="menu-item" onclick="previewFile(${JSON.stringify(item).replace(/"/g, '&quot;')})">Open Inspector</div>
        <div class="menu-item" onclick="renameItem(${item.id}, '${item.type}', '${item.name}')">Rename</div>
        <div class="menu-divider"></div>
        <div class="menu-item" onclick="copyAction(${item.id}, '${item.type}', 'copy')">Copy Item</div>
        <div class="menu-item" onclick="copyAction(${item.id}, '${item.type}', 'cut')">Cut Item</div>
        ${item.type === 'folder' && hasClipboard ? `<div class="menu-item" style="color:var(--success);" onclick="performPaste(${item.id})">Paste into Folder</div>` : ''}
        <div class="menu-divider"></div>
        <div class="menu-item" onclick="archiveItem(${item.id}, '${item.type}')">Move to Archive</div>
        <div class="menu-item delete" onclick="deleteItem(${item.id}, '${item.type}')">Move to Trash</div>
    `;
    displayMenu(menu, e.clientX, e.clientY);
}

function showGlobalContextMenu(x, y) {
    const menu = document.getElementById("screenContextMenu");
    const hasClipboard = !!sessionStorage.getItem("eufile_clipboard");
    
    menu.innerHTML = `
        <div class="menu-item" onclick="renderVaultFiles()">Refresh Explorer</div>
        <div class="menu-divider"></div>
        <div class="menu-item" onclick="openCreateModal('folder')">New Folder</div>
        <div class="menu-item" onclick="openCreateModal('file')">Upload Document</div>
        ${hasClipboard ? `<div class="menu-divider"></div><div class="menu-item" style="color:var(--success);" onclick="performPaste()">Paste Items Here</div>` : ''}
    `;
    displayMenu(menu, x, y);
}

function displayMenu(menu, x, y) {
    menu.style.display = "block";
    const { innerWidth: windowWidth, innerHeight: windowHeight } = window;
    const { offsetWidth: menuWidth, offsetHeight: menuHeight } = menu;
    if (x + menuWidth > windowWidth) x -= menuWidth;
    if (y + menuHeight > windowHeight) y -= menuHeight;
    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;
}

// 7. XHR PROGRESS UPLOAD & DRAG DROP
function uploadWithProgress(formData, progressCallback) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "manage_vault.php", true);
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressCallback(percentComplete);
            }
        };
        xhr.onload = function() {
            if (xhr.status === 200) {
                try { resolve(JSON.parse(xhr.responseText)); } 
                catch (err) { reject("Invalid JSON response"); }
            } else reject("Server error");
        };
        xhr.onerror = () => reject("Network error");
        xhr.send(formData);
    });
}

async function handleDropUpload(files) {
    if (currentDeptId === null) return showToast("Enter a department folder first before uploading.", "error");
    
    const dragOverlay = document.getElementById("dragOverlay");
    const fd = new FormData();
    fd.append("action", "upload_file");
    for (let i = 0; i < files.length; i++) fd.append("files[]", files[i]);
    fd.append("parent_id", currentParentId || "");
    fd.append("dept_id", currentDeptId);

    dragOverlay.classList.add("active");
    dragOverlay.innerHTML = `
        <svg class="spinner" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="margin-bottom: 20px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
        <h2 style="margin:0;">Uploading ${files.length} file(s)...</h2>
        <div class="progress-container" style="display:block; width: 50%; max-width: 400px; background: rgba(255,255,255,0.2);">
            <div id="dragProgressBar" class="progress-bar" style="background: white; width: 0%;"></div>
        </div>
        <div id="dragProgressText" style="margin-top:10px; font-weight:700;">0%</div>
    `;

    const dProgressBar = document.getElementById("dragProgressBar");
    const dProgressText = document.getElementById("dragProgressText");

    try {
        const result = await uploadWithProgress(fd, (percent) => {
            dProgressBar.style.width = percent + "%";
            dProgressText.innerText = percent + "%";
        });
        if (result.status === "success") { showToast("Files uploaded successfully!"); renderVaultFiles(); } 
        else showToast(result.message, "error");
    } catch (err) { 
        showToast("Upload failed.", "error"); 
    } finally {
        dragOverlay.classList.remove("active");
        setTimeout(() => {
            dragOverlay.innerHTML = `
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="margin-bottom: 20px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                <h2 style="margin:0;">Drop files here to upload</h2>
                <p style="margin:10px 0 0; opacity:0.8;">Files will be added to the current folder.</p>
            `;
        }, 300);
    }
}

async function saveItem() {
    const nameInput = document.getElementById("itemName");
    const fileInput = document.getElementById("itemFile");
    const confirmBtn = document.getElementById("confirmBtn");
    const cancelBtn = document.getElementById("cancelBtn");
    const progressContainer = document.getElementById("uploadProgressContainer");
    const progressBar = document.getElementById("uploadProgressBar");
    const progressText = document.getElementById("uploadProgressText");
    
    if (currentDeptId === null) return showToast("Select a department first.", "error");

    const formData = new FormData();
    if (currentModalType === "folder") {
        if (!nameInput.value.trim()) return showToast("Name required.", "error");
        formData.append("action", "create_folder");
        formData.append("name", nameInput.value.trim());
    } else {
        if (!fileInput.files.length) return showToast("Select files.", "error");
        formData.append("action", "upload_file");
        for (let i = 0; i < fileInput.files.length; i++) formData.append("files[]", fileInput.files[i]);
    }
    formData.append("parent_id", currentParentId || "");
    formData.append("dept_id", currentDeptId);

    const originalText = confirmBtn.innerText;
    confirmBtn.innerHTML = `<svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:text-bottom; margin-right:5px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg> Processing...`;
    confirmBtn.disabled = true; cancelBtn.disabled = true;
    confirmBtn.style.opacity = "0.7"; confirmBtn.style.cursor = "not-allowed";

    if (currentModalType === "file") {
        progressContainer.style.display = "block"; progressText.style.display = "block";
        progressBar.style.width = "0%"; progressText.innerText = "0%";
    }

    try {
        let result;
        if (currentModalType === "file") {
            result = await uploadWithProgress(formData, (percent) => {
                progressBar.style.width = percent + "%";
                progressText.innerText = percent + "%";
            });
        } else {
            const res = await fetch("manage_vault.php", { method: "POST", body: formData });
            result = await res.json();
        }

        if (result.status === "success") { showToast("Success!"); closeModal(); renderVaultFiles(); } 
        else showToast(result.message, "error");
    } catch (err) { 
        showToast("Upload failed.", "error"); 
    } finally {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false; cancelBtn.disabled = false;
        confirmBtn.style.opacity = "1"; confirmBtn.style.cursor = "pointer";
        setTimeout(() => { progressContainer.style.display = "none"; progressText.style.display = "none"; }, 1000);
    }
}

async function renameItem(id, type, oldName) {
    const newName = prompt(`Rename ${type}:`, oldName);
    if (!newName || newName === oldName) return;
    const fd = new FormData();
    fd.append("action", "rename");
    fd.append("id", id); fd.append("type", type); fd.append("new_name", newName);
    try {
        await fetch("manage_vault.php", { method: "POST", body: fd });
        renderVaultFiles();
    } catch (err) { showToast("Rename failed.", "error"); }
}

// 8. UI UTILITIES
function formatSize(bytes) {
    if (!bytes) return "0 KB";
    const kb = bytes / 1024;
    return kb >= 1024 ? (kb / 1024).toFixed(2) + " MB" : kb.toFixed(2) + " KB";
}

function showToast(message, type = "success") {
    const container = document.getElementById("toastContainer");
    if (!container) return;
    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span> <div>${message}</div>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = "0"; setTimeout(() => toast.remove(), 500); }, 3000);
}

function openCreateModal(type) {
    currentModalType = type;
    document.getElementById("itemName").style.display = (type === "folder") ? "block" : "none";
    document.getElementById("uploadArea").style.display = (type === "file") ? "block" : "none";
    document.getElementById("modalTitle").innerText = (type === "folder") ? "Create New Folder" : "Upload Documents";
    document.getElementById("modalOverlay").style.display = "flex";
}

function closeModal() { document.getElementById("modalOverlay").style.display = "none"; }

function toggleViewMode(mode) {
    viewMode = mode;
    document.getElementById("vaultGrid").style.display = (mode === "grid") ? "grid" : "none";
    document.getElementById("vaultList").style.display = (mode === "list") ? "block" : "none";
    document.getElementById("gridToggle").classList.toggle("active", mode === "grid");
    document.getElementById("listToggle").classList.toggle("active", mode === "list");
}

function previewFile(item) {
    const overlay = document.getElementById("previewOverlay");
    const path = `../uploads/vault/${item.storage_name}`;
    const ext = item.storage_name.split(".").pop().toLowerCase();
    
    document.getElementById("prevName").innerText = item.name;
    document.getElementById("prevName").title = item.name;
    document.getElementById("prevSize").innerText = formatSize(item.size);
    document.getElementById("prevDate").innerText = item.date || "-";
    
    // UPDATE: Tinanggal ang "E-cabinet/" sa path indicator
    document.getElementById("prevPath").innerText = currentPath.length > 0 ? currentPath.map(p => p.name).join(' / ') : 'Departments Overview';
    
    const content = document.getElementById("previewContent");
    content.innerHTML = "";
    if (["jpg", "jpeg", "png", "gif", "webp"].includes(ext)) {
        content.innerHTML = `<img src="${path}" alt="Preview">`;
    } else if (ext === "pdf") {
        content.innerHTML = `<iframe src="${path}#toolbar=0" style="width:100%; height:100%; border:none;"></iframe>`;
    } else {
        content.innerHTML = `<div style="text-align:center; color: #64748b;"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:15px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg><p>No visual preview available.</p></div>`;
    }
    document.getElementById("prevDownloadBtn").onclick = () => downloadFile(item.storage_name, item.name);
    overlay.style.display = "flex";
}

function closePreview() { document.getElementById("previewOverlay").style.display = "none"; }

function downloadFile(storageName, displayName) {
    const link = document.createElement("a");
    link.href = `../uploads/vault/${storageName}`;
    link.download = displayName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function updateFileLabel(input) {
    const label = document.getElementById("fileCount");
    const uploadText = document.getElementById("uploadText");
    if (input.files.length > 0) {
        label.innerText = `${input.files.length} file(s) selected`;
        label.style.display = "block";
        uploadText.innerText = "Ready to Upload";
        uploadText.style.color = "var(--success)";
    } else {
        label.style.display = "none";
        uploadText.innerText = "Click to select files";
        uploadText.style.color = "var(--text-main)";
    }
}