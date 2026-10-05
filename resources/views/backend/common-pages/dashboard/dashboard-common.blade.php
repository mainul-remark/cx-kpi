@extends('backend.master')

@section('title', 'Dashboard')

@section('body')
    <div class="container">


        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h4 class="mb-0">Hi, welcome back!</h4>
                <p class="mb-0 text-muted">{{ auth()->user()->name ?? 'Admin' }}</p>
            </div>
        </div>
        <!-- End Page Header -->


    </div>
@endsection

@push('styles')

@endpush

@push('scripts')


@endpush
