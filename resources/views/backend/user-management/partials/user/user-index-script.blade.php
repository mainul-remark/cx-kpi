{{--from and to date script--}}
<script>
    flatpickr("#from_date", {
        enableTime: false,
        dateFormat: "Y-m-d",
        maxDate: "today",
    });

    flatpickr("#to_date", {
        enableTime: false,
        dateFormat: "Y-m-d",
        maxDate: "today",
    });

</script>

{{--User YajraDatatable script --}}

{{--<script>--}}

{{--    $(document).ready(function () {--}}
{{--        let table =  $('#userDataTablexxxxx').DataTable({--}}
{{--            dom         : 'Blfrtip',--}}
{{--            serverSide  :   true,--}}
{{--            processing  :   true,--}}
{{--            searchDelay :   500,--}}
{{--            search      :   { smart: false},--}}
{{--            order       :   [[0,'DESC']],--}}
{{--            columnDefs: [--}}
{{--                { width: '7%', targets: 0 },--}}
{{--            ],--}}
{{--            ajax: {--}}
{{--                url : "{{route('users.index')}}",--}}
{{--                 data: function (d) {--}}
{{--                     d.from_date = $('#from_date').val();--}}
{{--                     d.to_date   = $('#to_date').val();--}}
{{--                     console.log(d)--}}
{{--                 }--}}
{{--            },--}}
{{--            columns : [--}}
{{--                {--}}
{{--                    data: 'profile_image',--}}
{{--                    title: 'Image',--}}
{{--                    searchable: false,--}}
{{--                    render: function (data, type, row, meta) {--}}
{{--                        return '<img src="' + data + '" alt="User Image" class="rounded-circle" style="width: 50px; height: 50px;">';--}}
{{--                    }--}}
{{--                },--}}
{{--                { data: 'name',  title: 'Name', searchable:true },--}}
{{--                { data: 'email', title: 'Email', searchable:true },--}}
{{--                { data: 'mobile_no', title: 'Mobile', searchable:true },--}}
{{--                {--}}
{{--                    data: 'created_at',--}}
{{--                    title: 'Joined At',--}}
{{--                    render: function (data, type, row) {--}}
{{--                        return '<span class="badge bg-outline-secondary px-2">' + data + '</span>';--}}
{{--                    }--}}
{{--                },--}}
{{--                {--}}
{{--                    data: 'account_type',--}}
{{--                    title: 'Type',--}}
{{--                    render: function (data, type, row) {--}}
{{--                        return '<span class="badge bg-outline-info px-2">' + data + '</span>';--}}
{{--                    }--}}
{{--                },--}}
{{--                {--}}
{{--                    data: 'roles',--}}
{{--                    title: 'Role',--}}
{{--                    render: function (data) {--}}
{{--                        if (data && data.trim() !== '') {--}}
{{--                            return data.split(', ')--}}
{{--                                .map(role => '<span class="badge bg-outline-primary px-2">' + role.trim() + '</span>')--}}
{{--                                .join(' ');--}}
{{--                        }--}}
{{--                        return '<span class="badge bg-outline-warning px-2">User</span>';--}}
{{--                    }--}}
{{--                },--}}
{{--                {--}}
{{--                    data: 'is_active',--}}
{{--                    title: 'Status',--}}
{{--                    searchable: true,--}}
{{--                    render: function (data, type, row) {--}}
{{--                        return data === 1--}}
{{--                            ? '<span class="badge bg-outline-success px-2">Active</span>'--}}
{{--                            : data === 0--}}
{{--                                ? '<span class="badge bg-outline-danger px-2">Inactive</span>'--}}
{{--                                : '';--}}
{{--                    }--}}
{{--                },--}}
{{--                {--}}
{{--                    data: null,--}}
{{--                    title: 'Action',--}}
{{--                    searchable: false,--}}
{{--                    className: "text-center",--}}
{{--                    render: function (data, type, row) {--}}
{{--                        let viewUrl  = "/admin/users/" + row.id;--}}
{{--                        let editUrl  = "/admin/users/" + row.id + "/edit";--}}
{{--                        let deleteId = row.id;--}}

{{--                        const accessRole = @json(hasRole(['super-admin']));--}}

{{--                        let html  = '';--}}


{{--                        html += '<a href="' + viewUrl + '" class="btn btn-sm btn-outline-secondary me-1" title="View">';--}}
{{--                        html += '<i class="ri-eye-line"></i>';--}}
{{--                        html += '</a>';--}}

{{--                        html += '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1" title="Edit x">';--}}
{{--                        html += '<i class="ri-edit-2-line"></i>';--}}
{{--                        html += '</a>';--}}

{{--                        if(accessRole){--}}
{{--                            let deleteUrl = "/admin/users/" + deleteId;--}}
{{--                            html += '<button type="button" class="btn btn-sm btn-outline-danger deleteUserBtn" data-id="' + deleteId + '" data-url="'+deleteUrl+'" title="Delete">';--}}
{{--                            html += '<i class="ri-delete-bin-line"></i>';--}}
{{--                            html += '</button>';--}}
{{--                        }--}}

{{--                        return html;--}}
{{--                    }--}}
{{--                }--}}

{{--            ],--}}
{{--            buttons: [--}}
{{--                {--}}
{{--                    extend: 'copy',--}}
{{--                    title: 'User list',--}}
{{--                    exportOptions: {--}}
{{--                        columns: [1, 2, 3, 4, 5, 6, 7]--}}
{{--                    }--}}
{{--                },--}}
{{--                {--}}
{{--                    extend: 'csv',--}}
{{--                    title: 'Admin User list',--}}
{{--                    exportOptions: {--}}
{{--                        columns: [1, 2, 3, 4, 5, 6, 7]--}}
{{--                    }--}}

{{--                },--}}
{{--                {--}}
{{--                    extend: 'excel',--}}
{{--                    title: 'User list',--}}
{{--                    exportOptions: {--}}
{{--                        columns: [1, 2, 3, 4, 5, 6, 7]--}}
{{--                    }--}}
{{--                },--}}
{{--                {--}}
{{--                    extend: 'print',--}}
{{--                    title: 'User list',--}}
{{--                    exportOptions: {--}}
{{--                        columns: [1, 2, 3, 4, 5, 6, 7]--}}
{{--                    }--}}
{{--                }--}}
{{--            ],--}}
{{--        });--}}
{{--        // ✅ সঠিক draw event (same id + same instance)--}}
{{--        table.on('draw.dt', function () {--}}
{{--            let data = table.rows().data();--}}
{{--            console.log(data);--}}
{{--        });--}}

{{--        $('#filter_form').on('submit', function(e) {--}}
{{--            e.preventDefault();--}}
{{--            table.draw();--}}
{{--        });--}}
{{--        $('.ajax_reload').on('click', function(e) {--}}
{{--            e.preventDefault();--}}
{{--            // Optional: clear filters--}}
{{--            $('#from_date').val('');--}}
{{--            $('#to_date').val('');--}}
{{--            table.ajax.reload();--}}
{{--        });--}}
{{--    });--}}
{{--</script>--}}



{{--User jquery datatable script script --}}
<script>
    $(document).ready(function() {

        let table = $("#userDataTable").DataTable({
            dom         :   'QBflrtip',
            processing  :   true,
            deferRender :   true,
            serverSide  :   false,
            buttons     :   ['csv', 'excel'],
            order       :   [[0, 'DESC']],

            ajax: {
                url: "{{ route('users.index') }}",
                cache: false,
                data: function (d) {
                    d.role_id = $('#selectRole').val();
                },
            },

          columns : [
                {
                    data: 'profile_image',
                    defaultContent: '',
                    render : function(data, type, row, meta) {
                        return '<img src="' + data + '" alt="User Image" class="rounded-circle" style="width: 50px; height: 50px;">';
                    }
                },
              { data: 'name', title: 'Name' },
              { data: 'email', title: 'Email' },
              { data: 'employee_id', title: 'Employee Id' },
              // { data: 'mobile_no', title: 'Mobile'},
              {
                  data: 'role_names',
                  title: 'Role',
                  render : function(data, type, row, meta) {
                      if (data && data.trim() !== '') {
                          return data.split(', ')
                              .map(role => '<span class="badge bg-outline-primary px-2">' + role.trim() + '</span>')
                              .join(' ');
                      }
                      return '<span class="badge bg-outline-warning px-2">User</span>';
                  }
              },
              {
                  data: 'assigned_store_names',
                  title: 'Assigned Stores',
                  render: function (data, type, row) {
                      const stores = Array.isArray(row.assigned_stores) ? row.assigned_stores : [];

                      if (type !== 'display') {
                          return data || '';
                      }

                      if (stores.length) {
                          return '<button type="button" class="btn btn-sm btn-outline-secondary view-assigned-stores-btn" '
                              + 'data-user-id="' + row.id + '">'
                              + '<i class="ri-store-2-line me-1"></i>' + stores.length + (stores.length === 1 ? ' Store' : ' Stores')
                              + '</button>';
                      }

                      return '<span class="text-muted">No stores assigned</span>';
                  }
              },
              {
                  data: 'created_at',
                  title: 'Created_at',
                  render: function (data) {
                      if (!data) return '';
                      return String(data).slice(0, 10);
                  }
              },
              // {
              //     data: 'is_active',
              //     title: 'Status',
              //     render: function (data, type, row) {
              //         return data === 1
              //             ? '<span class="badge bg-outline-success px-2">Active</span>'
              //             : data === 0
              //                 ? '<span class="badge bg-outline-danger px-2">Inactive</span>'
              //                 : '';
              //     }
              // },
                {
                    data: null,
                    title: 'Action',
                    className: "text-center",
                    render: function (data, type, row) {
                        let viewUrl  = "/admin/users/" + row.id;
                        let editUrl  = "/admin/users/" + row.id + "/edit";
                        let deleteId = row.id;

                        const accessRole = @json(hasRole(['super-admin']));

                        let html  = '';

                        html += '<a href="' + viewUrl + '" class="btn btn-sm btn-outline-secondary me-1" title="View">';
                        html += '<i class="ri-eye-line"></i>';
                        html += '</a>';

                        html += '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1" title="Edit">';
                        html += '<i class="ri-edit-2-line"></i>';
                        html += '</a>';

                        if(accessRole){
                            let deleteUrl = "/admin/users/" + deleteId;
                            html += '<button type="button" class="btn btn-sm btn-outline-danger deleteUserBtn" data-id="' + deleteId + '" data-url="'+deleteUrl+'" title="Delete">';
                            html += '<i class="ri-delete-bin-line"></i>';
                            html += '</button>';
                        }

                        return html;
                    }
                }

            ],

        });

        $('.dt-buttons .btn').removeClass('btn-secondary').addClass(' btn-outline-secondary mb-3 rounded-0 btn-sm');
        $('.dt-buttons').addClass('gap-2');

        $('#filter_form').on('submit', function (e) {
            e.preventDefault();
            table.ajax.reload();
        });

        $('#resetBtn').on('click', function () {
            $('#selectRole').val('');
            table.ajax.reload();
        });

        $(document).on('click', '.view-assigned-stores-btn', function () {
            const userId = $(this).data('user-id');
            const rowData = table.rows().data().toArray().find(r => r.id === userId);
            const stores = (rowData && Array.isArray(rowData.assigned_stores)) ? rowData.assigned_stores : [];

            $('#viewAssignedStoresUserName').text(rowData ? (rowData.name || '') : '');
            $('#viewAssignedStoresList').html(
                stores
                    .map(store => '<span class="badge bg-outline-secondary px-2">' + $('<div>').text(store).html() + '</span>')
                    .join('')
            );

            new bootstrap.Modal(document.getElementById('viewAssignedStoresModal')).show();
        });

    });
</script>
{{--User jquery datatable script script --}}


{{--user delete script--}}
<script>
    $(document).on('click', '.deleteUserBtn', function (e) {
        e.preventDefault();
        const btn       = $(this);
        const url       = btn.data('url');
        const csrfToken = $('meta[name="csrf-token"]').attr('content');

        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed)
            {
                $.ajax({
                    url      : url,
                    type     : 'POST',
                    dataType : 'json',
                    data     : { _method: 'DELETE', _token: csrfToken },
                    success  : function (response) {
                        if (response.status === true) {
                            showAjaxToast('primary', response.message || 'User deleted successfully !!');
                            $('#userDataTable').DataTable().ajax.reload(null, false);
                        }
                    },
                    error: function (xhr) {
                        if (xhr.status === 403) {
                            showAjaxToast('error', 'You do not have permission to delete this user.');
                        }
                        else if (xhr.status === 422) {
                            showAjaxToast('warning', xhr.responseJSON?.message || 'Validation error occurred.');
                        }
                        else if (xhr.status === 404) {
                            showAjaxToast('error', 'User not found.');
                        }
                        else {
                            showAjaxToast('error', 'Something went wrong. Please try again.');
                        }
                    }
                });
            }
        });

        // if (!confirm('Are you sure you want to delete this user?')) {
        //     return;
        // }


    });
</script>
