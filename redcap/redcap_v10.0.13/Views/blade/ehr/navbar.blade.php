<nav class="navbar navbar-light fixed-top" style="margin-bottom:5px;min-height:25px;padding:4px 0;background-color:#eee;border-bottom:1px solid #ccc;">
    <div class="container-fluid" style="padding:0 10px 0 5px;">
        <div class="navbar-collapse clearfix">
            <div class="float-left" style="font-size:14px;">
                <a href="{{$app_path_webroot}}ehr.php?fhirPatient={{urlencode($patientID)}}" style="margin-right:25px;">
                <img src="{{$app_path_images}}redcap-logo-small.png" style="height:22px;"></a>
                @if(empty($patientMrn))
                Patient <span style="color:#C00000;">{{$patientID}}</span>
                @else
                MRN <span style="color:#C00000;">{{$patientMrn}}</span>
                @endif
                – <b>{{$patientFirstName}}, {{$patientLastName}}</b>
                (DOB {{$patientBirthDate}})
            </div>
            <div class="float-right" style="font-size:12px;">Logged in as <b>{{$ehr_user}}</b> / <b>{{$user}}</b></div>
        </div>
        @if($app_title)
        <div class="clearfix" style="font-size:14px;">
            <div class="float-left" style="width:100px;"><a href="{{$app_path_webroot}}ehr.php?fhirPatient={{urlencode($patientID)}}" style="margin-left:5px;font-size:12px;text-decoration:underline;">{{$lang['home_22']}}</a></div>
            <div class="float-left">{{$lang['create_project_87']}} <span style="font-weight:700;color:#C00000;">{{strip_tags($app_title)}}</span></div>
        </div>
        @endif
    </div>
</nav>