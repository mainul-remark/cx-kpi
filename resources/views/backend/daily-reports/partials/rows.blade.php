@if($rows->isEmpty())
    <p class="text-muted mb-0">{{ $emptyText }}</p>
@else
    <div class="table-responsive">
        <table class="table table-bordered align-middle w-100">
            <thead>
            <tr>
                <th style="width: 30%">{{ $nameHeading }}</th>
                <th style="width: 20%">{{ $countHeading }} <span class="text-danger">*</span></th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td>
                        <input type="hidden" name="{{ $group }}[{{ $index }}][{{ $idField }}]" value="{{ $row['id'] }}">
                        {{ $row['name'] }}
                        @unless($row['active'])
                            <span class="badge text-bg-danger ms-1">Inactive</span>
                        @endunless
                        <div class="invalid-feedback" data-error-for="{{ $group }}.{{ $index }}.{{ $idField }}"></div>
                    </td>
                    <td>
                        <input type="number" name="{{ $group }}[{{ $index }}][{{ $countField }}]" class="form-control {{ $countClass }}" min="0" step="1" value="{{ $row['count'] ?? 0 }}" aria-label="{{ $row['name'] }} {{ $countHeading }}">
                        <div class="invalid-feedback" data-error-for="{{ $group }}.{{ $index }}.{{ $countField }}"></div>
                    </td>
                    <td>
                        <input type="text" name="{{ $group }}[{{ $index }}][note]" class="form-control" maxlength="5000" placeholder="Optional note" value="{{ $row['note'] }}" aria-label="{{ $row['name'] }} note">
                        <div class="invalid-feedback" data-error-for="{{ $group }}.{{ $index }}.note"></div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
