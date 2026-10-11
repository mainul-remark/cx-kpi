{{--
    One table of projects or social platforms with an approximate target input per activity the row is switched on for.
    A row with none of the shown activities switched on is left out. The inputs are optional: an empty one is no target.
    $columns narrows the activities shown (field => label); $withId = false leaves out the hidden id of the row,
    when the page posts it once itself.
--}}
@php
    $columns = $columns ?? [
        'inbound_calls' => 'Outbound Calls',
        'comments' => 'Comments',
        'message_replies' => 'Message Replies',
    ];
    $withId = $withId ?? true;
    $visible = $rows->filter(fn ($row) => collect(array_keys($columns))->contains(fn ($field) => $row[$field]));
@endphp
@if($visible->isEmpty())
    <p class="text-muted mb-0">{{ $emptyText }}</p>
@else
    <div class="table-responsive">
        <table class="table table-bordered align-middle w-100">
            <thead>
            <tr>
                <th style="width: {{ count($columns) > 1 ? '34%' : '50%' }}">{{ $nameHeading }}</th>
                @foreach($columns as $label)
                    <th>{{ $label }} {{ $per ?? 'per Day' }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($visible as $index => $row)
                <tr>
                    <td>
                        @if($withId)
                            <input type="hidden" name="{{ $group }}[{{ $index }}][{{ $idField }}]" value="{{ $row['id'] }}">
                        @endif
                        {{ $row['name'] }}
                    </td>
                    @foreach($columns as $field => $label)
                        <td>
                            @if($row[$field])
                                <input type="number" name="{{ $group }}[{{ $index }}][{{ $field }}]" class="form-control" min="0" step="1" placeholder="No target" value="{{ $row['values'][$field] }}" aria-label="{{ $row['name'] }} {{ $label }}">
                                <div class="invalid-feedback" data-error-for="{{ $group }}.{{ $index }}.{{ $field }}"></div>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
