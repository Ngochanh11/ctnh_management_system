    </main>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (optional, for legacy support) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // Auto dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Confirm delete actions
        document.querySelectorAll('[data-confirm-delete]').forEach(element => {
            element.addEventListener('click', function(e) {
                if (!confirm('Bạn có chắc chắn muốn xóa? Hành động này không thể hoàn tác!')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        
        // Number formatting
        function formatNumber(num, decimals = 0) {
            return new Intl.NumberFormat('vi-VN', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(num);
        }
        
        // Date formatting
        function formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('vi-VN');
        }
        
        // Loading overlay
        function showLoading() {
            const overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;';
            overlay.innerHTML = '<div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>';
            document.body.appendChild(overlay);
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.remove();
        }
        
        // AJAX helper
        async function fetchAPI(url, options = {}) {
            try {
                showLoading();
                const response = await fetch(url, {
                    headers: Object.assign({
                        'Content-Type': 'application/json'
                    }, options.headers || {}),
                });
                
                const data = await response.json();
                hideLoading();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Có lỗi xảy ra');
                }
                
                return data;
            } catch (error) {
                hideLoading();
                alert('Lỗi: ' + error.message);
                throw error;
            }
        }
        
        // Print helper
        function printDocument(documentId) {
            window.open('<?php echo BASE_URL; ?>/modules/print.php?id=' + documentId, '_blank', 'width=800,height=600');
        }
        
        // Export helper
        function exportTableToCsv(tableId, filename = 'bao-cao.csv') {
            const table = document.getElementById(tableId);
            if (!table) {
                alert('Không tìm thấy bảng cần xuất.');
                return;
            }
            const csv = Array.from(table.rows, row => Array.from(row.cells, cell => {
                let value = cell.innerText.trim().replace(/\s+/g, ' ');
                if (/^[=+@]/.test(value) || /^-\d/.test(value)) value = `'${value}`;
                return `"${value.replace(/"/g, '""')}"`;
            }).join(',')).join('\r\n');
            const blob = new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
            URL.revokeObjectURL(url);
        }
    </script>
    
    <!-- Custom scripts for specific pages -->
    <?php if (isset($customJS)): ?>
        <?php echo $customJS; ?>
    <?php endif; ?>
    
    <script src="<?php echo APP_ROOT_URL; ?>/js/utils.js"></script>
</body>
</html>
