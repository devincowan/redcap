<html>
    <head><title>{{$emailSubject}}</title></head>
    <body style='font-family:arial,helvetica;font-size:10pt;'>
        <p><em>{{$lang['global_21']}}</em></p>

        {{$lang['email_admin_03']}} <b>{{html_entity_decode($user_fullname, ENT_QUOTES)}}</b>
        (<a href="mailto:{{$user_email}}">{{$user_email}}</a>)
        has requested to insert this code in the list of mappable FHIR fields in REDCap.
        <p>Code: <strong>{{$code}}</strong></p>

        <p>Additional data:</p>
        <ul>
            <li>Project ID: {{$project_id}}</li>
            <li>Resource: {{$resource_type}}</li>
            <li>Interaction: {{$interaction}}</li>
            <li>MRN: {{$mrn}}</li>
        </ul>
    </body>
</html>