<?php
// admin/includes/footer.php
?>
        </div> <!-- Close page-content-wrapper -->
    </div> <!-- Close wrapper -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('.data-table').DataTable({
                pageLength: 10,
                order: [[0, 'asc']],
                columnDefs: [
                    {
                        targets: 0,
                        type: 'num',
                        render: function(data, type, row) {
                            var num = data.toString().replace(/^#/, '');
                            return (type === 'display' || type === 'filter') ? '#' + num : num;
                        }
                    }
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
                }
            });
        });

        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>