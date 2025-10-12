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
  <button title="Change password"><i class="ti ti-password-user"></i>Change Password</button>
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
        // Swap icon to check icon
        const originalClass = icon.className;
        icon.className = 'ti ti-copy-check copy-icon';

        // Optional: temporarily update tooltip
        const originalTitle = icon.title;
        icon.title = 'Copied!';

        // Revert back after 1 second
        setTimeout(() => {
          icon.className = originalClass;
          icon.title = originalTitle;
        }, 1000);

      }).catch(err => console.error('Failed to copy:', err));
    });
  });
</script>
{% endblock %}

