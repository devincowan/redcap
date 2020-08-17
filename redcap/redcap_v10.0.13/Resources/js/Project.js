$(function(){
    // Impersonate user
    $('#impersonate-user-select').change(function(){
        $.post(app_path_webroot+"index.php?route=UserRightsController:impersonateUser&&pid="+pid, { user: $(this).val() },function(data){
            if (data == '0') {
                alert(woops);
                return false;
            }
            simpleDialog(data,null,null,500,function(){
                showProgress(1);
                window.location.reload();
            });
        });
    });
    // DAG Switch
    $('#dag-switcher-change-button').click(function(e) {
        simpleDialog(null,null,'dag-switcher-change-dialog',500,null,lang.global_53,function(){
            showProgress(1);
            var dagname = $('#dag-switcher-change-select option:selected').text();
            $.post(app_path_webroot+'index.php?route=DataAccessGroupsController:switchDag&pid='+pid, { dag: $('#dag-switcher-change-select').val() }, function(data){
                showProgress(0,0);
                if (data != '1') alert(data);
                try {
                    Swal.fire(
                        lang.data_access_groups_17+'<br>"'+dagname+'"',
                        '',
                        'success'
                    );
                    setTimeout('window.location.reload();', 1500);
                } catch(e) {
                    alert(lang.data_access_groups_17+'<br>"'+dagname+'"');
                }
            });
        },lang.data_access_groups_15);
    });
    $('#dag-switcher-change-button-span[data-toggle="popover"]').popover();
    // Record-level locking
    $('#record_lock_pdf_confirm_checkbox').click(function(){
        if ($(this).prop('checked')) {
            $('#record_lock_pdf_confirm_checkbox_div').removeClass('yellow').addClass('green');
            $('#recordLockPdfConfirmDialog').parent().find('.ui-dialog-buttonpane button:eq(1)').prop('disabled',false).removeClass('opacity50');
        } else {
            $('#record_lock_pdf_confirm_checkbox_div').removeClass('green').addClass('yellow');
            $('#recordLockPdfConfirmDialog').parent().find('.ui-dialog-buttonpane button:eq(1)').prop('disabled',true).addClass('opacity50');
        }
    });
    // Add ALT text to all images that lack it
    $('img').each(function(){
        if (typeof $(this).attr('alt') == "undefined") $(this).attr('alt', '');
    });
});

// Opens pop-up for sending Send-It files on forms and in File Repository
function popupSendIt(doc_id,loc) {
    window.open(app_path_webroot+'index.php?route=SendItController:upload&loc='+loc+'&id='+doc_id,'sendit','width=900, height=700, toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1');
}

function showEv(day_num) {
    document.getElementById('hiddenlink'+day_num).style.display = 'none';
    document.getElementById('hidden'+day_num).style.display = 'block';
}

var REDCap = {
    richTextFieldLabelPrefix: '<div class="rich-text-field-label">',
    richTextFieldLabelSuffix: '</div>',

    isTinyMCESupported: function() {
        return !isIE || vIE() >= 11;
    },

    getFieldLabelSelector: function() {
        return '#field_label';
    },

    removeFieldLabelTinyMCE: function() {
        if(!REDCap.isTinyMCESupported()){
            return;
        }

        tinymce.remove(REDCap.getFieldLabelSelector());

        var field = REDCap.getFieldLabel();

        // Remove newlines so REDCap doesn't replace them with <br> tags.
        field.val(field.val().split('\n').join(' '));

        // Set the text color back to normal to undo the hacky way of preventing users from seeing the raw HTML during save.
        field.css('color', 'inherit');
    },

    initTinyMCEFieldLabel: function(isPreInit) {
        if(!REDCap.isTinyMCESupported()){
            return;
        }

        if(isPreInit){
            // The following allows TinyMCE's internal dialogs to work (like when adding links).
            // It was copied from here: https://stackoverflow.com/questions/18111582/tinymce-4-links-plugin-modal-in-not-editable
            $(document).on('focusin', function(e) {
                if ($(e.target).closest(".mce-window").length) {
                    e.stopImmediatePropagation();
                }
            });
        } else {
            // Convert existing line breaks to <br> tags
            var field = REDCap.getFieldLabel();
            field.val(nl2br(field.val()));
        }

        tinymce.init({
            selector: REDCap.getFieldLabelSelector(),
            height: 350,
            branding: false,
            statusbar: true,
            menubar: false,
            elementpath: false, // Hide this, since it oddly renders below the textarea.
            plugins: ['paste autolink lists link searchreplace code fullscreen table directionality'],
            toolbar1: 'formatselect | bold italic link | alignleft aligncenter alignright alignjustify | undo redo | fullscreen',
            toolbar2: 'bullist numlist | outdent indent | table | forecolor backcolor | searchreplace code removeformat ',
            contextmenu: "copy paste | link image inserttable | cell row column deletetable",
            relative_urls: false,
            convert_urls : false,
            convert_fonts_to_spans: true,
            paste_word_valid_elements: "b,strong,i,em,h1,h2,u,p,ol,ul,li,a[href],span,color,font-size,font-color,font-family,mark,table,tr,td",
            paste_retain_style_properties: "all",
            paste_postprocess: function(plugin, args) {
                args.node.innerHTML = cleanHTML(args.node.innerHTML);
            },
            remove_linebreaks: true,
            content_style: 'body { font-weight: bold; }', // Match REDCap's default bold label style.
            formats: {
                bold: {
                    inline: 'span',
                    styles: {
                        'font-weight': 'normal'  // Make the 'bold' option function like an 'unbold' instead.
                    }
                }
            }
        });
    },

    toggleFieldLabelRichText: function(enabled) {
        if(enabled === undefined){
            enabled = REDCap.isFieldLabelRichTextChecked();
        }
        else{
            REDCap.getFieldLabelRichTextCheckbox().prop('checked', enabled);
        }

        if(enabled){
            REDCap.initTinyMCEFieldLabel(false);
        }
        else{
            REDCap.removeFieldLabelTinyMCE();
        }
    },

    getFieldLabel: function() {
        return $(REDCap.getFieldLabelSelector());
    },

    initFieldLabel: function(value) {
        var prefix = REDCap.richTextFieldLabelPrefix;
        var suffix = REDCap.richTextFieldLabelSuffix;

        value = value.slice(prefix.length, -suffix.length);

        // If TinyMCE was previously initialized, remove it.
        // This has no effect if it wasn't previously initialized.
        // This is required for the the val() call below to correctly set the textarea value.
        REDCap.removeFieldLabelTinyMCE();

        REDCap.getFieldLabel().val(value);
        REDCap.toggleFieldLabelRichText(true);
    },

    isRichTextFieldLabel: function(value) {
        return REDCap.isTinyMCESupported() && value.indexOf(REDCap.richTextFieldLabelPrefix) === 0;
    },

    getFieldLabelRichTextCheckbox: function() {
        return $('#field_label_rich_text_checkbox');
    },

    isFieldLabelRichTextChecked: function() {
        return REDCap.getFieldLabelRichTextCheckbox().is(':checked');
    },

    beforeAddFieldFormSubmit: function() {
        if(REDCap.isTinyMCESupported() && REDCap.isFieldLabelRichTextChecked()){
            // Remove TinyMCE in order to remove newlines that REDCap would replace with <br> tags.
            // This also allows us to interact with the textarea directly below.
            REDCap.removeFieldLabelTinyMCE();

            var field = REDCap.getFieldLabel();
            field.val(REDCap.richTextFieldLabelPrefix + field.val() + REDCap.richTextFieldLabelSuffix);

            // Hack to prevent the user from seeing the raw html while the field is saving.
            field.css('color', 'white');
        }
    }
}

// Popup to explain Smart Variables
function smartVariableExplainPopup() {
    $.get(app_path_webroot+"Design/smart_variable_explain.php"+(isProjectPage ? "?pid="+pid : ""), { },function(data){
        var json_data = jQuery.parseJSON(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        simpleDialog(json_data.content,json_data.title,'smart_variable_explain_popup',1000);
        fitDialog($('#smart_variable_explain_popup'));
    });
}

// Popup to explain Action Tags
function actionTagExplainPopup(hideBtns) {
    $.post(app_path_webroot+"Design/action_tag_explain.php?pid="+pid, { hideBtns: hideBtns },function(data){
        var json_data = jQuery.parseJSON(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        simpleDialog(json_data.content,json_data.title,'action_tag_explain_popup',750);
        fitDialog($('#action_tag_explain_popup'));
    });
}


var logicSuggestAjax = null;
function logicSuggestSearchTip(ob, event, longitudinalthis, draft_mode, forceMetadataTable) {
    // Force it to look at the metadata table only (instead of metadata temp if in prod draft mode)?
    if (typeof forceMetadataTable == "undefined") {
        forceMetadataTable = 1;
    }
    if (typeof longitudinalthis != "undefined") {
        var longitudinal = longitudinalthis;
    }
    if (typeof draft_mode == "undefined") {
        draft_mode = false;
    }
    draft_mode = (draft_mode) ? 1 : 0;
    var res_ob_id = $(ob).prop('id') + "_res";
    if ($("#"+res_ob_id))
    {
        $("#"+res_ob_id).html("");
        var sel_id = "logicTesterRecordDropdown";
        if ($("#"+sel_id))
        {
            $("#"+sel_id).val("");
        }
    }

    // Do preliminary validation via JS then full validation via PHP/AJAX
    logicValidate(ob, longitudinal, forceMetadataTable);

    // If these keys are hit, abort, so user can keep working
    // ascii codes http://unixpapa.com/js/key.html
    // backspace event.keyCode === 8
    // [ event.keyCode === 219
    if (event.keyCode === 32 || event.keyCode === 33 || event.keyCode === 61 || event.keyCode === 60 || event.keyCode === 62 // AbortOn <Space> ! = < >
        || event.keyCode === 91  || event.keyCode === 123
    ) {
        logicSuggestHidetip($(ob).prop('id'));  // hide the tips
        return; // since one of these disabled keys was pressed, we abort, so user can keep working
    }

    // Is the element an iframe?
    var isIframe = ($(ob).prop("tagName").toLowerCase() == 'iframe');

    var text = isIframe ? strip_tags($(ob).contents().find('body').html()) : $(ob).val();
    var word = "";
    if (text.indexOf(' ') >= 0) {
        word = text.split(' ').pop();
    } else {
        word = text;  // If there are no spaces, then use the word since it's first
    }
    if (trim(word) == "") return;

    // timeout to let new text value to enter field;
    // just to back of queue to process
    setTimeout(function() {
        var word = "";
        if (text.indexOf(' ') >= 0) {
            word = text.split(' ').pop();
        } else {
            word = text;  // If there are no spaces, then use the word since it's first
        }

        var location;
        if ($(ob).prop('id') == "") {
            location = "textarea[name='"+$(ob).prop('name')+"']";  // since we can't get the name of the id, we're gonna get the name of the name= in the textarea
        } else {
            location = '#'+$(ob).prop('id');  // get name of id
        }

        var location_plain = location.replace(/^\#/, "");
        var elems = $(".fs-item");
        for (var i=0; i < elems.length; i++)
        {
            if (elems[i].id && (elems[i].id.match(/^LSC_id_/)) && (!elems[i].id.match(location_plain)))
            {
                $("#"+elems[i].id).hide();
            }
        }

        var elem = $("#LSC_id_"+location_plain);

        if (!elem.length) return;

        // If there are spaces then grab the last word and change the value of 'text' to be equal to the last word

        // Now that we have the word we want to autocomplete, let's run some tests
        // If the last word is a space, then abort
        if (trim(word) == '') {
            logicSuggestHidetip($(ob).prop('id'));  // hide the tips
            return;
        }

        // If there's a left bracket in the word, that means we want to autocomplete it
        if ((word.indexOf('[') >= 0) && (!word.match(/\]\[[^\]^\s]+\]\[/)))
        {
            // Kill previous ajax instance (if running from previous keystroke)
            if (logicSuggestAjax !== null) {
                if (logicSuggestAjax.readyState == 1) logicSuggestAjax.abort();
            }
            // Ajax request
            logicSuggestAjax = $.post(app_path_webroot+'Design/logic_field_suggest.php?pid='+pid, { draft_mode: draft_mode, location: location_plain, word: word.substring(1,word.length)  }, function(data){
                // Position the element
                elem.html(data)
                    .show();
                elem.position({
                    my:        "left top",
                    at:        "left bottom",
                    of:        $(location),
                    collision: "fit"
                });
                // If nothing returned, then hide the suggest box
                if (data == '') logicSuggestHidetip($(ob).prop('id'));
            });
        } else {
            logicSuggestHidetip($(ob).prop('id')); // There is not a left bracket in the word, so hide the box
        }
    }, 0);
}

// event and field are only applicable to calc fields; can be blank for branching
function logicCheck(logic_ob, type, longitudinal, field, rec, mssg, err_mssg, invalid, action, logic_ob_id_opt)
{
    var logic_ob_id = $(logic_ob).prop('id');
    if (!logic_ob_id)
        logic_ob_id = logic_ob_id_opt;
    if (rec !== "")
    {
        setTimeout(function() {
            var res_ob_id = logic_ob_id+"_res";
            if (!checkLogicErrors($(logic_ob).val(), false, longitudinal))
            {
                var page = "";
                var page = getParameterByName("page");
                if (type == "branching")
                    page = "Design/logic_test_record.php";
                else if (type == "calc")
                    page = "Design/logic_calc_test_record.php";
                var logic = $(logic_ob).val();
                var hasrecordevent = ($(logic_ob).attr('hasrecordevent') == '1') ? 1 : 0;
                if ($("#"+res_ob_id))
                {
                    $.post(app_path_webroot+page+"?pid="+pid, { hasrecordevent: hasrecordevent, record: rec, logic: logic }, function(data) {
                        if (data !== "")
                        {
                            if (data.match("ERROR"))
                            {
                                $("#"+res_ob_id).html(data);
                            }
                            else if (typeof mssg != "undefined")
                            {
                                if (data.toString().match(/hide/i))
                                    $("#"+res_ob_id).html(mssg+" "+action[1]);
                                else if (data.toString().match(/show/i))
                                    $("#"+res_ob_id).html(mssg+" "+action[0]);
                                else
                                    $("#"+res_ob_id).html(mssg+" "+data.toString());
                            }
                            else
                            {
                                $("#"+res_ob_id).html(data.toString());
                            }
                        }
                        else
                        {
                            $("#"+res_ob_id).html("["+action[2]+"]");
                        }
                    });
                }
            }
            else
            {
                $("#"+res_ob_id).html(invalid);
            }
        }, 0);
    }
}

function showInstrumentsToggle(ob,collapse) {
    var targetid = 'show-instruments-toggle';
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:saveShowInstrumentsToggle',{ object: 'sidebar', targetid: targetid, collapse: collapse },function(data){
        if (data == '0') { alert(woops);return; }
        if (collapse == 0) {
            $('.formMenuList').removeClass('hidden');
        } else {
            $('.formMenuList').addClass('hidden');
        }
        $('a.show-instruments-toggle').removeClass('hidden');
        $(ob).addClass('hidden');
    });
}


function logicValidate(ob, longitudinal, forceMetadataTable) {
    // Force it to look at the metadata table only (instead of metadata temp if in prod draft mode)?
    if (typeof forceMetadataTable == "undefined") {
        forceMetadataTable = 1;
    }
    var mssg = '<span class="logicValidatorOkay"><img src="'+app_path_images+'tick_small.png">Valid</span>';
    var err_mssg = "<span class='logicValidatorOkay'><img style='position:relative;top:-1px;margin-right:4px;' src='"+app_path_images+"cross_small2.png'>Error in syntax</span>";
    // timeout to let new text value to enter field;
    // just to back of queue to process
    setTimeout(function() {
        var logic = trim($(ob).val());
        var b = checkLogicErrors(logic, false, longitudinal);
        var ob_id = $(ob).prop('id');
        var confirm_ob_id = ob_id + "_Ok";
        var confirm_ob = "#"+confirm_ob_id;
        if ($(confirm_ob))
        {
            if (b || logic == '') {   // obvious errors or nothing
                $(confirm_ob).html("");
            } else {
                // If logic ends with any of these strings, then don't display OK or ERROR (to prevent confusion mid-condition)
                var allowedEndings = new Array(' and', ' or', '=', '>', '<');
                for (var i=0; i<allowedEndings.length; i++) {
                    if (ends_with(logic, allowedEndings[i])) {
                        $(confirm_ob).html("");
                        return;
                    }
                }
                // Kill previous ajax instance (if running from previous keystroke)
                if (logicSuggestAjax !== null) {
                    if (logicSuggestAjax.readyState == 1) logicSuggestAjax.abort();
                }
                // Check via AJAX if logic is really true
                logicSuggestAjax = $.post(app_path_webroot+'Design/logic_validate.php?pid='+pid, { logic: logic, forceMetadataTable: forceMetadataTable }, function(data){
                    if (data == '1') {
                        $(confirm_ob).css({"color": "green"}).html(mssg);
                    } else {
                        $(confirm_ob).css({"color": "red"}).html(err_mssg);
                    }
                });
            }
        }
    }, 100);
}


function logicSuggestClick(text, location) {
    // Is the element an iframe?
    var isIframe = ($("#"+location).prop("tagName").toLowerCase() == 'iframe');

    if (isIframe) {
        // TinyMCE Editor
        var originalText = $("#"+location).contents().find('body').html();
        var originalTextNoTags = strip_tags(originalText);
        var lastLeftBracket = originalText.lastIndexOf("[");
        var lastLeftBracketNoTags = originalTextNoTags.lastIndexOf("[");
        var originalTextA = originalText.substring(0, lastLeftBracket);
        var originalTextB = originalText.substring(lastLeftBracket);
        var Match = originalTextNoTags.substring(lastLeftBracketNoTags);
        for (var i = 0; i < tinymce.editors.length; i++) {
            var editor = tinymce.editors[i];
            if (location == $(editor.iframeElement).prop('id')) {
                editor.setContent(originalTextA + text + originalTextB.substring(Match.length));
            }
        }
    } else {
        var originalText = $("#"+location).val();
        var lastLeftBracket = originalText.lastIndexOf("[");
        $("#" + location).val(originalText.substring(0, lastLeftBracket) + text);

        // Rerun the validation
        logicValidate($("#"+location), true);

        // must disable any additional checking in onblur before resetting focus
        var onblur_ev = $("#"+location).attr("onblur");
        $("#"+location).removeAttr("onblur");
        setTimeout(function() {
            $("#"+location).attr("onblur", onblur_ev);
            $("#"+location).focus();
        }, 100);
    }
    logicSuggestHidetip(location);  // hide the tips
}

function logicSuggestHidetip(location) {
    $("#LSC_id_"+location).hide();
    var elems = $(".fs-item");
    for (var i=0; i < elems.length; i++)
    {
        if (elems[i].id && ((elems[i].id.match("LSC_fn_"+location+"_")) || (elems[i].id.match("LSC_ev_"+location+"_"))))
        {
            $("#"+elems[i].id).hide();
        }
    }
}

function logicSuggestShowtip(location) {
    var elems = $(".fs-item");
    $("#LSC_id_"+location).show();
    $("#LSC_id_"+location).css({ position: "absolute", zIndex: "1000000" });
    for (var i=0; i < elems.length; i++)
    {
        if (elems[i].id && ((elems[i].id.match("LSC_fn_"+location+"_")) || (elems[i].id.match("LSC_ev_"+location+"_"))))
        {
            $("#"+elems[i].id).show();
        }
    }
}

function logicHideSearchTip(ob) {
    var location = $(ob).prop('id');  // get name of id
    if (document.getElementById("LSC_id_"+location))
    {
        $("#LSC_id_"+location).hide();
    }
}

// Validate the Automated Survey Invitation logic
function validate_auto_invite_logic(ob,evalOnSuccess) {
    var dfd = $.Deferred();
    // Get logic as value of object passed
    var logic = ob.val();
    // First, make sure that the logic is not blank
    if (trim(logic).length < 1) return dfd.resolve(true);
    // Make ajax request to check the logic via PHP
    $.post(app_path_webroot+'Surveys/automated_invitations_check_logic.php?pid='+pid, { logic: logic }, function(data){
        if (data == '0') {
            alert(woops);
            dfd.reject(data);
        } else if (data == '1') {
            // Success
            dfd.resolve(data);
            if (evalOnSuccess != null) eval(evalOnSuccess);
        } else {
            // Error msg - problems in logic to fix
            simpleDialog(data,null,null,null,"$('#"+ob.attr('id')+"').focus();");
            dfd.reject(data);
        }
    });
    return dfd;
}

// Create a new DD snapshot via AJAX
function createDataDictionarySnapshot() {
    $.post(app_path_webroot+'Design/data_dictionary_snapshot.php?pid='+pid,{},function(data){
        if (data == '0') {
            alert(woops);
        } else {
            $('#dd_snapshot_btn').attr('disabled','disabled').addClass('opacity65');
            $('#last_dd_snapshot_ts').html(data);
            $('#dd_snapshot_btn img:first').prop('src',app_path_images+'tick.png');
            $('#last_dd_snapshot').effect('highlight',{},3000);
        }
    });
}

function cancelRequest(pid,reqName,ui_id){
    areYouSure(function(res){
        if(res === 'yes'){
            $.post(app_path_webroot+'ToDoList/todo_list_ajax.php',
                { action: 'delete-request', pid: pid, ui_id: ui_id, req_type: reqName },
                function(data){
                    if (data == '1'){
                        if (reqName == 'move to prod') {
                            window.location.href = app_path_webroot+page+'?pid='+pid;
                        } else {
                            window.location.reload();
                        }
                    }
                });
        }
    });
}

// Change default behavior of the multi-select boxes so that they are more intuitive to users when selecting/de-selecting options
function modifyMultiSelect(multiselect_jquery_object, option_css_class) {
    if (option_css_class == null) option_css_class = 'ms-selection';
    // Add classes to options in case some are already pre-selected on page load
    multiselect_jquery_object.find('option:selected').addClass(option_css_class);
    // Set click trigger to add class to whichever option is clicked and then manually select it
    multiselect_jquery_object.click(function(event){
        var obparent = $(this);
        var ob = obparent.find('option[value="'+event.target.value+'"]');
        if (!ob.hasClass(option_css_class)) {
            ob.addClass(option_css_class);
        } else {
            ob.removeClass(option_css_class);
        }
        $('option:not(.'+option_css_class+')', obparent).prop('selected', false);
        $('option.'+option_css_class, obparent).prop('selected', true);
    });
}

// Load ajax call into dialog to analyze a survey for use as SMS/Voice Call survey
function dialogTwilioAnalyzeSurveys() {
    $.post(app_path_webroot+'Surveys/twilio_analyze_surveys.php?pid='+pid, { }, function(data){
        var json_data = jQuery.parseJSON(data);
        if (json_data.length < 1) {
            alert(woops);
            return false;
        }
        var dlg_id = 'tas_dlg';
        $('#'+dlg_id).remove();
        initDialog(dlg_id);
        $('#'+dlg_id).html(json_data.popupContent);
        simpleDialog(null,json_data.popupTitle,dlg_id,700);
    });
}

// AJAX call to regenerate API token
function regenerateToken() {
    $.post(app_path_webroot + "API/project_api_ajax.php?pid="+pid,{ action: "regenToken" },function (data) {
        simpleDialog(data);
        $.get(app_path_webroot + "API/project_api_ajax.php",{ action: 'getToken', pid: pid },function(data) {
            $("#apiTokenId").html(data);
        });
    });
}

// AJAX call to delete API token
function deleteToken() {
    $.post(app_path_webroot + "API/project_api_ajax.php?pid="+pid,{ action: "deleteToken" },function (data) {
        simpleDialog(data,null,null,400,function(){
            if (page == 'MobileApp/index.php') {
                window.location.reload();
            }
        });
        $.get(app_path_webroot + "API/project_api_ajax.php",{ action: 'getToken', pid: pid },function(data) {
            if (page != 'MobileApp/index.php') {
                if (data.length == 0) {
                    $("#apiReqBoxId").show();
                    $("#apiTokenBoxId").hide();
                    $("#apiTokenId, #apiTokenUsersId").html("");
                } else {
                    $("#apiTokenId").html(data);
                }
            }
        });
    });
}

// AJAX call to request API token from admin
function requestToken(autoApprove) {
    $.post(app_path_webroot +'API/project_api_ajax.php?pid='+pid,{ action: 'requestToken' },function (data) {
        if (autoApprove == '1' || super_user || AUTOMATE_ALL == '1') {
            window.location.reload();
        } else {
            $('.chklistbtn .jqbuttonmed, .yellow .jqbuttonmed').prop('disabled', true)
                .addClass('api-req-pending')
                .css('color','grey');
            $('.api-req-pending').parent().append('<p class="api-req-pending-text">Request pending</p>');
            simpleDialog(data);
            if($('.mobile-token-alert-text').length != 0){
                $('.mobile-token-alert-text').remove();
            }else{
                $('.chklistbtn .api-req-pending').text('Request Api token');
                $('api-req-pending span, .mobile-token-alert-text').remove();
            }
        }
    });
}

// Display explanation dialog for survey participant's invitation delivery preference
function deliveryPrefExplain() {
    // Get content via ajax
    $.get(app_path_webroot+'Surveys/delivery_preference_explain.php',{ pid: pid }, function(data){
        if (data == "") {
            alert(woops);
        } else {
            // Decode JSON
            var json_data = jQuery.parseJSON(data);
            simpleDialog(json_data.content, json_data.title, null, 600);
        }
    });
}

// Survey Reminder related setup
function initSurveyReminderSettings() {
    // Option up reminder options
    $('#enable_reminders_chk').click(function(){
        if ($(this).prop('checked')) {
            $('#reminders_text1').show();
            $('#reminders_choices_div').show('fade',function(){
                // Try to reposition each dialog (depending on which page we're on)
                if ($('#emailPart').length) {
                    fitDialog($('#emailPart'));
                    $('#emailPart').dialog('option','position','center');
                }
                if ($('#popupSetUpCondInvites').length) {
                    fitDialog($('#popupSetUpCondInvites'));
                    $('#popupSetUpCondInvites').dialog('option','position','center');
                }
                if ($('#inviteFollowupSurvey').length) {
                    fitDialog($('#inviteFollowupSurvey'));
                    $('#inviteFollowupSurvey').dialog('option','position','center');
                }
            });
        } else {
            $('#reminders_text1').hide();
            $('#reminders_choices_div').hide('fade',{ },200);
        }
    });
    // Disable recurrence option if using exact time reminder
    $('#reminders_choices_div input[name="reminder_type"]').change(function(){
        if ($(this).val() == 'EXACT_TIME') {
            $('#reminders_choices_div select[name="reminder_num"]').val('1').prop('disabled', true);
        } else {
            $('#reminders_choices_div select[name="reminder_num"]').prop('disabled', false);
        }
    });
    // Enable exact time reminder's datetime picker
    $('#reminders_choices_div .reminderdt').datetimepicker({
        onClose: function(dateText, inst){ $('#'+$(inst).attr('id')).blur(); },
        buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
        hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
        showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
    });
}

// Validate the surveys reminders options
function validateSurveyRemindersOptions() {
    // If not using reminders, return true to skip it
    if (!$('#enable_reminders_chk').prop('checked')) return true;
    // Is reminder option chosen?
    var reminder_type = $('#reminders_choices_div input[name="reminder_type"]:checked').val();
    if ((reminder_type == 'NEXT_OCCURRENCE' && ($('#reminders_choices_div select[name="reminder_nextday_type"]').val() == ''
        || $('#reminders_choices_div input[name="reminder_nexttime"]').val() == ''))
        || (reminder_type == 'TIME_LAG' && $('#reminders_choices_div input[name="reminder_timelag_days"]').val() == ''
            && $('#reminders_choices_div input[name="reminder_timelag_hours"]').val() == ''
            && $('#reminders_choices_div input[name="reminder_timelag_minutes"]').val() == '')
        || (reminder_type == 'EXACT_TIME' && $('#reminders_choices_div input[name="reminder_exact_time"]').val() == '')
        || reminder_type == null)
    {
        // Get fieldset title
        var reminder_title = $('#reminders_choices_div').parents('fieldset:first').find('legend:first').html();
        // Display error msg
        simpleDialog("<div style='color:#C00000;font-size:13px;'><img src='"+app_path_images+"exclamation.png'> ERROR: If you are enabling reminders, please make sure all reminder choices are selected. One or more options are not entered/selected.</div>", reminder_title, null, 400);
        return false;
    }
    return true;
}

// Generate a survey Quick code and QR code and open dialog window
function getAccessCode(hash,shortCode) {
    // Id of dialog
    var dlgid = 'genQSC_dialog';
    // Get short code?
    if (shortCode != '1') shortCode = 0;
    // Show progres icon for short code generation
    if (shortCode) $('#gen_short_access_code_img').show();
    // Get content via ajax
    $.post(app_path_webroot+'Surveys/get_access_code.php?pid='+pid+'&hash='+hash+'&shortCode='+shortCode,{ }, function(data){
        if (data == "0") {
            alert(woops);
            return;
        }
        // Decode JSON
        var json_data = jQuery.parseJSON(data);
        // Put short code in input box
        if (shortCode) {
            $('#short_access_code_expire').html(json_data.expiration);
            $('#short_access_code').val(json_data.code);
            $('#short_access_code_div').show().effect('highlight',{},2000);
            $('#gen_short_access_code_div').hide();
        } else {
            // Add html
            initDialog(dlgid);
            $('#'+dlgid).html(json_data.content);
            // If QR codes are not being displayed, then make the dialog less wide
            var dwidth = ($('#'+dlgid+' #qrcode-info').length) ? 800 : 600;
            // Display dialog
            $('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: dwidth, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); },
                buttons: [{
                    text: "Close", click: function(){ $(this).dialog('close'); }
                }, {
                    text: "Print for Respondent", click: function(){
                        window.open(app_path_webroot+'ProjectGeneral/print_page.php?pid='+pid+'&action=accesscode&hash='+hash,'myWin','width=850, height=600, toolbar=0, menubar=1, location=0, status=0, scrollbars=1, resizable=1');
                    }
                }]
            });
            $('#'+dlgid).parent().find('div.ui-dialog-buttonpane button:eq(1)').css({'font-weight':'bold','color':'#222'});
            // Init buttons
            initButtonWidgets();
        }
    });
}

// On data export page, display/hide the Send-It option for each export type
function displaySendItExportFile(doc_id) {
    $('#sendit_'+doc_id+' div').each(function(){
        if ($(this).css('visibility') == 'hidden') $(this).hide();
    });
    $('#sendit_'+doc_id).toggle('blind',{},'fast');
}

// Initialize a "fake" drop-down list (like a button to reveal a "drop-down" list)
function showBtnDropdownList(ob,event,list_div_id) {
    // Prevent $(window).click() from hiding this
    try {
        event.stopPropagation();
    } catch(err) {
        window.event.cancelBubble=true;
    }
    // Set drop-down div object
    var ddDiv = $('#'+list_div_id);
    // If drop-down is already visible, then hide it and stop here
    if (ddDiv.css('display') != 'none') {
        ddDiv.hide();
        return;
    }
    // Set width
    if (ddDiv.css('display') != 'none') {
        var ebtnw = $(ob).width();
        var eddw  = ddDiv.width();
        if (eddw < ebtnw) ddDiv.width( ebtnw );
    }
    // Set position
    var btnPos = $(ob).offset();
    ddDiv.show().offset({ left: btnPos.left, top: (btnPos.top+$(ob).outerHeight()) });
}

// Display DDP explanation dialog
function ddpExplainDialog(fhir) {
    initDialog('ddpExplainDialog');
    var dialogHtml = $('#ddpExplainDialog').html();
    if (dialogHtml.length > 0) {
        $('#ddpExplainDialog').dialog('open');
    } else {
        fhir = (fhir == '1') ? '?type=fhir' : '';
        $.get(app_path_webroot+'DynamicDataPull/info.php'+fhir,{ },function(data) {
            var json_data = jQuery.parseJSON(data);
            $('#ddpExplainDialog').html(json_data.content).dialog({ bgiframe: true, modal: true, width: 750, title: json_data.title,
                open: function(){ fitDialog(this); },
                buttons: {
                    Close: function() { $(this).dialog('close'); }
                }
            });
        });
    }
}

// Enable fixed table headers for event grid
var rcDataTable;
function enableFixedTableHdrs(table_id,ordering,searching,searchDom) {
    var num_cols = $('#'+table_id+' th').length;
    if (!$('#'+table_id).length || !num_cols) return;
    // Set params
    if (typeof ordering == "undefined") ordering = false;
    if (typeof searching == "undefined") searching = false;
    if (typeof searchDom == "undefined") searchDom = false;
    // Check height and width of table to see if we should even try to enable floating
    var window_width = $(window).width();
    var table_width  = $('#'+table_id).width();
    var window_height = $(window).height();
    var table_height  = $('#'+table_id).height();
    floatFirstCol = (table_width > window_width*0.9);
    floatFirstRow = (table_height > window_height*0.9);
    // If table is too big, then don't perform fixed header or column
    var IEfudge = isIE ? (IEv < 10 ? 3 : 2) : 1; // Set fudge factor for IE, which is weak
    if (floatFirstRow && num_cols > (3000/IEfudge)) {
        floatFirstRow = false;
    }
    if (floatFirstCol && (num_cols > (2000/IEfudge) || (num_cols > (500/IEfudge) && num_cols*$('#'+table_id+' tr').length > (200000/IEfudge)))) {
        floatFirstCol = false;
    }
    // Try to destroy data table object if already exists
    var forceDataTable = false;
    try {
        rcDataTable.destroy();
        forceDataTable = true;
    } catch(e) { }
    // Get original table position (prior to enabling DataTables)
    var table_pos = $('#'+table_id).position();
    // If this DataTable is disabled, then set params to false
    if (DataTableDisabled(table_id)) {
        forceDataTable = floatFirstCol = floatFirstRow = false;
        // Display link to reenable DataTable
        renderDisableDTlink(table_pos.top, table_id, 1, searching, searchDom);
    }
    // If nothing to do, then leave
    if (!forceDataTable && !floatFirstCol && !floatFirstRow && !searching && !ordering) return;
    // Set table params
    var dataTableParams = {
        "autoWidth": false,
        "processing": true,
        "paging": false,
        "info": false,
        "aaSorting": [],
        "fixedHeader": { header: floatFirstRow, footer: false },
        // Configurable
        "searching": searching,
        "ordering": ordering
    };
    if (searching) {
        // Set search label to ""
        $.extend(dataTableParams, {
            "oLanguage": { "sSearch": "" }
        });
    }
    if (floatFirstCol) {
        $.extend(dataTableParams, {
            "fixedColumns": true,
            scrollY: (floatFirstRow ? round(window_height*0.7) : table_height)+"px",
            scrollX: true
        });
    }
    try {
        // Enable the data table
        rcDataTable = $('#'+table_id).DataTable(dataTableParams);
        // Set width of scrollable area if we're fixing the first column
        if (floatFirstCol) {
            $('#report_div .dataTables_scroll').width( window_width-($('#west').length ? $('#west').width() : 0)-60 );
        }
        // DataTables prevents Backspace key from being used in search, so change type to "text"
        if (searching) {
            $('#'+table_id+'_filter.dataTables_filter input[type="search"]').attr('type','text').prop('placeholder','Search');
            if (searchDom && $(searchDom).length) {
                $(searchDom).append('<div class="row mt-1"><div class="col-sm-6"></div><div class="col-sm-6"><div class="dataTables_filter-parent"></div></div></div>');
                $(searchDom+' .dataTables_filter-parent').append( $('.dataTables_filter:first').detach() );
            } else if (searchDom && !$(searchDom).length && ($(window).width() > (800+$('#west').width())) && $('#'+table_id).width() < 800) {
                // Prevent search box from being placed too far to right when table is narrow
                $('div.dataTables_filter:first').css({'float': 'left', 'margin-left': '550px' });
            }
            // Deal with footer position when typing in search
            $('#'+table_id+'_filter.dataTables_filter input[type="text"]').keyup(function(){
                setProjectFooterPosition();
            });
        }
        // If this is a multipage report, then display message about sorting by header
        if (ordering && $('.report_sort_msg').length && $('.report_page_select option:selected').val() != 'ALL') {
            $('table.dataTable thead th').click(function(){
                $('.report_sort_msg:first').show();
            });
        }
        // Render link to disable/reenable DataTable
        if (floatFirstCol || floatFirstRow) renderDisableDTlink(table_pos.top, table_id, ((floatFirstCol || floatFirstRow) ? 0 : 1), searching, searchDom);
    } catch(e) {
        // Restripe table rows if failed (in case they didn't get added)
        if ($('#'+table_id).hasClass('dataTable')) {
            $('#'+table_id+' tbody tr').removeClass('even').removeClass('odd');
            $('#'+table_id+' tbody tr:odd').addClass('even');
            $('#'+table_id+' tbody tr:even').addClass('odd');
        }
    }
}

// Render link to disable/re-enable DataTable
function renderDisableDTlink(table_pos_top, table_id, disable, hasSearchInput, searchDom) {
    // Set params
    var linkText = (disable == '0') ? 'Table not displaying properly' : 'Re-enable floating table headers';
    var html = '<span id="FixedTableHdrsEnable"><a href="javascript:;" style="text-decoration:underline;" onclick="disableFixedTableHdrs(\''+table_id+'\','+disable+');return false;">'+linkText+'</a><a href="javascript:;" class="help" onclick="simpleDialog(\'On certain occasions, the table on this page might not display properly but might have its columns or rows appear misaligned in some way, thus making it difficult to view the table or navigate it well. If you click the &quot;Table not displaying properly&quot; link, it will disable the floating headers for the table, causing it to be displayed in a more viewable format.\',\'Is the table not displaying properly?\');">?</a></span>';
    // Create new link on the page
    if (searchDom) {
        setTimeout(function(){
            $('#'+table_id+'_filter.dataTables_filter').append(html);
            $('#FixedTableHdrsEnable').show().css({'position': 'relative'});
        },100);
    } else {
        // Position the link
        $('body').append(html);
        var span_pos_top  = (table_pos_top-$('#FixedTableHdrsEnable').outerHeight(true)-25);
        var span_pos_left = ($(window).width()-$('#FixedTableHdrsEnable').outerWidth(true)-35);
        // Display the link
        $('#FixedTableHdrsEnable').show().css({'top': span_pos_top+'px', 'left': span_pos_left+'px'});
    }
}

// Disable fixed header/column on a table
function disableFixedTableHdrs(table_id, disableDT) {
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:saveShowInstrumentsToggle',{ object: 'datatables_disable', targetid: table_id, collapse: disableDT },function(data){
        if (data == '0') { alert(woops);return; }
        showProgress(1);
        window.location.reload();
    });
}

// Check if a DataTable has been hidden in the current project
function DataTableDisabled(table_id) {
    try {
        if (typeof datatables_disable[table_id] != "undefined") {
            return (datatables_disable[table_id] == '1');
        }
        return false;
    } catch(e) {
        return false;
    }
}

// Display Piping explanation dialog pop-up
function pipingExplanation() {
    // Get content via ajax
    $.get(app_path_webroot+'DataEntry/piping_explanation.php',{},function(data){
        var json_data = jQuery.parseJSON(data);
        simpleDialog(json_data.content,json_data.title,'piping_explain_popup',900);
        fitDialog($('#piping_explain_popup'));
    });
}

// Display explanation dialog pop-up for Field Embedding
function fieldEmbeddingExplanation() {
    // Get content via ajax
    $.get(app_path_webroot+'DataEntry/field_embedding_explanation.php',{},function(data){
        var json_data = jQuery.parseJSON(data);
        initDialog('field_embed_explain_popup');
        simpleDialog(json_data.content,json_data.title,'field_embed_explain_popup',900);
        fitDialog($('#field_embed_explain_popup'));
    });
}

// Send single email
function sendSingleEmail(from,to,subject,message,showDialogSuccess,evalJs) {
    if (evalJs == null) evalJs = '';
    if (showDialogSuccess == null) showDialogSuccess = false;
    var this_pid = getParameterByName('pid');
    var url_pid = (isNumeric(this_pid)) ? '?pid='+this_pid : ''; // If within a project, send project_id
    $.post(app_path_webroot+'ProjectGeneral/send_single_email.php'+url_pid,{from:from,to:to,subject:subject,message:message},function(data){
        if (data != '1') {
            alert(woops);
        } else {
            if (showDialogSuccess) simpleDialog("Your email was successfully sent to <a style='text-decoration:underline;' href='mailto:"+to+"'>"+to+"</a>.","EMAIL SENT!");
            if (evalJs != '') eval(evalJs);
        }
    });
}

// Do quick check if logic errors exist in string (not very extensive)
// - used for both Data Quality and Automated Survey Invitations
function checkLogicErrors(brStr,display_alert,forceEventNotationForLongitudinal) {
    var brErr = false;
    if (display_alert == null) display_alert = false;
    // If forceEventNotationForLongitudinal=true, then make sure that field_names are preceded with [event_name] for longitudinal projects
    if (forceEventNotationForLongitudinal == null) forceEventNotationForLongitudinal = false;
    var msg = "<b>ERROR! Syntax errors exist in the logic:</b><br>"
    if ((typeof brStr != "undefined") && (brStr.length > 0)) {
        // Must have at least one [ or ]
        // if (brStr.split("[").length == 1 || brStr.split("]").length == 1) {
        // msg += "&bull; Square brackets are missing. You have either not included any variable names in the logic or you have forgotten to put square brackets around the variable names.<br>";
        // brErr = true;
        // }
        // If longitudinal and forcing event notation for fields, then must be referencing events for variable names
        // if (longitudinal && forceEventNotationForLongitudinal && (brStr.split("][").length <= 1
        // || (brStr.split("][").length-1)*2 != (brStr.split("[").length-1)
        // || (brStr.split("][").length-1)*2 != (brStr.split("]").length-1))) {
        // msg += "&bull; One or more fields are not referenced by event. Since this is a longitudinal project, you must specify the unique event name "
        // + "when referencing a field in the logic. For example, instead of using [age], you must use [enrollment_arm1][age], "
        // + "assuming that enrollment_arm1 is a valid unique event name in your project. You can find a list of all your project's "
        // + "unique event names on the Define My Events page.<br>";
        // brErr = true;
        // }
        // Check symmetry of "
        if ((brStr.split('"').length - 1)%2 > 0) {
            msg += "&bull; Odd number of double quotes exist<br>";
            brErr = true;
        }
        // Check symmetry of '
        if ((brStr.split("'").length - 1)%2 > 0) {
            msg += "&bull; Odd number of single quotes exist<br>";
            brErr = true;
        }
        // Check symmetry of [ with ]
        if (brStr.split("[").length != brStr.split("]").length) {
            msg += "&bull; Square bracket is missing<br>";
            brErr = true;
        }
        // Check symmetry of ( with )
        if (brStr.split("(").length != brStr.split(")").length) {
            msg += "&bull; Parenthesis is missing<br>";
            brErr = true;
        }
        // Make sure does not contain $ dollar signs
        if (brStr.indexOf('$') > -1) {
            msg += "&bull; Illegal use of dollar sign ($). Please remove.<br>";
            brErr = true;
        }
        // Make sure does not contain ` backtick character
        if (brStr.indexOf('`') > -1) {
            msg += "&bull; Illegal use of backtick character (`). Please remove.<br>";
            brErr = true;
        }
    }
    // If errors exist, stop and show message
    if (brErr && display_alert) {
        simpleDialog(msg+"<br>You must fix all errors listed before you can save this logic.");
        return true;
    }
    return brErr;
}

// Open dialog to randomize a record
function randomizeDialog(record) {
    // Open dialog pop-up populated by ajax call content
    if (!$('#randomizeDialog').length) $('body').append('<div id="randomizeDialog" style="display:none;"></div>');
    // Get the dialog content via ajax first
    $.post(app_path_webroot+'Randomization/randomize_record.php?pid='+pid, { action: 'view', record: record }, function(data){
        if (data == '0') {
            alert(woops);
            return;
        }
        // Load dialog content
        $('#randomizeDialog').html(data);
        // Check if returned without error
        if (!$('#randomizeDialog #randomCriteriaFields').length) {
            // Open dialog
            $('#randomizeDialog').dialog({ bgiframe: true, modal: true, width: 750, open: function(){fitDialog(this)},
                title: '<i class="fas fa-random"></i> Cannot yet randomize '+table_pk_label+' "'+record+'"',
                buttons: {
                    Close: function() {
                        $(this).dialog('close');
                    }
                }
            });
            return;
        }
        // Check if we're on a data entry page
        var isDataEntryPage = (page == 'DataEntry/index.php');
        // Get arrays of criteria fields/events
        var critFldsCsv = $('#randomizeDialog #randomCriteriaFields').val();
        var critFlds = (critFldsCsv.length > 0) ? critFldsCsv.split(',') : new Array();
        var critEvtsCsv = $('#randomizeDialog #randomCriteriaEvents').val();
        var critEvts = (critEvtsCsv.length > 0) ? critEvtsCsv.split(',') : new Array();
        // Check if we're on a form right now AND if our criteria fields are present.
        // If so, copy in their current values (because they may not have been saved yet).
        if (isDataEntryPage) {
            for (var i=0; i<critFlds.length; i++) {
                var field = critFlds[i];
                var event = critEvts[i];
                // Only do for correct event
                if (event == event_id) {
                    if ($('#form select[name="'+field+'"]').length) {
                        // Drop-down
                        var fldVal = $('#form select[name="'+field+'"]').val();
                        $('#random_form select[name="'+field+'"]').val(fldVal);
                    } else if ($('#form :input[name="'+field+'"]').length) {
                        // Radio/YN/TF
                        var fldVal = $('#form :input[name="'+field+'"]').val();
                        // First unselect all, then loop to find the one to select
                        if ($('#random_form input[type="radio"][name="'+field+'"]').length) {
                            radioResetVal(field,'random_form');
                        }
                        $('#random_form input[name="'+field+'"]').val(fldVal);
                        if (fldVal != '' && $('#random_form input[type="radio"][name="'+field+'___radio"]').length) {
                            $('#random_form input[name="'+field+'___radio"]').each(function(){
                                if ($(this).val() == fldVal) {
                                    $(this).prop('checked',true);
                                }
                            });
                        }
                    }
                }
            }
            // If we're grouping by DAG and user is NOT in a DAG, then transfer DAG value from form to pop-up
            if ($('#form select[name="__GROUPID__"]').length && $('#random_form select[name="redcap_data_access_group"]').length) {
                $('#random_form select[name="redcap_data_access_group"]').val( $('#form select[name="__GROUPID__"]').val() );
            }
        }
        // Open dialog
        $('#randomizeDialog').dialog({ bgiframe: true, modal: true, width: 750, open: function(){fitDialog(this);if (isMobileDevice) fitDialog(this);},
            title: '<i class="fas fa-random"></i> Randomizing '+table_pk_label+' "'+record+'"',
            buttons: {
                Cancel: function() {
                    // Lastly, clear out dialog content
                    $('#randomizeDialog').html('');
                    $(this).dialog('close');
                },
                'Randomize': function() {
                    // Disable buttons so they can't be clicked multiple times
                    $('#randomizeDialog').parent().find('div.ui-dialog-buttonpane button').button('disable');
                    // Make sure all fields have a value
                    var critFldVals = new Array();
                    if ($('#randomizeDialog #random_form table.form_border tr').length) {
                        var fldsNoValCnt = 0;
                        // Loop through all strata fields
                        for (var i=0; i<critFlds.length; i++) {
                            var isDropDownField = $('#randomizeDialog #random_form select[name="'+critFlds[i]+'"]').length;
                            if (!isDropDownField && $('#randomizeDialog #random_form input[name="'+critFlds[i]+'"]').val().length < 1) {
                                // Radio/TF/YN w/o value
                                fldsNoValCnt++;
                            } else if (isDropDownField && $('#randomizeDialog #random_form select[name="'+critFlds[i]+'"]').val().length < 1) {
                                // Dropdown w/o value
                                fldsNoValCnt++;
                            } else {
                                critFldVals[i] = (isDropDownField ? $('#randomizeDialog #random_form select[name="'+critFlds[i]+'"]').val() : $('#randomizeDialog #random_form input[name="'+critFlds[i]+'"]').val());
                            }
                        }
                        // Also check DAG field, if exists
                        if ($('#random_form select[name="redcap_data_access_group"]').length && $('#random_form select[name="redcap_data_access_group"]').val().length < 1) {
                            fldsNoValCnt++;
                        }
                        // If any missing fields are missing a value, stop here and prompt user
                        if (fldsNoValCnt > 0) {
                            simpleDialog(fldsNoValCnt+" strata/criteria field(s) do not yet have a value. "
                                + "You must first provide them with a value before randomization can be performed.","VALUES MISSING FOR STRATA/CRITERIA FIELDS!");
                            // Re-eable buttons
                            $('#randomizeDialog').parent().find('div.ui-dialog-buttonpane button').button('enable');
                            return;
                        }
                    }
                    // AJAX call to save data and randomize record
                    $.post(app_path_webroot+'Randomization/randomize_record.php?pid='+pid+'&instance='+getParameterByName('instance'), { event_id: event_id, redcap_data_access_group: $('#random_form select[name="redcap_data_access_group"]').val(), existing_record: document.form.hidden_edit_flag.value, action: 'randomize', record: record, fields: critFlds.join(','), field_values: critFldVals.join(',') }, function(data){
                        if (data == '0') {
                            alert(woops);
                            // Re-eable buttons
                            $('#randomizeDialog').parent().find('div.ui-dialog-buttonpane button').button('enable');
                            return;
                        }
                        // Replace dialog content with response data
                        $('#randomizeDialog').html(data);
                        // Replace dialog buttons with a Close button
                        $('#randomizeDialog').dialog("option", "buttons", []);
                        fitDialog($('#randomizeDialog'));
                        // Initialize widgets
                        initWidgets();
                        // Replace Randomize button on left-hand menu
                        var success = $('#randomizeDialog #alreadyRandomizedTextWidget').length;
                        if (success) {
                            // Replace Randomize button on form with "Already Randomized" text and redisplay the field
                            $('#alreadyRandomizedText').html( $('#randomizeDialog #alreadyRandomizedTextWidget').html() );
                            $('#randomizationFieldHtml').show();
                            // If on data entry form and criteria fields are on this form, disable them and set their values
                            if (isDataEntryPage) {
                                // Set hidden_edit_flag to 1 (in case this is a new record)
                                $('#form :input[name="hidden_edit_flag"]').val('1');
                                // Loop through criteria fields
                                for (var i=0; i<critFlds.length; i++) {
                                    var field = critFlds[i];
                                    var fldVal = critFldVals[i];
                                    var event = critEvts[i];
                                    // Only do for correct event
                                    if (event == event_id) {
                                        if ($('#form select[name="'+field+'"]').length) {
                                            // Drop-down
                                            $('#form select[name="'+field+'"]').val(fldVal).prop('disabled',true);
                                            // Also set autocomplete input for drop-down (if using auto-complete)
                                            if ($('#form #rc-ac-input_'+field).length)
                                                $('#form #rc-ac-input_'+field).val( $('#form select[name="'+field+'"] option:selected').text() ).prop('disabled',true).parent().find('button.rc-autocomplete').prop('disabled',true);
                                        } else if ($('#form :input[name="'+field+'"]').length) {
                                            // Radio/YN/TF
                                            // First unselect all, then loop to find the one to select
                                            if ($('#form input[type="radio"][name="'+field+'"]').length) {
                                                radioResetVal(field,'form');
                                            }
                                            $('#form :input[name="'+field+'"]').val(fldVal);
                                            if (fldVal != '' && $('#form input[type="radio"][name="'+field+'___radio"]').length) {
                                                $('#form :input[name="'+field+'___radio"]').each(function(){
                                                    if ($(this).val() == fldVal) {
                                                        $(this).prop('checked',true);
                                                    }
                                                    // Disable it
                                                    $(this).prop('disabled',true);
                                                });
                                            }
                                            // Now hide the "reset value" link for this field
                                            $('#form tr#'+field+'-tr a.cclink').hide();
                                        }
                                    }
                                }
                                // Now set value for randomization field, if on this form
                                var fldVal = $('#randomizeDialog #randomizationFieldRawVal').val();
                                var field = $('#randomizeDialog #randomizationFieldName').val();
                                var event = $('#randomizeDialog #randomizationFieldEvent').val();
                                // Only do for correct event
                                if (event == event_id) {
                                    if ($('#form select[name="'+field+'"]').length) {
                                        // Drop-down
                                        $('#form select[name="'+field+'"]').val(fldVal).prop('disabled',true);
                                        // Also set autocomplete input for drop-down (if using auto-complete)
                                        if ($('#form #rc-ac-input_'+field).length)
                                            $('#form #rc-ac-input_'+field).val( $('#form select[name="'+field+'"] option:selected').text() ).prop('disabled',true).parent().find('button.rc-autocomplete').prop('disabled',true);
                                    } else if ($('#form :input[name="'+field+'"]').length) {
                                        // Radio/YN/TF
                                        // First unselect all, then loop to find the one to select
                                        radioResetVal(field,'form');
                                        $('#form :input[name="'+field+'"]').val(fldVal);
                                        $('#form :input[name="'+field+'___radio"]').each(function(){
                                            if ($(this).val() == fldVal) {
                                                $(this).prop('checked',true);
                                            }
                                            // Disable it
                                            $(this).prop('disabled',true);
                                        });
                                    }
                                }
                                // If we're grouping by DAG and user is NOT in a DAG, then transfer DAG value from pop-up back to form
                                // after randomizing AND also disabled the DAG drop-down to prevent someone changing it.
                                if ($('#form select[name="__GROUPID__"]').length && $('#randomizeDialog #redcap_data_access_group').length) {
                                    $('#form select[name="__GROUPID__"]').val( $('#randomizeDialog #redcap_data_access_group').val() );
                                    $('#form select[name="__GROUPID__"]').prop('disabled',true);
                                }
                            }
                            // Just in case we're using auto-numbering and current ID does not reflect saved ID (due to simultaneous users),
                            // change the record value on the page in all places.
                            $('#form :input[name="'+table_pk+'"], #form :input[name="__old_id__"]').val( $('#randomizeDialog #record').val() );
                            // Hide the duplicate randomization field label (if Left-Aligned)
                            $('.randomizationDuplLabel').hide();
                            // Now that record is randomized, run branching and calculations on form in case any logic is built off of fields used in randomization
                            calculate();
                            doBranching();
                        }
                    });
                }
            }
        });
        // Init any autocomplete dropdowns inside the randomization dialog
        if (isDataEntryPage) enableDropdownAutocomplete();
    });
}

// Show/hide options for various delivery methods when sending survye invitations
function setInviteDeliveryMethod(ob) {
    var val = $(ob).val();
    $('#compose_email_subject_tr, #compose_email_from_tr, #compose_email_form_fieldset, #compose_email_to_tr').show();
    $('.show_for_sms, .show_for_voice, .show_for_part_pref, #compose_phone_to_tr, #surveyLinkWarningDeliveryType').hide();
    if (val == 'VOICE_INITIATE') {
        $('#compose_email_subject_tr, #compose_email_from_tr, #compose_email_form_fieldset, #compose_email_to_tr').hide();
        $('.show_for_voice, #compose_phone_to_tr').show();
    } else if (val == 'SMS_INVITE_MAKE_CALL' || val == 'SMS_INVITE_RECEIVE_CALL' || val == 'SMS_INITIATE' || val == 'SMS_INVITE_WEB') {
        $('#compose_email_subject_tr, #compose_email_from_tr, #compose_email_to_tr').hide();
        $('.show_for_sms, #compose_phone_to_tr').show();
    } else if (val == 'PARTICIPANT_PREF') {
        $('.show_for_part_pref').show();
    }
    if ($('#inviteFollowupSurvey').length) {
        $('#inviteFollowupSurvey').dialog('option', 'position', 'center');
    }
    if (val != 'EMAIL' && val != 'SMS_INVITE_WEB' && val != 'PARTICIPANT_PREF' && val != 'VOICE_INITIATE') {
        $('#surveyLinkWarningDeliveryType').show();
    }
}

// Dynamics when setting email address in pop-up for inviting participant to finish a follow-up survey
function inviteFollowupSurveyPopupSelectEmail(ob) {
    var isDD = ($(ob).attr('id') == 'followupSurvEmailToDD');
    if (isDD) {
        $('#followupSurvEmailTo').val('');
    } else {
        $('#followupSurvEmailToDD').val('');
    }
}

// Dynamics when setting phone number in pop-up for inviting participant to finish a follow-up survey
function inviteFollowupSurveyPopupSelectPhone(ob) {
    var isDD = ($(ob).attr('id') == 'followupSurvPhoneToDD');
    if (isDD) {
        $('#followupSurvPhoneTo').val('');
    } else {
        $('#followupSurvPhoneToDD').val('');
    }
}

// Open pop-up for the Help & FAQ page (can specify section using # anchor)
function helpPopup(anchor) {
    window.open(app_path_webroot_full+'index.php?action=help&newwin=1'+(anchor == null ? '' : '#'+anchor),'myWin','width=850, height=600, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');
}

// When clicking through the External Links, do logging via ajax before sending to destination
function ExtLinkClickThru(ext_id,openNewWin,url,form) {
    $.post(app_path_webroot+'ExternalLinks/clickthru_logging_ajax.php?pid='+pid, { url: url, ext_id: ext_id }, function(data){
        if (data != '1') {
            alert(woops);
            return false;
        }
        if (!openNewWin) {
            if (form != '') {
                // Adv Link: Submit the form
                $('#'+form).submit();
            } else {
                // Simple Link: If not opening a new window, then redirect the current page
                window.location.href = url;
            }
        }
    });
}

// Graphical page: Show/hide plots and stats tables
function showPlotsStats(option,obj) {
    // Enable all buttons
    $('#showPlotsStatsOptions button').each(function(){
        $(this).prop('disabled',false);
        $(this).button('enable');
    });
    // Disable this button
    $(obj).button('disable');
    // Options
    if (option == 1) {
        // Plots only
        $('.descrip_stats_table, .gct_plot img').hide();
        $('.gct_plot, .plot-download-div').show();
        $('.plot-download-div button').css('display','inline-block');
    } else if (option == 2) {
        // Stats only
        $('.descrip_stats_table, .gct_plot img').show();
        $('.gct_plot, .plot-download-div').hide();
        $('.plot-download-div button').css('display','none');
    } else {
        // Plots+Stats
        $('.descrip_stats_table, .gct_plot, .plot-download-div').show();
        $('.plot-download-div button').css('display','inline-block');
        $('.gct_plot img').hide();
    }
    $('.hideforever').hide();
}

// Function to download data dictionary (give warning if project has any forms downloaded from Shared Library)
function downloadDD(draft,showLegal) {
    var url = app_path_webroot+'Design/data_dictionary_download.php?pid='+pid;
    if (draft) url += '&draft';
    if (showLegal) {
        if (!$('#sharedLibLegal').length) $('body').append('<div id="sharedLibLegal"></div>');
        $.get(app_path_webroot+'SharedLibrary/terms_of_use.php', { }, function(data){
            $('#sharedLibLegal').html(data);
            $('#sharedLibLegal').dialog({ bgiframe: true, modal: true, width: 600, title: 'REMINDER', buttons: {
                    Cancel: function() { $(this).dialog('close'); },
                    'I Agree with Terms of Use': function() { window.location.href = url; $(this).dialog('close'); }
                } });
        });
    } else {
        window.location.href = url;
    }
}

// Give message if PK field was changed on Design page
function update_pk_msg(reload_page,moved_source) {
    $.get(app_path_webroot+'Design/update_pk_popup.php', { pid: pid, moved_source: moved_source }, function(data) {
        if (data != '') { // Don't show dialog if no callback html (i.e. no records exist)
            initDialog("update_pk_popup",data);
            $('#update_pk_popup').dialog({title: langRecIdFldChanged, bgiframe: true, modal: true, width: 600, buttons: [
                    { text: langOkay, click: function () {
                            $(this).dialog('close');
                            if (reload_page != null) {
                                if (reload_page) window.location.reload();
                            }
                        }}
                ]});
        } else if (moved_source == 'form') {
            simpleDialog(form_moved_msg,null,'','','window.location.reload();');
        }
    });
}

// Open window for viewing survey
function surveyOpen(path,preview) {
    // Determine if showing a survey preview rather than official survey (default preview=false or 0)
    if (preview == null) preview = 0;
    if (preview != 1 && preview != 0) preview = 0;
    // Open window
    window.open(path+(preview ? '&preview=1' : ''),'_blank');
}

// Selecting logo for survey and check if an image
function checkLogo(file) {
    extension = getfileextension(file);
    extension = extension.toLowerCase();
    if (extension != "jpeg" && extension != "jpg" && extension != "gif" && extension != "png" && extension != "bmp") {
        $("#old_logo").val("");
        alert("ERROR: The file you selected is not an image file (e.g., GIF, JPG, JPEG, BMP, PNG). Please try again.");
    }
}

// Display explanation dialog pop-up to explain create/rename/delete record settings on User Rights
function userRightsRecordsExplain() {
    $.get(app_path_webroot+'UserRights/record_rights_popup.php', { pid: pid }, function(data) {
        if (!$('#recordsExplain').length) $('body').append('<div id="recordsExplain"></div>');
        $('#recordsExplain').html(data);
        $('#recordsExplain').dialog({ bgiframe: true, modal: true, title: 'User privileges pertaining to project records', width: 650, buttons: { Close: function() { $(this).dialog('close'); } } });
    });
}

// Submit form to import records
function importDataSubmit(require_change_reason) {

    // If data change reason is required for existing record, loop through each, check for text in each, and add to form for submission
    if (require_change_reason)
    {
        var count_empty = 0;
        $('.change_reason').each(function(){
            var row_num = $(this).prop('id').replace('reason-','');
            var this_reason = $('#reason-'+row_num).val();
            if (trim(this_reason) == "") {
                count_empty++;
            } else {
                $('#change-reasons-div').append("<input name='records[]' value='"+$('#record-'+row_num).html()+"'><input name='events[]' value='"+$('#event-'+row_num).html()+"'><textarea name='reasons[]'>"+this_reason+"</textarea>");
            }
        });
        if (count_empty > 0) {
            $('#change-reasons-div').html('');
            alert("You have not entered a 'reason for data changes' for "+count_empty+" records. Please supply a reason in the text box for each before you can continue.");
            return false;
        }
    }
    $('#uploadmain2').css('display','none');
    $('#progress2').css('display','block');
    return true;
}

// Toggle project left-hand menu sections
function projectMenuToggle(selector) {
    $(selector).click(function(){
        var divBox = $(this).parent().parent().find('.x-panel-bwrap:first');
        // Toggle the box
        divBox.toggle('blind','fast');
        // Toggle the image
        var toggleImg = $(this).find('img:first');
        if (toggleImg.prop('src').indexOf('toggle-collapse.png') > 0) {
            toggleImg.prop('src', app_path_images+'toggle-expand.png');
            var collapse = 1;
        } else {
            toggleImg.prop('src', app_path_images+'toggle-collapse.png');
            var collapse = 0;
        }
        // Send ajax request to save cookie
        $.post(app_path_webroot+'ProjectGeneral/project_menu_collapse.php?pid='+pid, { menu_id: $(this).prop('id'), collapse: collapse });
    });
}

// Initialization functions for normal project-level pages
function initPage() {
    // Exclude survey theme view page
    if (page == 'Surveys/theme_view.php') return;
    // Get window height
    var winHeight = $(window).height();
    if (isMobileDevice) {
        // Make sure the bootstrap navbar stays at top-right (wide pages can push it to the right)
        var winWidth = $(window).width();
        try {
            $('button.navbar-toggler:visible').each(function(){
                var btnRight = $(this).offset().left+80;
                if (btnRight > winWidth) {
                    $(this).css({'margin-right':(btnRight-winWidth)+'px'});
                }
            });
        } catch(err) {}
    } else if ($('#center').length) {
        // Set project footer position
        setProjectFooterPosition();
    }
    // Perform actions upon page resize
    window.onresize = function() {
        if (isMobileDevice) $('#south').hide();
        try{ displayFormSaveBtnTooltip(); }catch(e){}
        if (!$('#west').hasClass('d-md-block') && !isMobileDeviceFunc()) {
            toggleProjectMenuMobile($('#west'));
        }
        // Reset project footer position
        setProjectFooterPosition();
        // User Messaging msg window
        try{ calculateMessageWindowPosition(); }catch(e){}
    }
    // Add fade mouseover for "Edit instruments" and "Edit reports" links on project menu
    $("#menuLnkEditInstr, #menuLnkEditBkmrk, #menuLnkEditReports, .projMenuToggle, #menuLnkProjectFolders, #menuLnkSearchReports").mouseenter(function() {
        $(this).removeClass('opacity65');
        if (isIE) $(this).find("img").removeClass('opacity65');
    }).mouseleave(function() {
        $(this).addClass('opacity65');
        if (isIE) $(this).find("img").addClass('opacity65');
    });
    // Toggle project left-hand menu sections
    projectMenuToggle('.projMenuToggle');
    // Add fade mouseover for "Choose other record" link on project menu
    $("#menuLnkChooseOtherRec").mouseenter(function() {
        $(this).removeClass('opacity65');
    }).mouseleave(function() {
        $(this).addClass('opacity65');
    });
    // Reset project footer position when the page's height changes
    onElementHeightChange(document.body, function(){
        setProjectFooterPosition();
    });
    // Put focus on main window for initial scrolling (only works in IE)
    if ($('#center').length) document.getElementById('center').focus();
}

// Set project footer position
function setProjectFooterPosition() {
    var centerHeight = $('#center').height();
    var westHeight = $('#west').height();
    var winHeight = $(window).height();
    var hasScrollBar = ($(document).height() > winHeight);
    if ((hasScrollBar && (centerHeight > winHeight || westHeight > centerHeight))
        || (!hasScrollBar && centerHeight+$('#south').height() > winHeight))
    {
        if (westHeight > centerHeight) {
            $('#south').css({'position':'absolute','margin':'50px 0px 0px -1px','bottom':'-'+(westHeight - centerHeight)+'px'});
            $('#center').css('padding-bottom','60px');
        } else {
            $('#south').css({'position':'relative','margin':'50px 0px 0px -1px','bottom':'0px'});
            $('#center').css('padding-bottom','0px');
        }
    } else {
        var leftMargin = ($('#west').css('display') == 'none') ? 0 : 269;
        $('#south').css({'position':'fixed','margin':'0 0 0 '+leftMargin+'px','bottom':'0px'});
        $('#south').width( $(window).width()-(leftMargin == 0 ? 0 : 280) );
        $('#center').css('padding-bottom','60px');
    }
    $('#south').css('visibility','visible');
}

// Set form as unlocked (enabled fields, etc.)
function setUnlocked(esign_action) {
    var form_name = getParameterByName('page');
    // Bring back Save buttons
    $('#__SUBMITBUTTONS__-div').css('display','block');
    $('#__DELETEBUTTONS__-div').css('display','block');
    // Remove locking informational text
    $('#__LOCKRECORD__').prop('checked', false);
    $('#__ESIGNATURE__').prop('checked', false);
    $('#lockingts').html('').css('display','none');
    $('#unlockbtn').css('display','none');
    $('#lock_record_msg').css('display','none');
    // Remove lock icon from menu (if visible)
    $('img#formlock-'+form_name).hide();
    $('img#formesign-'+form_name).hide();
    // Hide e-signature checkbox if e-signed but user does not have e-sign rights
    if (lock_record < 2 && $('#esignchk').length) {
        $('#esignchk').hide().html('');
    }
    // Determine if user has read-only rights for this form
    var readonly_form_rights = !($('#__SUBMITBUTTONS__-div').length && $('#__SUBMITBUTTONS__-div').css('display') != 'none');
    if (readonly_form_rights) {
        $('#__LOCKRECORD__').prop('disabled', false);
        $('#__ESIGNATURE__').prop('disabled', false);
    } else {
        // Remove the onclick attribute from the lock record checkbox so that the next locking is done via form post
        $('#__LOCKRECORD__').removeAttr('onclick').attr('onclick','');
        $('#__ESIGNATURE__').removeAttr('onclick').attr('onclick','');
        // Unlock and reset all fields on form
        $(':input').each(function() {
            // Re-enable field UNLESS field is involved in randomization (i.e. has randomizationField class)
            if (!$(this).hasClass('randomizationField')) {
                // Enable field
                $(this).prop('disabled', false);
            }
        });
        // Make radio "reset" link visible again
        $('.cclink').each(function() {
            // Re-enable link UNLESS field is involved in randomization (i.e. has randomizationField class)
            if (!$(this).hasClass('randomizationField')) {
                // Enable field
                $(this).css('display','block');
            }
        });
        // Enable "Randomize" button, if using randomization
        $('#redcapRandomizeBtn').removeAttr('aria-disabled').removeClass('ui-state-disabled').prop('disabled', false);
        // Add all options back to Form Status drop-down, and set value back afterward
        var form_status_field = $(':input[name='+form_name+'_complete]');
        var form_val = form_status_field.val();
        var sel = ' selected ';
        form_status_field
            .find('option')
            .remove()
            .end()
            .append('<option value="0"'+(form_val==0?sel:'')+'>Incomplete</option><option value="1"'+(form_val==1?sel:'')+'>Unverified</option><option value="2"'+(form_val==2?sel:'')+'>Complete</option>');
        // If editing a survey response, do NOT re-enable the Form Status field
        if (getParameterByName('editresp') == "1") form_status_field.prop("disabled",true);
        // Enable green row highlight for data entry form table
        enableDataEntryRowHighlight();
        // Re-display the save form buttons tooltip
        displayFormSaveBtnTooltip();
        //re-display missing data buttons
        $('.missingDataButton').show();
        // Enable sliders
        $('.slider').each(function(index,item){
            $(item).attr('locked','0');
            var field = $(item).prop('id').substring(7);
            $("#slider-"+field).attr('onmousedown',"enableSldr('"+field+"');$(this).attr('modified','1');");
            if ($(item).attr('modified') == '1') {
                enableSldr(field);
            }
        });
    }
    // Check for e-sign negation
    var esign_msg = "";
    if (esign_action == "negate") {
        $('#esignts').hide();
        $('#esign_msg').hide();
        $('#__ESIGNATURE__').prop('checked', false);
        esign_msg = ", and the existing e-signature has been negated";
    }
    // Give confirmation
    simpleDialog("This form has now been unlocked"+esign_msg+". Users can now modify the data again on this form.","UNLOCK SUCCESSFUL!");
}

// Lock/Unlock records for entire record
function lockUnlockFormsDo(fetched, fetched2, lock, arm) {
    if (lock == 'lock') {
        var alertmsg = lang.global_49+' "'+fetched2+'" '+lang.data_entry_478;
    } else if (lock == 'unlock') {
        var alertmsg = lang.global_49+' "'+fetched2+'" '+lang.data_entry_479;
    } else {
        return;
    }
    showProgress(1);
    $.get(app_path_webroot+'Locking/all_forms_action.php', { pid: pid, id: fetched, action: lock, arm: arm },
        function(data) {
            showProgress(0, 0);
            if (data == "1") {
                Swal.fire(
                    alertmsg, lang.create_project_97, 'success'
                );
                setTimeout('showProgress(1);', 2500);
                setTimeout('window.location.reload();', 3000);
            } else {
                alert(woops);
            }
        }
    );
}
function lockUnlockForms(fetched, fetched2, event_id, arm, grid, lock) {
    var showLockConfirmationDialog = ($('#recordLockPdfConfirmDialog').length && lock == 'lock');
    var lockConfirmationPdfUrl = app_path_webroot+"index.php?route=PdfController:index&pid="+pid+"&id="+fetched+"&__noLogPDFSave=1&compact=1&display=inline";
    if (showLockConfirmationDialog) {
        simpleDialog(null,null,'recordLockPdfConfirmDialog',800,'',lang.global_53,"lockUnlockFormsDo('"+fetched+"','"+fetched2+"','"+lock+"','"+arm+"');",lang.data_entry_482);
        $('#record_lock_pdf_confirm_iframe').attr('src', lockConfirmationPdfUrl);
        $('#record_lock_pdf_confirm_iframe').parent().attr('data', lockConfirmationPdfUrl);
        fitDialog($('#recordLockPdfConfirmDialog'));
        $('#record_lock_pdf_confirm_checkbox_label').removeClass('opacity50');
        $('#record_lock_pdf_confirm_checkbox_div').removeClass('green').addClass('yellow');
        $('#record_lock_pdf_confirm_checkbox').prop('checked',false);
        showProgress(1);
        setTimeout(function(){
            showProgress(0,0);
            $('#record_lock_pdf_confirm_checkbox').prop('disabled',false);
        },1000);
        $('#recordLockPdfConfirmDialog').parent().find('.ui-dialog-buttonpane button:eq(1)').prop('disabled',true).addClass('opacity50');
        return;
    } else if (lock == 'lock') {
        var prompt = lang.data_entry_480+' "<b>'+fetched2+'</b>"'+lang.questionmark+' '+lang.data_entry_484;
        var btn = lang.data_entry_482;
    } else if (lock == 'unlock') {
        var prompt = lang.data_entry_481+' "<b>'+fetched2+'</b>"?';
        var btn = lang.data_entry_483;
    } else {
        return;
    }
    simpleDialog('<div class="fs14">'+prompt+'</div>',btn,null,600,null,lang.global_53,"lockUnlockFormsDo('"+fetched+"','"+fetched2+"','"+lock+"','"+arm+"');",btn);
}

// Run any time an esign fails to verify username/password
function esignFail(numLogins) {
    if (numLogins == 3) {
        alert("SYSTEM LOGOUT:\n\nYou have failed to enter a valid username/password three (3) times. "
            + "You will now be automatically logged out of REDCap.");
        window.location.href += "&logout=1";
    } else {
        $('#esign_popup_error').toggle('blind',{},'normal');
    }
}

// Save the locking value from the form, then submit form
function saveLocking(lock_action,esign_action)
{
    // Determine action
    if (lock_action == 2) 		var action = "";
    else if (lock_action == 1)  var action = "lock";
    else if (lock_action == 0)  var action = "unlock";
    // Error msg
    var error_msg = "Woops! An error occurred, and the changes could not be made. Please try again.";
    // E-signature required (i.e. lock_record==2), but not if simply unlocking/negating esign
    if (lock_record == 2 && $('#__ESIGNATURE__').prop('checked') && esign_action == "save")
    {
        // Count login attempts
        var numLogins = 0;
        // Username/password popup
        $('#esign_popup').dialog({ bgiframe: true, modal: true, width: 530, zIndex: 3999, buttons: {
                'Save': function() {
                    // Check username/password entered is correct
                    $('#esign_popup_error').css('display','none'); //Default state
                    $.post(app_path_webroot+"Locking/single_form_action.php?pid="+pid, {instance: getParameterByName('instance'), esign_action: esign_action, event_id: event_id, action: action, username: $('#esign_username').val(), password: $('#esign_password').val(), record: getParameterByName('id'), form_name: getParameterByName('page')}, function(data){
                        $('#esign_password').val('');
                        if (data == "1") {
                            // If response=1, then correct username/password was entered and e-signature was saved
                            $('#esign_popup').dialog('close');
                            numLogins = 0;
                            // Submit the form if saving e-signature
                            if (action == 'lock' || action == '') {
                                formSubmitDataEntry();
                            } else {
                                setUnlocked(esign_action);
                            }
                        } else if (data == "2") {
                            // If response=2, then a php/sql error occurred
                            $('#esign_popup').dialog('close');
                            alert(error_msg);
                        } else {
                            // Login failed
                            numLogins++;
                            esignFail(numLogins);
                        }
                    });
                }
            } });
    }
    // No e-signature, so just save locking value
    else
    {
        $.post(app_path_webroot+"Locking/single_form_action.php?pid="+pid, {instance: getParameterByName('instance'), esign_action: esign_action, no_auth_key: 'q4deAr8s', event_id: event_id, action: action, record: getParameterByName('id'), form_name: getParameterByName('page')}, function(data){
            if (data == "1") {
                // Submit the form if saving e-signature
                if (action == 'lock' || action == '') {
                    formSubmitDataEntry();
                } else {
                    setUnlocked(esign_action);
                }
            } else {
                // error occurred
                alert(error_msg);
            }
        });
    }
}

// Unlock a record on a form
function unlockForm(unlockBtnJs) {
    var esign_notice = "";
    var esign_action = "";
    if (unlockBtnJs == null) unlockBtnJs = '';
    // Show extra notice if record has been e-signed (because unlocking will negate it)
    if ($('#__ESIGNATURE__').length && $('#__ESIGNATURE__').prop('checked') && $('#__ESIGNATURE__').prop('disabled')) {
        esign_notice = " NOTICE: Unlocking this form will also negate the current e-signature.";
        esign_action = "negate";
    }
    simpleDialog("Are you sure you wish to unlock this form for record \"<b>"+getParameterByName('id')+"</b>\"?"+esign_notice,"UNLOCK FORM?",null,null,
        null,"Cancel","saveLocking(0,'"+esign_action+"');"+unlockBtnJs,"Unlock");
}

// Function used when whole form is disabled *except* the lock record checkbox (this avoids a form post to prevent issues of saving for disabled fields)
function lockDisabledForm(ob) {
    // Dialog for confirmation
    if (confirm("LOCK FORM?\n\nAre you sure you wish to lock this form for record \""+getParameterByName('id')+"\"?")) {
        $.post(app_path_webroot+"Locking/single_form_action.php?pid="+pid, {instance: getParameterByName('instance'), esign_action: '', no_auth_key: 'q4deAr8s', event_id: event_id, action: "lock", record: getParameterByName('id'), form_name: getParameterByName('page')}, function(data){
            if (data == "1") {
                $(ob).prop('disabled',true);
                simpleDialog("The form has now been locked. The page will now reload to reflect this change.","LOCK SUCCESSFUL!",null,null,"window.location.reload();");
            } else {
                alert(woops);
            }
        });
    } else {
        // Make sure we uncheck the checkbox if they decline after checking it.
        $(ob).prop('checked',false);
    }
}

// Data Quality: Reload an individual record-event[-field] table of rules violated on data entry page
function reloadDQResultSingleRecord(show_excluded) {
    // Do ajax call to set exclude value
    $.post(app_path_webroot+'DataQuality/data_entry_single_record_ajax.php?pid='+pid+'&instance='+getParameterByName('instance'), { dq_error_ruleids: getParameterByName('dq_error_ruleids'),
        show_excluded: show_excluded, record: getParameterByName('id'), event_id: getParameterByName('event_id'),
        page: getParameterByName('page')}, function(data){
        $('#dq_rules_violated').html(data);
        initWidgets();
    });
}

// Data Quality: When user clicks data value on form for real-time execution, close dialog and highlight field with pop-up to save
function dqRteGoToField(field) {
    // Close dialog
    $('#dq_rules_violated').dialog('close');
    // Go to the field
    $('html, body').animate({
        scrollTop: $('tr#'+field+'-tr').offset().top - 150
    }, 700);
    // Put focus on field
    $('form#form :input[name="'+field+'"]').focus();
    // Open tooltip right above field
    $('tr#'+field+'-tr')
        .tooltip2({ tip: '#dqRteFieldFocusTip', relative: true, effect: 'fade', offset: [10,0], position: 'top center', events: { tooltip: "mouseenter" } })
        .trigger('mouseenter')
        .unbind();
}

// Data Quality: Exclude an individual record-event[-field] from displaying in the results table
function excludeDQResult(ob,rule_id,exclude,record,event_id,field_name,instance,repeat_instrument) {
    if (typeof instance == "undefined") instance = 1;
    if (typeof repeat_instrument == "undefined") repeat_instrument = '';
    // Do ajax call to set exclude value
    $.post(app_path_webroot+'DataQuality/exclude_result_ajax.php?pid='+pid+'&instance='+instance+'&repeat_instrument='+repeat_instrument, { exclude: exclude, field_name: field_name, rule_id: rule_id, record: record, event_id: event_id }, function(data){
        if (data == '1') {
            // Change style of row to show exclusion value change
            var this_row = $(ob).parent().parent().parent();
            this_row.removeClass('erow');
            if (exclude) {
                this_row.css({'background-color':'#FFE1E1','color':'red'});
                $(ob).parent().html("<a href='javascript:;' style='font-size:10px;text-decoration:underline;color:#800000;' onclick=\"excludeDQResult(this,'"+rule_id+"',0,'"+record+"',"+event_id+",'"+field_name+"','"+instance+"','"+repeat_instrument+"');\">"+lang_remove_exlusion+"</a>");
            } else {
                this_row.css({'background-color':'#EFF6E8','color':'green'});
                $(ob).parent().html("<a href='javascript:;' style='font-size:10px;text-decoration:underline;' onclick=\"excludeDQResult(this,'"+rule_id+"',1,'"+record+"',"+event_id+",'"+field_name+"','"+instance+"','"+repeat_instrument+"');\">"+lang_exclude+"</a>");
                // Remove the "(excluded)" label under record name
                this_row.children('td:first').find('.dq_excludelabel').html('')
            }
        } else {
            alert(woops);
        }
    });
}

// Data Quality: Display the explainExclude dialog
function explainDQExclude() {
    $('#explain_exclude').dialog({ bgiframe: true, modal: true, width: 500,
        buttons: {'Close':function(){$(this).dialog("close");}}
    });
}

// Data Quality: Display the explainResolve dialog
function explainDQResolve() {
    $('#explain_resolve').dialog({ bgiframe: true, modal: true, width: 500,
        buttons: {'Close':function(){$(this).dialog("close");}}
    });
}

// Data Resolution Workflow: Open dialog for uploading files (for query response)
function openDataResolutionFileUpload(record, event_id, field, rule_id) {
    // Reset all hidden/non-hidden divs
    $('#drw_upload_success').hide();
    $('#drw_upload_failed').hide();
    $('#drw_upload_progress').hide();
    $('#drw_upload_form').show();
    // Reset file input field (must replace it because val='' won't work)
    var fileInput = $('#dc-upload_doc_id-container').html();
    $('#dc-upload_doc_id-container').html('').html(fileInput);
    // Add values to the hidden inputs inside the dialog
    $("#drw_file_upload_popup input[name='record']").val(record);
    $("#drw_file_upload_popup input[name='event_id']").val(event_id);
    $("#drw_file_upload_popup input[name='field']").val(field);
    $("#drw_file_upload_popup input[name='rule_id']").val(rule_id);
    // Open dialog
    $("#drw_file_upload_popup").dialog({ bgiframe: true, modal: true, width: 450, buttons: {
            "Cancel": function() { $(this).dialog("close"); },
            "Upload document": function() { $('form#drw_upload_form').submit(); }
        }});
}
// Data Resolution Workflow: Delete uploaded file (for query response)
function dataResolutionDeleteUpload() {
    // If any hidden input doc_id's already exist, they must be deleted, so keep them but mark them for deletion
    $('#drw_upload_file_container input.drw_upload_doc_id').attr('delete','yes');
    // Show "add new document" link
    $('#drw_upload_new_container').show();
    // Hide "remove document" link
    $('#drw_upload_remove_doc').hide();
    // Hide doc_name link
    $('#dc-upload_doc_id-label').html('').hide();
}
// Data Resolution Workflow: Start uploading file (for query response)
function dataResolutionStartUpload() {
    $('#drw_upload_form').hide();
    $('#drw_upload_progress').show();
}
// Data Resolution Workflow: Stop uploading file (for query response)
function dataResolutionStopUpload(doc_id,doc_name) {
    $('#drw_file_upload_popup #drw_upload_form').hide();
    $('#drw_file_upload_popup #drw_upload_progress').hide();
    if (doc_id > 0) {
        // Success
        $('#drw_file_upload_popup #drw_upload_success').show();
        // Add doc_id as hidden input in hidden div container inside dialog
        $('#drw_upload_file_container').append('<input type="hidden" class="drw_upload_doc_id" value="'+doc_id+'">');
        // Hide "add new document" link
        $('#drw_upload_new_container').hide();
        // Show "remove document" link
        $('#drw_upload_remove_doc').show();
        // Add doc_name to hidden link
        $('#dc-upload_doc_id-label').html(doc_name).show();
    } else {
        // Failed
        $('#drw_file_upload_popup #drw_upload_failed').show();
    }
    // Add close button
    $('#drw_file_upload_popup').dialog('option', 'buttons', { "Close": function() { $(this).dialog("close"); } });
}

// Save new values from data cleaner pop-up dialog for individual field
function dataResolutionSave(field,event_id,record,rule_id,instance) {
    if (typeof instance == "undefined") instance = 1;
    // Set vars
    if (record == null) record = getParameterByName('id');
    if (rule_id == null) rule_id = '';
    // Check input values
    var comment = trim($('#dc-comment').val());
    //alert( $('#data_resolution input[name="dc-status"]:checked').val() );return;
    if (comment.length == 0 && ($('#data_resolution input[name="dc-status"]').length == 0
        || ($('#data_resolution input[name="dc-status"]').length && $('#data_resolution input[name="dc-status"]:checked').val() != 'VERIFIED'))) {
        simpleDialog("A comment is required. Please enter a comment.","ERROR: Enter comment");
        return;
    }
    var query_status = ($('#data_resolution input[name="dc-status"]:checked').length ? $('#data_resolution input[name="dc-status"]:checked').val() : '');
    if ($('#dc-response').length && query_status != 'CLOSED' && $('#dc-response').val().length == 0) {
        simpleDialog("A response is required. Please select a response option from the drop-down.","ERROR: Select response option");
        return;
    }
    var response = (($('#dc-response').length && query_status != 'CLOSED') ? $('#dc-response').val() : '');
    // Note if user is sending query back for further attention (rather than closing it)
    var send_back = (query_status != 'CLOSED' && $('#dc-response_requested-closed').length) ? 1 : 0;
    // Determine if we're re-opening the query (i.e. if #dc-response_requested is a checkbox and assign user drop-down is not there)
    var reopen_query = ($('#dc-response_requested').length && $('#dc-response_requested').attr('type') == 'checkbox' && $('#dc-assigned_user_id').length == 0) ? 1 : 0;
    // If user is responding to query, check for file uploaded
    var upload_doc_id = '';
    var delete_doc_id = '';
    delete_doc_id_count = 0;
    if ($('#drw_upload_file_container input.drw_upload_doc_id').length > 0) {
        // Loop through all doc_id's available
        delete_doc_id = new Array();
        $('#drw_upload_file_container input.drw_upload_doc_id').each(function(){
            if ($(this).attr('delete') == 'yes') {
                delete_doc_id[delete_doc_id_count++] = $(this).val();
            } else {
                upload_doc_id = $(this).val();
            }
        });
        delete_doc_id = delete_doc_id.join(",");
    }
    // Disable all input fields in pop-up while saving
    $('#newDCHistory :input').prop('disabled',true);
    $('#data_resolution .jqbutton').button('disable');
    // Display saving icon
    $('#drw_saving').removeClass('hidden');
    // Get start time before ajax call is made
    var starttime = new Date().getTime();
    // Make ajax call
    $.post(app_path_webroot+"DataQuality/data_resolution_popup.php?pid="+pid+'&instance='+instance, { action: 'save', field_name: field, event_id: event_id, record: record,
        comment: comment,
        response_requested: (($('#dc-response_requested').length && $('#dc-response_requested').prop('checked')) ? 1 : 0),
        upload_doc_id: upload_doc_id, delete_doc_id: delete_doc_id,
        assigned_user_id: (($('#dc-assigned_user_id').length) ? $('#dc-assigned_user_id').val() : ''),
        status: query_status, send_back: send_back,
        response: response, reopen_query: reopen_query,
        rule_id: rule_id
    }, function(data){
        if (data=='0') {
            alert(woops);
        } else {
            // Parse JSON
            var json_data = jQuery.parseJSON(data);
            // Update new timestamp for saved row (in case different)
            $('#newDCnow').html(json_data.tsNow);
            // Display saved icon
            $('#drw_saving').addClass('hidden');
            $('#drw_saved').removeClass('hidden');
            // Set bg color of last row to green
            $('table#newDCHistory tr td.data').css({'background-color':'#C1FFC1'});
            // Page-dependent actions
            if (page == 'DataQuality/field_comment_log.php') {
                // Field Comment Log page: reload table
                reloadFieldCommentLog();
            } else if (page == 'DataQuality/resolve.php') {
                // Data Quality Resolve Issues page: reload table
                dataResLogReload();
            } else if (page == 'DataQuality/index.php') {
                // Update count in tab badge
                $('#dq_tab_issue_count').html(json_data.num_issues);
            }
            // Update icons/counts
            if (page == 'DataEntry/index.php' || page == 'DataQuality/index.php') {
                // Data Quality Find Issues page: Change ballon icon for this field/rule result
                $('#dc-icon-'+rule_id+'_'+field+'__'+record).attr('src', json_data.icon);
                // Update number of comments for this field/rule result
                $('#dc-numcom-'+rule_id+'_'+field+'__'+record).html(json_data.num_comments);
                // Data Entry page: Change ballon icon for field
                $('#dc-icon-'+field).attr('src', json_data.icon).attr('onmouseover', '').attr('onmouseout', '');
            }
            // CLOSE DIALOG: Get response time of ajax call (to ensure closing time is always the same even with longer requests)
            var endtime = new Date().getTime() - starttime;
            var delaytime = 1500;
            var timeouttime = (endtime >= delaytime) ? 1000 : (delaytime - endtime);
            setTimeout(function(){
                // Close dialog with fade effect
                $('#data_resolution').dialog('option', 'hide', {effect:'fade', duration: 500}).dialog('close');
                // Highlight table row in form (to emphasize where user was) - Data Entry page only
                if (page == 'DataEntry/index.php') {
                    setTimeout(function(){
                        highlightTableRow(field+'-tr',3000);
                    },200);
                }
                // Destroy the dialog so that fade effect doesn't persist if reopened
                setTimeout(function(){
                    if ($('#data_resolution').hasClass('ui-dialog-content')) $('#data_resolution').dialog('destroy');
                },500);
            }, timeouttime);
        }
    });
}

// Open pop-up dialog for viewing data resolution for a field
function dataResPopup(field,event_id,record,existing_record,rule_id,instance) {
    if (typeof instance == "undefined") instance = 1;
    if (record == null) record = getParameterByName('id');
    if (existing_record == null) existing_record = $('form#form :input[name="hidden_edit_flag"]').val();
    if (rule_id == null) rule_id = '';
    // Hide floating field tooltip on form (if visible)
    $('#tooltipDRWsave').hide();
    showProgress(1,0);
    // Get dialog content via ajax
    $.post(app_path_webroot+"DataQuality/data_resolution_popup.php?pid="+pid+'&instance='+instance, { rule_id: rule_id, action: 'view', field_name: field, event_id: event_id, record: record, existing_record: existing_record }, function(data){
        showProgress(0,0);
        // Parse JSON
        var json_data = jQuery.parseJSON(data);
        if (existing_record == 1) {
            // Get window scroll position before we load dialog content
            var windowScrollTop = $(window).scrollTop();
            // Load the dialog content
            initDialog('data_resolution');
            $('#data_resolution').html(json_data.content);
            initWidgets();
            // Set dialog width
            var dialog_width = (data_resolution_enabled == '1') ? 700 : 750;
            // Open dialog
            $('#data_resolution').dialog({ bgiframe: true, title: json_data.title, modal: true, width: dialog_width, zIndex: 3999, destroy: 'fade' });
            // Adjust table height within the dialog to fit
            var existingRowsHeightMax = 300;
            if ($('#existingDCHistoryDiv').height() > existingRowsHeightMax) {
                $('#existingDCHistoryDiv').height(existingRowsHeightMax);
                $('#existingDCHistoryDiv').scrollTop( $('#existingDCHistoryDiv')[0].scrollHeight );
                // Reset window scroll position, if got moved when dialog content was loaded
                $(window).scrollTop(windowScrollTop);
                // Re-center dialog
                $('#data_resolution').dialog('option', 'position', { my: "center", at: "center", of: window });
            }
            // Put cursor inside text box
            $('#dc-comment').focus();
        } else {
            // If record does not exist yet, then give warning that will not work
            initDialog('data_resolution');
            $('#data_resolution').css('background-color','#FFF7D2').html(json_data.content);
            initWidgets();
            $('#data_resolution').dialog({ bgiframe: true, title: json_data.title, modal: true, width: 500, zIndex: 3999 });
        }
    });
}

// Edit a Field Comment
function editFieldComment(res_id, form, openForEditing, cancelEdit) {
    var td_div = $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(3) div:first');
    if (openForEditing) {
        // Make the text an editable textarea
        var comment = br2nl(td_div.html().replace(/\t/g,'').replace(/\r/g,'').replace(/\n/g,''));
        var textarea = '<div id="dc-comment-edit-div-'+res_id+'"><textarea id="dc-comment-edit-'+res_id+'" class="x-form-field notesbox" style="height:45px;width:97%;display:block;margin-bottom:2px;">'+comment+'</textarea>'
            + '<button id="dc-comment-savebtn-'+res_id+'" class="jqbuttonmed" style="font-size:11px;font-weight:bold;" onclick="editFieldComment('+res_id+',\''+form+'\',0,0);">Save</button>'
            + '<button id="dc-comment-cancelbtn-'+res_id+'" class="jqbuttonmed" style="font-size:11px;" onclick="editFieldComment('+res_id+',\''+form+'\',0,1);">Cancel</button></div>';
        td_div.hide().after(textarea);
        $('#dc-comment-savebtn-'+res_id+', #dc-comment-cancelbtn-'+res_id).button();
        $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(0) img').css('visibility','hidden');
    } else if (cancelEdit) {
        // Cancel the edit (return as it was)
        $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(0) img').css('visibility','visible');
        td_div.show();
        $('#dc-comment-edit-div-'+res_id).remove();
    } else {
        var comment = $('#dc-comment-edit-'+res_id).val();
        // Make ajax call
        $.post(app_path_webroot+"DataQuality/field_comment_log_edit_delete_ajax.php?pid="+pid, { action: 'edit', comment: comment, form_name: form, res_id: res_id}, function(data){
            if (data=='0') {
                alert(woops);
            } else {
                // Parse JSON
                var json_data = jQuery.parseJSON(data);
                $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(0) img').css('visibility','visible');
                highlightTableRowOb( $('table#existingDCHistory tr#res_id-'+res_id), 3000);
                td_div.show().html(nl2br(comment));
                $('#dc-comment-edit-div-'+res_id).remove();
                // Display the "edit" text
                $('table#existingDCHistory tr#res_id-'+res_id+' .fc-comment-edit').show();
            }
        });
    }
}

// Delete a Field Comment
function deleteFieldComment(res_id, form, confirmDelete) {
    var url = app_path_webroot+"DataQuality/field_comment_log_edit_delete_ajax.php?pid="+pid;
    // Make ajax call
    $.post(url, { action: 'delete', form_name: form, res_id: res_id, confirmDelete: confirmDelete}, function(data){
        if (data=='0') {
            alert(woops);
        } else {
            // Parse JSON
            var json_data = jQuery.parseJSON(data);
            if (confirmDelete) {
                simpleDialog(json_data.html,json_data.title,null,null,null,json_data.closeButton,'deleteFieldComment('+res_id+', "'+form+'",0);',json_data.actionButton);
            } else {
                $('table#existingDCHistory tr#res_id-'+res_id+' td:eq(0) img').css('visibility','hidden');
                $('table#existingDCHistory tr#res_id-'+res_id+' td').each(function(){
                    $(this).removeClass('data').addClass('red').css('color','gray');
                });
                setTimeout(function(){
                    $('table#existingDCHistory tr#res_id-'+res_id).hide('fade');
                },3000);
            }
        }
    });
}

// Data Cleaner icon onmouseover/out actions
function dc1(ob) {
    ob.src = app_path_images+'balloon_left.png';
}
function dc2(ob) {
    ob.src = app_path_images+'balloon_left_bw2.gif';
}

// Missing Data icon onmouseover/out actions
function md1(ob) {
    ob.src = app_path_images+'missing_active.png';
}
function md2(ob) {
    if (ob.missing!=true){
        ob.src = app_path_images+'missing.png';
    }
}

// Data history icon onmouseover/out actions
function dh1(ob) {
    ob.src = app_path_images+'history_active.png';
}
function dh2(ob) {
    ob.src = app_path_images+'history.png';
}

// Open pop-up dialog for viewing data history of a field
function dataHist(field,event_id,record) {
    // Get window scroll position before we load dialog content
    var windowScrollTop = $(window).scrollTop();
    if (record == null) record = decodeURIComponent(getParameterByName('id'));
    if ($('#data_history').hasClass('ui-dialog-content')) $('#data_history').dialog('destroy');
    $('#dh_var').html(field);
    $('#data_history2').html('<p><img src="'+app_path_images+'progress_circle.gif"> Loading...</p>');
    $('#data_history').dialog({ bgiframe: true, title: 'Data History for variable "'+field+'" for record "'+record+'"', modal: true, width: 650, zIndex: 3999, buttons: {
            Close: function() { $(this).dialog('destroy'); } }
    });
    $.post(app_path_webroot+"DataEntry/data_history_popup.php?pid="+pid, {field_name: field, event_id: event_id, record: record, instance: getParameterByName('instance') }, function(data){
        $('#data_history2').html(data);
        // Adjust table height within the dialog to fit
        var tableHeightMax = 300;
        if ($('#data_history3').height() > tableHeightMax) {
            $('#data_history3').height(tableHeightMax);
            $('#data_history3').scrollTop( $('#data_history3')[0].scrollHeight );
            // Reset window scroll position, if got moved when dialog content was loaded
            $(window).scrollTop(windowScrollTop);
            // Re-center dialog
            $('#data_history').dialog('option', 'position', { my: "center", at: "center", of: window });
        }
        // Highlight the last row in DH table
        if ($('table#dh_table tr').length > 1) {
            setTimeout(function(){
                highlightTableRowOb($('table#dh_table tr:last'), 3500);
            },300);
        }
    });
}

// Chack Two-byte character (for Japanese)
function checkIsTwoByte(value) {
    for (var i = 0; i < value.length; ++i) {
        var c = value.charCodeAt(i);
        if (c >= 256 || (c >= 0xff61 && c <= 0xff9f)) {
            return true;
        }
    }
    return false;
}

// Change status of project
function doChangeStatus(archive,super_user_action,user_email,randomization,randProdAllocTableExists) {
    randomization = (randomization == null) ? 0 : (randomization == 1 ? 1 : 0);
    randProdAllocTableExists = (randProdAllocTableExists == null) ? 0 : (randProdAllocTableExists == 1 ? 1 : 0);
    var delete_data = 0;
    if (randomization == 1 && randProdAllocTableExists == 0) {
        alert('ERROR: This project is utilizing the randomization module and cannot be moved to production status yet because a randomization allocation table has not been uploaded for use in production status. Someone with appropriate rights must first go to the Randomization page and upload an allocation table.');
        return false;
    }
    var alertMessage =  '<div class="select-radio-button-msg" style="color: #C00000; font-size: 16px; margin-top: 10px;">Please select one of the options above before moving to production.</div>';
    if (archive == 0 && $('#delete_data').length) {
        if ($('input[name="data"]:checked').prop('id') !== undefined ) {
            if ($('input[name="data"]:checked').prop('id') == "delete_data") {
                delete_data = 1;
                $('.select-radio-button-msg').remove();
                // Make user confirm that they want to delete data
                if (archive == 0 && super_user_action != 'move_to_prod') { // Don't show prompt when super users are processing users' requests to push to prod
                    if (!confirm("DELETE ALL DATA?\n\nAre you sure you really want to delete all existing data when the project is moved to production? If not, click Cancel and change the setting inside the yellow box.")) {
                        return false;
                    }
                }
            } else if (randomization) {
                // If not deleting all data BUT using randomization module, remind that the randomization field's values will be erased
                if (!confirm("WARNING: RANDOMIZATION FIELD'S DATA WILL BE DELETED\n\nSince you have enabled the randomization module, please be advised that if any records contain a value for your randomization field (i.e. have been randomized), those values will be PERMANENTLY DELETED once the project is moved to production. (Only data for that field will be deleted. Other fields will not be touched.) Is this okay?")) {
                    return false;
                }
            }
        }else if($('input[name="data"]:checked').prop('id') !== undefined){
            if ($('input[name="data"]:checked').prop('id') == "keep_data") {
                delete_data = 0;
                $('.select-radio-button-msg').remove();
            }
        }else{//if both undefined display message
            $('.select-radio-button-msg').remove();
            $('#status_dialog .yellow').append(alertMessage);
            return false;
        }
    }
    $(":button:contains('YES, Move to Production Status')").html('Please wait...');
    $(":button:contains('Cancel')").css("display","none");
    $.post(app_path_webroot+'ProjectGeneral/change_project_status.php?pid='+pid, { do_action_status: 1, archive: archive, delete_data: delete_data },
        function(data) {
            if (archive == 1) $('#completed_time_dialog').dialog('destroy'); else $('#status_dialog').dialog('destroy');
            if (data != '0') {
                if (archive == 1) {
                    alert("The project has now been marked as COMPLETED. The project and its data will remain in the system and cannot be modified. "
                        + "Only a REDCap administrator may return the project back to its previous status.\n\n(You will now be redirected back to the Home page.)");
                    window.location.href = app_path_webroot_full+'index.php?action=myprojects';
                } else {
                    if (data == '1') {
                        if (super_user_action == 'move_to_prod') {
                            $.get(app_path_webroot+'ProjectGeneral/notifications.php', { pid: pid, type: 'move_to_prod_user', this_user_email: user_email },
                                function(data2) {
                                    if(self!=top){//decect if in iframe
                                        simpleDialog('The user request for their REDCap project to be moved to production status has been approved.',
                                            'Request Approved / User Notified',null,null,function(){
                                                closeToDoListFrame();
                                            });
                                    }else{
                                        window.location.href = app_path_webroot_full+'index.php?action=approved_movetoprod&user_email='+user_email;
                                    }
                                }
                            );
                        } else if (status == '2') {
                            alert("The project has now been moved back to PRODUCTION status.\n\n(The page will now be reloaded to reflect the change.)");
                            window.location.href = app_path_webroot+'ProjectSetup/other_functionality.php?pid='+pid;
                        } else {
                            window.location.href = app_path_webroot+'ProjectSetup/index.php?pid='+pid+'&msg=movetoprod';
                        }
                    } else {
                        alert("The project has now been set to ANALYSIS/CLEANUP status.\n\n(The page will now be reloaded to reflect the change.)");
                        window.location.reload();
                    }
                }
            } else {
                alert('ERROR: The action could not be performed.');
            }
        }
    );
}

//Function to begin editing an event/visit
function beginEdit(arm,event_id) {
    document.getElementById("progress").style.visibility = "visible";
    $.get(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, edit: '', event_id: event_id },
        function(data) {
            document.getElementById("table").innerHTML = data;
            initDefineEvents();
            setCaretToEnd(document.getElementById("day_offset_edit"));
        }
    );
}
//Function for editing an event/visit
function editVisit(arm,event_id) {
    if (trim($("#descrip_edit").val()) == "" || ($("#offset_min_edit").length && ($("#offset_min_edit").val() == "" || $("#offset_max_edit").val() == "" || $("#day_offset_edit").val() == ""))) {
        simpleDialog("Please enter a value for Days Offset and Event Name");
        return;
    } else if ($("#offset_min_edit").length) {
        var offset_min = $("#offset_min_edit").val();
        var offset_max = $("#offset_max_edit").val();
        var day_offset = $("#day_offset_edit").val();
    } else {
        var offset_min = '';
        var offset_max = '';
        var day_offset = '';
    }
    if ($("#offset_min_edit").length) {
        document.getElementById("day_offset_edit").disabled = true;
        document.getElementById("offset_min_edit").disabled = true;
        document.getElementById("offset_max_edit").disabled = true;
    }
    document.getElementById("editbutton").disabled = true;
    document.getElementById("descrip_edit").disabled = true;
    document.getElementById("progress").style.visibility = "visible";
    $.post(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, action: 'edit', event_id: event_id, offset_min: offset_min, offset_max: offset_max, day_offset: day_offset, descrip: document.getElementById("descrip_edit").value, custom_event_label: document.getElementById("custom_event_label_edit").value },
        function(data) {
            document.getElementById("table").innerHTML = data;
            initDefineEvents();
            highlightTableRow('design_'+event_id,2000);
        }
    );
}
//Function for adding an event/visit
function addEvents(arm,num_events_total) {
    if (trim($("#descrip").val()) == "") {
        simpleDialog("Please enter a name for the event you wish to add");
        $("#descrip").val(jQuery.trim($("#descrip").val()));
        return;
    } else if ($("#offset_min").length && ($("#offset_min").val() == "" || $("#offset_max").val() == "" || $("#day_offset").val() == "" || trim($("#descrip").val()) == "")) {
        simpleDialog("Please enter a value for Days Offset and Event Name");
        $("#descrip").val(jQuery.trim($("#descrip").val()));
        return;
    } else if ($("#offset_min").length) {
        var offset_min = $("#offset_min").val();
        var offset_max = $("#offset_max").val();
        var day_offset = $("#day_offset").val();
    } else {
        var offset_min = 0;
        var offset_max = 0;
        var day_offset = 9999;
    }
    // Check if event name is duplicated
    var event_names = "|";
    $("#event_table .evt_name").each(function(){
        event_names += jQuery.trim($(this).html()) + "|";
    });
    if (event_names.indexOf("|"+jQuery.trim($("#descrip").val())+"|") > -1) {
        simpleDialog("You have duplicated an existing event name. All events must have unique names. Please enter a different value.",null,null,null,'$("#descrip").focus()');
        return;
    }
    document.getElementById("progress").style.visibility = "visible";
    document.getElementById("addbutton").disabled = true;
    document.getElementById("descrip").disabled = true;
    if ($("#offset_min").length) {
        document.getElementById("day_offset").disabled = true;
        document.getElementById("offset_min").disabled = true;
        document.getElementById("offset_max").disabled = true;
    }
    $.get(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, action: 'add', offset_min: offset_min, offset_max: offset_max, day_offset: day_offset, descrip: document.getElementById("descrip").value, custom_event_label: document.getElementById("custom_event_label").value },
        function(data) {
            $("#table").html(data);
            initDefineEvents();
            highlightTableRow('design_'+$("#new_event_id").val(), 2000);
            $('#descrip').focus();
            //Reload page if just added second event (so that all Longitudinal functions show)
            if (num_events_total == 1) {
                showProgress(1);
                setTimeout("window.location.reload();",300);
            } else {
                // If add event for first time on page, show tooltip reminder about designating forms
                if (hasShownDesignatePopup == 0) {
                    $("#popupTrigger").trigger('mouseover');
                    hasShownDesignatePopup++;
                }
            }
        }
    );
}

// Init Designate Instruments page
function initDesigInstruments() {
    initButtonWidgets();
    $('#downloadUploadEventsInstrDropdown').menu();
    $('#downloadUploadEventsInstrDropdownDiv ul li a').click(function(){
        $('#downloadUploadEventsInstrDropdownDiv').hide();
    });
    // Enable fixed table headers for event grid
    enableFixedTableHdrs('event_grid_table');
}

// Init Define Events page
function initDefineEvents() {
    initButtonWidgets();
    $('#downloadUploadEventsArmsDropdown').menu();
    $('#downloadUploadEventsArmsDropdownDiv ul li a').click(function(){
        $('#downloadUploadEventsArmsDropdownDiv').hide();
    });
    // If not using scheduling, then enable drag-n-drop for events in table
    if (!scheduling && $('.dragHandle').length > 1) {
        // Modify event order: Enable drag-n-drop on table
        $('#event_table').tableDnD({
            onDrop: function(table, row) {
                // Loop through table
                var event_ids = new Array(); var i=0;
                $("#event_table tr").each(function() {
                    if ($(this).attr('id') != null) {
                        event_ids[i++] = $(this).attr('id').substr(7);
                    }
                });
                // Save event order
                $.post(app_path_webroot+'Design/define_events_ajax.php?pid='+pid, { action: 'reorder_events', arm: $('#arm').val(), event_ids: event_ids.join(',') }, function(data){
                    $('#table').html(data);
                    initDefineEvents();
                    highlightTableRow($(row).attr('id'),2000);
                });
            },
            dragHandle: "dragHandle"
        });
        // Create mouseover image for drag-n-drop action and enable button fading on row hover
        $("#event_table tr:not(.nodrop)").mouseenter(function() {
            $(this.cells[0]).css('background','#fafafa url("'+app_path_images+'updown.gif") no-repeat center');
        }).mouseleave(function() {
            $(this.cells[0]).css('background','');
        });
        // Set up drag-n-drop pop-up tooltip
        $('.dragHandle').mouseenter(function() {
            $("#reorderTrigger").trigger('mouseover');
        }).mouseleave(function() {
            $("#reorderTrigger").trigger('mouseout');
        });
        // Miscellaneous things to init
        $('#reorderTip').hide('fade');
        $("#reorderTrigger").tooltip2({ tip: '#reorderTip', relative: true, effect: 'fade', position: 'top center', offset: [35,0] });
    }
}

//Open pop-up for month/year/week conversion to days
function openConvertPopup() {
    if ($('#convert').hasClass('ui-dialog-content')) $('#convert').dialog('destroy');
    var this_day = $('#day_offset').val();
    if (this_day != '') {
        $("#calc_year").val(this_day/365);
        $("#calc_month").val(this_day/30);
        $("#calc_week").val(this_day/7);
        $("#calc_day").val(this_day);
    } else {
        $("#calc_year").val('');
        $("#calc_month").val('');
        $("#calc_week").val('');
        $("#calc_day").val('');
    }
    var pos = $('#day_offset').offset();
    $('#convTimeBtn').addClass('ui-state-default ui-corner-all');
    $('#convert').addClass('simpleDialog').dialog({ bgiframe: true, modal: true, width: 350, height: 250});
}
//Provide month/year/week conversion to days
function calcDay(el) {
    var isNumeric=function(symbol){var objRegExp=/(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;return objRegExp.test(symbol);};
    if (!isNumeric(el.value)) {
        var oldval = el.value;
        $("#calc_year").val('');
        $("#calc_month").val('');
        $("#calc_week").val('');
        $("#calc_day").val('');
        $("#"+el.id).val(oldval);
    } else if (el.id == "calc_year") {
        $("#calc_month").val(el.value*12);
        $("#calc_week").val(el.value*52);
        $("#calc_day").val(Math.round(el.value*365));
    } else if (el.id == "calc_month") {
        $("#calc_year").val(el.value/12);
        $("#calc_week").val(el.value*4);
        $("#calc_day").val(Math.round(el.value*30));
    } else if (el.id == "calc_week") {
        $("#calc_year").val(el.value/52);
        $("#calc_month").val(el.value/4);
        $("#calc_day").val(Math.round(el.value*7));
    } else if (el.id == "calc_day") {
        $("#calc_year").val(el.value/365);
        $("#calc_month").val(el.value/30);
        $("#calc_week").val(el.value/7);
    }
    //Value of 9999 days is max
    if ($("#calc_day").val() != '' && isNumeric($("#calc_day").val())) {
        if ($("#calc_day").val() > 9999) $("#calc_day").val(9999);
    }
}


//Function for deleting an event/visit
function delVisit(arm,event_id,num_events_total) {
    if (confirm('DELETE EVENT?\n\nAre you sure you wish to delete this event?')) {
        if (status > 0) {
            if (!confirm('ARE YOU SURE?\n\nDeleting this event will DELETE ALL DATA collected for this event. Are you sure you wish to delete this event?')) {
                return;
            }
        }
        document.getElementById("progress").style.visibility = "visible";
        $.get(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, action: 'delete', event_id: event_id },
            function(data) {
                document.getElementById("table").innerHTML = data;
                initDefineEvents();
                //Reload page if just added second event (so that all Longitudinal functions show)
                if (num_events_total == 2) {
                    showProgress(1);
                    setTimeout("window.location.reload();",300);
                }
            }
        );
    }
}
function delVisit2(arm,event_id,num_events_total) {
    if (confirm('DELETE EVENT?\n\nAre you sure you wish to delete this event?')) {
        document.getElementById("progress").style.visibility = "visible";
        $.get(app_path_webroot+"Design/define_events_ajax.php", { pid: pid, arm: arm, action: 'delete', event_id: event_id },
            function(data) {
                document.getElementById("table").innerHTML = data;
                initDefineEvents();
                //Reload page if just added second event (so that all Longitudinal functions show)
                if (num_events_total == 2) {
                    showProgress(1);
                    window.location.reload();
                }
            }
        );
    }
}

//Place focus at end of text in an input Text field
function setCaretToEnd(el) {
    try {
        if (isIE) {
            if (el.createTextRange) {
                var v = el.value;
                var r = el.createTextRange();
                r.moveStart('character', v.length);
                r.select();
                return;
            }
            el.focus();
            return;
        }
        el.focus();
    } catch(e) { }
}

function delete_doc(docs_id) {
    if(confirm(delete_doc_msg)) {
        showProgress(1);
        $.post(app_path_webroot+page+"?pid="+pid,{ 'delete': docs_id },function(data){
            window.location.href = app_path_webroot+page+"?pid="+pid;
        });
    }
    return false;
}