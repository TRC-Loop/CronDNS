{% extends 'templates/dashboard.j2' %}
{% set active_page = 'domains' %}
{% block main %}
<?php
require_once __DIR__."/../lib/utils.php";
require_once __DIR__."/../conf/config.php";
require_once __DIR__."/../lib/domain.php";

$domainManager = new PersistentEntityManager(Domain::class, $logger, DB, 'domains');
$domains = $domainManager->list([], ['domain' => 'ASC']);

// Handle form submission inline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'createDomain') {
    $domain = trim($_POST['domain'] ?? '');
    $provider = trim($_POST['provider'] ?? '');
    $credentials = $_POST['credentials'] ?? [];

    $success = false;
    $error = '';

    if (!$domain || !$provider) {
        $error = 'Domain and provider are required.';
    } else {
      $domainManager = new PersistentEntityManager(Domain::class, $logger, DB, 'domains');
      $domainKeyObject = $domainManager->find(["domain"=>$domain]);
      if (empty($domainKeyObject)) {
        $domainKey = new Domain();
        $domainKey->domain = $domain;
        $domainKey->provider = $provider;
        $domainKey->credentials = json_decode($credentials, true); 
        $domainManager->save($domainKey);
      } else { $error = "Domain: $domain already exists"; }
    }
}
?>
<div class="dashboard-title">
  <i class="ti ti-world"></i>
  <h1>Domains</h1>
</div>

<div class="table-container">
  <div class="table-controls">
    <div class="search-wrapper full-width">
      <i class="ti ti-search"></i>
      <input type="text" id="searchInput" placeholder="Search domains...">
    </div>
    <button id="createDomainBtn" class="create-btn">
      <i class="ti ti-plus"></i> Add Domain
    </button>
  </div>

  <table id="domainTable">
    <thead>
      <tr>
        <th data-sort="domain">
          <i class="ti ti-world"></i> Domain
          <i class="ti ti-arrows-sort sort-icon"></i>
        </th>
        <th><i class="ti ti-building"></i> Provider</th>
        <th><i class="ti ti-server"></i> Current IP</th>
        <th><i class="ti ti-settings"></i> Actions</th>
      </tr>
    </thead>

    <tbody>
      <?php foreach ($domains as $d): ?>
      <tr>
        <td><?= htmlspecialchars($d->domain) ?></td>
        <td><?= htmlspecialchars($d->provider) ?></td>
        <td>
          <?php
          // Try to resolve the current IP dynamically
          $ip = gethostbyname($d->domain);
          echo htmlspecialchars($ip);
          ?>
        </td>
        <td class="actions">
          <button class="small secondary"><i class="ti ti-eye"></i> Show</button>
          <button class="small"><i class="ti ti-edit"></i> Edit</button>
          <button class="small danger"><i class="ti ti-trash"></i> Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div id="createDomainModal" class="modal">
  <div class="modal-content">
    <h3>Add DynDNS Record</h3>

    <label for="domainName">Domain Name</label>
    <input type="text" id="domainName" placeholder="subdomain.example.com">

    <label for="providerSelect">Provider</label>
    <select id="providerSelect">
      <option value="">Select Provider</option>
      <option value="strato">Strato</option>
      <option value="namecheap">Namecheap</option>
      <option value="cloudflare">Cloudflare</option>
    </select>

    <div id="providerFields"></div>

    <div class="modal-actions">
      <button id="cancelCreateDomain" class="secondary">Cancel</button>
      <button id="okCreateDomain">Create</button>
    </div>
    <p id="createDomainError" class="error-text"></p>
  </div>
</div>
<form id="inlineCreateForm" method="POST" style="display:none;">
  <input type="hidden" name="action" value="createDomain">
  <input type="hidden" name="domain" id="formDomain">
  <input type="hidden" name="provider" id="formProvider">
  <input type="hidden" name="credentials" id="formCredentials">
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('domainTable');
  const searchInput = document.getElementById('searchInput');
  const sortIcon = document.querySelector('.sort-icon');
  let ascending = true;

  // --- Sorting by Domain ---
  document.querySelector('th[data-sort="domain"]').addEventListener('click', () => {
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const sorted = rows.sort((a, b) => {
      const aText = a.children[0].textContent.trim().toLowerCase();
      const bText = b.children[0].textContent.trim().toLowerCase();
      return ascending ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });
    ascending = !ascending;
    sortIcon.className = ascending ? 'ti ti-sort-ascending sort-icon' : 'ti ti-sort-descending sort-icon';
    table.querySelector('tbody').innerHTML = '';
    sorted.forEach(row => table.querySelector('tbody').appendChild(row));
  });

  // --- Search filter ---
  searchInput.addEventListener('input', e => {
    const value = e.target.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const createBtn = document.getElementById('createDomainBtn');
  const modal = document.getElementById('createDomainModal');
  const cancelBtn = document.getElementById('cancelCreateDomain');
  const okBtn = document.getElementById('okCreateDomain');
  const providerSelect = document.getElementById('providerSelect');
  const providerFields = document.getElementById('providerFields');
  const errorText = document.getElementById('createDomainError');

  const form = document.getElementById('inlineCreateForm');
  const formDomain = document.getElementById('formDomain');
  const formProvider = document.getElementById('formProvider');
  const formCredentials = document.getElementById('formCredentials');

  const providerFieldMap = {
    strato: [
      { label: 'Username', id: 'stratoUser', type: 'text' },
      { label: 'Password', id: 'stratoPass', type: 'password' }
    ],
    namecheap: [
      { label: 'API Key', id: 'namecheapApiKey', type: 'text' },
      { label: 'API User', id: 'namecheapApiUser', type: 'text' },
      { label: 'Client IP', id: 'namecheapClientIp', type: 'text' }
    ],
    cloudflare: [
      { label: 'API Token', id: 'cfApiToken', type: 'text' },
      { label: 'Zone ID', id: 'cfZoneId', type: 'text' }
    ]
  };

  createBtn.addEventListener('click', () => {
    document.getElementById('domainName').value = '';
    providerSelect.value = '';
    providerFields.innerHTML = '';
    errorText.style.display = 'none';
    modal.style.display = 'flex';
  });

  cancelBtn.addEventListener('click', () => {
    modal.style.display = 'none';
  });

  providerSelect.addEventListener('change', () => {
    providerFields.innerHTML = '';
    const selected = providerSelect.value;
    if (!selected) return;

    providerFieldMap[selected].forEach(field => {
      const label = document.createElement('label');
      label.setAttribute('for', field.id);
      label.textContent = field.label;
      const input = document.createElement('input');
      input.type = field.type;
      input.id = field.id;
      input.placeholder = field.label;
      providerFields.appendChild(label);
      providerFields.appendChild(input);
    });
  });

  okBtn.addEventListener('click', () => {
    errorText.style.display = 'none';
    const domain = document.getElementById('domainName').value.trim();
    const provider = providerSelect.value;

    if (!domain) {
      errorText.textContent = 'Please enter a domain name.';
      errorText.style.display = 'block';
      return;
    }
    if (!provider) {
      errorText.textContent = 'Please select a provider.';
      errorText.style.display = 'block';
      return;
    }

    const credentials = {};
    (providerFieldMap[provider] || []).forEach(field => {
      credentials[field.id] = document.getElementById(field.id).value.trim();
    });

    // Fill hidden form and submit
    formDomain.value = domain;
    formProvider.value = provider;
    formCredentials.value = JSON.stringify(credentials);
    form.submit();
  });

  // Optional: show success/error from PHP after submission
  <?php if(isset($success) && $success): ?>
    alert('Domain created successfully!');
  <?php elseif(isset($error) && $error): ?>
    alert('Error: <?= addslashes($error) ?>');
  <?php endif; ?>
});
</script>
{% endblock %}
