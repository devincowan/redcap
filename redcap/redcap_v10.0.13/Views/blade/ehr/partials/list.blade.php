@if (is_array($value))
    <li>{{$key}}:
        <ul>
        @foreach($value as $sub_key => $sub_value)
            @include('ehr.partials.list', array('key' => $sub_key,'value' => $sub_value))
        @endforeach
        </ul>
    </li>
@else
<li>{{$key}}: {!!$value!!}</li>
@endif