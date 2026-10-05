<!-- css files -->
<link rel="stylesheet" href="{{ asset('/') }}backend/build/cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('/') }}backend/build/cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('/') }}backend/build/cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">

<!-- js files -->
<!-- Datatables Cdn -->
<script src="{{ asset('/') }}backend/build/cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="{{ asset('/') }}backend/build/cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="{{ asset('/') }}backend/build/cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script src="{{ asset('/') }}backend/build/cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="{{ asset('/') }}backend/build/cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="{{ asset('/') }}backend/build/cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script>
    $(document).ready(function () {

        // Brands table with advanced features
        const $table = $("#data-table");

        if (
            $table.length &&
            !$table.data("datatableManual") &&
            !$.fn.DataTable.isDataTable($table[0])
        ) {
            $table.DataTable({
                dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex align-items-center gap-2"f>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
                // buttons: [
                //     { extend: "copy", className: "btn btn-sm btn-outline-primary" },
                //     { extend: "csv", className: "btn btn-sm btn-outline-primary" },
                //     { extend: "excel", className: "btn btn-sm btn-outline-primary" },
                //     { extend: "pdf", className: "btn btn-sm btn-outline-primary" },
                //     { extend: "print", className: "btn btn-sm btn-outline-primary" }
                // ],
                language: {
                    searchPlaceholder: "Search data...",
                    sSearch: "",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ data",
                    infoEmpty: "No data found",
                    zeroRecords: "No matching records found",
                    paginate: { previous: "<i class='ri-arrow-left-s-line'></i>", next: "<i class='ri-arrow-right-s-line'></i>" }
                },
                // responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[1, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [2, -1] },
                    { searchable: false, targets: [0, 2, -1] }
                ],
                stateSave: true
            });
        }
    })
</script>
