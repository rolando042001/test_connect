lucide.createIcons();

let currentMode = 'login';
let locked = false;

// UI references
const card = document.getElementById('card');
const content = document.getElementById('form-content');
const signupExtras = document.getElementById('names-row');
const deptRow = document.getElementById('dept-row');
const cpwRow = document.getElementById('cpw-group');
const meter = document.getElementById('meter-box');
const forgotSection = document.getElementById('forgot-wrap');
const switchBtn = document.getElementById('switch-btn');
const footerLabel = document.getElementById('foot-txt');
const actionBtn = document.getElementById('submit-btn');
const actionLabel = document.getElementById('btn-label');
const toast = document.getElementById('toast');
const viewTitle = document.getElementById('view-title');
const viewSubtitle = document.getElementById('view-subtitle');
const mailIn = document.getElementById('email');
const passIn = document.getElementById('password');
const cpassIn = document.getElementById('confirm-password');

document.addEventListener('DOMContentLoaded', () => {
    loadDepartments();
    content.classList.add('view-active');
});

function loadDepartments() {
    const deptSelect = document.getElementById('department');
    fetch('../Backend/get_Departments.php')
        .then(res => res.json())
        .then(data => {
            deptSelect.innerHTML = '<option value="" disabled selected>Select Department</option>';
            data.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.name;
                option.textContent = dept.name;
                deptSelect.appendChild(option);
            });
        })
        .catch(err => console.error('Error:', err));
}

function notify(msg, type) {
    toast.innerHTML = '';
    const ico = document.createElement('i');
    ico.setAttribute('data-lucide', type === 'success' ? 'check-circle-2' : 'alert-circle');
    ico.classList.add('w-5');
    toast.appendChild(ico);
    toast.appendChild(document.createTextNode(msg));
    toast.className = `absolute top-6 left-6 right-6 p-4 rounded-[20px] flex items-center gap-3 text-sm font-bold z-[100] shadow-lg ${type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}`;
    toast.classList.remove('hidden');
    lucide.createIcons();
    setTimeout(() => { toast.classList.add('hidden'); }, 4000);
}

function toggleView(mode) {
    currentMode = mode;
    content.classList.remove('view-active');
    
    setTimeout(() => {
        card.classList.toggle('is-signup', mode === 'signup');
        
        if(mode === 'login') {
            viewTitle.textContent = 'Log In';
            viewSubtitle.textContent = 'Enter your credentials to continue.';
            actionLabel.textContent = 'Log In';
            signupExtras.classList.add('hidden');
            deptRow.classList.add('hidden');
            cpwRow.classList.add('hidden');
            meter.classList.add('hidden');
            forgotSection.classList.remove('hidden');
            switchBtn.textContent = 'Sign Up';
            footerLabel.textContent = "New around here?";
        } else {
            viewTitle.textContent = 'Sign Up';
            viewSubtitle.textContent = 'Create your secure account.';
            actionLabel.textContent = 'Create Account';
            signupExtras.classList.remove('hidden');
            deptRow.classList.remove('hidden');
            cpwRow.classList.remove('hidden');
            meter.classList.remove('hidden');
            forgotSection.classList.add('hidden');
            switchBtn.textContent = 'Log In';
            footerLabel.textContent = "Already a member?";
        }
        lucide.createIcons();
        setTimeout(() => content.classList.add('view-active'), 50);
    }, 400);
}

// Password toggle
document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.getAttribute('data-target'));
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    });
});

switchBtn.addEventListener('click', () => toggleView(currentMode === 'login' ? 'signup' : 'login'));

document.getElementById('auth-form').addEventListener('submit', function(e) {
    e.preventDefault();
    actionBtn.disabled = true;
    const prevLabel = actionLabel.textContent;
    actionLabel.textContent = 'Processing...';

    let payload = {
        email: mailIn.value,
        password: passIn.value
    };

    if (currentMode === 'signup') {
        payload.first_name = document.getElementById('fname').value;
        payload.last_name = document.getElementById('lname').value;
        payload.department = document.getElementById('department').value;
    }

    fetch(currentMode === 'login' ? '../Backend/login.php' : '../Backend/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            notify(data.msg, 'success');
            if (currentMode === 'login') {
                // If the user has MFA enabled, force the second-factor screen.
                window.location.href = data.mfa_required
                    ? '../Login/Verify_MFA.html'
                    : '../Landing/Dashboard.html';
            } else {
                toggleView('login');
            }
        } else if (data.status === 'locked') {
            notify(data.msg + (data.retry_in ? ` (${data.retry_in}s)` : ''), 'error');
        } else {
            notify(data.msg, 'error');
        }
    })
    .catch(() => notify('Server connection failed.', 'error'))
    .finally(() => {
        actionBtn.disabled = false;
        actionLabel.textContent = prevLabel;
    });
});

// Strength Meter Logic
passIn.addEventListener('input', () => {
    if (currentMode !== 'signup') return;
    const val = passIn.value;
    const tests = {
        len: val.length >= 8,
        num: /\d/.test(val),
        spec: /[!@#$%^&*(),.?":{}|<>]/.test(val),
        case: /[a-z]/.test(val) && /[A-Z]/.test(val)
    };
    
    let points = 0;
    Object.keys(tests).forEach(k => {
        const item = document.querySelector(`[data-req="${k}"]`);
        const dot = item.querySelector('.check-dot');
        if (tests[k]) {
            item.classList.add('text-emerald-500');
            dot.classList.add('bg-emerald-500', 'border-emerald-500');
            points++;
        } else {
            item.classList.remove('text-emerald-500');
            dot.classList.remove('bg-emerald-500', 'border-emerald-500');
        }
    });

    const bars = document.querySelectorAll('.bar-seg');
    bars.forEach((b, i) => {
        b.classList.remove('bg-emerald-500', 'bg-amber-500');
        if (points > i) b.classList.add(points <= 2 ? 'bg-amber-500' : 'bg-emerald-500');
    });
});