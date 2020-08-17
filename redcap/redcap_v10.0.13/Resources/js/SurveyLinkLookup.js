"use strict";
/**
 * Survey Link Lookup External Module
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
(function(window, document, $, app_path_webroot, undefined)
{
    function getResults(lookupVal) {
        return $.getJSON(
            app_path_webroot+page,
            { lookup: lookupVal },
            function(data) {
                return data;
            }
        );
    }

    function searchBtnActiveState(active) {
        $('button#btnFind').prop("disabled",!active);
    }

    function resultPaneState(show) {
        var resultPane = $('div#results');
        var resultPaneDivs = $(resultPane).children('div');

        if (show) {
            $(resultPane).show();
            $(resultPaneDivs).each(function() {
                if (this.id===show) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            $(resultPane).hide();
            $(resultPaneDivs).each(function() {
                $(this).hide();
            });
        }
    }

    function displayResults(results) {
        if (!results) {
            results = {};
            results.project_id='';
            results.app_title='';
            results.survey_title='';
            results.app_title='';
            results.record='';
            results.event_id='';
            results.arm_id='';
            results.event_name='';
            results.form_name='';
            results.instance='';
            results.is_public_survey_link=false;
        }
        var setupPageHref = (results.project_id) 
            ? app_path_webroot+'ProjectSetup/index.php?pid='+results.project_id
            : '#';
        var designPageHref = (results.project_id) 
            ? app_path_webroot+'Design/online_designer.php?pid='+results.project_id
            : '#';
        var dataEntryPageHref = (results.project_id && results.record) 
            ? app_path_webroot+'DataEntry/index.php?pid='+results.project_id+'&id='+results.record+'&event_id='+results.event_id+'&page='+results.form_name+'&instance='+results.instance
            : '#';
        var publicSurveyPageHref = (results.project_id && results.is_public_survey_link) 
            ? app_path_webroot+'Surveys/invite_participants.php?pid='+results.project_id+'&public_survey=1&arm_id='+results.arm_id
            : '#';

        $('span#result_project_id').html(results.project_id);
        $('span#result_app_title').html(results.app_title);
        $('span#result_survey_title').html(results.survey_title);
        $('span#result_record').html(results.record);
        $('span#result_event_name').html(results.event_name);
        $('span#result_instance').html(results.instance);
        $('a#result_link_setup_page').attr('href', setupPageHref);
        $('a#result_link_designer_page').attr('href', designPageHref);
        $('a#result_link_data_entry_page').attr('href', dataEntryPageHref).toggle(results.is_public_survey_link==false);
        $('a#result_link_public_survey_page').attr('href', publicSurveyPageHref).toggle(results.is_public_survey_link==true);
    }

    function displayError(msg) {
        $('span#result_error_msg').html((msg)?msg:'');
    }

    function clearResults(){
        resultPaneState(false);
        displayResults(false);
        displayError(false);
    }

    function link_lookup() {
        clearResults();
        var searchFor = $('input#lookup_val').val();
        if (searchFor) {
            window.history.pushState({ dummy: true },"REDCap", app_path_webroot+page+'?lookup='+encodeURIComponent(searchFor));
            resultPaneState('results_spin');
            searchBtnActiveState(false);

            $.when(getResults(searchFor))
            .always(function(results) {
                if (results.lookup_success) {
                    displayResults(results.lookup_result);
                    resultPaneState('results_detail');
                } else {
                    var resultMessage = 'Something went wrong with that lookup.';
                    if (results.lookup_success===false) { 
                        resultMessage = results.lookup_result; 
                    } else if (results.responseText) { 
                        resultMessage = results.responseText; 
                    }                
                    displayError(resultMessage);
                    resultPaneState('results_error');
                }
                searchBtnActiveState(true);
            });

        }
    }
    
    function getQuerystringParameter(paramName) {
        var searchString = window.location.search.substring(1),
                i, val, params = searchString.split("&");

        for (i=0; i<params.length; i++) {
            val = params[i].split("=");
            if (val[0]===paramName) {
                return val[1];
            }
        }
        return null;
    }
    
    function init() {
        clearResults();
        $('button#btnFind').click(function() {
            link_lookup();
        });
        $('input#lookup_val').keydown(function(event) {
            if(event.keyCode==13) { 
                $('button#btnFind').focus().click(); 
            }
        });
    }

    $(document).ready(function() {
        init();
        link_lookup(); // look up anything in the search box on page load
        $('#lookup_val').focus();
    });
})(window, document, jQuery, app_path_webroot);
