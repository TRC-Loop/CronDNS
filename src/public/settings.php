<?php
require_once __DIR__."/../lib/utils.php";
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

<div class="group-box" data-title="Info">
  <button onclick="window.location.href='php-info.php'" title="View phpinfo().">
    <i class="ti ti-info-circle"></i>PHP Info
  </button>

  <dl class="app-info-dl">
    <dt>Database Size</dt><dd><?= getFileSize(DB) ?></dd>
    <dt>Last updater run</dt><dd>5h ago</dd>
    <dt>Total Domains</dt><dd>0</dd>
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
</div>
</div>

<script>
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

    // send to server via fetch
    fetch('/change-password.php', {
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
</script>
{% endblock %}
