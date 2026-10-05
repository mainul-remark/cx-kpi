@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <button type="button" class="btn float-end" data-bs-dismiss="alert"> <i class="fa fa-times"></i></button>
        @if($errors->count() == 1)
            {{ $errors->first() }}
        @else
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
