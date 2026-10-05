@extends('backend.master')

@section('title', 'Site Setting')

@section('body')
    <div class="container m-t-50">
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="card-title">Permission Labels</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Controller</th>
                                        <th>Name</th>
                                        <th>Label</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($resources as $resource)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $resource->controller ?? '' }}</td>
                                            <td>{{ $resource->name ?? '' }}</td>
                                            <td>
                                                <form action="update-resource-label/{{ $resource->resource_id }}" enctype="multipart/form-data" method="POST">
                                                    @csrf
                                                    <div class="input-group mb-3">
                                                        <input type="text" class="form-control" placeholder="Insert Label" value="{{ $resource->label ?? '' }}" name="label">
                                                        <button type="button" class="input-group-text text-white bg-success update-btn" >Update</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')

@endsection

@push('styles')


@endpush

@push('scripts')
    @include('backend.includes.plugins.toastr')
    <script>
        $(document).on('click', '.update-btn', function () {
            let closestForm = $(this).closest('form');
            sendAjaxRequest(closestForm.attr('action'), 'POST', new FormData(closestForm[0])).then(function (data) {
                if (data.status)
                    toastr.success(data.message);
                else
                    toastr.error(data.message);
            });
        })
    </script>
@endpush
