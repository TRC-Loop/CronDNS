{% extends 'templates/dashboard.j2' %}
{% set active_page = 'domains' %}
{% block main %}
<?php
require_once __DIR__."/../lib/utils.php";
require_once __DIR__."/../conf/config.php";
require_once __DIR__."/../lib/domain.php";

$domainManager = new PersistentEntityManager(Domain::class, $logger, DB, 'domains');
$domains = $domainManager->list([], ['domain' => 'ASC']);

// Handle JSON AJAX POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {

    ob_clean(); // clear any previous output
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $domain = trim($input['domain'] ?? '');
    $provider = trim($input['provider'] ?? '');
    $credentials = $input['credentials'] ?? [];

    $success = false;
    $error = '';
    $newDomain = null;

    try {
        if (!$domain || !$provider) {
            $error = 'Domain and provider are required.';
        } else {
            $existing = $domainManager->find(['domain'=>$domain]);
            if (!$existing) {
                $d = new Domain();
                $d->domain = $domain;
                $d->provider = $provider;
                $d->credentials = $credentials;
                $domainManager->save($d);
                $success = true;
                $newDomain = [
                    'domain' => $d->domain,
                    'provider' => $d->provider,
                    'ip' => gethostbyname($d->domain)
                ];
            } else {
                $error = "Domain $domain already exists";
            }
        }
        echo json_encode(['success'=>$success,'error'=>$error,'domain'=>$newDomain]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage()]);
    }
    exit;
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
      <?php if (!empty($domains)): ?>
        <?php foreach ($domains as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d->domain) ?></td>
            <td><?= htmlspecialchars($d->provider) ?></td>
            <td><?= htmlspecialchars(@gethostbyname($d->domain)) ?></td>
            <td class="actions">
              <button class="small secondary"><i class="ti ti-eye"></i> Show</button>
              <button class="small"><i class="ti ti-edit"></i> Edit</button>
              <button class="small danger"><i class="ti ti-trash"></i> Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>

      <tr data-placeholder>
        <td colspan="4" style="text-align:center;">No domains added yet.</td>
      </tr>
      <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('domainTable');
  const searchInput = document.getElementById('searchInput');
  const sortIcon = document.querySelector('.sort-icon');
  let ascending = true;

  // Sorting
  document.querySelector('th[data-sort="domain"]').addEventListener('click', () => {
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const sorted = rows.sort((a,b) => {
      const aText = a.children[0].textContent.trim().toLowerCase();
      const bText = b.children[0].textContent.trim().toLowerCase();
      return ascending ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });
    ascending = !ascending;
    sortIcon.className = ascending ? 'ti ti-sort-ascending sort-icon' : 'ti ti-sort-descending sort-icon';
    table.querySelector('tbody').innerHTML = '';
    sorted.forEach(r => table.querySelector('tbody').appendChild(r));
  });

  // Search filter
  searchInput.addEventListener('input', e => {
    const value = e.target.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
    });
  });

  const createBtn = document.getElementById('createDomainBtn');
  const modal = document.getElementById('createDomainModal');
  const cancelBtn = document.getElementById('cancelCreateDomain');
  const okBtn = document.getElementById('okCreateDomain');
  const providerSelect = document.getElementById('providerSelect');
  const providerFields = document.getElementById('providerFields');
  const errorText = document.getElementById('createDomainError');

  const providerFieldMap = {
    strato: [{ label: 'Username', id: 'stratoUser', type: 'text' },
             { label: 'Password', id: 'stratoPass', type: 'password' }],
    namecheap: [{ label: 'API Key', id: 'namecheapApiKey', type: 'text' },
                { label: 'API User', id: 'namecheapApiUser', type: 'text' },
                { label: 'Client IP', id: 'namecheapClientIp', type: 'text' }],
    cloudflare: [{ label: 'API Token', id: 'cfApiToken', type: 'text' },
                 { label: 'Zone ID', id: 'cfZoneId', type: 'text' }]
  };

  createBtn.addEventListener('click', () => {
    document.getElementById('domainName').value = '';
    providerSelect.value = '';
    providerFields.innerHTML = '';
    errorText.style.display = 'none';
    modal.style.display = 'flex';
  });

  cancelBtn.addEventListener('click', () => modal.style.display = 'none');

  providerSelect.addEventListener('change', () => {
    providerFields.innerHTML = '';
    const selected = providerSelect.value;
    if (!selected) return;
    providerFieldMap[selected].forEach(f => {
      const label = document.createElement('label');
      label.setAttribute('for', f.id);
      label.textContent = f.label;
      const input = document.createElement('input');
      input.type = f.type;
      input.id = f.id;
      input.placeholder = f.label;
      providerFields.appendChild(label);
      providerFields.appendChild(input);
    });
  });

  okBtn.addEventListener('click', () => {
    errorText.style.display = 'none';
    const domain = document.getElementById('domainName').value.trim();
    const provider = providerSelect.value;
    if (!domain || !provider) {
      errorText.textContent = !domain ? 'Enter a domain.' : 'Select a provider.';
      errorText.style.display = 'block';
      return;
    }

    const credentials = {};
    (providerFieldMap[provider] || []).forEach(f => {
      credentials[f.id] = document.getElementById(f.id).value.trim();
    });

    // AJAX POST
    fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'createDomain', domain, provider, credentials })
    })
    .then(res => res.json())
    .then(data => {
      if(data.success){
        modal.style.display = 'none';
        appendDomainRow(data.domain);
      } else {
        errorText.textContent = data.error;
        errorText.style.display = 'block';
      }
    })
    .catch(err => {
      errorText.textContent = 'Unexpected error.';
      errorText.style.display = 'block';
      console.error(err);
    });
  });


function appendDomainRow(d) {
  const tbody = table.querySelector('tbody');

  const placeholder = tbody.querySelector('tr[data-placeholder]');
  if (placeholder) placeholder.remove();

  const row = document.createElement('tr');
  row.innerHTML = `
    <td>${d.domain}</td>
    <td>${d.provider}</td>
    <td>${d.ip}</td>
    <td class="actions">
      <button class="small secondary"><i class="ti ti-eye"></i> Show</button>
      <button class="small"><i class="ti ti-edit"></i> Edit</button>
      <button class="small danger"><i class="ti ti-trash"></i> Delete</button>
    </td>`;
  tbody.appendChild(row);
}
  
});
</script>
{% endblock %}
