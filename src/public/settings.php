<?php
require_once __DIR__."/../lib/utils.php";
require_once __DIR__."/../conf/config.php";
require_once __DIR__ . "/../lib/domain.php";

$domainManager = new PersistentEntityManager(Domain::class, $logger, DB, 'domains');
$totalDomains = $domainManager->getPDO()->query("SELECT COUNT(*) FROM domains")->fetchColumn();

$apiKeyObject = $settingsManager->find(["key"=>"apiKey"]);
$apiKey = $apiKeyObject->value;

$lastDynDnsRun = $settingsManager->find(["key" => "lastDynDnsRun"]);
$lastDynDnsRunValue = $lastDynDnsRun ? date('Y-m-d H:i:s', strtotime($lastDynDnsRun->value)) : 'Never';
?>
{% extends 'templates/dashboard.j2' %}
{% set active_page = 'settings' %}
{% block main %}
<div class="dashboard-title">
  <i class="ti ti-settings"></i><h1>Settings</h1>
</div>

<div class="group-box" data-title="Account">
  <button id="changePwdBtn" title="Change password"><i class="ti ti-password-user"></i>Change Password</button>
</div>

<div class="group-box" data-title="Appearance">
  <label class="theme-toggle">
    <input type="checkbox" id="themeSwitcher">
    <span class="slider"></span>
    <span class="label-text">Dark Mode</span>
  </label>
</div>

<!-- Password Change Modal -->
<div id="pwdModal" class="modal">
  <div class="modal-content">
    <h3>Change Password</h3>
    <input type="password" id="newPwd1" placeholder="New Password" />
    <input type="password" id="newPwd2" placeholder="Repeat Password" />
    <div class="modal-actions">
      <button id="cancelPwd" class="secondary">Cancel</button>
      <button id="okPwd">OK</button>
    </div>
    <p id="pwdError" class="error-text"></p>
  </div>
</div>

<div class="group-box" data-title="API Key">
  <button id="regenApiKeyBtn" title="Regenerate API Key"><i class="ti ti-key"></i>Regenerate API Key</button>
</div>

<!-- API Key Modal -->
<div id="apiKeyModal" class="modal">
  <div class="modal-content">
    <h3>New API Key</h3>
    <p>Copy this API key now. You won't be able to see it again!</p>
    <input type="text" id="newApiKey" readonly />
    <div class="modal-actions">
      <button id="closeApiKeyModal" class="secondary">Close</button>
      <button id="copyApiKey">Copy</button>
    </div>
  </div>
</div>

<div class="group-box" data-title="Info">
  <div class="info-buttons">
    <button onclick="window.location.href='php-info.php'" title="View phpinfo().">
      <i class="ti ti-info-circle"></i>PHP Info
    </button>

    <button id="checkUpdatesBtn" title="Check for updates">
      <i class="ti ti-refresh"></i>Check for Updates
    </button>
  </div>
  <dl class="app-info-dl">
    <dt>Database Size</dt><dd><?= getFileSize(DB) ?></dd>
    <dt>Last updater run</dt><dd><?= htmlspecialchars($lastDynDnsRunValue) ?></dd>
    <dt>Total Domains</dt><dd><?= htmlspecialchars($totalDomains) ?></dd>
  </dl>

  <div class="app-info-inline">
    <span>
      <strong>Build:</strong> {{ cfg.build_timestamp }}
      <i class="ti ti-copy copy-icon" data-copy="{{ cfg.build_timestamp }}" title="Copy Build"></i>
    </span>
    <span class="separator">•</span>

    <span>
      <strong>Environment:</strong> {{ cfg.env }}
      <i class="ti ti-copy copy-icon" data-copy="{{ cfg.env }}" title="Copy Environment"></i>
    </span>
    <span class="separator">•</span>

    <span>
      <strong>Version:</strong> {{ cfg.version }}
      <i class="ti ti-copy copy-icon" data-copy="{{ cfg.version }}" title="Copy Version"></i>
    </span>

    <div class="links">
      <a href="https://github.com/TRC-Loop/CronDNS/wiki" target="_blank">Documentation<i class="ti ti-external-link"></i></a>
      <span class="separator">·</span>
      <a href="https://github.com/TRC-Loop/CronDNS" target="_blank">GitHub<i class="ti ti-external-link"></i></a>
    </div>
  </div>
</div>


<script>
// --- Dark Mode Toggle (Settings Page) ---
const themeCheckbox = document.getElementById('themeSwitcher');
const root = document.documentElement;
const themeKey = 'theme';

// Apply stored theme on load
const savedTheme = localStorage.getItem(themeKey);
if (savedTheme === 'dark') {
  root.classList.add('dark');
  themeCheckbox.checked = true;
}

// Handle toggle changes
themeCheckbox.addEventListener('change', () => {
  const isDark = themeCheckbox.checked;
  const mode = isDark ? 'dark' : 'light';
  root.classList.toggle('dark', isDark);
  localStorage.setItem(themeKey, mode);

  // Broadcast to all other pages (listened to in base template)
  window.dispatchEvent(new CustomEvent('theme-toggle', { detail: mode }));
});
// Copy icon logic
document.querySelectorAll('.copy-icon').forEach(icon => {
  icon.addEventListener('click', () => {
    const text = icon.getAttribute('data-copy');
    navigator.clipboard.writeText(text).then(() => {
      const originalClass = icon.className;
      icon.className = 'ti ti-copy-check copy-icon';
      const originalTitle = icon.title;
      icon.title = 'Copied!';
      setTimeout(() => {
        icon.className = originalClass;
        icon.title = originalTitle;
      }, 1000);
    }).catch(err => console.error('Failed to copy:', err));
  });
});

// Password change modal
const changeBtn = document.getElementById('changePwdBtn');
const pwdModal = document.getElementById('pwdModal');
const cancelBtn = document.getElementById('cancelPwd');
const okBtn = document.getElementById('okPwd');
const pwd1 = document.getElementById('newPwd1');
const pwd2 = document.getElementById('newPwd2');
const pwdError = document.getElementById('pwdError');

changeBtn.addEventListener('click', () => {
  pwd1.value = '';
  pwd2.value = '';
  pwdError.style.display = 'none';
  pwdModal.style.display = 'flex';
  pwd1.focus();
});

cancelBtn.addEventListener('click', () => {
  pwdModal.style.display = 'none';
});

okBtn.addEventListener('click', () => {
  const p1 = pwd1.value.trim();
  const p2 = pwd2.value.trim();

  if (p1.length < 8) {
    pwdError.textContent = 'Password must be at least 8 characters long.';
    pwdError.style.display = 'block';
    return;
  }

  if (p1 !== p2) {
    pwdError.textContent = 'Passwords do not match.';
    pwdError.style.display = 'block';
    return;
  }

  pwdError.style.display = 'none';

  fetch('/api/change-password.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ password: p1 })
  })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        alert('Password changed successfully!');
        pwdModal.style.display = 'none';
      } else {
        pwdError.textContent = data.error || 'Failed to change password.';
        pwdError.style.display = 'block';
      }
    })
    .catch(err => {
      console.error(err);
      pwdError.textContent = 'An error occurred.';
      pwdError.style.display = 'block';
    });
});

// API key regeneration logic
const regenBtn = document.getElementById('regenApiKeyBtn');
const apiKeyModal = document.getElementById('apiKeyModal');
const newApiKeyInput = document.getElementById('newApiKey');
const closeApiKeyBtn = document.getElementById('closeApiKeyModal');
const copyApiKeyBtn = document.getElementById('copyApiKey');

regenBtn.addEventListener('click', () => {
  regenBtn.disabled = true;
  regenBtn.textContent = 'Regenerating...';

  fetch(`/api/regen-apikey.php?oldKey=<?= urlencode($apiKey) ?>`)
    .then(res => res.json())
    .then(data => {
      regenBtn.disabled = false;
      regenBtn.innerHTML = '<i class="ti ti-key"></i>Regenerate API Key';

      if (data.ok && data.newKey) {
        // Store the new key in sessionStorage before reload
        sessionStorage.setItem('newApiKey', data.newKey);
        location.reload(); // reload page to update PHP $apiKey
      } else {
        alert(data.error || 'Failed to regenerate API key.');
      }
    })
    .catch(err => {
      regenBtn.disabled = false;
      regenBtn.innerHTML = '<i class="ti ti-key"></i>Regenerate API Key';
      console.error(err);
      alert('An error occurred while regenerating API key.');
    });
});

// Show modal after reload if new API key exists in sessionStorage
window.addEventListener('DOMContentLoaded', () => {
  const storedKey = sessionStorage.getItem('newApiKey');
  if (storedKey) {
    newApiKeyInput.value = storedKey;
    apiKeyModal.style.display = 'flex';
    sessionStorage.removeItem('newApiKey'); // clear it so it only shows once
  }
});

closeApiKeyBtn.addEventListener('click', () => {
  apiKeyModal.style.display = 'none';
  newApiKeyInput.value = '';
});

copyApiKeyBtn.addEventListener('click', () => {
  navigator.clipboard.writeText(newApiKeyInput.value).then(() => {
    copyApiKeyBtn.textContent = 'Copied!';
    setTimeout(() => copyApiKeyBtn.textContent = 'Copy', 1000);
  }).catch(err => console.error('Failed to copy:', err));
});
</script>
{% endblock %}
