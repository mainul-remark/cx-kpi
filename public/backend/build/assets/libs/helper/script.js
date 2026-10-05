function sendAjaxRequest(url, method, data = {}) {
    return $.ajax({ // Return the Promise from $.ajax
        url: base_url + url,
        method: method,
        data: data,
        processData: false,
        contentType: false,
    })
        .done(function (data) { // .done() for success
            // console.log(data.job.employer_company);
            // console.log('print from dno');
            // No need to assign to 'response' here, it's passed to .then()
        })
        .fail(function (error) { // .fail() for error
            toastr.error(error);
            // The error will also be propagated to the .catch() when called
        });
}

$(document).on('click', '.data-delete-form', function () {
    event.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Swal.fire(
            //     'Deleted!',
            //     'Your file has been deleted.',
            //     'success'
            // )
            $(this).parent().submit();
        }

    })
})
