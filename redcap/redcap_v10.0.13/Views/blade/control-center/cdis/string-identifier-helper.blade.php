<button class="jqbuttonmed" style="color:#0101bb;font-size: 11px;top: 3px;" onclick="showStringIdentifierHelperDialog(event)">{{$lang['ws_324']}}</button>
<!-- Modal -->
<div class="modal fade" id="stringIdentifierHelperModal" tabindex="-1" role="dialog" aria-labelledby="stringIdentifierHelperModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLongTitle">{{$lang['ws_325']}}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>{{$lang['ws_326']}}</p>
                <p>{{$lang['ws_329']}}</p>

                <div class="form-row">
                    <div class="col">
                        <input class="form-control" type="text" id="patient_ssn" value="" placeholder="XXX-XX-XXXX" onkeydown="if(event.keyCode == 13){ $('#ssn-lookup-button').click(); return false; }">
                    </div>
                    <div class="col">
                        <button class="form-control btn btn-primary" type="button" id="ssn-lookup-button">{{$lang['ws_327']}} <i style="visibility:hidden;" class="loading-indicator fas fa-spinner fa-spin"></i></button>
                    </div>
                </div>
                <div class="fs13 mt-3 hidden darkgreen" id="string-identifiers-parent">
                    <div class="my-2">{{$lang['ws_328']}}</div>
                    <!-- container for the found string identifiers -->
                    <ul class="my-2" id="string-identifiers"></ul>
                </div>
            </div>
            <!-- <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div> -->
        </div>
    </div>
</div>

<script>
    var modalSelector = 'stringIdentifierHelperModal';
    
    (function($,window,document){

        var createStringIdentifierElement = function(string_identifier) {
            var element = document.createElement('li')
            var html_array = [
                '<span><b>',string_identifier.value,'</b>: </span>',
                '<span>',string_identifier.system,'</span>',
            ]
            element.innerHTML = html_array.join('')
            return element
        }

        $(function() {
            var modal_element = document.getElementById(modalSelector)
            var string_identifiers_container = modal_element.querySelector('#string-identifiers')
            var lookup_button = modal_element.querySelector('#ssn-lookup-button')
            var loading_indicator = lookup_button.querySelector('.loading-indicator')
            var ssn_input = modal_element.querySelector('#patient_ssn')
            var url = app_path_webroot+'index.php?route=ControlCenterController:getFhirStringIdentifiers'

            /**
             * reset the status of the component
             */
            var reset = function() {
                lookup_button.disabled = false
                loading_indicator.style.visibility = 'hidden'
            };

            // set onClick logic
            lookup_button.addEventListener('click', function(e) {
                lookup_button.disabled = true
                loading_indicator.style.visibility = 'visible'
                string_identifiers_container.innerHTML = ''; //reset the results
                $('#string-identifiers-parent').addClass('hidden');
                var ssn = ssn_input.value
                $.ajax(url, {
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        ssn: ssn
                    },
                }).done(function(data, textStatus, jqXHR){
                    try {
                        // console.log(data, textStatus, jqXHR)
                        var string_identifiers = data.string_identifiers
                        string_identifiers.forEach(function(string_identifier) {
                            if(string_identifier.value && string_identifier.system) {
                                var element = createStringIdentifierElement(string_identifier)
                                string_identifiers_container.appendChild(element)
                            }
                        })
                        $('#string-identifiers-parent').removeClass('hidden').effect('highlight',2500);
                    } catch (error) {
                        console.error(error)
                        reset()
                        alert(error)
                    }
                }).fail(function(jqXHR, textStatus, errorThrown){
                    // console.error(jqXHR, textStatus, errorThrown)
                    // try to get the error from the JSON
                    var error_message = jqXHR.responseJSON.message;
                    // if JSON is not available, try the text version
                    if(!error_message) error_message = JSON.parse(jqXHR.responseText).message ;
                    // if also the text version fails get the generic textStatus
                    if(!error_message) error_message = textStatus;
                    string_identifiers_container.innerHTML = '<b>'+error_message+'</b>';
                    $('#string-identifiers-parent').removeClass('hidden').effect('highlight',2500);
                }).always(function(){
                    reset()
                });
            })
        })
    }(jQuery, window, document));
    
    function showStringIdentifierHelperDialog(e) {
        e.preventDefault()
        $('#'+modalSelector).modal({})
    }
</script>