/*!
* Clamp.js 0.5.1
*
* Copyright 2011-2013, Joseph Schmitt http://joe.sh
* Released under the WTFPL license
* http://sam.zoy.org/wtfpl/
*/
(function(){window.$clamp=function(c,d){function s(a,b){n.getComputedStyle||(n.getComputedStyle=function(a,b){this.el=a;this.getPropertyValue=function(b){var c=/(\-([a-z]){1})/g;"float"==b&&(b="styleFloat");c.test(b)&&(b=b.replace(c,function(a,b,c){return c.toUpperCase()}));return a.currentStyle&&a.currentStyle[b]?a.currentStyle[b]:null};return this});return n.getComputedStyle(a,null).getPropertyValue(b)}function t(a){a=a||c.clientHeight;var b=u(c);return Math.max(Math.floor(a/b),0)}function x(a){return u(c)*
    a}function u(a){var b=s(a,"line-height");"normal"==b&&(b=1.2*parseInt(s(a,"font-size")));return parseInt(b)}function l(a){if(a.lastChild.children&&0<a.lastChild.children.length)return l(Array.prototype.slice.call(a.children).pop());if(a.lastChild&&a.lastChild.nodeValue&&""!=a.lastChild.nodeValue&&a.lastChild.nodeValue!=b.truncationChar)return a.lastChild;a.lastChild.parentNode.removeChild(a.lastChild);return l(c)}function p(a,d){if(d){var e=a.nodeValue.replace(b.truncationChar,"");f||(h=0<k.length?
    k.shift():"",f=e.split(h));1<f.length?(q=f.pop(),r(a,f.join(h))):f=null;m&&(a.nodeValue=a.nodeValue.replace(b.truncationChar,""),c.innerHTML=a.nodeValue+" "+m.innerHTML+b.truncationChar);if(f){if(c.clientHeight<=d)if(0<=k.length&&""!=h)r(a,f.join(h)+h+q),f=null;else return c.innerHTML}else""==h&&(r(a,""),a=l(c),k=b.splitOnChars.slice(0),h=k[0],q=f=null);if(b.animate)setTimeout(function(){p(a,d)},!0===b.animate?10:b.animate);else return p(a,d)}}function r(a,c){a.nodeValue=c+b.truncationChar}d=d||{};
    var n=window,b={clamp:d.clamp||2,useNativeClamp:"undefined"!=typeof d.useNativeClamp?d.useNativeClamp:!0,splitOnChars:d.splitOnChars||[".","-","\u2013","\u2014"," "],animate:d.animate||!1,truncationChar:d.truncationChar||"\u2026",truncationHTML:d.truncationHTML},e=c.style,y=c.innerHTML,z="undefined"!=typeof c.style.webkitLineClamp,g=b.clamp,v=g.indexOf&&(-1<g.indexOf("px")||-1<g.indexOf("em")),m;b.truncationHTML&&(m=document.createElement("span"),m.innerHTML=b.truncationHTML);var k=b.splitOnChars.slice(0),
        h=k[0],f,q;"auto"==g?g=t():v&&(g=t(parseInt(g)));var w;z&&b.useNativeClamp?(e.overflow="hidden",e.textOverflow="ellipsis",e.webkitBoxOrient="vertical",e.display="-webkit-box",e.webkitLineClamp=g,v&&(e.height=b.clamp+"px")):(e=x(g),e<=c.clientHeight&&(w=p(l(c),e)));return{original:y,clamped:w}}})();

var message_letter = getParameterByName('message');
if (message_letter != '' && getParameterByName('log') == '') {
    // Modify the URL
    modifyURL(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:setup');
}
var changedCronSendEmailOn = false;

function initTinyMCE()
{
    if (isIE && vIE() < 11) return;
    tinymce.init({
        mode: 'specific_textareas',
        editor_selector: 'external-modules-rich-text-field',
        height: 250,
        menubar: false,
        branding: false,
        statusbar: true,
        elementpath: false, // Hide this, since it oddly renders below the textarea.
        plugins: ['paste autolink lists link searchreplace code fullscreen table directionality'],
        toolbar1: 'formatselect | bold italic link | alignleft aligncenter alignright alignjustify | undo redo | fullscreen',
        toolbar2: 'bullist numlist | outdent indent | table | forecolor backcolor | searchreplace code  removeformat ',
        contextmenu: "copy paste | link image inserttable | cell row column deletetable",
        relative_urls: false,
        convert_urls : false,
        convert_fonts_to_spans: true,
        paste_word_valid_elements: "b,strong,i,em,h1,h2,u,p,ol,ul,li,a[href],span,color,font-size,font-color,font-family,mark,table,tr,td",
        paste_retain_style_properties: "all",
        paste_postprocess: function(plugin, args) {
            args.node.innerHTML = cleanHTML(args.node.innerHTML);
        },
        setup: function(ed) {
            ed.on('keyup', function (e) {
                logicSuggestSearchTip($('#alert-message_ifr'), e);
            })
        }
    });
}

/**
 * Function to preview the message on the alerts table
 * @param index, the alert id
 */
function previewEmailAlert(index,alertnumber){
    var data = "&index_modal_preview="+index+"&redcap_csrf_token="+redcap_csrf_token;
    $.ajax({
        type: "POST",
        url: app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:previewAlertMessage',
        data: data,
        error: function (xhr, status, error) {
            alert(xhr.responseText);
        },
        success: function (result) {
            $('#modal_message_preview').html(result);
            $('#modalPreviewNumber').text("- "+lang.alerts_24+" #"+alertnumber);
            $('#myModalLabelA').show();
            $('#myModalLabelB').hide();
            $('#external-modules-configure-modal-preview').modal('show');
        }
    });
}

function previewEmailAlertRecord(index, alertnumber){
    $('#index_modal_record_preview').val(index)
    $('#modalRecordNumber').text("- "+lang.alerts_24+" #"+alertnumber);

    var data = "&index_modal_alert="+index+"&redcap_csrf_token="+redcap_csrf_token;
    $.ajax({
        type: "POST",
        url: app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:previewAlertMessageByRecordDialog',
        data: data,
        error: function (xhr, status, error) {
            alert(xhr.responseText);
        },
        success: function (result) {
            $('#load_preview_record').html(result);
            $('#external-modules-configure-modal-record').modal('show');
        }
    });

}
function deleteRecurrenceDo(aq_id, alert_id){
    ajaxLoadOptionAndMessage("&aq_id="+aq_id+"&pid="+pid+"&alert_id="+alert_id, app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteQueuedRecord',"");
}

function deleteRecurrence(aq_id, alert_id, alert_number, record, event) {
    $('#delete-recurrence-modal-body-alert').html(lang.alerts_24+" #"+alert_number);
    $('#delete-recurrence-modal-body-record').html(record);
    $('#delete-recurrence-modal-body-event').html(event);
    $('#delete-recurrence-modal').modal('show');
    $('#delete-recurrence-modal-body-submit').attr('onclick','deleteRecurrenceDo('+aq_id+','+alert_id+');return false;');
}

function loadPreviewEmailAlertRecord(alert_sent_log_id, aq_id, alertnumber) {
    var data = $('#selectPreviewRecord').serialize();
    if (alert_sent_log_id != ""){
        data += "&alert_sent_log_id="+alert_sent_log_id+"&redcap_csrf_token="+redcap_csrf_token;
    } else if (aq_id != ""){
        data += "&aq_id="+aq_id+"&redcap_csrf_token="+redcap_csrf_token;
    }
    $.ajax({
        type: "POST",
        url: app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:previewAlertMessageByRecord',
        data: data,
        error: function (xhr, status, error) {
            alert(xhr.responseText);
        },
        success: function (result) {
            if (alert_sent_log_id == "" && aq_id == "") {
                $('#modal_message_record_preview').html(result);
            } else {
                $('#modal_message_preview').html(result);
                $('#modalPreviewNumber').text("- "+lang.alerts_24+" #" + alertnumber);
                $('#myModalLabelA').hide();
                $('#myModalLabelB').show();
                $('#external-modules-configure-modal-preview').modal('show');
            }
        }
    });
}

function checkSchedule(repetitive, repetitive_change, cron_send_email_on, cron_send_email_on_date, cron_repeat_for) {
    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-date"]').val('');
    if (repetitive == '1' || repetitive_change == '1') {
        // Send immediately (either once or every time form is saved)
        $('[name=external-modules-configure-modal] input[name="cron-send-email-on"][value="now"]').prop('checked',true);
    } else {
        // Send using schedule
        $('[name=external-modules-configure-modal] input[name="cron-repeat-for"]').val(cron_repeat_for);
        $('[name=external-modules-configure-modal] input[name="cron-send-email-on"][value="'+cron_send_email_on+'"]').prop('checked',true);
        if (cron_send_email_on == "date") {
            $('[name=external-modules-configure-modal] :input[name="cron-send-email-on-date"]').val(cron_send_email_on_date);
        }
    }
}

function displayTriggerSettings() {
    var val = $('[name="alert-trigger"]:checked').val();
    $('[field="form-name"], [field="condition-andor"], [field="alert-condition"], .condition-andor-text2').hide();
    if (val == 'submit') {
        // Form submit only
        $('[field="form-name"]').show();
    } else if (val == 'submit-logic') {
        // Form submit + logic
        $('[field="form-name"], [field="condition-andor"], [field="alert-condition"]').show();
    } else {
        // Logic only
        $('[field="alert-condition"], .condition-andor-text2').show();
    }
}

function checkTriggerSettings() {
    var trigger;
    var form = $('[name=external-modules-configure-modal] select[name="form-name"]').val();
    var logic = $('[name=external-modules-configure-modal] :input[name="alert-condition"]').val().trim();
    if ((form == '-' || form == '') && logic != '') {
        // Logic only
        trigger = 'logic';
    } else if (form != '-' && form != '' && logic != '') {
        // Form submit + logic
        trigger = 'submit-logic';
    } else {
        // Form submit only
        trigger = 'submit';
    }
    $('input[name="alert-trigger"][value="'+trigger+'"]').prop('checked',true).trigger('click');
}

function checkMessageSettings() {
    if (!$('[name=external-modules-configure-modal] input[name="alert-type"]').length || $('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val() == 'EMAIL') {
        // Email
        $('#code_modal_table_update tr[field="phone-number-to"]').hide();
        $('#code_modal_table_update tr[field="email-from"]').show();
        $('#code_modal_table_update tr[field="email-to"]').show();
        $('#code_modal_table_update tr[field="email-subject"]').show();
        $('#code_modal_table_update #showAttachments').show();
    } else {
        // SMS or Voice Call
        $('#code_modal_table_update tr[field="phone-number-to"]').show();
        $('#code_modal_table_update tr[field="email-from"]').hide();
        $('#code_modal_table_update tr[field="email-to"]').hide();
        $('#code_modal_table_update tr[field="email-subject"]').hide();
        $('#code_modal_table_update #showAttachments').hide();
    }
    showAttachmentFields(false);
}

/**
 * Function that shows the modal with the alert information to modify it
 * @param modal, array with the data from a specific aler
 * @param index, the alert id
 */
function editEmailAlert(modal, index, alertNum)
{
    if (!(isIE && vIE() < 11)) {
        tinymce.remove();
        initTinyMCE();
    }
    changedCronSendEmailOn = false;
    // Remove nulls
    for (var key in modal) {
       if (modal[key] == null) modal[key] = "";
    }
    if (!(isIE && vIE() < 11)) {
        for (var i = 0; i < tinymce.editors.length; i++) {
            var editor = tinymce.editors[i];
            editor.on('init', function () {
                editor.setContent(modal['alert-message'])
            });
        }
    }

    $("#index_modal_update").val(index);
    $('[name=cron-queue]').prop('checked', false);

    if (index == '') {
        $('#add-edit-title-text').html(lang.alerts_36);
    } else {
        $('#add-edit-title-text').html(lang.alerts_37+' #'+alertNum);
    }

    // Remove left-over items
    $('select .email-select-temp').remove();
    $('select .email-from-select-temp').remove();

   var twilio_enabled_alerts = !$('[name=external-modules-configure-modal] input#alert-type-sms').prop('disabled');
   if (!twilio_enabled_alerts) modal['alert-type'] = 'EMAIL';
    $('[name=external-modules-configure-modal] input[name="alert-type"][value="'+modal['alert-type']+'"]').prop('checked',true);

    // Phone numbers (Twilio only)
    $.each(modal['phone-number-to'].split(";"), function(i,e){
        e = trim(e);
        if (e == '') return;
        var isVariable = (e.charAt(0) == '[');
        if (!isVariable) {
            // Remove all non-numerals
            e = e.replace(/\D/g,'');
        }
        if (!$('[name=external-modules-configure-modal] select[name="phone-number-to"] option[value="' + e + '"]').length) {
            if ($('input[name="phone-number-to-freeform"]').length && isNumeric(e)) {
                var val = $('input[name="phone-number-to-freeform"]').val();
                if (val != '') val += '; ';
                $('input[name="phone-number-to-freeform"]').val(val+e);
            } else {
                if (!$('[name=external-modules-configure-modal] select[name="phone-number-to"] optgroup[label="' + langPrevSaved + '"]').length) {
                    $('[name=external-modules-configure-modal] select[name="phone-number-to"]').append('<optgroup class="email-select-temp" label="' + langPrevSaved + '"></optgroup>');
                }
                $('[name=external-modules-configure-modal] select[name="phone-number-to"] optgroup[label="' + langPrevSaved + '"]').append('<option class="email-select-temp" value="' + e + '">' + e + '</option>');
            }
        }
        $('[name=external-modules-configure-modal] select[name="phone-number-to"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });

    //Add values
    $('[name=external-modules-configure-modal] :input[name="email-deleted"]').val(modal['email-deleted']);
    $('[name=external-modules-configure-modal] input[name="alert-title"]').val(modal['alert-title']);
    $('[name=external-modules-configure-modal] input[name="email-from-display"]').val(modal['email-from-display']);
    $('[name=external-modules-configure-modal] select[name="form-name"]').val(modal['form-name']+'-'+modal['form-name-event']);
    $('[name=external-modules-configure-modal] select[name="email-incomplete"]').val(modal['email-incomplete']);
    $('[name=external-modules-configure-modal] select[name="cron-repeat-for-units"]').val(modal['cron-repeat-for-units']);
    $('[name=external-modules-configure-modal] :input[name="alert-condition"]').val(modal['alert-condition']);
    $('[name=external-modules-configure-modal] :input[name="email-repetitive"]').val(modal['email-repetitive']);
    $('[name=external-modules-configure-modal] :input[name="email-repetitive-change"]').val(modal['email-repetitive-change']);
    $('[name=external-modules-configure-modal] :input[name="email-repetitive-change-calcs"]').prop('checked', (modal['email-repetitive-change-calcs'] == '1' && modal['email-repetitive-change'] == '1'));
    $('[name=external-modules-configure-modal] :input[name="cron-repeat-for"]').val(modal['cron-repeat-for']);
    $('[name=external-modules-configure-modal] :input[name="cron-repeat-for-max"]').val(modal['cron-repeat-for-max']);
    if (modal['email-repetitive'] == '1' || modal['email-repetitive-change'] == '1') {
        $('[name="alert-send-how-many"][value="every"]').prop('checked', true);
        if (modal['email-repetitive'] == '1') {
            $('#every-time-type').val('every');
        } else if (modal['email-repetitive-change'] == '1' && modal['email-repetitive-change-calcs'] == '1') {
            $('#every-time-type').val('every-change-calcs');
        } else {
            $('#every-time-type').val('every-change');
        }
    } else {
        if (modal['cron-repeat-for'] == '0' || modal['cron-repeat-for'] == '') {
            $('[name="alert-send-how-many"][value="once"]').prop('checked',true);
        } else {
            $('[name="alert-send-how-many"][value="schedule"]').prop('checked',true);
        }
    }
    $('input[name="email-to-freeform"]').val('');
    $('input[name="email-cc-freeform"]').val('');
    $('input[name="email-bcc-freeform"]').val('');
    $('[name=external-modules-configure-modal] select[name="email-to"] option').prop("selected", false);
    var langPrevSaved = '-- Previously Saved --';
    $.each(modal['email-to'].split(";"), function(i,e){
        if (e == '') return;
        if (!$('[name=external-modules-configure-modal] select[name="email-to"] option[value="' + e + '"]').length) {
            if ($('input[name="email-to-freeform"]').length && isEmail(e)) {
                var val = $('input[name="email-to-freeform"]').val();
                if (val != '') val += '; ';
                $('input[name="email-to-freeform"]').val(val+e);
            } else {
                if (!$('[name=external-modules-configure-modal] select[name="email-to"] optgroup[label="' + langPrevSaved + '"]').length) {
                    $('[name=external-modules-configure-modal] select[name="email-to"]').append('<optgroup class="email-select-temp" label="' + langPrevSaved + '"></optgroup>');
                }
                $('[name=external-modules-configure-modal] select[name="email-to"] optgroup[label="' + langPrevSaved + '"]').append('<option class="email-select-temp" value="' + e + '">' + e + '</option>');
            }
        }
        $('[name=external-modules-configure-modal] select[name="email-to"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });
    $('[name=external-modules-configure-modal] select[name="email-cc"] option').prop("selected", false);
    $.each(modal['email-cc'].split(";"), function(i,e){
        if (e == '') return;
        if (!$('[name=external-modules-configure-modal] select[name="email-cc"] option[value="' + e + '"]').length) {
            if ($('input[name="email-cc-freeform"]').length && isEmail(e)) {
                var val = $('input[name="email-cc-freeform"]').val();
                if (val != '') val += '; ';
                $('input[name="email-cc-freeform"]').val(val+e);
            } else {
                if (!$('[name=external-modules-configure-modal] select[name="email-cc"] optgroup[label="' + langPrevSaved + '"]').length) {
                    $('[name=external-modules-configure-modal] select[name="email-cc"]').append('<optgroup class="email-select-temp" label="' + langPrevSaved + '"></optgroup>');
                }
                $('[name=external-modules-configure-modal] select[name="email-cc"] optgroup[label="' + langPrevSaved + '"]').append('<option class="email-select-temp" value="' + e + '">' + e + '</option>');
            }
        }
        $('[name=external-modules-configure-modal] select[name="email-cc"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });
    $('[name=external-modules-configure-modal] select[name="email-bcc"] option').prop("selected", false);
    $.each(modal['email-bcc'].split(";"), function(i,e){
        if (e == '') return;
        if (!$('[name=external-modules-configure-modal] select[name="email-bcc"] option[value="' + e + '"]').length) {
            if ($('input[name="email-bcc-freeform"]').length && isEmail(e)) {
                var val = $('input[name="email-bcc-freeform"]').val();
                if (val != '') val += '; ';
                $('input[name="email-bcc-freeform"]').val(val+e);
            } else {
                if (!$('[name=external-modules-configure-modal] select[name="email-bcc"] optgroup[label="' + langPrevSaved + '"]').length) {
                    $('[name=external-modules-configure-modal] select[name="email-bcc"]').append('<optgroup class="email-select-temp" label="' + langPrevSaved + '"></optgroup>');
                }
                $('[name=external-modules-configure-modal] select[name="email-bcc"] optgroup[label="' + langPrevSaved + '"]').append('<option class="email-select-temp" value="' + e + '">' + e + '</option>');
            }
        }
        $('[name=external-modules-configure-modal] select[name="email-bcc"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });
    $('[name=external-modules-configure-modal] select[name="email-attachment-variable"] option').prop("selected", false);
    $.each(modal['email-attachment-variable'].split(";"), function(i,e){
        if (e == '') return;
        $('[name=external-modules-configure-modal] select[name="email-attachment-variable"] option[value="' + e + '"]').prop("selected", true).addClass('ms-selection');
    });

    if (!$('[name=external-modules-configure-modal] select[name="email-from"] option[value="'+modal['email-from']+'"]').length) {
        $('[name=external-modules-configure-modal] select[name="email-from"]').append('<option class="email-from-select-temp" value="'+modal['email-from']+'">'+modal['email-from']+' '+lang.survey_1237+'</option>');
    }
    $('[name=external-modules-configure-modal] select[name="email-from"]').val(modal['email-from']);

    $('[name=external-modules-configure-modal] select[name="email-failed"]').val(modal['email-failed']);
    $('[name=external-modules-configure-modal] input[name="email-subject"]').val(modal['email-subject']);
    $('[name=external-modules-configure-modal] textarea[name="alert-message"]').val(modal['alert-message']);
    $('[name=external-modules-configure-modal] input[name="alert-expiration"]').val(modal['alert-expiration']);
    $('[name=external-modules-configure-modal] input[name="ensure-logic-still-true"]').prop('checked', modal['ensure-logic-still-true']=='1' );
    $('[name=external-modules-configure-modal] input[name="prevent-piping-identifiers"]').prop('checked', modal['prevent-piping-identifiers']=='1' );
    $('[name=external-modules-configure-modal] select[name="cron-send-email-on-next-day-type"]').val(modal['cron-send-email-on-next-day-type']);
    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-next-time"]').val(modal['cron-send-email-on-next-time'].substring(0,5));

    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-time-lag-days"]').val(modal['cron-send-email-on-time-lag-days']);
    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-time-lag-hours"]').val(modal['cron-send-email-on-time-lag-hours']);
    $('[name=external-modules-configure-modal] input[name="cron-send-email-on-time-lag-minutes"]').val(modal['cron-send-email-on-time-lag-minutes']);

    $('[name=external-modules-configure-modal] :input[name="cron-send-email-on-field"]').val(modal['cron-send-email-on-field']);
    if (modal['cron-send-email-on-field'] == '') modal['cron-send-email-on-field-after'] = 'after';
    $('[name=external-modules-configure-modal] :input[name="cron-send-email-on-field-after"]').val(modal['cron-send-email-on-field-after']);

    if (!$('[name=external-modules-configure-modal] :input[name="alert-stop-type"] option[value="'+modal['alert-stop-type']+'"]').length) {
        if ($('[name=external-modules-configure-modal] :input[name="alert-stop-type"] option[value="RECORD_EVENT"]').length) {
            modal['alert-stop-type'] = 'RECORD_EVENT';
        } else {
            modal['alert-stop-type'] = 'RECORD';
        }
    }
    $('[name=external-modules-configure-modal] :input[name="alert-stop-type"]').val(modal['alert-stop-type']);

    checkSchedule(modal['email-repetitive'], modal['email-repetitive-change'], modal['cron-send-email-on'], modal['cron-send-email-on-date'], modal['cron-repeat-for']);
    checkTriggerSettings();

    // Add Files
    $('.external-modules-edoc-file, button.external-modules-configure-modal-delete-file').remove();
    var fileDocIds = [];
    for (var i=1; i<6 ; i++){
        $('[name=external-modules-configure-modal] input[name="email-attachment'+i+'"]').val('').prop('type','file');
        if (isNumeric(modal['email-attachment' + i])) {
            fileDocIds.push(modal['email-attachment' + i]+','+i);
        }
    }
    if (fileDocIds.length > 0) {
        getFileFieldElement(fileDocIds);
    }

    //clean up error messages
    $('#errMsgContainerModal').empty();
    $('#errMsgContainerModal').hide();
    $('[name=external-modules-configure-modal] input[name=form-name]').removeClass('alert');
    $('[name=external-modules-configure-modal] :input[name=email-to]').removeClass('alert');
    $('[name=external-modules-configure-modal] input[name=email-subject]').removeClass('alert');
    $('[name=external-modules-configure-modal] [name=alert-message]').removeClass('alert');

    // Hide CC fields and attachments, if necessary
    if (trim(modal['email-cc']+modal['email-bcc']+modal['email-failed']) == '') {
        $('tr[field="email-cc"], tr[field="email-bcc"], tr[field="email-failed"]').hide();
        $('#showCC').addClass('d-block').removeClass('d-none');
    } else {
        $('tr[field="email-cc"], tr[field="email-bcc"], tr[field="email-failed"]').show();
        $('#showCC').removeClass('d-block').addClass('d-none');
    }
    showAttachmentFields(false);
    multipageSurveyWarningCheckDo();
    checkMessageSettings();
    setEmailRepetitiveFields();
    showStopType();

    //Show modal
    $('[name=external-modules-configure-modal]').modal('show');
}

// Reload the Survey Invitation Log for another "page" when paging the log
function loadNotificationLog(pagenum) {
    showProgress(1);
    window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:setup&log=1&pagenum='+pagenum+
        '&filterBeginTime='+$('#filterBeginTime').val()+'&filterEndTime='+$('#filterEndTime').val()+'&filterRecord='+$('#filterRecord').val()+'&filterAlert='+$('#filterAlert').val();
}

function showAttachmentFields(forceOpen) {
    var hideBtn = false;
    if ($('[name=external-modules-configure-modal] input[name="alert-type"]').length) {
        var alert_type = $('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val();
    } else {
        alert_type = 'EMAIL';
    }
    $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment3"], tr[field="email-attachment4"], tr[field="email-attachment5"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').hide();
    if (alert_type == 'EMAIL') {
        if (forceOpen || ($(':input[name="email-attachment-variable"]').length && $(':input[name="email-attachment-variable"]').val().length > 0)) {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
        if ($('input[name="email-attachment4"]').val() != '') {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment3"], tr[field="email-attachment4"], tr[field="email-attachment5"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
        if ($('input[name="email-attachment3"]').val() != '') {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment3"], tr[field="email-attachment4"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
        if ($('input[name="email-attachment2"]').val() != '') {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment3"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
        if ($('input[name="email-attachment1"]').val() != '') {
            $('.email-attachment-andor, tr[field="email-attachment1"], tr[field="email-attachment2"], tr[field="email-attachment-variable"], tr[field="email-attachment-hdr"]').show();
            hideBtn = true;
        }
    } else {
        hideBtn = true;
    }
    if (hideBtn) {
        $('tr[field="email-attachment-btn"]').hide();
    } else {
        $('tr[field="email-attachment-btn"]').show();
    }
    addNewAttachmentBtn();
    enableMultiSelect2();
}

function addNewAttachmentBtn() {
    $('.addNewAttachmentBtn').remove();
    if ($('td.email-attach-label-td:visible:last').length > 0 && $('tr[field="email-attachment5"]:visible').length == 0) {
        var lastVisibleAttachFld = 'email-attachment' + ($('td.email-attach-label-td:visible:last').parent().find('td:eq(1) input:first').prop('name').replace('email-attachment', '') * 1 + 1);
        $('td.email-attach-label-td:visible:last').append('<div class="fs11 mt-2 ml-3 addNewAttachmentBtn"><button onclick=\'$("tr[field='+lastVisibleAttachFld+']").show();addNewAttachmentBtn();return false;\' class="btn btn-outline-success btn-xs" style="border:0;"><i class="fas fa-plus"></i> '+lang.alerts_38+'</button></div>');
    }
}

/***FILES***/
function getAttributeValueHtml(s){
    if(typeof s == 'string'){
        s = s.replace(/"/g, '&quot;');
        s = s.replace(/'/g, '&apos;');
    }

    if (typeof s == "undefined") {
        s = "";
    }

    return s;
}

function getFileFieldElement(nextValues)
{
    if (nextValues.length < 1) {
        showAttachmentFields(false);
        return;
    }
    var valueCSV = nextValues.shift();
    var arr = valueCSV.split(',');
    var value = arr[0];
    var file_number = arr[1];
    var name = "email-attachment"+file_number+"";
    if (typeof value != "undefined" && value !== "" && value != null) {
        var html = '<input type="hidden" name="' + name + '" value="' + getAttributeValueHtml(value) + '" >';
        html += '<span class="external-modules-edoc-file"></span> ';
        html += '<button class="btn btn-xs btn-outline-danger external-modules-configure-modal-delete-file" style="border:0;" onclick="hideFile('+value+','+file_number+')"><i class="fas fa-times"></i> '+lang.docs_72+'</button>';
        $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:getEdocName', { edoc : value }, function(data) {
            $("[name='"+name+"']").closest("tr").find(".external-modules-edoc-file").html("<b>" + data.doc_name + "</b> ");
            // Call recursively until we're done with all of them
            getFileFieldElement(nextValues);
        });
    } else {
        var html = '<input type="file" name="' + name + '" value="' + getAttributeValueHtml(value) + '" class="external-modules-input-element">';
    }
    $('[name=external-modules-configure-modal] input[name="email-attachment'+file_number+'"]').parent().html(html);
}

function hideFile(value,file_number){
    var name = "email-attachment"+file_number;
    var html = '<input type="file" name="' + name + '" value="" class="external-modules-input-element">';
    html += '<input type="hidden" name="'+name+'" value="'+value+'" class="external-modules-input-element deletedFile">';
    $('[name=external-modules-configure-modal] input[name="email-attachment'+file_number+'"]').parent().html(html);
}

function deleteEmailAlert(index,modal,indexmodal){
    $('#'+indexmodal).val(index);
    $('#'+modal).modal('show');
}

function reactivateEmailAlert(index){
    ajaxLoadOptionAndMessage("&index_modal_delete_user="+index+"&enable=1",app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteAlert',"R");
}

function duplicateEmailAlert(index){
    ajaxLoadOptionAndMessage("&index_duplicate="+index,app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:copyAlert',"P");
}

function addQueue(index,form){
    $('#external-modules-configure-modal-addQueue').modal('show');
    $('#index_modal_queue').val(index);
    alert('TODO: add form+event dropdown here!')
    // var data = "index="+index+"&form="+form+"&queue=1";
    // $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:getEventByForm', data, function(returnData){
    //     jsonAjax = jQuery.parseJSON(returnData);
    //     $('#event_queue').html(jsonAjax.event);
    // });
}

/**
 * Function that reloads the page and updates the success message
 * @param letter
 * @returns {string}
 */
function getUrlMessageParam(letter){
    var url = window.location.href;
    if (letter == '') return url;
    if (url.substring(url.length-1) == "#")
    {
        url = url.substring(0, url.length-1);
    }
    if(window.location.href.match(/(&message=)([A-Z]{1})/)){
        url = url.replace( /(&message=)([A-Z]{1})/, "&message="+letter );
    }else{
        url = url + "&message="+letter;
    }
    return url;
}

/**
 * Function that checks if all required fields form the alerts are filled @param errorContainer
 * @returns {boolean}
 */
function checkRequiredFieldsAndLoadOption(){
    $('#succMsgContainer').hide();
    $('#errMsgContainerModal').hide();
    if ($('[name=external-modules-configure-modal] input[name="alert-type"]').length) {
        var alert_type = $('[name=external-modules-configure-modal] input[name="alert-type"]:checked').val();
    } else {
        alert_type = 'EMAIL';
    }

    var errMsg = [];
    if (alert_type == "EMAIL") {
        var hasFreeformEmails = $('input[name="email-to-freeform"]').length;
        if ($('[name=external-modules-configure-modal] :input[name=email-to] option:selected').length == 0) {
            if (!hasFreeformEmails || (hasFreeformEmails && $('input[name="email-to-freeform"]').val().trim().length == 0)) {
                $('[name=external-modules-configure-modal] :input[name=email-to]').addClass('alert');
                errMsg.push(lang.alerts_197);
            }
        } else {
            $('[name=external-modules-configure-modal] :input[name=email-to]').removeClass('alert');
        }
        if ($('[name=external-modules-configure-modal] input[name=email-subject]').val() === "") {
            errMsg.push(lang.alerts_214);
            $('[name=external-modules-configure-modal] input[name=email-subject]').addClass('alert');
        } else {
            $('[name=external-modules-configure-modal] input[name=email-subject]').removeClass('alert');
        }
    } else {
        var hasFreeformPhones = $('input[name="phone-number-to-freeform"]').length;
        if ($(':input[name=phone-number-to]').val() == ''
            && (!hasFreeformPhones || (hasFreeformPhones && $('input[name="phone-number-to-freeform"]').val().trim().length == 0))) {
            $(':input[name=phone-number-to-freeform]').addClass('alert');
            errMsg.push(lang.alerts_197);
        } else {
            $(':input[name=phone-number-to-freeform]').removeClass('alert');
        }
    }

    if ($('[name=external-modules-configure-modal] select[name=form-name]').val() == "-" && $('[name=external-modules-configure-modal] :input[name=alert-condition]').val().trim() == "") {
        errMsg.push(lang.alerts_198);
        $('[name=external-modules-configure-modal] select[name=form-name]').addClass('alert');
        $('[name=external-modules-configure-modal] :input[name=alert-condition]').addClass('alert');
    }else{
        $('[name=external-modules-configure-modal] select[name=form-name]').removeClass('alert');
    }

    if (isIE && vIE() < 11) {
        var editor_text = $(':input[name="alert-message"]').val();
    } else {
        var editor_text = tinymce.activeEditor.getContent();
    }
    if(editor_text == ""){
        errMsg.push(lang.alerts_39);
        $('#alert-message_ifr').addClass('alert');
    }else{ $('#alert-message_ifr').removeClass('alert');}

    if (errMsg.length > 0) {
        $('#errMsgContainerModal').empty();
        $.each(errMsg, function (i, e) {
            $('#errMsgContainerModal').append('<div>' + e + '</div>');
        });
        $('#errMsgContainerModal').show();
        $('html,body').scrollTop(0);
        $('[name=external-modules-configure-modal]').scrollTop(0);
        return false;
    }
    else {
        return true;
    }
}

function ajaxLoadOptionAndMessage(data, url, message){
    $.post(url, data, function(returnData){
        jsonAjax = jQuery.parseJSON(returnData);
        if(jsonAjax.status == 'success'){
            //refresh page to show changes
            if(jsonAjax.message != '' && jsonAjax.message != undefined){
                message = jsonAjax.message;
            }
            var newUrl = getUrlMessageParam(message);
            if (newUrl.substring(newUrl.length-1) == "#")
            {
                newUrl = newUrl.substring(0, newUrl.length-1);
            }
            window.location.href = newUrl;
        } else {
	        alert(woops);
        }
    });
}

// Hide Step 1C if choose either of the "every time" options in 2B
function showStopType() {
    if (!$('tr[field="alert-stop-type"]').length) return;
    if ($('[name="alert-send-how-many"]:checked').val() == 'every') {
        if ($('tr[field="alert-stop-type"]').text().trim() != '') {
            // Hide (but only if visible)
            $('tr[field="alert-stop-type"]').hide();
        }
    } else {
        $('tr[field="alert-stop-type"]').show();
    }
}

// Set values for email-repetitive fields
function setEmailRepetitiveFields() {
    // Reset to defaults
    $(':input[name="email-repetitive"], :input[name="email-repetitive-change"], :input[name="email-repetitive-change-calcs"]').val('0');
    // Set based on selections
    if ($('[name="alert-send-how-many"]:checked').val() == 'every') {
        var everyTimeType = $('#every-time-type option:selected').val();
        if (everyTimeType == 'every-change-calcs') {
            $(':input[name="email-repetitive"]').val('0');
            $(':input[name="email-repetitive-change"], :input[name="email-repetitive-change-calcs"]').val('1');
        } else if (everyTimeType == 'every-change') {
            $(':input[name="email-repetitive-change"]').val('1');
            $(':input[name="email-repetitive"], :input[name="email-repetitive-change-calcs"]').val('0');
        } else {
            $(':input[name="email-repetitive"]').val('1');
            $(':input[name="email-repetitive-change"], :input[name="email-repetitive-change-calcs"]').val('0');
        }
    }
}

function uploadRepeatableInstances(data){
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:displayRepeatingFormTextboxQueue', data, function(returnData){
        jsonAjax = jQuery.parseJSON(returnData);
        if (jsonAjax.status == 'success') {
            $('#addQueueInstance').html(jsonAjax.instance);
        }
        else {
            alert(woops);
        }
    });
}

function getInstances(element){
    uploadRepeatableInstances('event='+element.value+'&index_modal_queue='+$('#index_modal_queue').val());
}
function saveFilesIfTheyExist(url, files, alertExists, data) {
    var lengthOfFiles = 0;
    var formData = new FormData();
    for (var name in files) {
        lengthOfFiles++;
        formData.append(name, files[name]);   // filename agnostic
    }
    // Find available file fields to use
    var filesAvail = new Array();
    var x=0, thisFileVal;
    for (var i=1; i<=5; i++){
        thisFileVal = $('input[name="email-attachment'+i+'"]').val();
        if (thisFileVal != '' && !isNumeric(thisFileVal)) {
            filesAvail[x++] = i;
        }
    }
    if (lengthOfFiles > 0) {
        x = 0;
        $.ajax({
            url: url,
            data: formData,
            processData: false,
            contentType: false,
            async: false,
            type: 'POST',
            success: function(returnData) {
                if (returnData.status == 'success') {
                    var attach = returnData.doc_ids.split(',');
                    for (var i=0; i<attach.length; i++){
                        data += '&email-attachment'+filesAvail[i]+'='+attach[i];
                    }
                } else {
                    alert(returnData.status+" "+lang.alerts_40);
                }
            },
            error: function(e) {
                alert(lang.alerts_40+" "+JSON.stringify(e));
            }
        });
    }
    return data;
}

function deleteFile(index, data) {
    $('.deletedFile').each(function() {
        var inputname = $(this).attr('name');
        $.ajax({
            url: app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteAttachment',
            type: 'POST',
            data: { key: inputname, edoc: $(this).val(), index: index, redcap_csrf_token: redcap_csrf_token },
            async: false,
            success:
                function(data2){
                    if (data2.status == "success") {
                        $('input[type="hidden"][name="'+inputname+'"]').val('');
                        data += '&'+inputname+'=';
                    } else {
                        // failure
                        alert(lang.alerts_41+" "+JSON.stringify(data2));
                    }
                }
        });
    });
    return data;
};

function showChangeRecurrenceDialog() {
    return (changedCronSendEmailOn && $('#index_modal_update').val() != '' && $('input[name="alert-send-how-many"][value="schedule"]').prop('checked'));
}

function datepicker_init() {
    if ($('.alert-datetimepicker').length) {
        $('.alert-datetimepicker').datetimepicker({
            buttonText: lang.alerts_42, yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
            hour: currentTime('h'), minute: currentTime('m'),
            showOn: 'both', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
        });
    }
    if ($('.alert-datepicker').length) {
        $('.alert-datepicker').datepicker({
            dateFormat: user_date_format_jquery,
            yearRange: '-100:+10', showOn: 'button', buttonImage: app_path_images+'date.png', buttonImageOnly: true,
            showOn: 'both', changeMonth: true, changeYear: true
        });
    }
}

function multipageSurveyWarningCheck() {
    if (multipageSurveys.length == 0 || $('select[name="form-name"]').val() == null) return false;
    var formArr = $('select[name="form-name"]').val().split('-');
    var form = formArr[0];
    // If the selected form is not a multi-page survey instrument, then leave
    if (!in_array(form, multipageSurveys)) return false;
    // If the "every time send" option is not selected, then leave
    if ($('[name="alert-send-how-many"]:checked').val() != 'every') return false;
    // Display message about using multi-page survey with "every time send" option
    return true;
}

function multipageSurveyWarningCheckDo() {
    if (multipageSurveyWarningCheck()) {
        $('#email-repetitive-multipage-warning').show();
    } else {
        $('#email-repetitive-multipage-warning').hide();
    }
}

// Validate the fields in the user-defined logic as real fields
function validate_logic(thisLogic) {
    // First, make sure that the logic is not blank
    if (trim(thisLogic).length < 1) return;
    // Make ajax request to check the logic via PHP
    $.post(app_path_webroot+'Design/logic_validate.php?pid='+pid, { logic: thisLogic, forceMetadataTable: 1 }, function(data){
        if (data == '1') {
            // All good!
        } else if (data == '0') {
            alert(woops);
            return false;
        } else {
            alert(data);
            return false;
        }
    });
}

function datepicker_destroy() {
    $('.alert-datetimepicker').datetimepicker("destroy");
    $('.alert-datepicker').datepicker("destroy");
}

function enableMultiSelect2() {
    $("#email-to, #email-cc, #email-bcc").select2({
        placeholder: lang.alerts_43
    });
    $("#email-attachment-variable").select2({
        placeholder: lang.alerts_44
    });
    $("#phone-number-to").select2({
        placeholder: lang.alerts_43
    });
}

var alertsDataTable;
var dataTableSettings = {
    "autoWidth": false,
    "processing": true,
    "paging": false,
    "info": false,
    "aaSorting": [],
    "fixedHeader": { header: false, footer: false },
    "searching": true,
    "ordering": false,
    "oLanguage": { "sSearch": "" },
}

$(function() {
    jQuery('[data-toggle="popover"]').popover({
        html : true,
        content: function() {
            return $(jQuery(this).data('target-selector')).html();
        },
        title: function(){
            return '<span style="padding-top:0px;">'+jQuery(this).data('title')+'<span class="close" style="line-height: 0.5;padding-top:0px;padding-left: 10px">&times;</span></span>';
        }
    }).on('shown.bs.popover', function(e){
        var popover = jQuery(this);
        jQuery(this).parent().find('div.popover .close').on('click', function(e){
            popover.popover('hide');
        });
        $('div.popover .close').on('click', function(e){
            popover.popover('hide');
        });

    });
    //We add this or the second time we click it won't work. It's a bug in bootstrap
    $('[data-toggle="popover"]').on("hidden.bs.popover", function() {
        //BOOTSTRAP 4
        $(this).data("bs.popover")._activeTrigger.click = false;
    });

    //To prevent the popover from scrolling up on click
    $("a[rel=popover]")
        .popover()
        .click(function(e) {
            e.preventDefault();
        });

    //Messages on reload
    if(message != "") {
        $("#succMsgContainer").html(message);
        setTimeout(function(){
            $('#succMsgContainer').slideToggle('normal');
        },300);
        setTimeout(function(){
            $('#succMsgContainer').slideToggle(1200);
        }, 5000);
    }

    $('[name="alert-trigger"], [name="cron-send-email-on"]').click(function(e){
        displayTriggerSettings();
        // Set bold on label
        var thisname = $(this).prop('name');
        if (thisname == 'alert-trigger') {
            $('[name="alert-trigger"]').parent().find('label[for]').removeClass('boldish');
            var idVal = $(this).attr("id");
            var label = $("label[for='"+idVal+"']");
            label.addClass('boldish');
        }
        // Hide/display email-repetitive field
        if ($('[name="alert-trigger"]:checked').val() == 'logic' || $('[name="cron-send-email-on"]:checked').val() != 'now') {
            $('#alert-send-how-many2').parent().parent().hide();
            // If field is checked, then move selection to other option since we're hiding this one
            if ($('[name="alert-send-how-many"]:checked').val() == 'every') {
                $('[name="alert-send-how-many"][value="once"]').prop('checked',true);
            }
        } else {
            $('#alert-send-how-many2').parent().parent().show();
        }
    });

    /***SCHEDULED EMAIL OPTIONS***/
    $('[name="cron-send-email-on"]').on('click', function(e){
        if($(this).val() == 'date'){
            $('[name="cron-send-email-on-date"]').focus();
        }
    });

    $('#addQueue .close').on('click', function () {
        $('#addQueueInstance').html('');
    });

    $('#btnModalRescheduleForm2').click(function() {
        $('#saveAlert').submit();
    });

    $('[name="alert-send-how-many"]').click(function(){
        setEmailRepetitiveFields();
        showStopType();
    });

    $('#every-time-type').change(function(){
        setEmailRepetitiveFields();
        showStopType();
    });

    $('#external-modules-configure-modal-record').on('hidden.bs.modal', function () {
        //clean up
        $('[name=preview_record_id]').val('');
        $('#modal_message_record_preview').html('');
    });

    $('#btnModalsaveAlert').click(function()
    {
        // Make sure that email text does not contain survey-link or survey-url without an instrument
        var editor_text = tinymce.activeEditor.getContent();
        if (editor_text.indexOf('[survey-url]') > -1 || editor_text.indexOf('[survey-link]') > -1 || editor_text.indexOf('[form-url]') > -1 || editor_text.indexOf('[form-link]') > -1) {
            simpleDialog(lang.alerts_46, lang.alerts_45);
            return;
        }

        if (showChangeRecurrenceDialog()) {
            $('[name=cron-queue]').prop('checked', true);
            $('#external-modules-configure-modal').modal('hide');
            $('#external-modules-configure-modal-schedule-confirmation').modal('show');
        } else {
            $('#saveAlert').submit();
        }
    });

    $('#saveAlert').submit(function ()
    {
        // Clear form-name if using logic only
        if ($('input[name="alert-trigger"]').val() == 'logic') {
            $('[name=external-modules-configure-modal] select[name="form-name"]').val('');
        }

        var data = $('#saveAlert').serialize();
        if (!(isIE && vIE() < 11)) {
            var editor_text = tinymce.activeEditor.getContent();
            data += "&alert-message-editor=" + encodeURIComponent(editor_text);
        }
        data += "&email-to="+ encodeURIComponent($('#email-to').val());
        data += "&email-cc="+encodeURIComponent($('#email-cc').val());
        data += "&email-bcc="+encodeURIComponent($('#email-bcc').val());
        if ($('#phone-number-to').length) {
            data += "&phone-number-to=" + encodeURIComponent($('#phone-number-to').val());
        }

        if ($('#email-attachment-variable').length) {
            data += "&email-attachment-variable=" + encodeURIComponent($('#email-attachment-variable').val());
        }

        var files = {};
        $('#saveAlert').find('input, select, textarea').each(function(index, element){
            var element = $(element);
            var name = element.attr('name');
            var type = element[0].type;

            if (type == 'file') {
                name = name.replace("", "");
                // only store one file per variable - the first file
                jQuery.each(element[0].files, function(i, file) {
                    if (typeof files[name] == "undefined") {
                        files[name] = file;
                    }
                });
            }
        });
        if (checkRequiredFieldsAndLoadOption()) {
            //close confirmation modal
            $('#external-modules-configure-modal-schedule-confirmation').modal('hide');
            $('#external-modules-configure-modal').modal('hide');
            var index = $('#index_modal_update').val();
            data = deleteFile(index, data);
            data = saveFilesIfTheyExist(app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:saveAttachment', files, 1, data);
            ajaxLoadOptionAndMessage(data,app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:saveAlert',(index == "" ? "A" : "U"));
        }
        return false;
    });

    $('#deleteUserForm').submit(function () {
        var data = $('#deleteUserForm').serialize();
        ajaxLoadOptionAndMessage(data,app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteAlert',"D");
        return false;
    });

    $('#deleteForm').submit(function () {
        var data = $('#deleteForm').serialize();
        ajaxLoadOptionAndMessage(data,app_path_webroot+'index.php?pid='+pid+'&route=AlertsController:deleteAlertPermanent',"B");
        return false;
    });

    //To filter the data
    $.fn.dataTable.ext.search.push(
        function( settings, data, dataIndex ) {
            var deleted = $('#deleted_alerts').is(':checked');
            var column_deleted = data[3];
            if (deleted && column_deleted == 'Y') {
                return true;
            } else if (!deleted && column_deleted == 'N'){
                return true;
            }
            return false;
        }
    );

    // DataTable
    alertsDataTable = $('#customizedAlertsPreview').DataTable(dataTableSettings);
    $('#customizedAlertsPreview_filter input[type="search"]').attr('type','text').prop('placeholder','Search');
    $('#customizedAlertsPreview').show();
    alertsDataTable.draw();

    //When message reactivated reload on the Deleted status
    if(message_letter == 'R' || message_letter === 'N'){
        $('#deleted_alerts').prop('checked',true);
        alertsDataTable.draw();
    }
    // If copied an alert, then highlight the alert
    else if (message_letter == 'P') {
        $("html, body").animate({ scrollTop: $(document).height() }, "normal");
        $('#customizedAlertsPreview tr:last td:visible').effect('highlight',{},4000);
    }

    $('.trigger-descrip').each(function(){
        $clamp(document.getElementById($(this).prop('id')), {clamp: 3});
    });

    $('.expire-descrip').each(function(){
        $clamp(document.getElementById($(this).prop('id')), {clamp: 2});
    });

    // Date/time pickers
    datepicker_init();

    // If using IE9 or IE10, give notice that not everything will work
    if (isIE && vIE() <= 9) {
        $('#errMsgContainerIE9').show();
    } else if (isIE && vIE() <= 10) {
        $('#errMsgContainerIE10').show();
    }

    //when any of the filters is called upon change datatable data
    $('#deleted_alerts').change( function() {
        alertsDataTable.draw();
    } );

    $('#showCC').click(function(){
        $(this).removeClass('d-block').addClass('d-none');
        $('tr[field="email-cc"], tr[field="email-bcc"], tr[field="email-failed"]').show();
        enableMultiSelect2();
    });

    $('#showAttachments').click(function(){
        showAttachmentFields(true);
        return false;
    });

    $('input[name="cron-repeat-for"], input[name="cron-repeat-for-max"]').blur(function(){
        $('input[name="alert-send-how-many"][value="schedule"]').prop('checked', true);
    });

    $(':input[name="cron-repeat-for-units"]').on('click change', function(){
        $('input[name="alert-send-how-many"][value="schedule"]').prop('checked', true);
    });

    $('input[name="cron-send-email-on"]').change(function(e){
        changedCronSendEmailOn = true;
    });

    $('input[name="prevent-piping-identifiers"]').click(function(e){
        if (!$(this).prop('checked')) {
            setTimeout(function(){
                $('input[name="prevent-piping-identifiers"]').prop('checked',true);
            },100);
            simpleDialog(null,null,'prevent-piping-dialog',500,null,lang.alerts_48,function(){
                $('input[name="prevent-piping-identifiers"]').prop('checked',false);
            },lang.alerts_47);
        }
    });

    $('input[name="cron-send-email-on-date"]').change(function(e){
        changedCronSendEmailOn = true;
        if ($(this).val() != '') {
            $('input[name="cron-send-email-on"][value="date"]').prop('checked', true);
        }
    });

    $(':input[name="cron-send-email-on-field"], :input[name="cron-send-email-on-time-lag-days"], :input[name="cron-send-email-on-time-lag-hours"], :input[name="cron-send-email-on-time-lag-minutes"]').change(function(e){
        changedCronSendEmailOn = true;
        if ($(this).val() != '') {
            $('input[name="cron-send-email-on"][value="time_lag"]').prop('checked', true);
        }
    });

    // Check to/cc/bcc freeform email fields
    $('input[name="email-to-freeform"], input[name="email-cc-freeform"], input[name="email-bcc-freeform"]').blur(function(){
        var val = $(this).val().toLowerCase().replace(/\s/g,'').replace(/,/g,';');
        $(this).val(val);
        if (val == '') return;
        var emails = val.split(';');
        var invalid = new Array();
        var invalid_domain = new Array();
        var k = 0, j = 0;
        for (var i=0; i < emails.length; i++) {
            var email = emails[i];
            if (isEmail(email)) {
                // If we're using email domain allowlist, then validate
                if (alerts_email_freeform_domain_allowlist.length > 0 && !super_user) {
                    var thisEmailParts = email.split('@');
                    var thisEmailDomain = thisEmailParts[1];
                    if (!in_array(thisEmailDomain, alerts_email_freeform_domain_allowlist)) {
                        invalid_domain[j++] = email;
                    }
                }
            } else {
                // Not an email
                invalid[k++] = email;
            }
        }
        var thisInputName = $(this).prop('name');
        $(this).val(val.replace(/;/g,'; '));
        if (invalid.length > 0) {
            simpleDialog(lang.alerts_49+' <b>'+invalid.join("</b>, <b>")+'</b>'+lang.period+' '+lang.alerts_50,
                lang.global_01,null,null,'try{$("input[name='+thisInputName+']").focus()}catch(e){}');
        } else if (invalid_domain.length > 0) {
            simpleDialog(lang.alerts_51
                +'<br><br>'+lang.alerts_52+'<br><b>'+alerts_email_freeform_domain_allowlist.join('<br>')+'</b>'
                +'<br><br>'+lang.alerts_53+'<br><b>'+invalid_domain.join('<br>')+'</b>'
                ,lang.alerts_54,null,550,'try{$("input[name='+thisInputName+']").focus()}catch(e){}');
        }
    });

    // Run this after all elements in the dialog are visible when main dialog is opened
    $('[name=external-modules-configure-modal]').on('shown.bs.modal', function (e) {
        showAttachmentFields(false);
    });

    // Set datetime pickers
    $('.filter_datetime_mdy').datetimepicker({
        yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
        hour: currentTime('h'), minute: currentTime('m'), buttonText: lang.alerts_42,
        timeFormat: 'hh:mm', constrainInput: true
    });

    // Add fade mouseover for "delete scheduled invitation" icons
    $(".inviteLogDelIcon").mouseenter(function() {
        $(this).removeClass('opacity50');
    }).mouseleave(function() {
        $(this).addClass('opacity50');
    });

    $('select[name="form-name"], [name="alert-send-how-many"]').change(function(){
        multipageSurveyWarningCheckDo();
    });
});