<?php
require_once __DIR__ . "/../lib/orm.php";
require_once __DIR__ . "/../conf/config.php";

session_start();

if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    header('Location: /home.php'); 
    exit;
}

// default template variables used when rendering the page (GET)
$login_error = false;

// If this is a POST -> handle login API and return JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // basic JSON response helper
    function json_response(array $data, int $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    // read password from POST
    $pwd = isset($_POST['password']) ? (string) $_POST['password'] : '';

    // simple brute-force protection stored in session
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['lockout_until'] = 0;
    }

    $now = time();
    $maxAttempts = 5;
    $lockoutSeconds = 10; // lockout length after max attempts

    if ($_SESSION['lockout_until'] > $now) {
        $remaining = $_SESSION['lockout_until'] - $now;
        json_response(['ok' => false, 'error' => 'locked', 'locked_for' => $remaining], 429);
    }

    // load password from settings table (your ORM usage)
    $settingsManager = new PersistentEntityManager(KeyValue::class, $logger, DB, 'settings');
    $passwordObject = $settingsManager->find(["key" => "passwordHash"]);
    $passwordConfig = $passwordObject->value ?? '';


    $ok = false;
    $ok = password_verify($pwd, $passwordConfig);

    if (! $ok) {
        $_SESSION['login_attempts'] += 1;
        if ($_SESSION['login_attempts'] >= $maxAttempts) {
            $_SESSION['lockout_until'] = $now + $lockoutSeconds;
            $_SESSION['login_attempts'] = 0; // reset attempts after enforcing lockout
            json_response(['ok' => false, 'error' => 'locked', 'locked_for' => $lockoutSeconds], 429);
        } else {
            json_response(['ok' => false, 'error' => 'invalid', 'attempts_left' => $maxAttempts - $_SESSION['login_attempts']], 401);
        }
    }

    // success: start session (already started) and set session vars
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'username' => 'admin',
        'logged_in_at' => $now
    ];

    // reset attempts on success
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lockout_until'] = 0;

    // respond with success
    json_response(['ok' => true, 'redirect' => '/home.php']);
    // json_response already exits
}

// If it's not POST, execution continues and your template is rendered.
// You can set $login_error here based on query params or session if you want:
if (isset($_GET['error']) && $_GET['error'] === '1') {
    $login_error = true;
}

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

/* ---------- Login: replace simulated handler with real fetch ---------- */
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

    const resp = await fetch('/login.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    });

    const data = await resp.json();

    if (resp.ok && data.ok) {
      window.location.href = data.redirect || '/dashboard';
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
