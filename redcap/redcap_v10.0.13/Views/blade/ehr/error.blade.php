@extends('ehr.layout')

@section('title', 'FHIR Error')

@section('content')
<h3>Error</h3>
<p>{{$message}} - <span>Error code: {{$code}}</span></p>

<div>
    <ul>
    @foreach ($data as $key => $value )
        @include('ehr.partials.list', compact('key', 'value'))
    @endforeach
    </ul>
</div>



{{-- <details>
    <summary>details</summary>
    <pre>
        @php(print_r($data))
    </pre>
</details> --}}

@endsection