@if($total = $data['total'])
    @php($total_keys = array_keys($total))
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                @foreach ($total_keys as $key)
                <th>{{$key}}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach ($total as $value)
                <th>{{$value}}</th>
                @endforeach
            </tr>
        </tbody>
    </table>

    @include('control-center.fhir-stats.partials.chart', compact('name','data'))
@else
    no results
@endif