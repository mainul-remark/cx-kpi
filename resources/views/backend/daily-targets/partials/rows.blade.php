@if($rows->isEmpty())
    <p class="text-muted mb-0">{{ $emptyText }}</p>
@else
    <div class="table-responsive">
        <table class="table table-bordered align-middle w-100">
            <thead>
            <tr>
                <th style="width: 50%">{{ $nameHeading }}</th>
                <th>{{ $countHeading }} per Day</th>
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td>
                        <input type="hidden" name="{{ $group }}[{{ $index }}][{{ $idField }}]" value="{{ $row['id'] }}">
                        {{ $row['name'] }}
                        <div class="invalid-feedback" data-error-for="{{ $group }}.{{ $index }}.{{ $idField }}"></div>
                    </td>
                    <td>
                        <input type="number" name="{{ $group }}[{{ $index }}][{{ $countField }}]" class="form-control {{ $countClass }}" min="0" step="1" placeholder="No target" value="{{ $row['count'] }}" aria-label="{{ $row['name'] }} {{ $countHeading }}">
                        <div class="invalid-feedback" data-error-for="{{ $group }}.{{ $index }}.{{ $countField }}"></div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
