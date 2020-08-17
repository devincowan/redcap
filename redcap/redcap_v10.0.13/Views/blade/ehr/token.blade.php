@extends('ehr.layout')

@section('title', 'FHIR Token Success')

@section('content')
<h3 class="mt-4" style="color:green;"><i class="fas fa-check"></i> Successful login to the EHR</h3>
<p class="my-4">You have successfully logged in to the EHR, and you will now be automatically redirected back to REDCap.</p>

<div class="buttons">
    @if($launch_page)
    <div>
        <div class="progress mt-4">
            <div class="progress-bar bg-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="mt-4">
            <a href="{{$launch_page}}" class="btn btn-xs btn-primaryrc text-white"><i class="fas fa-arrow-left"></i> Go back to REDCap</a>
        </div>
    </div>
    @endif
    @if(!$launch_page)
        <div>
            <a href="{{$app_path_webroot_full}}index.php?action=myprojects"class="btn btn-sm btn-primary text-white"><i class="far fa-list-alt"></i> Go to projects</a>
        </div>
    @endif
</div>
<style>
.buttons > div + div {
    margin-top: 3px;
}
.progress {
    width: 50%;
    margin: 10px 0;
}
</style>
<script>
(function(document, window){
    /**
     * if a launch page is detected then redirect
     * automatically after X seconds
     */
    function goToPage(page) {
        location.href = page;
    };

    function startTimer(callback, seconds)
    {
        var progress_bar = document.querySelector('.progress .progress-bar');
        var total = remaining = seconds || 3; // default to X seconds
        progress_bar.style.width = '0%';
        var interval_id = setInterval(function(){
            remaining -= 1;
            var width = 100-(100/total*remaining);
            progress_bar.style.width = width+'%';
            if(remaining<=0) {
                clearInterval(interval_id)
                if(typeof callback == 'function') {
                    callback()
                }
            }
        }, 1000);
    }

    var launch_page = '@php(print $launch_page)';
    if( launch_page && (launch_page!=location.href) ) {
        // redirect after X seconds
        startTimer(function() {
            goToPage(launch_page)
        }, 3)
    }

}(document, window))
</script>
@endsection