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
?>

<div class="dashboard-title">
  <i class="ti ti-home"></i>
  <h1>Home</h1>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"><i class="ti ti-world"></i></div>
    <div class="stat-info">
      <h3><?= htmlspecialchars($totalDomains) ?></h3>
      <p>Total Domains</p>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon"><i class="ti ti-clock"></i></div>
    <div class="stat-info">
      <h3 id="last-dyndns"><?= htmlspecialchars($lastDynDnsRunValue) ?></h3>
      <p>Last DynDNS Update</p>
    </div>
  </div>

  <div class="stat-card" id="public-ip-card">
    <div class="stat-icon"><i class="ti ti-network"></i></div>
    <div class="stat-info">
      <h3 id="public-ip">Loading...</h3>
      <p>Current Public IP</p>
      <small id="last-updated" style="color: var(--placeholder); font-size: 0.8rem;"></small>
    </div>
  </div>

  <div class="stat-card" id="server-public-ip-card">
    <div class="stat-icon"><i class="ti ti-server"></i></div>
    <div class="stat-info">
      <h3 id="server-public-ip">Loading...</h3>
      <p>Server Public IP</p>
      <small id="server-ip-last-updated" style="color: var(--placeholder); font-size: 0.8rem;"></small>
    </div>
  </div>

</div>

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
      ipEl.style.color = 'var(--success)';
    }
    timeEl.textContent = `Last checked: ${formatTime()}`;
  } catch {
    ipEl.textContent = 'Error';
    ipEl.style.color = 'var(--danger)';
    timeEl.textContent = `Last checked: ${formatTime()}`;
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
      ipEl.style.color = 'var(--success)';
    }
    timeEl.textContent = `Last checked: ${formatTime()}`;
  } catch {
    ipEl.textContent = 'Error';
    ipEl.style.color = 'var(--danger)';
    timeEl.textContent = `Last checked: ${formatTime()}`;
  }
}
updateServerPublicIP();
setInterval(updateServerPublicIP, 10000);
updatePublicIP();
setInterval(updatePublicIP, 10000);
</script>
{% endblock %}
