{% extends 'templates/dashboard.j2' %}
{% set active_page = 'home' %}
{% block main %}
<?php
require_once __DIR__ . "/../conf/config.php";
require_once __DIR__ . "/../lib/domain.php";

$domainManager = new PersistentEntityManager(Domain::class, $logger, DB, 'domains');
$totalDomains = $domainManager->getPDO()->query("SELECT COUNT(*) FROM domains")->fetchColumn();

$apiKeyObject = $settingsManager->find(["key" => "apiKey"]);
$apiKey = $apiKeyObject->value ?? '';

$lastDynDnsRun = $settingsManager->find(["key" => "lastDynDnsRun"]);
$lastDynDnsRunValue = $lastDynDnsRun ? date('Y-m-d H:i:s', strtotime($lastDynDnsRun->value)) : 'Never';

$lastErrorsEntry = $settingsManager->find(["key" => "lastUpdateErrors"]);
$failedDomains = ($lastErrorsEntry && !empty($lastErrorsEntry->value)) ? (array)$lastErrorsEntry->value : [];

$logPath = __DIR__ . '/../data/latest.log';
$recentLog = [];
if (file_exists($logPath) && is_readable($logPath)) {
    $file = new SplFileObject($logPath, 'r');
    $file->seek(PHP_INT_MAX);
    $totalLines = $file->key();
    $start = max(0, $totalLines - 20);
    $file->seek($start);
    while (!$file->eof()) {
        $line = trim($file->current());
        if ($line !== '') $recentLog[] = $line;
        $file->next();
    }
    $file = null;
}
?>

<div class="dashboard-title">
  <i class="ti ti-home"></i>
  <h1>Home</h1>
</div>

<?php if (!empty($failedDomains)): ?>
<div class="update-error-notice" id="updateErrorNotice">
  <div class="update-error-notice__body">
    <i class="ti ti-alert-triangle"></i>
    <div>
      <strong>Last update had errors</strong>
      <span>Failed: <?= htmlspecialchars(implode(', ', $failedDomains)) ?></span>
    </div>
  </div>
  <button class="update-error-notice__dismiss" onclick="dismissUpdateError()" title="Dismiss">
    <i class="ti ti-x"></i>
  </button>
</div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"><i class="ti ti-world"></i></div>
    <div class="stat-info">
      <h3><?= htmlspecialchars((string)$totalDomains) ?></h3>
      <p>Total Domains</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon"><i class="ti ti-clock"></i></div>
    <div class="stat-info">
      <h3 id="last-dyndns" style="font-size:1.1rem;"><?= htmlspecialchars($lastDynDnsRunValue) ?></h3>
      <p>Last DynDNS Update</p>
    </div>
  </div>

  <div class="stat-card" id="public-ip-card">
    <div class="stat-icon"><i class="ti ti-network"></i></div>
    <div class="stat-info">
      <h3 id="public-ip">--</h3>
      <p>Client IP</p>
      <small id="last-updated" style="color: var(--placeholder); font-size: 0.8rem;"></small>
    </div>
  </div>

  <div class="stat-card" id="server-public-ip-card">
    <div class="stat-icon"><i class="ti ti-server"></i></div>
    <div class="stat-info">
      <h3 id="server-public-ip">--</h3>
      <p>Server Public IP</p>
      <small id="server-ip-last-updated" style="color: var(--placeholder); font-size: 0.8rem;"></small>
    </div>
  </div>
</div>

<?php if (!empty($recentLog)): ?>
<div class="activity-section">
  <div class="activity-header">
    <h2><i class="ti ti-list"></i> Recent Activity</h2>
    <a href="/domains.php" class="activity-link">Go to Domains <i class="ti ti-arrow-right"></i></a>
  </div>
  <div class="activity-log">
    <?php foreach (array_reverse($recentLog) as $line):
      $cls = 'log-line';
      if (str_contains($line, '[ERROR]') || str_contains($line, '[CRITICAL]')) $cls .= ' log-line-error';
      elseif (str_contains($line, '[WARNING]')) $cls .= ' log-line-warn';
      elseif (str_contains($line, '[INFO]')) $cls .= ' log-line-info';
    ?>
    <div class="<?= $cls ?>"><?= htmlspecialchars($line) ?></div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="activity-section">
  <div class="activity-header">
    <h2><i class="ti ti-list"></i> Recent Activity</h2>
  </div>
  <div class="activity-log activity-log--empty">
    <i class="ti ti-mood-empty"></i>
    <p>No activity yet. Add a domain and run the DynDNS update.</p>
  </div>
</div>
<?php endif; ?>

<script>
const apiKey = <?= json_encode($apiKey) ?>;

function formatTime() {
  const now = new Date();
  return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

async function updatePublicIP() {
  const ipEl = document.getElementById('public-ip');
  const timeEl = document.getElementById('last-updated');
  try {
    const res = await fetch('/api/public-ip.php', { headers: { 'X-API-KEY': apiKey } });
    const data = await res.json();
    if (!res.ok || !data.ok || !data.ipv4) {
      ipEl.textContent = 'Unavailable';
      ipEl.style.color = 'var(--danger)';
    } else {
      ipEl.textContent = data.ipv4;
      ipEl.style.color = '';
    }
    timeEl.textContent = `Checked ${formatTime()}`;
  } catch {
    ipEl.textContent = 'Error';
    ipEl.style.color = 'var(--danger)';
    timeEl.textContent = `Checked ${formatTime()}`;
  }
}

async function updateServerPublicIP() {
  const ipEl = document.getElementById('server-public-ip');
  const timeEl = document.getElementById('server-ip-last-updated');
  try {
    const res = await fetch('/api/server-public-ip.php', { headers: { 'X-API-KEY': apiKey } });
    const data = await res.json();
    if (!res.ok || !data.ok || !data.ipv4) {
      ipEl.textContent = 'Unavailable';
      ipEl.style.color = 'var(--danger)';
    } else {
      ipEl.textContent = data.ipv4;
      ipEl.style.color = '';
    }
    timeEl.textContent = `Checked ${formatTime()}`;
  } catch {
    ipEl.textContent = 'Error';
    ipEl.style.color = 'var(--danger)';
    timeEl.textContent = `Checked ${formatTime()}`;
  }
}

function dismissUpdateError() {
  const notice = document.getElementById('updateErrorNotice');
  if (notice) {
    notice.style.animation = 'fadeOut 0.3s ease forwards';
    setTimeout(() => notice.remove(), 300);
    sessionStorage.setItem('errorNoticeDismissed', '1');
  }
}

if (sessionStorage.getItem('errorNoticeDismissed') === '1') {
  const notice = document.getElementById('updateErrorNotice');
  if (notice) notice.remove();
}

updateServerPublicIP();
setInterval(updateServerPublicIP, 30000);
updatePublicIP();
setInterval(updatePublicIP, 30000);
</script>
{% endblock %}
