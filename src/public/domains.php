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
      <i class="ti ti-plus"></i> Create Domain
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

  // --- Create button handler (stub for now) ---
  document.getElementById('createDomainBtn').addEventListener('click', () => {
    alert('Create Domain action coming soon!');
  });
});
</script>
{% endblock %}
