{{--
    One table of projects or social platforms with an approximate target input per activity the row is switched on for.
    A row with none of them switched on is left out. The inputs are optional: an empty one is no target.
--}}
@php
    $columns = [
        'inbound_calls' => 'Outbound Calls',
        'comments' => 'Comments',
        'message_replies' => 'Message Replies',
    ];
    $visible = $rows->filter(fn ($row) => $row['inbound_calls'] || $row['comments'] || $row['message_replies']);
@endphp
@if($visible->isEmpty())
    <p class="text-muted mb-0">{{ $emptyText }}</p>
@else
    <div class="table-responsive">
        <table class="table table-bordered align-middle w-100">
            <thead>
            <tr>
                <th style="width: 34%">{{ $nameHeading }}</th>
                @foreach($columns as $label)
                    <th>{{ $label }} per Day</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($visible as $index => $row)
                <tr>
                    <td>
                        <input type="hidden" name="{{ $group }}[{{ $index }}][{{ $idField }}]" value="{{ $row['id'] }}">
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
