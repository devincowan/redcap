@extends('ehr.layout')

@section('title', 'Online Status check')

@section('content')
    <div>
        <h3>{{$lang['ws_319']}}</h3>
        <p>{{$lang['ws_320']}}</p>
        <div class="progress mt-4">
            <div class="progress-bar bg-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>

    <script>

    function startTimer(callback, seconds)
    {
        var progress_bar = document.querySelector('.progress .progress-bar');
        var total = remaining = seconds || 3; // default to X seconds
        progress_bar.style.width = '0%';
        var interval_id = setInterval(function(){

            var width = 100-( 100/total*(--remaining) );
            progress_bar.style.width = width+'%';
            if(remaining<=0) {
                clearInterval(interval_id)
                if(typeof callback == 'function') {
                    callback()
                }
            }
        }, 1000);
    }


    function redirect(url) {
        location.href = url
    }

    var onSuccess = function() {
        redirect(success_url)
    }
    var onError = function() {
        redirect(error_url)
    }

    function check(url, success_callback, error_callback) {
        var element = document.createElement('script')
        element.addEventListener('load', success_callback)
        element.addEventListener('error', error_callback)
        element.src = url
        document.body.appendChild(element)
    }



    var authorization_url = '{{$authorization_url}}'
    var error_url = '{{$error_url}}'
    var success_url = '{{$success_url}}'

    check(authorization_url, onSuccess, onError)
    startTimer(10, onError)

    </script>
@endsection