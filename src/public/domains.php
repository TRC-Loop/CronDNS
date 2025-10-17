{% extends 'templates/dashboard.j2' %}
{% set active_page = 'domains' %}
{% block main %}
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
      <tr>
        <td>example.com</td>
        <td>Cloudflare</td>
        <td>104.21.47.123</td>
        <td class="actions">
          <button class="small secondary"><i class="ti ti-eye"></i> Show</button>
          <button class="small"><i class="ti ti-edit"></i> Edit</button>
          <button class="small danger"><i class="ti ti-trash"></i> Delete</button>
        </td>
      </tr>
      <tr>
        <td>mycompany.net</td>
        <td>GoDaddy</td>
        <td>35.186.234.22</td>
        <td class="actions">
          <button class="small secondary"><i class="ti ti-eye"></i> Show</button>
          <button class="small"><i class="ti ti-edit"></i> Edit</button>
          <button class="small danger"><i class="ti ti-trash"></i> Delete</button>
        </td>
      </tr>
      <tr>
        <td>internal.dev</td>
        <td>Self-hosted</td>
        <td>192.168.1.5</td>
        <td class="actions">
          <button class="small secondary"><i class="ti ti-eye"></i> Show</button>
          <button class="small"><i class="ti ti-edit"></i> Edit</button>
          <button class="small danger"><i class="ti ti-trash"></i> Delete</button>
        </td>
      </tr>
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
  </div>
</div>

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

  // Provider-specific fields
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
    const domain = document.getElementById('domainName').value.trim();
    const provider = providerSelect.value;

    if (!domain) {
      alert('Please enter a domain name.');
      return;
    }
    if (!provider) {
      alert('Please select a provider.');
      return;
    }

    const credentials = {};
    (providerFieldMap[provider] || []).forEach(field => {
      credentials[field.id] = document.getElementById(field.id).value.trim();
    });

    // Placeholder for your create logic
    console.log('Creating domain', domain, provider, credentials);

    modal.style.display = 'none';
  });
});
</script>
{% endblock %}
