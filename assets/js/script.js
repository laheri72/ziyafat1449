// Main JavaScript File

// Show success message
function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: message,
        confirmButtonColor: '#2563eb'
    });
}

// Show error message
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: message,
        confirmButtonColor: '#ef4444'
    });
}

// Confirm delete action
function confirmDelete(message = 'Are you sure you want to delete this?') {
    return Swal.fire({
        title: 'Are you sure?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!'
    });
}

// Format currency
function formatCurrency(amount, currency = 'USD') {
    if (currency === 'USD') {
        return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    } else {
        return '₹' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
}

// Currency converter
function convertCurrency(amount, fromCurrency, toCurrency, exchangeRate) {
    if (fromCurrency === 'USD' && toCurrency === 'INR') {
        return amount * exchangeRate;
    } else if (fromCurrency === 'INR' && toCurrency === 'USD') {
        return amount / exchangeRate;
    }
    return amount;
}

// Auto-convert currency on input
document.addEventListener('DOMContentLoaded', function() {
    const amountUSD = document.getElementById('amount_usd');
    const amountINR = document.getElementById('amount_inr');
    const exchangeRate = 84.6667; // Default exchange rate

    if (amountUSD && amountINR) {
        amountUSD.addEventListener('input', function() {
            const usdValue = parseFloat(this.value) || 0;
            amountINR.value = (usdValue * exchangeRate).toFixed(2);
        });

        amountINR.addEventListener('input', function() {
            const inrValue = parseFloat(this.value) || 0;
            amountUSD.value = (inrValue / exchangeRate).toFixed(2);
        });
    }
});

// Sidebar toggle functionality
function initSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSidebar = document.getElementById('closeSidebar');
    const html = document.documentElement;

    if (menuToggle && sidebar && sidebarOverlay) {
        menuToggle.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                // Mobile: Toggle slide-in
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            } else {
                // Desktop: Toggle collapse
                sidebar.classList.toggle('collapsed');
                html.classList.remove('sidebar-is-collapsed');
                
                if (sidebar.classList.contains('collapsed')) {
                    localStorage.setItem('sidebarState', 'collapsed');
                } else {
                    localStorage.setItem('sidebarState', 'expanded');
                }
            }
        });

        const closeActions = [sidebarOverlay, closeSidebar];
        closeActions.forEach(el => {
            if (el) {
                el.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });
    }
}

// Table search functionality
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (input && table) {
        input.addEventListener('keyup', function() {
            const filter = this.value.toUpperCase();
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                let found = false;
                const cells = rows[i].getElementsByTagName('td');
                
                for (let j = 0; j < cells.length; j++) {
                    const cell = cells[j];
                    if (cell) {
                        const textValue = cell.textContent || cell.innerText;
                        if (textValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        });
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    searchTable('searchInput', 'dataTable');
});

// Export table to CSV
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');

        for (let j = 0; j < cols.length - 1; j++) { // Exclude last column (actions)
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }

        csv.push(row.join(','));
    }

    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Print functionality
function printPage() {
    window.print();
}