<?php
require_once __DIR__ . "/../conf/config.php";

session_start();

if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    header('Location: /home.php'); 
    exit;
}

$login_error = isset($_GET['error']) && $_GET['error'] === '1';
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
const pwdInput = document.getElementById('pwd');
const toggleBtn = document.getElementById('togglePwd');
toggleBtn.addEventListener('click', () => {
  const type = pwdInput.type === 'password' ? 'text' : 'password';
  pwdInput.type = type;
  toggleBtn.classList.toggle('ti-eye-off', type === 'text');
  toggleBtn.classList.toggle('ti-eye', type === 'password');
});

const loginBtn = document.getElementById('loginBtn');
const loginForm = document.getElementById('loginForm');
let lockoutTimer = null;

loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  if (loginBtn.disabled) return;

  const pwd = pwdInput.value.trim();
  if (!pwd) return;

  loginBtn.disabled = true;

  try {
    const form = new FormData();
    form.append('password', pwd);

    const resp = await fetch('/api/login.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    });

    const data = await resp.json();

    if (resp.ok && data.ok) {
      window.location.href = data.redirect || '/home.php';
      return;
    }

    if (data.error === 'locked') {
      showLoginError(true);
      startTemporaryLock((data.locked_for || 3) * 1000);
    } else {
      showLoginError(true);
      startTemporaryLock(3500);
    }
  } catch (err) {
    console.error(err);
    showLoginError(true);
    startTemporaryLock(3500);
  }
});

function showLoginError(flag) {
  loginForm.parentElement.classList.toggle('login-error', !!flag);
}

function startTemporaryLock(ms) {
  clearTimeout(lockoutTimer);
  loginBtn.disabled = true;
  lockoutTimer = setTimeout(() => {
    loginBtn.disabled = false;
    showLoginError(false);
  }, ms);
}
{% endblock %}
