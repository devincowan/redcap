$(function(){

  'use strict';

  function getUrlParameter(param) {
    var pageURL = decodeURIComponent(window.location.search.substring(1)),
        URLVariables = pageURL.split('&'),
        parameterName,
        i;

    for (i = 0; i < URLVariables.length; i++) {
        parameterName = URLVariables[i].split('=');

        if (parameterName[0] === param) {
            return parameterName[1] === undefined ? 'request_time' : parameterName[1];
        }
    }
  };

  var action = getParameterByName('action');
  switch(action) {
    case 'prompt_delete_window':
      if (super_user && !isInteger(getParameterByName('request_id'))) {
          deleteProject(0, '1');
      }
      break;
    case 'prompt_confirm_window':
      showReqCopyConfirmation();
      break;
  }

});

function deleteProject(delete_now,super_user_request) {
    delete_now = (delete_now == null || delete_now != 1) ? '0' : '1';
    super_user_request = (super_user_request == null || super_user_request != 1) ? '0' : '1';
    $.post(app_path_webroot+'ProjectGeneral/delete_project.php?pid='+pid, { action: 'prompt', delete_now: delete_now }, function(data) {
        initDialog("del_db_dialog",data);
        $('#del_db_dialog').dialog({ bgiframe: true, title: 'Permanently delete this project?', modal: true, width: 550, buttons: {
                Cancel: function() { $(this).dialog('close'); } ,
                'Delete the project': function() {
                    if (trim($('#delete_project_confirm').val().toLowerCase()) != "delete") {
                        simpleDialog('You must type "DELETE" first.');
                        return;
                    }
                    simpleDialog('<span style="font-size:14px;color:#800000;">Are you really sure you wish to delete this project?</span>','CONFIRM DELETION',null,null,"$('#del_db_dialog').dialog('close');",'Cancel','delete_project_do('+pid+','+delete_now+','+super_user_request+')','Yes, delete the project');
                }
            } });
    });
}

function showReqCopyConfirmation(){
    var $container = $('<div>',{
            class: 'copy-req-msg-container',
        }),
        $msgWrapper = $('<div>',{
            class: 'copy-req-msg',
        }),
        $img = $('<img>',{
            class: 'copy-req-img',
            src: app_path_images+'tick.png',
        }),
        $text = $('<p>',{
            class: 'copy-req-text',
            text: 'Success! A request to COPY this project has been sent to a REDCap administrator'
        });
    $msgWrapper.append($img).append($text);
    $container.append($msgWrapper);
    $('.copy-bck-target').append($container);
    setTimeout(function(){
        $container.velocity({height:'39px'},{duration: 700, complete: function(){
                $container.velocity({height:0},{duration: 700, delay:2500});
            }
        });
    },500);
}