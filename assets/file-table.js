// JS pour tri et filtre du tableau des fichiers

document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.file-checkbox');
    const btnDelete = document.getElementById('bulk-delete-btn');
    const btnZip = document.getElementById('bulk-zip-btn');
    function toggleBtns() {
        let checked = false;
        checkboxes.forEach(cb => {
            if (cb.checked) checked = true;
        });
        btnDelete.classList.toggle('hidden', !checked);
        btnZip.classList.toggle('hidden', !checked);
    }
    checkboxes.forEach(cb => {
        cb.addEventListener('change', toggleBtns);
    });
    toggleBtns();

    // Tri et filtre JS sur le tableau
    const table = document.getElementById('file-table');
    const getCellValue = (row, idx) => row.children[idx].innerText.trim();
    function sortTable(idx, type = 'string', asc = true) {
        const rows = Array.from(table.tBodies[0].rows);
        rows.sort((a, b) => {
            let v1 = getCellValue(a, idx);
            let v2 = getCellValue(b, idx);
            if (type === 'number') {
                v1 = parseInt(v1.replace(/\D/g, '')) || 0;
                v2 = parseInt(v2.replace(/\D/g, '')) || 0;
            }
            if (type === 'date') {
                v1 = Date.parse(v1.split(' ')[0] + 'T' + (v1.split(' ')[1] || '00:00'));
                v2 = Date.parse(v2.split(' ')[0] + 'T' + (v2.split(' ')[1] || '00:00'));
            }
            if (v1 < v2) return asc ? -1 : 1;
            if (v1 > v2) return asc ? 1 : -1;
            return 0;
        });
        rows.forEach(row => table.tBodies[0].appendChild(row));
    }
    let sortState = {name: true, date: true, size: true};
    table.querySelectorAll('th[data-sort]').forEach((th, idx) => {
        th.addEventListener('click', function () {
            let type = th.dataset.sort === 'size' ? 'number' : (th.dataset.sort === 'date' ? 'date' : 'string');
            sortTable(idx, type, sortState[th.dataset.sort]);
            sortState[th.dataset.sort] = !sortState[th.dataset.sort];
        });
    });
    // Filtrage
    function filterTable() {
        const nameVal = document.getElementById('filter-name').value.toLowerCase();
        const dateVal = document.getElementById('filter-date').value.toLowerCase();
        Array.from(table.tBodies[0].rows).forEach(row => {
            const name = getCellValue(row, 1).toLowerCase();
            const date = getCellValue(row, 2).toLowerCase();
            row.style.display = (name.includes(nameVal) && date.includes(dateVal)) ? '' : 'none';
        });
    }
    document.getElementById('filter-name').addEventListener('input', filterTable);
    document.getElementById('filter-date').addEventListener('input', filterTable);
});
