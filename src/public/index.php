<?php
require_once __DIR__."/../lib/orm.php";
?>
{% extends 'templates/base.j2' %}
{% block title %}CronDNS Login{% endblock %}

{% block content %}
<body class="login-page">
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
</body>
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

/* ---------- Brute-force protection ---------- */
const loginBtn = document.getElementById('loginBtn');
const loginForm = document.getElementById('loginForm');
let lockoutTimer = null;

// Simulated login handler – replace with your real logic
loginForm.addEventListener('submit', () => {
  const passwordCorrect = false; // <-- your real check here

  if (!passwordCorrect) {
    loginForm.parentElement.classList.add('login-error');
    loginBtn.disabled = true;
    clearTimeout(lockoutTimer);
    lockoutTimer = setTimeout(() => {
      loginBtn.disabled = false;
      loginForm.parentElement.classList.remove('login-error');
    }, 3500);
  } else {
    window.location.href = '/dashboard';
  }
});
{% endblock %}
