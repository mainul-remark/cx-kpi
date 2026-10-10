{{--
    One table of projects or social platforms, with a count input per activity the row is switched on for.
    $columns: list of ['field' => inbound_calls|comments|message_replies, 'label' => ..., 'class' => ...].
    A row shows only the inputs of its own activities, and a row with none of them is left out.
    The id of every row is posted from the bottom of the form, so a key here is the same in every tab.
--}}
@php
    $visible = $rows->filter(fn ($row) => collect($columns)->contains(fn ($column) => $row[$column['field']]));
@endphp
@if($visible->isEmpty())
    <p class="text-muted mb-0">{{ $emptyText }}</p>
@else
    <div class="table-responsive">
        <table class="table table-bordered align-middle w-100">
            <thead>
            <tr>
                <th style="width: 30%">{{ $nameHeading }}</th>
                @foreach($columns as $column)
                    <th style="width: 20%">{{ $column['label'] }}</th>
                @endforeach
                @if($withNote)
                    <th>Note</th>
                @endif
            </tr>
            </thead>
            <tbody>
            @foreach($visible as $index => $row)
                <tr>
                    <td>
                        {{ $row['name'] }}
                        @unless($row['active'])
                            <span class="badge text-bg-danger ms-1">Inactive</span>
                        @endunless
                    </td>
                    @foreach($columns as $column)
                        <td>
                            @if($row[$column['field']])
                                <input type="number" name="{{ $group }}[{{ $index }}][{{ $column['field'] }}]" class="form-control {{ $column['class'] }}" min="0" step="1" value="{{ $row['values'][$column['field']] ?? 0 }}" aria-label="{{ $row['name'] }} {{ $column['label'] }}">
                                <div class="invalid-feedback" data-error-for="{{ $group }}.{{ $index }}.{{ $column['field'] }}"></div>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                    @endforeach
                    @if($withNote)
                        <td>
                            <input type="text" name="{{ $group }}[{{ $index }}][note]" class="form-control" maxlength="5000" placeholder="Optional note" value="{{ $row['note'] }}" aria-label="{{ $row['name'] }} note">
                            <div class="invalid-feedback" data-error-for="{{ $group }}.{{ $index }}.note"></div>
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
