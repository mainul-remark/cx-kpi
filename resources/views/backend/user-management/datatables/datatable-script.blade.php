<style>
    /* Bottom info + pagination same line */
    .dt-container .dt-info,
    .dt-container .dt-paging {
        display: inline-flex;
        align-items: center;
    }
    /* Wrap both in one row */
    .dt-container {
        position: relative;
    }

    /* Info (Showing...) left */
    .dt-container .dt-info {
        float: left;
    }

    /* Pagination right */
    .dt-container .dt-paging {
        float: right;
    }


    .dt-buttons .btn-group .flex-wrap .gap-2 { float: left }
    .dt-search {float: right}
    .dt-length {margin-left: 10px!important; float: left}

    /* Mobile fix for dt-search and dt-length (100px to 488px) */
    @media (min-width: 100px) and (max-width: 488px) {
        .dt-length,
        .dt-search {
            float: left !important;
            width: 50% !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }
        .dt-length select,
        .dt-search input {
            width: auto !important;
        }
        /*.dt-length {margin-left: 5px!important;}*/
    }
</style>
<!-- DataTables Core -->
<script src="{{asset('backend/build/bs5_datatable/js/dataTables.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/dataTables.bootstrap5.min.js')}}"></script>


<script src="{{asset('backend/build/bs5_datatable/js/dataTables.responsive.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/responsive.bootstrap5.min.js')}}"></script>

<script src="{{asset('backend/build/bs5_datatable/js/dataTables.buttons.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/buttons.bootstrap5.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/buttons.html5.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/buttons.print.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/buttons.colVis.min.js')}}"></script>

<script src="{{asset('backend/build/bs5_datatable/js/jszip.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/pdfmake.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/vfs_fonts.js')}}"></script>

<script src="{{asset('backend/build/bs5_datatable/js/dataTables.rowGroup.min.js')}}"></script>

<script src="{{asset('backend/build/bs5_datatable/js/dataTables.select.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/select.bootstrap5.min.js')}}"></script>

<!-- ✅ SearchBuilder after DateTime -->
<script src="{{asset('backend/build/bs5_datatable/js/dataTables.dateTime.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/dataTables.searchBuilder.min.js')}}"></script>
<script src="{{asset('backend/build/bs5_datatable/js/searchBuilder.bootstrap5.min.js')}}"></script>





