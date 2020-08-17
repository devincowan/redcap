@extends('ehr.layout')

@section('title', 'EHR Launcher')

@section('content')

@if($authentication_flow=='client_credentials')
<div>
    <h3>Client credentials flow</h3>
    <p>The OAuth 2.0  <strong>client credentials</strong> grant flow permits a web service (confidential client)
        to use its own credentials, instead of impersonating a user,
        to authenticate when calling another web service. In this scenario,
        the client is REDCap.</p>
    <pre>
    +---------------+       +---------------+
    |               |       |               |
    |   SMART       +-(1)--->               |
    |  Application  |       | FHIR Resource |
    |               <-(2)---+ Server        |
    |               |       |               |
    |               +-(5)--->               |  
    |               |       |               |
    |               <-(6)---+               |
    |               |       |               |
    |               +       +---------------+
    |               |
    |               +       +---------------+
    |               |       |               |
    |               +-(3)---> Authorization |
    |               |       | Server        |
    |               <-(4)---+               |
    |               |       |               |
    +---------------+       +---------------+</pre>
    <ul style="list-style-type:decimal;">
        <li>The SMART application performs discovery by requesting the FHIR® server’s conformance statement. The mechanism for how the SMART application is provided the URL for the FHIR® server is not defined by this specification.</li>
        <li>The FHIR® server returns the conformance statement, which provides the needed endpoint for step 3.</li>
        <li>The SMART application requests an access token using its client credentials.</li>
        <li>The authorization server returns the access token.</li>
        <li>The SMART application utilizes the access token to request a FHIR® resource.</li>
        <li>The FHIR® resource server returns the desired resource.</li>
    </ul>
    <div class="alert alert-dark p">
        <p>This authentication method is currently <strong>supported only by Cerner</strong></p>
    </div>
    <a class="btn btn-primary btn-sm" href="{{$app_path_webroot}}ehr.php?client_credentials=1"><i class="fas fa-rocket"></i> client credentials</a>
</div>
@else
<div>
    <h3>Standalone launch</h3>
    <p>SMART supports EHR launch and standalone launch.</p>
    <p>The standalone application does not need to be launched by an EHR.
        The app can launch and access FHIR data on its own, provided the app is authorized and given the iss URL.</p>
    <p>Once an app receives a launch request, it requests authorization to access
    a FHIR resource by causing the browser to navigate to the EHR’s authorization endpoint.
    Based on pre-defined rules and possibly end-user authorization,
    the EHR authorization server either grants the request by returning
    an authorization code to the app’s redirect URL, or denies the request.
    The app then exchanges the authorization code for an access token,
    which the app presents to the EHR’s resource server to access requested
    FHIR resources. If a refresh token is returned along with the access token,
    the app may use this to request a new access token, with the same scope,
    once the access token expires.</p>
    <div class="alert alert-warning p">
        <p>Standalone launch in Cerner is currently only supported for patient access workflow.<br />
        <a href="https://engineering.cerner.com/smart-on-fhir-tutorial/#run-your-app-against-smart-health-it" target="_blank">More info</a></p>
    </div>
    <a class="btn btn-primary btn-sm text-white" href="{{$app_path_webroot}}ehr.php?standalone_launch=1" ><i class="fas fa-rocket"></i> standalone launch</a>

</div>
@endif

{{-- destroy the FHIR session data if the user presses shift+alt+d --}}
<script src="{{$app_path_webroot}}Resources/js/axios.min.js"></script>
<script>
(function(document, window){

    document.addEventListener("DOMContentLoaded", function() {
        var destroyerForm = document.querySelector('#fhir-session-destroyer')
        var deleteKeyCode = 'KeyD'
        
        document.addEventListener('keypress', function(e){
            var shiftPressed = e.shiftKey
            var altPressed = e.altKey
            var keyCode = e.code
            if(shiftPressed && altPressed && keyCode==deleteKeyCode) {
                try {
                    var params = new URLSearchParams()
                    params.append('action', 'destroy_fhir_session')
                    axios.post('', params)
                }catch(error) {
                    console.log(error)
                }
            }
        })
    });
}(document, window))
</script>
@endsection