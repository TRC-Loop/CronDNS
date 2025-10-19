{% extends 'templates/dashboard.j2' %}
{% set active_page = 'domains' %}
{% block main %}
<?php
require_once __DIR__ . "/../lib/utils.php";
require_once __DIR__ . "/../conf/config.php";
require_once __DIR__ . "/../lib/domain.php";

$domainManager = new PersistentEntityManager(Domain::class, $logger, DB, 'domains');
$domains = $domainManager->list([], ['domain' => 'ASC']);

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {

    ob_clean();
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'create';

    try {
        switch ($action) {

          case 'updateDomain':
              $domainName = trim($input['domain'] ?? '');
              $credentials = $input['credentials'] ?? [];

              if (!$domainName) {
                  echo json_encode(['success' => false, 'error' => 'Missing domain parameter.']);
                  exit;
              }

              $existing = $domainManager->find(['domain' => $domainName]);
              if (!$existing) {
                  echo json_encode(['success' => false, 'error' => 'Domain not found.']);
                  exit;
              }

              $existing->credentials = $credentials;
              $domainManager->save($existing);

              echo json_encode([
                  'success' => true,
                  'domain' => [
                      'domain' => $existing->domain,
                      'provider' => $existing->provider,
                      'ip' => gethostbyname($existing->domain),
                      'updated' => $existing->Updated
                  ]
              ]);
              exit;

          case 'deleteDomain':
              $domainName = trim($input['domain'] ?? '');
              if (!$domainName) {
                  echo json_encode(['success' => false, 'error' => 'Missing domain parameter.']);
                  exit;
              }

              $existing = $domainManager->find(['domain' => $domainName]);
              if (!$existing) {
                  echo json_encode(['success' => false, 'error' => 'Domain not found.']);
                  exit;
              }

              if ($domainManager->delete($existing)) {
                  echo json_encode(['success' => true]);
              } else {
                  echo json_encode(['success' => false, 'error' => 'Failed to delete domain.']);
              }
              exit;
            case 'createDomain':
                $domain = trim($input['domain'] ?? '');
                $provider = trim($input['provider'] ?? '');
                $credentials = $input['credentials'] ?? [];

                if (!$domain || !$provider) {
                    echo json_encode(['success' => false, 'error' => 'Domain and provider are required.']);
                    exit;
                }

                $existing = $domainManager->find(['domain' => $domain]);
                if ($existing) {
                    echo json_encode(['success' => false, 'error' => "Domain $domain already exists"]);
                    exit;
                }

                $d = new Domain();
                $d->domain = $domain;
                $d->provider = $provider;
                $d->credentials = $credentials;
                $domainManager->save($d);

                echo json_encode([
                    'success' => true,
                    'domain' => [
                        'domain' => $d->domain,
                        'provider' => $d->provider,
                        'ip' => gethostbyname($d->domain)
                    ]
                ]);
                exit;

            case 'getDomainDetails':
                $domainName = trim($input['domain'] ?? '');
                if (!$domainName) {
                    echo json_encode(['success' => false, 'error' => 'Missing domain parameter.']);
                    exit;
                }

                $result = $domainManager->find(['domain' => $domainName]);
                if (!$result) {
                    echo json_encode(['success' => false, 'error' => 'Domain not found.']);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'domain' => [
                        'domain' => $result->domain,
                        'provider' => $result->provider,
                        'credentials' => $result->credentials,
                        'ip' => gethostbyname($result->domain),
                        'created' => $result->Created,
                        'updated' => $result->Updated
                    ]
                ]);
                exit;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action.']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        exit;
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
      <?php if (!empty($domains)): ?>
        <?php foreach ($domains as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d->domain) ?></td>
            <td><?= htmlspecialchars($d->provider) ?></td>
            <?php
            $ip = gethostbyname($d->domain);
            $resolved = $ip === $d->domain ? null : $ip;
            ?>
            <td title="<?= $resolved ? '' : 'Domain could not be resolved' ?>">
                <?= $resolved ?? 'N/A' ?>
            </td>
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
<div id="showDomainModal" class="modal">
  <div class="modal-content large">
    <h3>Domain Details</h3>
    <div id="showDomainDetails" class="domain-details"></div>

    <div class="modal-actions">
      <button id="closeShowDomain">Close</button>
    </div>
  </div>
</div>
<div id="editDomainModal" class="modal">
  <div class="modal-content large">
    <h3>Edit Domain</h3>
    <label for="editDomainName">Domain</label>
    <input type="text" id="editDomainName" readonly>

    <label for="editProvider">Provider</label>
    <input type="text" id="editProvider" readonly>

    <div id="editCredentialsContainer" class="credentials-group"></div>

    <div class="modal-actions">
      <button id="cancelEditDomain" class="secondary">Cancel</button>
      <button id="saveEditDomain">Save Changes</button>
    </div>
    <p id="editDomainError" class="error-text"></p>
  </div>
</div>
<div id="deleteDomainModal" class="modal">
  <div class="modal-content small">
    <h3>Confirm Deletion</h3>
    <p>Are you sure you want to delete <strong id="deleteDomainName"></strong>?</p>

    <div class="modal-actions">
      <button id="cancelDeleteDomain" class="secondary">Cancel</button>
      <button id="confirmDeleteDomain" class="danger">Delete</button>
    </div>
    <p id="deleteDomainError" class="error-text"></p>
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
    strato: [
      { label: 'Username', id: 'stratoUser', type: 'text' },
      { label: 'Password', id: 'stratoPass', type: 'password' }
    ],
    namecheap: [
      { label: 'Dynamic DNS Password', id: 'namecheapPassword', type: 'password' }
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
    fetch(window.location.pathname, {
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

    const ip = d.ip && d.ip !== d.domain ? d.ip : 'N/A';
    const ipTitle = ip === 'N/A' ? 'Domain could not be resolved' : '';
    row.innerHTML = `
<td>${d.domain}</td>
<td>${d.provider}</td>
<td title="${ipTitle}">${ip}</td>
<td class="actions">
  <button class="small secondary"><i class="ti ti-eye"></i> Show</button>
  <button class="small"><i class="ti ti-edit"></i> Edit</button>
  <button class="small danger"><i class="ti ti-trash"></i> Delete</button>
</td>`;
    tbody.appendChild(row);
  }
  
// Handle "Show" button click
table.addEventListener('click', e => {
  if (!e.target.closest('button')) return;
  const btn = e.target.closest('button');
  if (!btn.textContent.includes('Show')) return;

  const domain = btn.closest('tr').children[0].textContent.trim();
  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getDomainDetails', domain })
  })
  .then(res => res.json())
  .then(data => {
    if (!data.success) return alert(data.error || 'Failed to load domain info.');

    const d = data.domain;
    const creds = Object.entries(d.credentials || {})
      .map(([k,v]) => `
        <div class="cred-row">
          <dt>${k}</dt>
          <dd>
            <input type="password" value="${v}" readonly>
            <i class="ti ti-eye toggle-cred"></i>
          </dd>
        </div>
      `).join('');

    document.getElementById('showDomainDetails').innerHTML = `
      <dl class="app-info-dl">
        <dt>Domain</dt><dd>${d.domain}</dd>
        <dt>Provider</dt><dd>${d.provider}</dd>
        <dt>IP</dt><dd>${d.ip}</dd>
        <dt>Created</dt><dd>${d.created}</dd>
        <dt>Updated</dt><dd>${d.updated}</dd>
      </dl>
      <h4>Credentials</h4>
      <div class="credentials">${creds || '<em>No credentials stored.</em>'}</div>
    `;

    document.getElementById('showDomainModal').style.display = 'flex';
  })
  .catch(console.error);
});

document.getElementById('closeShowDomain').addEventListener('click', () => {
  document.getElementById('showDomainModal').style.display = 'none';
});

// Toggle credential visibility
document.addEventListener('click', e => {
  if (e.target.classList.contains('toggle-cred')) {
    const input = e.target.previousElementSibling;
    input.type = input.type === 'password' ? 'text' : 'password';
    e.target.classList.toggle('ti-eye');
    e.target.classList.toggle('ti-eye-off');
  }
});
table.addEventListener('click', e => {
  if (!e.target.closest('button')) return;
  const btn = e.target.closest('button');
  if (!btn.textContent.includes('Edit')) return;

  const domain = btn.closest('tr').children[0].textContent.trim();

  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getDomainDetails', domain })
  })
  .then(res => res.json())
  .then(data => {
    if (!data.success) return alert(data.error || 'Failed to load domain info.');

    const d = data.domain;
    document.getElementById('editDomainName').value = d.domain;
    document.getElementById('editProvider').value = d.provider;

    const container = document.getElementById('editCredentialsContainer');
    container.innerHTML = '';

    Object.entries(d.credentials || {}).forEach(([key, val]) => {
      const label = document.createElement('label');
      label.textContent = key;
      const input = document.createElement('input');
      input.type = 'text';
      input.value = val;
      input.dataset.key = key;
      container.appendChild(label);
      container.appendChild(input);
    });

    document.getElementById('editDomainError').style.display = 'none';
    document.getElementById('editDomainModal').style.display = 'flex';
  })
  .catch(console.error);
});

document.getElementById('cancelEditDomain').addEventListener('click', () => {
  document.getElementById('editDomainModal').style.display = 'none';
});

document.getElementById('saveEditDomain').addEventListener('click', () => {
  const domain = document.getElementById('editDomainName').value.trim();
  const credentials = {};
  document.querySelectorAll('#editCredentialsContainer input').forEach(inp => {
    credentials[inp.dataset.key] = inp.value.trim();
  });

  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'updateDomain', domain, credentials })
  })
  .then(res => res.json())
  .then(data => {
    const err = document.getElementById('editDomainError');
    if (!data.success) {
      err.textContent = data.error;
      err.style.display = 'block';
      return;
    }

    document.getElementById('editDomainModal').style.display = 'none';
    err.style.display = 'none';

    // Optional: update "Updated" info visually or re-fetch row
    alert('Domain updated successfully!');
  })
  .catch(console.error);
});
const deleteModal = document.getElementById('deleteDomainModal');
const deleteDomainNameEl = document.getElementById('deleteDomainName');
const deleteError = document.getElementById('deleteDomainError');
let domainToDelete = null;

table.addEventListener('click', e => {
  const btn = e.target.closest('button');
  if (!btn || !btn.textContent.includes('Delete')) return;

  const row = btn.closest('tr');
  const domain = row.children[0].textContent.trim();
  domainToDelete = row;
  deleteDomainNameEl.textContent = domain;
  deleteError.style.display = 'none';
  deleteModal.style.display = 'flex';
});

document.getElementById('cancelDeleteDomain').addEventListener('click', () => {
  deleteModal.style.display = 'none';
  domainToDelete = null;
});

document.getElementById('confirmDeleteDomain').addEventListener('click', () => {
  if (!domainToDelete) return;

  const domain = deleteDomainNameEl.textContent.trim();
  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'deleteDomain', domain })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      domainToDelete.remove();
      deleteModal.style.display = 'none';

      // Show "no domains" row if table empty
      const tbody = table.querySelector('tbody');
      if (!tbody.querySelector('tr')) {
        const emptyRow = document.createElement('tr');
        emptyRow.dataset.placeholder = true;
        emptyRow.innerHTML = `<td colspan="4" style="text-align:center;">No domains added yet.</td>`;
        tbody.appendChild(emptyRow);
      }
    } else {
      deleteError.textContent = data.error || 'Delete failed.';
      deleteError.style.display = 'block';
    }
  })
  .catch(err => {
    console.error(err);
    deleteError.textContent = 'Unexpected error.';
    deleteError.style.display = 'block';
  });
});
});

</script>
{% endblock %}
