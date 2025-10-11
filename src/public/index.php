{% extends 'templates/base.j2' %}
{% block title %}CronDNS Login{% endblock %}
{% block content %}
<!--  Add class="login-error" from PHP if the password was wrong -->
<div class="login-card <?php echo $login_error ? 'login-error' : ''; ?>">
  <h1>CronDNS</h1>
  <form id="loginForm" onsubmit="return false;">
    <div class="input-wrapper">
      <i class="ti ti-lock icon"></i>
      <input type="password" id="pwd" placeholder="Password" required>
      <i class="ti ti-eye toggle-visibility" id="togglePwd"></i>
    </div>
    <button type="submit" id="loginBtn">Login</button>
  </form>

  <div class="links">
    <a href="https://github.com/TRC-Loop/CronDNS/wiki" target="_blank">Documentation</a>
    <span class="separator">·</span>
    <a href="https://github.com/TRC-Loop/CronDNS" target="_blank">GitHub</a>
  </div>

  <div class="version">v{{ cfg.version }}</div>
</div>
{% endblock %}
{% block scripts %}
/* ---------- Password visibility toggle ---------- */
const pwdInput = document.getElementById('pwd');
const toggleBtn = document.getElementById('togglePwd');

toggleBtn.addEventListener('click', () => {
  const type = pwdInput.type === 'password' ? 'text' : 'password';
  pwdInput.type = type;
  toggleBtn.classList.toggle('ti-eye-off', type === 'text');
  toggleBtn.classList.toggle('ti-eye', type === 'password');
});

/* ---------- Brute‑force protection ---------- */
const loginBtn = document.getElementById('loginBtn');
const loginForm = document.getElementById('loginForm');
let lockoutTimer = null;

// Simulated login handler – replace with your real logic
loginForm.addEventListener('submit', () => {
  // Example: pretend the password is wrong
  const passwordCorrect = false; // <-- change to your real check

  if (!passwordCorrect) {
    // Show error state
    loginForm.parentElement.classList.add('login-error');

    // Disable button for 2–3 seconds
    loginBtn.disabled = true;
    clearTimeout(lockoutTimer);
    lockoutTimer = setTimeout(() => {
      loginBtn.disabled = false;
      loginForm.parentElement.classList.remove('login-error');
    }, 3500); // 3.5 s
  } else {
    // Successful login – redirect or whatever
    window.location.href = '/dashboard';
  }
});
{% endblock %}
