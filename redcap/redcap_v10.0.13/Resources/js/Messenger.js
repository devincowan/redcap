// getCursorPosition inside a text box
(function ($, undefined) {
    $.fn.getCursorPosition = function() {
        var el = $(this).get(0);
        var pos = 0;
        if('selectionStart' in el) {
            pos = el.selectionStart;
        } else if('selection' in document) {
            el.focus();
            var Sel = document.selection.createRange();
            var SelLength = document.selection.createRange().text.length;
            Sel.moveStart('character', -el.value.length);
            pos = Sel.text.length - SelLength;
        }
        return pos;
    }
})(jQuery);

// Messenger
var eUiid;
$(function(){

  'use strict';

  function initializeMessenger(){
  
  //detect screen width
  var width = $(window).width();
  var maxFileUpload;
  var screenSize;
  if(width > 1024){
    screenSize = 'big-screen';
  }else if(width < 1024 && width > 769){
    screenSize = 'small-screen';
  }else{
    screenSize = 'mobile-screen';
  }
  $('body').addClass(screenSize);

  var develop = false;

  //set message center container height to window height
  $('.message-center-container').height($(window).height());

  if($('.message-center-messages-container').length > 0){//if messages panel is open
    var container = $('.message-center-messages-container');
    if(container.attr('data-thread_id') != 1 && container.attr('data-thread_id') != 3){
      $('.channel-members-count, .channel-members-username').bind('click',function(e){
        var thread_id = $('.msgs-wrapper').attr('data-thread_id');
        openMembersPanel(e,thread_id,'','pageload');
      });
    }
    var height = $('.msgs-wrapper')[0].scrollHeight;
    $('.msgs-wrapper').scrollTop(height);
  }

  function diffArray(arrOne,arrTwo){
    var newArr = [];

    arrOne.forEach(function(val){
     if(arrTwo.indexOf(val) < 0) newArr.push(val);
    });

    arrTwo.forEach(function(val){
     if(arrOne.indexOf(val) < 0) newArr.push(val);
    });

    return newArr;
  }

  function clearSimpleDialog(){
    $('body').find('.ui-dialog-buttonpane .confirm-btn, .ui-dialog-buttonpane .input-confirm-delete, .edit-panel-delete-message, .edit-panel-edit-message').remove();
  }

  function getMessageSectionHeight(){
    if($('.message-section[data-section="channel"] .mc-message').length > 0){
        var itemHeight = $($('.message-section[data-section="channel"] .mc-message')[0]).outerHeight(true),
            length = $('.message-section[data-section="channel"] .mc-message').length;
        var newHeight = (length*itemHeight)+1;
        return (newHeight < 451 ? newHeight : 450);//set max-height value
    }
    return;
  }

  function updateMessageSectionHeight(){
    var itemHeight = $($('.message-section[data-section="channel"] .mc-message')[0]).outerHeight(true),
        length = $('.message-section[data-section="channel"] .mc-message').length;
    $('.message-section[data-section="channel"]').height((length*itemHeight)+1);
  }

  function successNotification(triggerContainer){
    $('.ui-dialog-buttonpane').find('.confirm-btn')
      .text('Success!')
      .css('background-color','#7ad47a');
    setTimeout(function(){
      if(triggerContainer){
        triggerContainer.trigger('click');
      }
      $('.ui-dialog-buttonset').find('button').trigger('click');
    },1000);
  }

  function transformData(data){
	try {
		var data = JSON.parse(data);
		if (typeof data == 'string' || data != null || data != undefined){ //to prevent errors outside projects
		  var keys = Object.keys(data);
		  var items = [];
		  for (var i=0; i < keys.length; i++) {
			items[i] = data[keys[i]];
		  }
		  return items;
		}else{
		  return '';
		}
	}catch(e){
		return '';
	}
  }

  function convertData(data){
    var channels = JSON.parse(data);
    return (channels != null ? channels : '');
  }

  function getTodayDate(){
  // function getTodayDate(callback){
    var today = new Date();
    var dd = today.getDate();
    var mm = today.getMonth()+1; //January is 0
    var yyyy = today.getFullYear();

    if(dd<10) {
      dd = '0'+dd;
      dd = dd*1;
    }

    if(mm<10) {
      mm = '0'+mm;
      mm = mm*1;
    }

    today = mm+'/'+dd+'/'+yyyy;
    var date = {month:mm,day:dd,year:yyyy};
    return date;
  }

  // Returns a function, that, as long as it continues to be invoked, will not
  // be triggered. The function will be called after it stops being called for
  // N milliseconds. If `immediate` is passed, trigger the function on the
  // leading edge, instead of the trailing.
  function debounce(func, wait, immediate) {
    var timeout;
    return function() {
      var context = this, args = arguments;
      var later = function() {
        timeout = null;
        if (!immediate) func.apply(context, args);
      };
      var callNow = immediate && !timeout;
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
      if (callNow) func.apply(context, args);
    };
  };

  function ajaxCall(path, options, callback){
    $.post(app_path_webroot+path,options,
    function(data){
      if (data){
        callback(data);
      }
    });
  }

  function getUrlParameter(param) {
    var pageURL = decodeURIComponent(window.location.search.substring(1)),
        URLVariables = pageURL.split('&'),
        parameterName,
        i;

    for (i = 0; i < URLVariables.length; i++) {
      parameterName = URLVariables[i].split('=');

      if (parameterName[0] === param) {
        return parameterName[1] === undefined ? true : parameterName[1];
      }
    }
  }

  var pid = getUrlParameter('pid');

  var checkIfNewTimeout;

  function setMessengerSession(){
    var path = 'Messenger/messenger_ajax.php';
    var options = {action: 'set-message-center-session'};
    if($('.thread-selected').length > 0){//if a thread is open
        var thread_id = $('.thread-selected').attr('thread-id');
        var msg = $('.msg-input-new').val();
        var important = ($('.messaging-mark-as-important-cb').prop('checked') === true ? '1' : '0');
        var conv_win_size = $('.message-center-expand').attr('data-open');
        options = {action: 'set-message-center-session',thread_id:thread_id,msg:msg,important:important,conv_win_size:conv_win_size}
    }
    ajaxCall(path, options, function(data){
      if(data){
        var data = JSON.parse(data);
        clearTimeout(checkIfNewTimeout);
        checkForNewMessages(data.mc_open);
      }
    });
  }

  function applySessionParameters(session){
    $('.message-section[data-section="channel"] .mc-message[thread-id="'+session.thread_id+'"]').trigger('click');
    setTimeout(function(){
      $('.msg-input-new').val(session.thread_msg);
    },1000);
  }

  function toggleMessagingContainer(){
	// Check for javascript variables needed. If don't have them, then load them synchronously via AJAX.
	if (typeof MessengerLangInit == 'undefined') {
		$.ajax({
			url: app_path_webroot+'Messenger/messenger_ajax.php',
			type: 'POST',
			data: { action: 'get_js_vars', redcap_csrf_token: redcap_csrf_token },
			success: function(data){
				eval(data);
				toggleMessagingContainerDo();
			}
		});
	} else {
		toggleMessagingContainerDo();	
	}
  }
  
  function toggleMessagingContainerDo(){
	var $MessengerContainer = $('.message-center-container');
    var open = $($MessengerContainer).attr('data_open');
    var direction;
    var dataOpen;
    var mainWinPos;
    var messengerPos;
    var session = {};
    $('.message-center-container').removeClass('mc-close').removeClass('mc-open');
	// Does not work for IE8 and below, so give popup message
	if (isIE && IEv < 9 && open === '0') {
		simpleDialog('<div class="yellow">We are sorry, but REDCap Messenger is not compatible with older versions of Internet Explorer, specifically IE8 and earlier. You will need to upgrade your browser or use another browser in order to use REDCap Messenger.</div>','Browser compatiblity issue');
		return;
	}
	// Open or close?
    if(open === '0'){
      direction = '+';
      dataOpen = '1';
      mainWinPos = '300px';
      messengerPos = '0px'
      session = {Messenger:'0'};
    }else{
      direction = '-';
      dataOpen = '0';
      mainWinPos = '0';
      messengerPos = '-300px'
      session = {Messenger:'1'};
    }
    (direction === '+' ? $('body').addClass('msg-center-open') : $('body').removeClass('msg-center-open'));
    $($MessengerContainer).attr('data_open', dataOpen);
    $($MessengerContainer).velocity({left:messengerPos},{duration:250});
    if($('body').hasClass('big-screen')){
      if($('.row-offcanvas-left').length > 0){
        $('.row-offcanvas-left').removeClass('body-override');
        $('.row-offcanvas-left').attr('data_open', dataOpen);
        $('.row-offcanvas-left').velocity({left:mainWinPos},{duration:250});
      }
    }

    //set php section
    setMessengerSession();
  }

  function resetMasterTimeout(delay){
    clearTimeout(checkIfNewTimeout);
    var masterTimer = ($('.message-center-container').attr('data_open') === '1' ? (develop ? 5000 : 20000) : 60000);//change here 5000 to 20000 to increase refresh if only the lists are open
    masterTimer = ($('.message-center-messages-container').css('display') === 'block' ? 5000 : masterTimer);
    masterTimer = (delay ? delay : masterTimer);
    checkIfNewTimeout = setTimeout(function(){
      checkForNewMessages('1');
    },masterTimer);
  }

  function filterInputValue(that){
    debugger;
    var x = $(that).contents();
    var filteredMsg = $(that).contents().filter(function() {
      return this.nodeType == 3;
    }).text();
    return x[0].nodeValue;
  }

  function createNewMessage(msgBody,thread_id){
    $('.msg-input-new').val('');
	hideTagSuggest();
    var username = $('#username-reference').text();
    var path = 'Messenger/messenger_ajax.php';
    var important = ($('.messaging-mark-as-important-cb').prop('checked') === true ? '1' : '0');
    var limit = $('.msgs-wrapper .msg-container').length+1;
    var taggedData = $('.msg-input-new-container').attr('data-tagged');
    var options = {action: 'post-channel-message', thread_id:thread_id, msg_body:msgBody, important:important, username:username, limit:limit,tagged_data:taggedData};
    resetMasterTimeout(1500);
    $('.msg-input-new').unbind();//to avoid send the same message multiple times
    ajaxCall(path, options, function(data){
      if(data){
        $('.msg-input-new').bind('keyup', function(e){
          checkKeyDown(e,this,thread_id);
        });
        var data = JSON.parse(data);
        var messages = transformData(data.messages);
        var members = transformData(data.members);
        var status = data.isdev;
        var channels = convertData(data.channels);
        var origin = 'post-channel-message';
        refreshMessages(messages,members,status,channels,origin);
        $('.messaging-mark-as-important-cb').prop('checked',false);
        $('.msg-input-new-container').attr('data-tagged','');
      }
    });
  }

  function changeConversationTitle(e,that,thread_id,oldTitle){
    var newTitle = $(that).val(),
        username = $('#username-reference').text();
    thread_id = $('.msgs-wrapper').attr('data-thread_id');
    if($(that).val() === '' || $(that).val() === oldTitle){
      $(that).replaceWith('<h4 class="conversation-title" data-tooltip="'+oldTitle+'">'+oldTitle+'</h4>');
      // instanciateTitleHoverEffect('.conversation-title');
      instanciateHoverEffect('.conversation-title',1000);
      $('.message-center-messages-container .conversation-title').bind('click', function(){
        renameConversation(thread_id);
      });
    }else{
      resetMasterTimeout(2000);
      var path = 'Messenger/messenger_ajax.php';
      var limit = $('.msgs-wrapper .msg-container').length+1;
      // debugger;
      var options = {action: 'change-conversation-title',thread_id:thread_id,new_title:newTitle,username:username,limit:limit};
      ajaxCall(path, options, function(data){
        if(data){
          var data = JSON.parse(data);
          var messages = transformData(data.messages);
          var members = transformData(data.members);
          var status = data.isdev;
          var channels = convertData(data.channels);
          var openedThreadId = $('.msgs-wrapper').attr('data-thread_id');
          //var newTitle = data.newtitle.replace(/"/g, '');
          $('.msg-input-new').val('');
          $(that).replaceWith('<h4 class="conversation-title" data-tooltip="'+strip_tags(newTitle)+'">'+window.escapeHTML(newTitle)+'</h4>');
          instanciateHoverEffect('.conversation-title',1000);
          var origin = 'change-title';
          refreshMessages(messages,members,status,channels,origin);
          $('.message-center-messages-container .conversation-title').bind('click', function(){
            renameConversation(thread_id);
          });
        }
      });
    }
  }

  function addMoreUsersToConversation(thread_id){
    closeAllConversationsMenus();
    createUserPanel('add-new-users',thread_id);
  }

  function removeUsersFromConversation(thread_id){
    closeAllConversationsMenus();
    createUserPanel('remove-conversation-users',thread_id);
  }

  function renameConversation(thread_id){
      if(thread_id == 1 || thread_id == 3 || $('.channel-members-username').attr('data-leader-conv') != '1'){
		return;
	  }
      var oldTitle = $('.conversation-title').attr('data-tooltip');
      var $newTitleInput = $('<input>', {
        'class': 'edit-message-title-input',
        'placeholder': oldTitle,
        'val': oldTitle
      }).bind('blur', function(e){
        changeConversationTitle(e,this,thread_id,oldTitle);
      }).bind('keyup', function(e){
        (e.keyCode == 13 ? changeConversationTitle(e,this,thread_id,oldTitle) : '');
      });
      $('.conversation-title').replaceWith($newTitleInput);
      $newTitleInput.focus();
  }

  function pinConversationToTop(thread_id){
    resetMasterTimeout(1500);
    var pinned = $('.mc-message[thread-id="'+thread_id+'"]').attr('data-prioritize');
    var value = (pinned === '1' ? '0' : '1');
    var username = $('#username-reference').text();
    var path = 'Messenger/messenger_ajax.php';
    var options = {action: 'prioritize-conversation',thread_id:thread_id,value:value,username:username};
    ajaxCall(path, options, function(data){
      if(data){
        $('.close-btn-small').trigger('click');
        var channels = convertData(data);
        refreshConversationsList(channels,'3',thread_id);
      }
    });
  }

  function deleteArchiveConv(thread_id,that){
    var action = $(that).attr('data-action');
    closeAllConversationsMenus();
    if(action === 'delete'){
      var message = langMsg02+'<p class="delete-conv-text">'+langMsg03+'</p>',
      btnText = langMsg04,
      confirmText = 'delete';
      simpleDialog(message,langMsg05);
    }else{//archive
      var message = langMsg06,
      btnText = langMsg07,
      confirmText = 'archive';
      simpleDialog(message,langMsg08);
    }
    var $confirm =  $('<button>',{
      'class': 'confirm-btn',
      'text': btnText
    }).bind('click', function(){
      var path = 'Messenger/messenger_ajax.php';
      var username = $('#username-reference').text();
      var options = {action: 'delete-archive-conversation',action_type:action,thread_id:thread_id,username:username};
      if($('.input-confirm-delete').val() === confirmText || action === 'archive'){
        resetMasterTimeout(1500);
        ajaxCall(path, options, function(data){
          if(data){
            if(data === '0'){
              validationErrorMessage();
              closeAllConversationsMenus();
            }else{
              $('.message-section .mc-message[thread-id="'+data+'"]').remove();
              var triggerContainer = $('.message-center-messages-container .close-btn-small');
              successNotification(triggerContainer);
              if(action === 'archive'){
                $('.message-center-archived-conv').velocity({scale:1.15,duration:200}).velocity({scale:1,duration:200});
              }
            }
          }
        });
      }else{
        $('.input-confirm-delete').velocity({scale:1.1},{duration:150,complete:function(){
          $('.input-confirm-delete').velocity({scale:1},{duration:150}).focus();
        }})
      }
    }),
    $input = $('<input>',{
      'class': 'input-confirm-delete'
    });
    $('body').find('.ui-dialog-buttonpane .confirm-btn, .ui-dialog-buttonpane .input-confirm-delete, .members-management-alert-confirm-btn').remove();
    $('body').find('.ui-dialog').addClass('remove-users-dialog');
    if(action === 'delete'){
      $('body').find('.ui-dialog-buttonpane').append($input).append($confirm);
      $input.focus();
    }else{
      $('body').find('.ui-dialog-buttonpane').append($confirm);
    }
  }

  function generateActionButtons(thread_id,remove,status,leaderStatus,threadStatus){
    if(remove === '1'){//on clicking
		$('.message-center-container .action-icons-wrapper *').remove();
		if(thread_id != '1' && thread_id !='3' && threadStatus === 'active'){
			var leaderStatusClass = (leaderStatus === '1' ? '' : 'hidden');
			var nonLeaderStatusClass = (leaderStatus === '1' ? 'hidden' : '');
			var actionIcons = '<div class="btn-group">'+
					'<button type="button" class="btn btn-defaultrc btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'+
					langMsg09+
					'</button>'+
					'<ul class="dropdown-menu">'+
					'	<li class="mc-action-icon mc-icon-add-users '+leaderStatusClass+'">'+
					'		<a href="#"><img src="'+app_path_images+'user_add3.png"> '+langMsg10+'</a></li>'+
					'	<li class="mc-action-icon mc-icon-remove-users '+leaderStatusClass+'">'+
					'		<a href="#"><img src="'+app_path_images+'list-remove-user.png"> '+langMsg11+'</a></li>'+
					'	<li class="mc-action-icon mc-icon-remove-self '+nonLeaderStatusClass+'">'+
					'		<a href="#"><img src="'+app_path_images+'list-remove-user.png"> '+langMsg12+'</a></li>'+
					'	<li class="mc-action-icon mc-icon-rename-conv '+leaderStatusClass+'">'+
					'		<a href="#"><i class="fas fa-pencil-alt"></i> '+langMsg13+'</a></li>'+
					'	<li class="mc-action-icon mc-icon-member-perm">'+
					'		<a href="#"><i class="fas fa-user"></i> '+langMsg14+'</a></li>'+						
					'	<li class="mc-action-icon mc-icon-pin-to-top">'+
					'		<a href="#"><span class="fas fa-thumbtack"></span> '+langMsg15+'</a></li>						'+
					'	<li class="mc-action-icon mc-icon-download-csv-conv" data-id="'+thread_id+'">'+
					'		<a href="#"><img src="'+app_path_images+'xls.gif"> '+langMsg16+'</a></li>						'+
					'	<li class="mc-action-icon mc-icon-archive-conv '+leaderStatusClass+'" data-action="archive">'+
					'		<a href="#"><img src="'+app_path_images+'archive_icon.png"> '+langMsg17+'</a></li>'+
					'	<li class="mc-action-icon mc-icon-delete-conversation '+leaderStatusClass+'" data-action="delete">'+
					'		<a href="#"><span class="fas fa-times"></span> '+langMsg18+'</a></li>'+
					'</ul>'+
				'</div>';
			$('.message-center-container .action-icons-wrapper').html(actionIcons);
			initActionIcons();
		}
    }

  }

  //bind close button and action-icons in case message window is rendered first in php
  $('.message-center-messages-container .close-btn-small').click(function(){
    toggleWindow();
  });
	
	function initActionIcons() {
	  $('.action-icons-wrapper .mc-icon-add-users').bind('click', function(){
		var thread_id = $('.msgs-wrapper').attr('data-thread_id');
		addMoreUsersToConversation(thread_id);
	  });

	  $('.action-icons-wrapper .mc-icon-remove-users').bind('click', function(){
		var thread_id = $('.msgs-wrapper').attr('data-thread_id');
		removeUsersFromConversation(thread_id);
	  });

	  $('.action-icons-wrapper .mc-icon-remove-self').bind('click', function(){
		var thread_id = $('.msgs-wrapper').attr('data-thread_id');
		var path = 'Messenger/messenger_ajax.php';
		var users = $('#username-reference').text();
		var options = {action: 'remove-users-from-conversation',thread_id:thread_id,users:users};
		removeUsers(path,options,this);
	  });

	  $('.action-icons-wrapper .mc-icon-rename-conv, .message-center-messages-container .conversation-title').bind('click', function(){
		var thread_id = $('.msgs-wrapper').attr('data-thread_id');
		renameConversation(thread_id);
	  });

	  $('.action-icons-wrapper .mc-icon-member-perm').bind('click', function(){
		$('.channel-members-count').trigger('click');
	  });

	  $('.action-icons-wrapper .mc-icon-pin-to-top').bind('click', function(){
		var thread_id = $('.msgs-wrapper').attr('data-thread_id');
		pinConversationToTop(thread_id);
	  });

	  $('.action-icons-wrapper .mc-icon-download-csv-conv').bind('click', function(){
		downloadConverstionAsCsv(this);
	  });

	  $('.action-icons-wrapper .mc-icon-delete-conversation, .action-icons-wrapper .mc-icon-archive-conv').bind('click', function(){
		var thread_id = $('.msgs-wrapper').attr('data-thread_id');
		deleteArchiveConv(thread_id,this);
	  });
	}
	initActionIcons();

  $('.messaging-mark-as-important-text').bind('click',function(){
    $(this).next().trigger('click');
  });

  //tooltip functionality
  function showTooltip(e){
    var $button = $(e.target);
    var pos = $button.offset();
    var text = window.escapeHTML(br2nl($button.attr('data-tooltip')));
    var heightModifier = 42;
    if(text != null && text.indexOf('<br>') != -1){
      heightModifier = 65;
    }
    // var length = $button.text().length;
    var $tooltip =  $('<div>',{
      'class': 'mc-icon-tooltip',
      'html': text,
      'style': 'top: '+(e.clientY-heightModifier)+'px;left: '+(e.clientX-10)+'px;'
    });
    $('.mc-icon-tooltip').remove();
    $('body').append($tooltip);
  }

  function instanciateHoverEffect(item,delay){
    var tooltipTimer;
    var tooltipDelay = delay;
    $('body '+item+'').hover(function(e){
      // on mouse in, start a timeout
      tooltipTimer = setTimeout(function() {
          showTooltip(e);
      }, tooltipDelay);
    }, function() {
      // on mouse out, cancel the timer
      clearTimeout(tooltipTimer);
      $('.mc-icon-tooltip').remove();
    });
  }
  instanciateHoverEffect('.action-icons-wrapper img',1000);
  instanciateHoverEffect('.message-center-conv-icon',1000);
  instanciateHoverEffect('.conversation-title',1000);

  function instanciateConvTitleHoverEffect(item,delay){
    var titleConvTooltipTimer;
    var titleConvTooltipDelay = delay;
    $('body '+item+'').hover(function(e){
      // on mouse in, start a timeout
      titleConvTooltipTimer = setTimeout(function() {
        var length = 0;
        if($(e.target).attr('data-tooltip')){
          var length = $(e.target).attr('data-tooltip').length;
        }
        if(length > 21){
          showTooltip(e);
        }
      }, titleConvTooltipDelay);
    }, function() {
      // on mouse out, cancel the timer
      clearTimeout(titleConvTooltipTimer);
      $('.mc-icon-tooltip').remove();
    });
  }
  instanciateConvTitleHoverEffect('.mc-message-text',1500);

  function instanciateConvProjHoverEffect(item,delay){
    var titleConvProjTooltipTimer;
    var titleConvProjTooltipDelay = delay;
    $('body '+item+'').hover(function(e){
      // on mouse in, start a timeout
      titleConvProjTooltipTimer = setTimeout(function() {
        var length = 0;
        if($(e.target).attr('data-tooltip')){
          showTooltip(e);
        }
      }, titleConvProjTooltipDelay);
    }, function() {
      // on mouse out, cancel the timer
      clearTimeout(titleConvProjTooltipTimer);
      $('.mc-icon-tooltip').remove();
    });
  }

  // show/hide action buttons
  $('.message-center-messages-container .show-hide-action-icons').click(function(){
    if($(this).attr('data-state') === 'closed'){
      $(this).next().velocity('fadeIn',{duration:200});
      $(this).attr('data-state','opened').text('Hide actions');
    }else{
      $(this).next().velocity('fadeOut',{duration:200});
      $(this).attr('data-state','closed').text('Show actions');
    }
  });

  //members list tooltipTimer
  function showMembersTooltip(e){
    var containerAttr = $('.message-center-messages-container').attr('data-thread_id');
    if(containerAttr != 1 && containerAttr != 3){
      $('.message-center-messages-container .channel-members-username').prepend('<p class="members-tooltip-title">'+langMsg19+'<br><span>'+langMsg20+'</span></p>');
      $('.message-center-messages-container .channel-members-username').addClass('members-show');
    }else{
      $('.channel-members-username').text('');
      $('.channel-members-username').html('<p class="visible-to-all-notification">'+langMsg21+'</p>');
      $('.message-center-messages-container .channel-members-username').addClass('members-show');
    }
  }

  var membersTooltipTimer;
  var membersTooltipDelay = 1000;
  function instanciateMembersHoverEffect(){
    $('body .channel-members-count').hover(function(e){
      membersTooltipTimer = setTimeout(function() {
        showMembersTooltip(e);
      }, membersTooltipDelay);
    }, function() {
      clearTimeout(membersTooltipTimer);
      $('.message-center-messages-container .channel-members-username').removeClass('members-show');
      $('.members-tooltip-title').remove();
    });
  }
  instanciateMembersHoverEffect();

  function buildMessageInput(thread_id,status,callback){
    var $newMessageInput,
        $explanation = '',
        $uploadFileBtn = '',
        $markAsImportant = '',
        $uploadButton = '',
        $tagButton = '',
        $inputContainer = '';
    switch(thread_id) {
      case '1': // what's new
      // if(status === 'true'){
        // var $newMessageInput = $('<a>', {
          // 'class': 'add-new-whatsnew',
          // 'text': 'add new'
        // });
        // $inputContainer = $('<div>',{
          // 'class': 'msg-input-new-container'
        // });
        // $inputContainer.append($newMessageInput);
      // }
      break;
      case '3': //general notifications
        if($('.mc-message[thread-id="3"]').attr('data-admin') === 'yes'){
          $('.msgs-wrapper').removeClass('small-screen').removeClass('normal-screen').addClass('admin-small-screen');
          $newMessageInput = $('<textarea>', {
            'class': 'msg-input-new',
            'row': 1,
            'placeholder': 'Type a message to ALL users...'
          }).bind('keyup', function(e){
            checkKeyDown(e,this,thread_id);
          });
          $inputContainer = $('<div>',{
            'class': 'msg-input-new-container'
          });
          $explanation = '<p class="admin-input-explanation">Add a message here for all users in your REDCap installation</p>';
          $markAsImportant = '<div class="messaging-mark-as-important-container"><p class="messaging-mark-as-important-text">'+langMsg21+'</p><input class="messaging-mark-as-important-cb" type="checkbox"></div>';
          $inputContainer.append($newMessageInput).append($markAsImportant);
        }
      break;
      default: //conversations
        $('.msgs-wrapper').removeClass('admin-small-screen');
        $newMessageInput = $('<textarea>', {
          'class': 'msg-input-new',
          'row': 1,
          'placeholder': 'Type a message…'
        }).bind('keyup', function(e){
          checkKeyDown(e,this,thread_id);
        }),
        $uploadFileBtn = $('<img>',{
          'class': 'msg-upload-btn',
          'src': app_path_images+'attach.png'
        }),
        $inputContainer = $('<div>',{
          'class': 'msg-input-new-container',
          'data-tagged': ''
        });
        $uploadButton = '<button class="btn btn-defaultrc btn-xs upload-file-button-container">'+
        '<img class="upload-file-button-icon" src="'+app_path_images+'attach.png">'+
        'Add file'+
        '</button>';
        $markAsImportant = '<div class="messaging-mark-as-important-container"><p class="messaging-mark-as-important-text">'+langMsg21+'</p><input class="messaging-mark-as-important-cb" type="checkbox"></div>';
        $inputContainer.append($newMessageInput).append($uploadButton).append($markAsImportant);
    }
    callback($newMessageInput,$explanation,$uploadFileBtn,$markAsImportant,$inputContainer);
  }

  function calculateRows(){
    var rowCounter = ($('.msg-input-new').attr('row')*1);
    var limit = rowCounter*19;
    var valLength = $('.msg-input-new').val().length;
    if($('.msg-input-new').val().length > limit){
      $('.msg-input-new').attr('row',rowCounter+1);
      $('.msg-input-new').addClass('expanded');
    }else{
      if(rowCounter > 1){
        $('.msg-input-new').attr('row',rowCounter-1);
      }else{
        $('.msg-input-new').removeClass('expanded');
      }
    }
  }

  //bind msg-input-new class in case message window is rendered first in php
  $('.message-center-messages-container .msg-input-new').bind('keyup', function(e){
    var thread_id = $('.msgs-wrapper').attr('data-thread_id');
    checkKeyDown(e,this,thread_id);
  });

  function updateActionIcons(ui_id,value){
    if(value === '0'){
      $('.channel-members-username').find('.conv-members-text[data-id="'+ui_id+'"]').removeClass('conv-leader');
      $('.mc-icon-delete-conversation, .mc-icon-remove-users, .mc-icon-add-users, .mc-icon-archive-conv, .mc-icon-rename-conv').addClass('hidden');
      $('.mc-icon-remove-self').removeClass('hidden');
	  $('.channel-members-username').attr('data-leader-conv','0');
    }else{
      $('.channel-members-username').find('.conv-members-text[data-id="'+ui_id+'"]').addClass('conv-leader');
      $('.mc-icon-delete-conversation, .mc-icon-remove-users, .mc-icon-add-users, .mc-icon-archive-conv, .mc-icon-rename-conv').removeClass('hidden');
      $('.mc-icon-remove-self').addClass('hidden');
      $('.channel-members-username').attr('data-leader-conv','1');
    }
  }

  function confirmToggleConvLeader(e,username){
    var ui_id = $(e.target).attr('data-id');
    var thread_id = $(e.target).attr('data-thread_id');
    var value = ($(e.target).prop('checked') ? '1' : '0');
    var leaders = $('.members-management-user .selected').length
    if(value === '0' && leaders === 1){
      simpleDialog(langMsg25);
      return;
    }
    var path = 'Messenger/messenger_ajax.php';
    var limit = $('.msgs-wrapper .msg-container').length+1;
    var options = {action: 'toggle-conv-leader', thread_id:thread_id,ui_id:ui_id,value:value,username:username,limit:limit};
    resetMasterTimeout(1500);
    ajaxCall(path, options, function(data){
      if(data){
        if(data === '0'){
          simpleDialog(langMsg22,langMsg23);
        }else{
          $('.members-management-user[data-id="'+ui_id+'"]').append('<span class="permission-management-edited">'+langMsg24+'</span>');
          $('.permission-management-edited').velocity('transition.slideRightIn',{duration:300}).velocity('transition.slideLeftOut',{duration:300,delay:200,complete:function(){
            $('.permission-management-edited').remove();
          }});
          var data = JSON.parse(data);
          var messages = transformData(data.messages);
          var members = transformData(data.members);
          var status = data.isdev;
          var channels = convertData(data.channels);
          var origin = 'toggle-conv-leader';
          setTimeout(function(){
            refreshMessages(messages,members,status,channels,origin);
          },510);
        }
      }
    });
  }
  
  function toggleConvLeader(e){
    var eUsername = $(e.target).attr('data-username');
    eUiid = $(e.target).attr('data-id');
    var username = $('#username-reference').text();
    if(eUsername === username){
      simpleDialog(langMsg26,
		langMsg27,1,400,function(){
			$('div.members-management-user-status input[type="checkbox"][data-id="'+eUiid+'"]').prop('checked',true);
		},langMsg28,function(){
			confirmToggleConvLeader(e,username);
		},langMsg29);
    }else{
      confirmToggleConvLeader(e,username);
    }
  }

  function displayConversationMembers(members,thread_id,$wrapper){
    var username = $('#username-reference').text();
    for (var i = 0; i < members.length; i++) {
		var userFirstLast = members[i].user_firstname+' '+members[i].user_lastname;
		if (userFirstLast == 'null null') userFirstLast = ' ';
		userFirstLast = ' ('+userFirstLast+')';
      var $user = $('<div>',{
        'class': 'members-management-user',
        'text': members[i].username+userFirstLast,
        'data-id': members[i].ui_id
      }),
	  $checkboxDiv = $('<div>',{
	    'class': 'members-management-user-status'
	  }),
      $checkbox = $('<input>',{
        'type': 'checkbox',
        'data-id': members[i].ui_id,
        'data-thread_id': thread_id,
        'data-username': members[i].username
      }).bind('click', function(e){
        toggleConvLeader(e);
      });
	  if (members[i].conv_leader === '1') {
		$checkbox.prop('checked', true);
	  }
	  if ($('.channel-members-username').attr('data-leader-conv') != '1') {
		$checkbox.prop('disabled', true);
	  }
	  $checkboxDiv.html($checkbox);
      $user.append($checkboxDiv);
      $wrapper.append($user);
      if(members[i].username === username){
        updateActionIcons(members[i].ui_id,members[i].conv_leader);//change to update on conversation refresh
      }
    }
  }

  function displayForbiddenActionDialog(){
    simpleDialog(langMsg30);
  }

  //members management panel
  function openMembersPanel(e,thread_id,members,origin){
    var threadStatus = ( $('.mc-message[thread-id="'+thread_id+'"]').attr('data-status') === undefined ? 'inactive' : 'active');
    if(thread_id != 1 && thread_id != 3){
      if(threadStatus === 'inactive'){
        displayForbiddenActionDialog();
        return;
      }
      if($('.members-management-options').length === 0){//if closed
        closeAllConversationsMenus();
        var $wrapper = $('<div>',{
          'class': 'members-management-options'
        }),
        $userWrapper = $('<div>',{
          'class': 'members-management-options-user-wrapper'
        }),
        $title = $('<h3>',{
          'class': 'members-management-title',
          'text': langMsg31
        }),
        $desc = $('<p>',{
          'class': 'members-management-description',
          'text': langMsg32
        }),
        $members = $('<span>',{
          'class': 'members-management-delimiter-members',
          'text': langMsg33
        }),
        $status = $('<span>',{
          'class': 'members-management-delimiter-status',
          'text': langMsg34
        }),
        $closeBtn = $('<img>',{
          'class': 'conversations-search-close-btn',
          'src': app_path_images+'close_button_black.png'
        }).bind('click', function(){
          closeAllConversationsMenus();
        });
        $wrapper.append($title).append($desc).append($closeBtn).append($members).append($status).append($userWrapper);
        if(origin === 'click'){
          displayConversationMembers(members,thread_id,$userWrapper);
        }else{
          var path = 'Messenger/messenger_ajax.php';
          var options = {action: 'retrieve-list-of-users-in-conv', thread_id:thread_id};
          ajaxCall(path, options, function(data){
            if(data){
              var members = transformData(data);
              displayConversationMembers(members,thread_id,$userWrapper);
            }
          });
        }
        $('body').append($wrapper);
        $wrapper.velocity('transition.slideLeftIn',{duration:100});
      }
    }
  }

  function adjustMembersList(){
    if($('.channel-members-username').text().length > 28){
      var membersArray = $('.channel-members-username').text().split(',');
    }
  }

  function editDeleteCall(path,$that,action){
    var msgId = $that.parent().attr('data-id');
    var msgBody = (action === 'edit' ? $('.message-to-edit').val() : $('.msg-container[data-id="'+msgId+'"] .msg-body').text().replace(langMsg35,''));
    msgBody = window.escapeHTML(msgBody);
    var msgOld = (action === 'edit' ? $('.message-to-edit').attr('data-msg') : $('.msg-container[data-id="'+msgId+'"] .msg-body').text().replace(langMsg35,''));
    var limit = $('.msgs-wrapper .msg-container').length+1;
    var msgType = ($that.parent().find('.msg-body').next().length != 0 ? 'file-upload' : 'message');
    var threadId = $('.message-center-messages-container').attr('data-thread_id');
    var options = {action: 'edit-delete-message',action_type:action,msgId:msgId,thread_id:threadId,msg_body:msgBody,limit:limit,msg_old:msgOld,msg_type:msgType};
    var go;
    if(action === 'delete'){
      if($('.input-confirm-delete').val() === 'delete'){
        var go = 1;
      }else{
        $('.input-confirm-delete').velocity({scale:1.1},{duration:150,complete:function(){
          $('.input-confirm-delete').velocity({scale:1},{duration:150}).focus();
        }});
        var go = 0;
      }
    }else{//edit action
      go = ( (msgOld.replace(langMsg35,'') != msgBody && msgBody != '') ? 1 : 0 );
    }
    if(go === 1){
      // return;
      resetMasterTimeout(2500);
      ajaxCall(path, options, function(data){
        if(data){
          successNotification();
          var data = JSON.parse(data);
          var messages = transformData(data.messages);
          var members = transformData(data.members);
          var status = data.isdev;
          var channels = convertData(data.channels);
          var origin = 'post-channel-message';
          refreshMessages(messages,members,status,channels,origin);
        }
      });
    }
  }

  function editDeleteAction($that,action){
    var path = 'Messenger/messenger_ajax.php';
    var $confirm =  $('<button>',{
      'class': 'confirm-btn',
      'text': 'Confirm'
    }).bind('click', function(){
      editDeleteCall(path,$that,action);
    }),
    $input = $('<input>',{
      'class': 'input-confirm-delete'
    });
    if(action === 'edit'){//edit action
      if(!$('.edit-message-button-container').hasClass('button-selected')){
        clearSimpleDialog();
        var $msgTextarea = $('<textarea>',{
          'class': 'message-to-edit',
          'data-msg': $that.parent().find('.msg-body').text(),
          'text': $that.parent().find('.msg-body').text().replace(langMsg35,'')
        }).bind('keyup', function(e){
          if(e.keyCode === 13){
            $msgTextarea.blur();
            $('.confirm-btn').trigger('click');
          }
        });
        $('.message-to-edit').replaceWith($msgTextarea);
        $msgTextarea.focus();
        $('.edit-message-button-container').addClass('button-selected');
        $('.delete-message-button-container').removeClass('button-selected');
        $('body').find('.ui-dialog-buttonpane').append($confirm);
        $('.edit-delete-info-wrapper').prepend('<p class="edit-panel-edit-message">'+langMsg36+'</p>');
      }
    }else{//delete action
      if(!$('.delete-message-button-container').hasClass('button-selected')){
        clearSimpleDialog();
        $('.edit-message-button-container').removeClass('button-selected');
        $('.delete-message-button-container').addClass('button-selected');
        $('body').find('.ui-dialog-buttonpane').append($input).append($confirm);
        $input.focus();
        $('.edit-delete-info-wrapper').prepend('<p class="edit-panel-delete-message"><span style="color:red;">'+langMsg37+'</span><br>'+langMsg38+'</p>');
      }
    }
  }

  function buildEditInterface($that){
    closeAllConversationsMenus();
    var $edit = '<div class="edit-message-button-container">'+
    '<img class="edit-message-button" src="'+app_path_images+'pencil.png">'+
    '<p class="edit-message-text">'+langMsg39+'</p>'+
    '</div>',
    $delete = '<div class="delete-message-button-container">'+
    '<img class="delete-message-button" src="'+app_path_images+'cross_small.png">'+
    '<p class="delete-message-text">'+langMsg40+'</p>'+
    '</div>';
    clearSimpleDialog();
    simpleDialog($edit+' '+langMsg41+' '+$delete+' '+langMsg42,langMsg43);
    $('.edit-message-button-container').bind('click', function(){
      editDeleteAction($that,'edit');
    });
    $('.delete-message-button-container').bind('click', function(){
      editDeleteAction($that,'delete');
    });
    var $msgInfo = '<div class="edit-delete-info-wrapper">'+
    '<p class="edit-delete-info-author">'+langMsg44+' '+$that.parent().find('.msg-name').text()+'</p>'+
    '<p class="edit-delete-info-ts">'+langMsg45+' '+$that.parent().find('.msg-time').attr('data-time')+'</p>'+
    '<p class="edit-delete-body-label">'+langMsg46+'</p>'+
    '</div>',
    $msgBody = $('<p>',{
      'class': 'message-to-edit',
      'text': $that.parent().find('.msg-body').text().replace(langMsg35,'')
    }),
    $msgTextarea = $('<textarea>',{
      'class': 'message-to-edit',
      'text': br2nl($that.parent().find('.msg-body').text())
    });
    $('body').find('.ui-dialog-buttonpane .confirm-btn').remove();
    $('body').find('.ui-dialog').addClass('edit-delete-panel');
    $('.ui-dialog-content').append($msgInfo).append($msgBody);
    $msgBody.bind('click',function(){
      $('.edit-message-button-container').trigger('click');
    })
  }

  function checkAvailableActions(e){
    var $that = $(e.target);
    var path = 'Messenger/messenger_ajax.php',
        msgId = $that.parent().attr('data-id'),
        options = {action: 'check-available-actions', msgId:msgId};
    resetMasterTimeout(1500);
    ajaxCall(path, options, function(data){
      if(data === '1'){
        buildEditInterface($that);
      }else{
        var msg = (data === '2' ? langMsg47 : langMsg48);
        clearSimpleDialog();
        simpleDialog(msg);
      }
    });
  }

  //bind checkAvailableActions to edit button
  $('body').on('click', '.dot-dot-dot', function(e){
    checkAvailableActions(e);
  });

  function generateAttachedFileHtml(docId,docIdHash,storedUrl){
    var attachmentData = JSON.parse(storedUrl),
        fileName = attachmentData[0].doc_name,
        action = attachmentData[0].action,
        fileNameHtml = '<p class="uploaded-file-text">'+fileName+'</p>';
    if(action === 'iframe-preview' || action === 'start-download'){
      var dataUrl = app_path_webroot+'DataEntry/image_view.php?doc_id_hash='+docIdHash+'&id='+docId+'&instance=1&origin=messaging';
      var download = '<p class="history-attached-file-text">'+langMsg49+'</p>'+
      '<div class="msgs-dowload-file" data-hash-id="'+docIdHash+'" data-id="'+docId+'">'+
      '<span>'+fileNameHtml+'</span>'+
      '</div>';
    }else{
      var usleep = 0;
      var dataUrl = app_path_webroot+'DataEntry/image_view.php?doc_id_hash='+docIdHash+'&id='+docId+'&instance=1&origin=messaging';
      var download = '<p class="history-attached-file-text">'+langMsg49+'</p>'+
      '<div class="msgs-dowload-file" data-hash-id="'+docIdHash+'" data-id="'+docId+'">'+
      '<img class="msgs-image-preview" data-url="'+dataUrl+'" src="'+dataUrl+'&usleep='+usleep+'">'+
      fileNameHtml+
      '</div>';
    }
    return download;
  }

  function showMessageHistory(msgId){
    var path = 'Messenger/messenger_ajax.php';
    var options = {action:'display-message-history',msgId:msgId};
    ajaxCall(path, options, function(data){
      if(data){
        //build table html
        var $labelsWrapper = $('<div>',{
          'class': 'message-history-labels-wrapper',
        }),
        $dateLabel = $('<div>',{
          'class': 'message-history-label-date',
          'text': langMsg50
        }),
        $userLabel = $('<div>',{
          'class': 'message-history-label-user',
          'text': langMsg51
        }),
        $actionLabel = $('<div>',{
          'class': 'message-history-label-action',
          'text': langMsg52
        }),
        $messageLabel = $('<div>',{
          'class': 'message-history-label-message',
          'text': langMsg53
        }),
        $historyContainer = $('<div>',{
          'class': 'history-container'
        }),
        $attachedFileContainer = $('<div>',{
          'class': 'attached-file-container'
        });
        $labelsWrapper.append($dateLabel).append($userLabel).append($actionLabel).append($messageLabel);
        clearSimpleDialog();
        simpleDialog(langMsg54,langMsg55,1,700);
        $('.ui-dialog-content').append($labelsWrapper).append($historyContainer).append($attachedFileContainer);
        var data = JSON.parse(data);
        var msgData = JSON.parse(data['data'].message_body);
        for (var i = 0; i < msgData.length; i++) {
          var $wrapper = $('<div>',{
            'class': 'message-history-wrapper',
          }),
          $date = $('<div>',{
            'class': 'message-history-date',
            'text': msgData[i].ts
          }),
          $user = $('<div>',{
            'class': 'message-history-user',
            'text': msgData[i].user
          }),
          $action = $('<div>',{
            'class': 'message-history-action',
            'text': msgData[i].action
          }),
          $message = $('<div>',{
            'class': 'message-history-message',
            'text': msgData[i].msg_body
          });
          $wrapper.append($date).append($user).append($action).append($message);
          $('.history-container').append($wrapper);
        }
        var docId = data['data'].attachment_doc_id;
        if(docId){
          var docIdHash = data['data'].doc_id_hash,
              storedUrl = data['data'].stored_url;
          $attachedFileContainer.append(generateAttachedFileHtml(docId,docIdHash,storedUrl));
        }else{
          $attachedFileContainer.append('<p class="history-attached-file-text">'+langMsg49+' '+langMsg56+'</p>');
        }
      }
    });
  }

  $('body').on('click', '.msg-edited-click', function(){
    var msgId = $(this).parent().parent().attr('data-id');
    showMessageHistory(msgId);
  });

  $('body').on('click', '.msg-deleted-click', function(){
    var path = 'Messenger/messenger_ajax.php';
    var msgId = $(this).parent().parent().attr('data-id');
    var options = {action:'show-deleted-message',msgId:msgId};
    resetMasterTimeout(2500);
    ajaxCall(path, options, function(data){
      if(data === '0'){
        simpleDialog(langMsg57);
      }else{//action forbidden
        showMessageHistory(msgId);
      }
    });
  });

  $('body').on('click', '.msg-body[data-anchor]', function(){
    var msgId = $(this).attr('data-anchor');
    var foundMessage = $('.msg-container[data-id="'+msgId+'"]').length;
    if(foundMessage){
      searchCallback(msgId);
    }else{
      var threadId = $('.message-center-messages-container').attr('data-thread_id'),
          title = $('.conversation-title').attr('data-tooltip');
      $('.message-center-messages-container .close-btn-small').trigger('click');
      toggleWindow(threadId,title,'no',function(data){
        setTimeout(function(){
          searchCallback(msgId);
        }, 500);
      });
    }
  });

  function checkMsgStatus(data){
    var data = JSON.parse(data);
    var action = data[data.length-1].action;
    switch(action) {
    case 'edit':
      return 'edited';
    case 'delete':
      return 'deleted';
    default:
      return 'no-special-status';
    }
  }

  function parseSearchedMessageBody(data,keyword){
    var data = JSON.parse(data);
    //check last action in data array and define message variables
    var action = data[data.length-1].action,
        message = data[data.length-1].msg_body,
        whoDidIt = data[data.length-1].user,
        dataEdited = '',
        msgId = '';
    switch(action) {
    case 'edit':
      return '-'+langMsg58+' '+message;
    case 'delete':
      return '-'+langMsg59+' "'+whoDidIt+'"';
    default:
      return '-'+langMsg46+' '+message;
    }
  }

  function parseMessageData(threadId,data){
      try {
          var data = JSON.parse(data);
          var action = data[data.length-1].action;
      } catch (e) {
          return '';
      }
    //check last action in data array and define message variables
	if(action === 'what-new'){
		var link = data[data.length-1].link;
		var message = '<div class="what-new-message-wrapper"><h4 class="new-feature-title">'+data[data.length-1].title+'</h4>'+
		'<h6 class="what-new-message-description">'+nl2br(data[data.length-1].description)+'</h6>'+
		(link == '' ? '' : '<h6 class="what-new-message-link"><a href="'+data[data.length-1].link+'">Click here</a></h6>')+
		'</div>';
        // Replace URLs with clickable links
        var replacePattern = /(\b(https?|ftp):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/gim;
        message = message.replace(replacePattern, '<a href="$1" style="text-decoration:underline;" target="_blank">$1</a>');
        var replacePattern = /(([a-zA-Z0-9\-\_\.])+@[a-zA-Z\_]+?(\.[a-zA-Z]{2,6})+)/gim;
        message = message.replace(replacePattern, '<a href="mailto:$1" style="text-decoration:underline;" target="_blank">$1</a>');
		return message;
    } else {
		var message = nl2br(html_entity_decode(data[data.length-1].msg_body)),
			whoDidIt = data[data.length-1].user,
			dataEdited = '',
			msgId = '';
		//this handles the notify message
		if(action != 'edit' && action != 'delete' && action != 'post' && action != 'what-new'){
		  msgId = action.replace('notify(','').replace(')','');
		  msgId = 'data-anchor="'+msgId+'"';
		  message = message+' <span class="click-to-go-notify">'+langMsg60+'</span>';
		}
		if(action === 'edit'){
		  message = message+' <span class="msg-edited-click">'+langMsg35+'</span>';
		  dataEdited = 'edited';
		}else if(action === 'delete'){
		  message = '<span class="msg-deleted-click">'+langMsg61+' "'+whoDidIt+'"'+langMsg62+' '+langMsg63+'</span>';
		  dataEdited = 'deleted';
		}
        // Replace URLs with clickable links
        var replacePattern = /(\b(https?|ftp):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/gim;
        message = message.replace(replacePattern, '<a href="$1" style="text-decoration:underline;" target="_blank">$1</a>');
        var replacePattern = /(([a-zA-Z0-9\-\_\.])+@[a-zA-Z\_]+?(\.[a-zA-Z]{2,6})+)/gim;
        message = message.replace(replacePattern, '<a href="mailto:$1" style="text-decoration:underline;" target="_blank">$1</a>');
		return '<p class="'+(threadId === '3' ? 'msg-body-notifications': 'msg-body')+'" data-edited="'+dataEdited+'" '+msgId+'>'+message+'</p>';
	}
  }

  function generateMsgs(messages,thread_id,members,remove,status){
    var threadStatus = ( $('.mc-message[thread-id="'+thread_id+'"]').attr('data-status') === undefined ? 'inactive' : 'active');
    if(thread_id === '1' || thread_id === '3'){
      threadStatus = 'active';
    }
    //members section
    var username = $('#username-reference').text();
    $('.channel-members-count').text(members.length+' Members');
    $('.channel-members-username').text('');//clear members usernames first
    for (var i = 0; i < members.length; i++) {//build usernames list
      var leaderStatus;
      if(members[i].username === username){
        leaderStatus = members[i].conv_leader;
      }
      var convLeaderClass = (members[i].conv_leader === '1' ? 'conv-leader' : '');
      if(members.length === 1){
        $('.channel-members-username').append('(').append('<p class="conv-members-text '+convLeaderClass+'" data-id="'+members[i].ui_id+'" data-full-name="'+members[i].user_firstname+' '+members[i].user_lastname+'">'+members[i].username+'</p>').append(')');
      }else if(i === 0){
        $('.channel-members-username').append('(').append('<p class="conv-members-text '+convLeaderClass+'" data-id="'+members[i].ui_id+'" data-full-name="'+members[i].user_firstname+' '+members[i].user_lastname+'">'+members[i].username+'</p>').append(', ');
      }else if(members.length - i === 1){
        $('.channel-members-username').append('<p class="conv-members-text '+convLeaderClass+'" data-id="'+members[i].ui_id+'" data-full-name="'+members[i].user_firstname+' '+members[i].user_lastname+'">'+members[i].username+'</p>').append(')');
      }else{
        $('.channel-members-username').append('<p class="conv-members-text '+convLeaderClass+'" data-id="'+members[i].ui_id+'" data-full-name="'+members[i].user_firstname+' '+members[i].user_lastname+'">'+members[i].username+'</p>').append(', ');
      }
    }
    if(thread_id === '1' || thread_id === '3'){//what's new, notifications
      $('.channel-members-username, .show-hide-action-icons').addClass('hidden');
      $('.channel-members-count').text('Members: Everyone');
      $('.channel-members-count, .channel-members-username').unbind('click');
      $('.channel-members-count, .channel-members-username').bind('click',function(e){
        openMembersPanel(e,thread_id,members,'click');
      });
    }else{
      $('.channel-members-username, .show-hide-action-icons').removeClass('hidden');
      // highlightConvLeaders() maybe do the binding here
      $('.channel-members-username').attr('data-leader-conv',leaderStatus);
      $('.channel-members-count, .channel-members-username').unbind('click');
      $('.channel-members-count, .channel-members-username').bind('click',function(e){
        openMembersPanel(e,thread_id,members,'click');
      });
    }
    if(threadStatus != 'inactive'){//if not archived conversation
      generateActionButtons(thread_id,remove,status,leaderStatus,threadStatus);
      $('.archived-conv-title.thread-selected').removeClass('thread-selected');
    }else{
      $('.channel-members-username, .show-hide-action-icons').addClass('hidden');
      $('.action-icons-wrapper *').remove();
      $('.thread-selected').removeClass('thread-selected');
      $('.archived-conv-container[data-id="'+thread_id+'"] .archived-conv-title').addClass('thread-selected');
    }
    //end of members section
    var existingMessages = $('.msgs-wrapper .msg-container').length;
    if(remove === '1'){//called by clicking
      var $messages = [];
      //remove dom elements before regenerating them
      $('.msgs-wrapper *').remove();
      $('.message-center-messages-container .msg-input-new, .message-center-messages-container .close-btn-small, .admin-input-explanation, .add-new-whatsnew, .messages-date-recents, .msg-upload-btn, .messaging-mark-as-important-container, .msg-input-new-container').remove();
    }else{//on refresh
      var $messages = $('.msgs-wrapper').find('.msg-container');
    }
	
    var lastMessageDay = (messages && messages.length ? (messages[messages.length-1].normalized_ts).substr(8,2) : '');
    var todayDay = getTodayDate().day;
    //generate messages html
    var imageCount = 0;
    //remove
    $('.msgs-wrapper *').remove();
    if(messages.length === 0){
      $('.msgs-wrapper').append('<p class="empty-notification">'+langMsg64+'</p>');
    }
    for (var i = 0; i < messages.length; i++) {
	  // Is an unread msg?
	  var unreadMsgClass = messages[i].unread == '1' ? 'unread-msg' : '';
      //date divider
      var messageDay = (messages[i].normalized_ts).substr(8,2);
      var messageMonth = (messages[i].normalized_ts).substr(5,2);
      var messageYear = (messages[i].normalized_ts).substr(0,4);
      if ($('.messages-date-recents[data-date="'+messageYear+messageMonth+messageDay+'"]').length === 0) {
		var dateDisplay = (messageYear+'-'+messageMonth+'-'+messageDay == today) ? "Today" : (messages[i].sent_time).split(' ')[0];
        $('.msgs-wrapper').append('<p class="messages-date-recents" data-date="'+messageYear+messageMonth+messageDay+'">'+dateDisplay+'</p>');
      }//date divider end
      switch(thread_id){
        case '1':// what's new
        var title = '',
            $urgentSpan = '',
            image = '<img class="msg-user-image" src="'+app_path_images+'redcap-logo-letter.png">',
            msgBody = parseMessageData(thread_id,messages[i].message_body),
            name = langMsg65,
            link = '',
            edit = '',
            download = '';
          break;
        case '3'://general notifications
        var title = '',
            image = '<img class="msg-user-image" src="'+app_path_images+'redcap-logo-letter.png">',
             msgBody = parseMessageData(thread_id,messages[i].message_body),
            name = langMsg66,
            link = '',
            edit = '',
            download = '';
			// Set as important?
			var important = '0',
				msgBodyJson = JSON.parse(messages[i].message_body);
			if (msgBodyJson !== null && typeof(msgBodyJson[0].important) !== 'undefined') {
				important = msgBodyJson[0].important;
			}
			var $urgentSpan = (important ===  '1' ? '<span class="urgent-span">!</span>' : '');
          break;
        default: //conversations
		messages[i].message_body = window.escapeHTML(messages[i].message_body);
        var str = (messages[i].user_firstvisit).substring(11);
        var arr = str.split(':');
        var bkColor = 'rgb('+(arr[0]*1)+'0,'+(arr[1]*1)+'0,'+(arr[2]*1)+'0)';
        var title ='',
            image = '<div class="channel-msg-user-image" style="background-color:'+bkColor+'">'+(messages[i].user_firstname).substring(1,0)+(messages[i].user_lastname).substring(1,0)+'</div>',
            msgBody = parseMessageData(thread_id,messages[i].message_body),
            name = messages[i].username,
            link = '',
            download = '';
		// Set as important?
		var important = '0',
			msgBodyJson = JSON.parse(messages[i].message_body);
		if (msgBodyJson !== null && typeof(msgBodyJson[0].important) !== 'undefined') {
			important = msgBodyJson[0].important;
		}
		var $urgentSpan = (important ===  '1' ? '<span class="urgent-span">!</span>' : '');
		if($(msgBody).attr('data-anchor') != undefined || $(msgBody).attr('data-edited') === 'deleted'){
		  var edit = '';
		}else{
		  var edit = '<img class="dot-dot-dot" src="'+app_path_images+'dot_dot_dot.png">';
		}
        if(messages[i].attachment_doc_id && $(msgBody).attr('data-edited') != 'deleted'){
          if(messages[i].stored_url){
            var attachmentData = JSON.parse(messages[i].stored_url);
            var fileName = attachmentData[0].doc_name;
            var action = attachmentData[0].action;
            var fileNameHtml = '<p class="uploaded-file-text">'+fileName+'</p>';
            if(action === 'iframe-preview' || action === 'start-download'){
              var dataUrl = app_path_webroot+'DataEntry/file_download.php?doc_id_hash='+messages[i].doc_id_hash+'&id='+messages[i].attachment_doc_id+'&instance=1&origin=messaging';
              download = '<div class="msgs-dowload-file" data-url="'+dataUrl+'" data-hash-id="'+messages[i].doc_id_hash+'" data-id="'+messages[i].attachment_doc_id+'">'+
              '<span>'+fileNameHtml+'</span>'+
              '</div>';
            }else{
			  // Manually set image height to improve auto-scrolling
			  var styleHeight = '';
			  if (typeof(attachmentData[0].img_height) !== 'undefined') {
				if (attachmentData[0].img_height > 100) {
					styleHeight = 'style="height:100px;"';
				} else {
					styleHeight = 'style="height:'+attachmentData[0].img_height+'px;"';
				}
			  }
              var usleep = 100000*imageCount;
              var dataUrl = app_path_webroot+'DataEntry/image_view.php?doc_id_hash='+messages[i].doc_id_hash+'&id='+messages[i].attachment_doc_id+'&instance=1&origin=messaging';
              imageCount += 1;
              download = '<div class="msgs-dowload-file" data-hash-id="'+messages[i].doc_id_hash+'" data-id="'+messages[i].attachment_doc_id+'">'+
              '<img class="msgs-image-preview" data-url="'+dataUrl+'" '+styleHeight+' src="'+dataUrl+'&usleep='+usleep+'">'+
              fileNameHtml+
              '</div>';
            }
          }
        }
      }
      var $html = '<div class="msg-container '+unreadMsgClass+'" data-id="'+messages[i].message_id+'">'+
      image+
      '<p class="msg-name">'+name+'</p>'+
      '<p class="msg-time" data-urgent="'+messages[i].urgent+'" data-time="'+messages[i].sent_time+'">'+messages[i].sent_time+$urgentSpan+'</p>'+
      edit+
      title+
      msgBody+
      link+
      download+
      '</div>';
      //append messages html
      $('.msgs-wrapper').append($html);
    }
    $('.msgs-wrapper').attr('data-thread_id',thread_id);
	// Highlight any new messages
	var msgDivider = '<div class="msg-unread-divider">'+langMsg67+'</div>';
	$('.msgs-wrapper .unread-msg:first').before(msgDivider);
	$('.msgs-wrapper .unread-msg, .msgs-wrapper .msg-unread-divider').effect('highlight',{},10000);
	setTimeout(function(){
		$('.msgs-wrapper .msg-unread-divider').css('visibility','hidden');
	},10000);
    //'just now' handler	
    if (thread_id != '1' && thread_id != '3' && messages.length > existingMessages && existingMessages != 0 && remove != '1'){
		var $justNowurgentSpan = $('.msgs-wrapper .msg-container:last').find('.msg-time .urgent-span').length ? '<span class="urgent-span">!</span>' : '';
		$('.msgs-wrapper .msg-container:last').find('.msg-time').html('Just now'+$justNowurgentSpan);
		setTimeout(function(){
			$('.msgs-wrapper .msg-container:last').find('.msg-time').html(messages[messages.length-1].sent_time+$justNowurgentSpan);
		},10000);
    }
    var $close = $('<img>',{
      'class': 'close-btn-small',
      src: app_path_images+'up-arrow-black.png'
    }).bind('click', function(){
      toggleWindow();
    });
    if(remove === '1'){//in case of clicking, no refreshing
      //handler for rendering the right input
      buildMessageInput(thread_id,status,function($newMessageInput,$explanation,$uploadFileBtn,$markAsImportant,$inputContainer){
        // if(thread_id != '1'){
        if(threadStatus != 'inactive'){
          $('.message-center-messages-container').append($inputContainer).prepend($close);
        }else{//do not display new message input for archived conversations
          $('.message-center-messages-container').prepend($close);
          $('.msgs-wrapper').height($('.message-center-messages-container').height()-66);
        }
        $('.messaging-mark-as-important-text').bind('click',function(){
          $(this).next().trigger('click');
        });
        if(threadStatus === 'inactive'){
          $('.msg-upload-btn, .msg-input-new, .messaging-mark-as-important-container').remove();
          $('.msgs-wrapper').addClass('archived-height');
        }else{
          $('.msgs-wrapper').removeClass('archived-height');
        }
      });
      setTimeout(function(){
        //display messages html
        $('.message-center-messages-container').attr('data-thread_id',thread_id);
        $('.message-center-messages-container').velocity('transition.slideDownIn',{duration:200,complete:function(){
          $('.msgs-wrapper').scrollTop($('.msgs-wrapper')[0].scrollHeight);
          $('.msg-input-new').focus();
        }});
      },200);
    }else{//on refreshing just scroll to the last message
      setTimeout(function(){
		$('.msgs-wrapper').scrollTop($('.msgs-wrapper')[0].scrollHeight);
      },200);
    }
  }

  (function() {
    var escapeEl = document.createElement('textarea');

    window.escapeHTML = function(html) {
        escapeEl.textContent = html;
        return escapeEl.innerHTML;
    };

    //just in case
    window.unescapeHTML = function(html) {
        escapeEl.innerHTML = html;
        return escapeEl.textContent;
    };
  })();

  function isBlank(str){
    return (!str || /^\s*$/.test(str));
  }

  function checkKeyDown(e,that,thread_id){
    if(e.keyCode === 13){
      if( isBlank($(that).val()) ){
        $(that).val('');
      }else{
		// Escape it
        var msg = window.escapeHTML($(that).val());
		// Create msg
        createNewMessage(msg,thread_id);
      }
    }else if(thread_id != '3' && thread_id != '1'){
		tagSuggest(e,that,thread_id);
    }
  }
  
	// Look for user typing @username, and display auto-suggest list of usernames to tag
	var TagSuggestAjax = null;
	function tagSuggest(e,ob,thread_id) {
		// On space, hide the tag suggest
		if (e.keyCode === 32) return hideTagSuggest();
		// Truncate the value where the cursor is located
		var cursorPosition = $(ob).getCursorPosition();
		var textOrig = $(ob).val();
		var text = textOrig.substring(0,cursorPosition);
		// Obtain the word being typed
		var word = (text.indexOf(' ') >= 0) ? text.split(' ').pop() : text;
		word = trim(word);
		if (word == "" || word == "@" || word.substring(0,1) != "@") return hideTagSuggest();
		// Make sure suggest box exists
		if (!$('#mention-suggest').length) $(ob).after('<div id="mention-suggest"></div>');
		// Kill previous ajax instance (if running from previous keystroke)
		if (TagSuggestAjax !== null) {
			if (TagSuggestAjax.readyState == 1) TagSuggestAjax.abort();
		}
		// Ajax request
		TagSuggestAjax = $.post(app_path_webroot+'Messenger/messenger_ajax.php', { thread_id: thread_id, action: 'tag-suggest', word: word }, function(data){
			// If nothing returned, then hide the suggest box
			if (data == '') return hideTagSuggest();
			// Position the element
			var whereToPlaceIt = $(ob).position();
			$('#mention-suggest').html(data);
			$('#mention-suggest').css({ 'right': $(ob).css('margin-left'), 'top': (whereToPlaceIt.top+1-$('#mention-suggest').height()) + "px" });				
			$('#mention-suggest').show();
			// Rebuild text to replace
			var textBegin = text.substring(0,cursorPosition-word.length);
			var textEnd = textOrig.substring(cursorPosition, textOrig.length);
			// Add onclick to tag a user
			$('#mention-suggest>div.ut').click(function(){
				hideTagSuggest();
				var tagSelected = $('span:first', this).text();
				if (textEnd == '') textEnd = ' ';
				$(ob).val(textBegin+tagSelected+textEnd);
				$(ob).focus();
			});
			// Add onclick to add user to conversation
			$('#mention-suggest>div.utn button').bind('click', function(){
				// Remove partially typed username from text box				
				$(ob).val(textBegin+textEnd);
				// Open 'add user' dialog
				var thread_id = $('.msgs-wrapper').attr('data-thread_id');
				addMoreUsersToConversation(thread_id);
				// Place username inside search box
				var userToAdd = $(this).parent().find('span:first').text().replace('@','');
				$('.add-new-user-panel .new-conv-search-input').val(userToAdd).trigger('keyup').focus();
			});
		});
	}
	
	function hideTagSuggest() {
		$('#mention-suggest').hide();
		return true;
	}

  function calculateConversationsListWindowState(thread_id){
    var height = getMessageSectionHeight();//height of the conversations list
    if(thread_id){//if opening a conversation
      if(thread_id != '1' && thread_id != '3' && $('.message-section[data-section="notification"]').height() > 0){
        $('.message-center-show-hide[data-section="notification"]').trigger('click');
      }
      var dynamicHeight = ((($('.message-center-messages-container').css('top')).replace('px',''))*1)-136;
      if($('.message-section[data-section="channel"]').height() > 114){
        $('.message-section[data-section="channel"]').velocity({height:dynamicHeight},{complete:function(){
          //scroll to selected thread
          $('.message-section[data-section="channel"] .mc-message[thread-id="'+thread_id+'"]').velocity("scroll",{container:$('.message-section[data-section="channel"]'),offset:-100});
        }});
      }
    }else{//if closing a conversation
      changeWindowSize(height);
    }
  }

  function closeThreadSpecificPanels(){
    if($('.members-management-options').length != 0 || $('.msgs-upload-form').length != 0 || $('.tag-menu-wrapper').length != 0){
      closeAllConversationsMenus();
    }
  }

  function toggleWindow(thread_id,title,msgsLimit,callback){
    //close permissions panel if open
    closeThreadSpecificPanels();
    if(thread_id){//if window is close
      resetMasterTimeout(1500);
	  setTimeout(function(){
		calculateMessageWindowPosition();
	  },500);
      //stop hat from rotating on next check in animation function
      $('.message-section[data-section="channel"] .mc-message[thread-id="'+thread_id+'"]').attr('data-rotating','stop-rotating');
      $('.message-center-messages-container').removeClass('show-override');
      var path = 'Messenger/messenger_ajax.php';
      var username = $('#username-reference').text();
      var limit = (msgsLimit ? 0 : 10);
      var options = {action: 'retrieve-channel-global-data', thread_id:thread_id, username:username,limit:limit};
      ajaxCall(path, options, function(data){
        if(data){
          //at this point is mark as read already so remove data-unread
          $('.conversation-title').html(title);
          $('.conversation-title').attr('data-tooltip',title);
          var data = JSON.parse(data);
          var members = transformData(data.members);
          var messages = transformData(data.messages);
          var totalMsgs = Number(data.total_msgs);
          $('.message-center-messages-container').attr('data-total-msgs',totalMsgs);
          var conversations = convertData(data.conversations);
          var status = data.isdev;
          var remove = '1';
          calculateConversationsListWindowState(thread_id);
          generateMsgs(messages,thread_id,members,remove,status);
          refreshConversationsList(conversations,'0');
          if(callback){callback('ready');}
        }
      });
    }else{//if opened already
      if($('.mc-overlay').length != 0){
        var containers = ['.mc-container','.secret-panel','.new-channel-panel','.message-center-messages-container *'];
        $(containers[0]+','+containers[2]).velocity('transition.slideLeftOut',{duration:300});
        $(containers[1]).velocity('transition.slideUpOut',{duration:300});
        $('.mc-overlay').remove();
        $('.thread-selected').removeClass('thread-selected');
        setTimeout(function(){
          $(containers[0]+','+containers[1]+','+containers[2]).remove();;
          $('body').removeClass('stop-scrolling');
        }, 350);
      }else{//click close button on messages panel
        calculateConversationsListWindowState();
        $('.message-center-messages-container').attr('data-thread_id','');
        $('.message-center-messages-container').velocity('transition.slideUpOut',{duration:100});
        $('.thread-selected').removeClass('thread-selected');
        $('.msgs-wrapper').attr('data-thread_id','');
        $('.message-center-messages-container').removeClass('show-override');
		// Ajax call to record that the message window is closed
		var path = 'Messenger/messenger_ajax.php';
		var options = {action: 'close-channel-window'};
		ajaxCall(path, options);
	  }
    }
  }

  $('body').on('click', '.message-section .mc-message', function(){
    var thread_id = $(this).attr('thread-id'),
        title = $(this).find('.mc-message-text').attr('data-tooltip');
    var offset = $(this).offset();
    $('.message-section .mc-message').removeClass('thread-selected');
    $(this).addClass('thread-selected');
    if($('.message-center-messages-container').height() != 0){
      $('.message-center-messages-container').velocity('transition.slideUpOut',{duration:200});
    }
    toggleWindow(thread_id,title,'');
  });

  $('body').on('click', '.message-section .conv-title-proj', function(e){
    e.stopPropagation();
    var pid = $(e.target).attr('data-proj-id');
    var currentPid = getUrlParameter('pid');
    if(pid != currentPid){
      window.location.href = app_path_webroot+'index.php?pid='+pid;
    }
  });

  function calculateHeight(that){
    var messageCount = that.children().length;
    return messageCount*32;
  }

  function rotateIcon(that,open){
    var rotation = (open != '1' ? -180 : 0);
    $(that).velocity({rotateZ:rotation},{duration:100});
  }

  function changeWindowSize(newHeight){
    $('.conversations-view-options').velocity('transition.slideLeftOut',{duration:100,complete:function(){
      $('.conversations-view-options').remove();
    }});
    var height = $('.message-section[data-section="channel"]').height();
    var newHeight = newHeight;
    $('.message-section[data-section="channel"]').velocity({height:[newHeight,height],duration:200});
  }

  function setMessageSectionOptions(that,e){
    if($('.conversations-view-options').length === 0){//if closed
      closeAllConversationsMenus();
      var state = $(that).attr('data-open');
      var $listIcon = $(e.target);
      var pos = $listIcon.offset();
      var $optionsMenu =  $('<div>',{
        'class': 'conversations-view-options',
        'style': 'top: '+(pos.top-19)+'px;left: '+(pos.left+28)+'px;'
      }),
      $smallOption =  $('<p>',{
        'class': 'conversations-small-option',
        'text': 'small size',
        'data-size': '0'
      }),
      $mediumOption =  $('<p>',{
        'class': 'conversations-mid-option',
        'text': 'medium size',
        'data-size': '1'
      }),
      $bigOption =  $('<p>',{
        'class': 'conversations-big-option',
        'text': 'large size',
        'data-size': '2'
      });
      $optionsMenu.append($smallOption).append($mediumOption).append($bigOption);
      $('body').append($optionsMenu);
      $('.conversations-small-option, .conversations-mid-option, .conversations-big-option').bind('click',function(){
        changeWindowSize($(this).attr('data-size'));
      });
      $optionsMenu.velocity('transition.slideLeftIn',{duration:100});
    }else{//if open already
      closeAllConversationsMenus();
    }
  }

  function orderConversations(data){
    data = data.reverse();
    var prioritizedConv = [];
    var unprioritizedConv = [];
    var indexArray = [];
    for (var i = 0; i < data.length; i++) {
      if(data[i].prioritize === '1'){
        prioritizedConv.push(data[i]);
      }else{
        unprioritizedConv.push(data[i]);
      }
    }
    if(prioritizedConv.length > 0){
      var data = [];
      for (var i = 0; i < unprioritizedConv.length; i++) {
        data.splice(i,0,unprioritizedConv[i]);
      }
      for (var i = 0; i < prioritizedConv.length; i++) {
        data.splice(i,0,prioritizedConv[i]);
      }
    }
    return data;
  }

  function checkIfConversationTitleChanged(channels){
    var openedThreadId = $('.msgs-wrapper').attr('data-thread_id'),
    $channels = $('.message-section[data-section="channel"]').find('.mc-message');
    for (var i = 0; i < channels.length; i++) {
      var name = channels[i].channel_name;
      var $name =  $($channels[i]).find('.mc-message-text').attr('data-tooltip');
      var thread_id = channels[i].thread_id;
      if(thread_id === openedThreadId){
        $('.conversation-title').text(channels[i].channel_name);
        $('.conversation-title').attr('data-tooltip',channels[i].channel_name);
      }
    }
    instanciateHoverEffect('.conversation-title',1000);
  }

  function priorityClass(priority){
    return (priority === '1' ? 'priority-class' : '');
  }

  function assignGlyphicon(priority){
    return (priority === '1' ? 'fas fa-thumbtack' : 'fas fa-comment-alt');
  }

  function checkIfOnDifferentProject(pid){
    var currentPid = getUrlParameter('pid');
    var result = (pid != currentPid ? '<br>'+langMsg68 : '<br>'+langMsg69);
    return result;
  }

  function refreshConversationsList(data,remove,thread_id){
    var displayedChannels = $('.message-section[data-section="channel"] .mc-message').length;
    (displayedChannels > data.length ? updateConversations(data) : '');
    $('.message-section[data-section="channel"] *').remove();
    for (var i = 0; i < data.length; i++) {
      var $threadTitle = $('<div>', {
        'class': 'mc-message '+priorityClass(data[i].prioritize)+'',
        'thread-id': data[i].thread_id,
        'data-updated': '0',
        'data-prioritize': data[i].prioritize,
        'data-status': (data[i].archived === 0 ? 'active' : 'archived'),
        'data-project-id': (data[i].project_id ? data[i].project_id : 'none'),
        'data-project-name': (data[i].project_id ? data[i].project_name : 'none')
      }),
      $threadTitleText = $('<p>',{
        'class': 'mc-message-text',
        'html': (data[i].project_id ? '<img class="conv-title-proj" data-proj-id="'+data[i].project_id+'" data-tooltip="'+escapeHtml(data[i].project_name)+' (pid: '+data[i].project_id+')'+checkIfOnDifferentProject(data[i].project_id)+'" src="'+app_path_images+'blog.png">'+escapeHtml(data[i].channel_name) : escapeHtml(data[i].channel_name)),
        'data-tooltip': escapeHtml(data[i].channel_name)
      }),
      $badge = $('<span>', {
        'class': 'mc-message-badge',
        'text': (data[i].unread_count != 0 ? data[i].unread_count : '')
      }),
      $span = $('<span>',{
        'class': 'fas '+assignGlyphicon(data[i].prioritize)
      });
      $threadTitle.append($span).append($threadTitleText).append($badge);
      $('.message-section[data-section="channel"]').append($threadTitle);
      if(data[i].project_id){
        instanciateConvProjHoverEffect('.conv-title-proj',1500);
      }
    }
    var openedThreadId = $('.msgs-wrapper').attr('data-thread_id');
    $('.mc-message[thread-id="'+openedThreadId+'"]').addClass('thread-selected');
    if(remove === '1'){//coming from create new conversation
      $('.mc-settings-close-btn').trigger('click');
      setTimeout(function(){
        $('.message-section[data-section="channel"] .mc-message[thread-id="'+thread_id+'"]').trigger('click');
      },500);
    }
    if(remove === '2' && displayedChannels != 0){//after check-if-new-messages
      if(displayedChannels < data.length && $('.message-center-messages-container').css('display') != 'block'){
        changeWindowSize(getMessageSectionHeight());
      }
    }
    if(remove === '3'){//coming from pinConversationToTop
      $('.mc-message[thread-id="'+thread_id+'"]').trigger('click');
    }

    // instanciateHoverEffect('.mc-message-text',1500);
    if(remove === '5'){//coming from change conversation title
      var $confirmMsg = $('<div>', {
        'class': 'confirm-title-edit-msg',
        'text': langMsg70
      });
      $('.mc-message[thread-id="'+openedThreadId+'"]').append($confirmMsg);
      $confirmMsg.velocity('transition.flipBounceYIn',{duration:300});
      setTimeout(function(){
        $confirmMsg.velocity('transition.flipBounceYOut',{duration:350});
      },1000);
    }
    instanciateConvTitleHoverEffect('.mc-message-text',1500);
  }

  function retrieveDynamicThreads(thread){
    if($('#west').length > 0){//check if in project or not
      if(thread === 'channel'){
        var path = 'Messenger/messenger_ajax.php';
        var username = $('#username-reference').text();
        var options = {action: 'retrieve-conversations', username:username};
        ajaxCall(path, options, function(data){
          if(data){
            var data = JSON.parse(data);
            var session = JSON.parse(data.session);
            var channels = convertData(data.channels);
            refreshConversationsList(channels);
          }
        });
      }
    }
  }

  $('.message-center-container .messaging-close-btn, .user-messaging-left-item, .navbar-user-messaging').bind('click', function(){
    closeAllConversationsMenus();
    toggleMessagingContainer();
  });

  function populateMessenger(){
    $('.new-message-total-badge').click(function(){
      $('.header-notifications-icon').trigger('click');
    });
  }

  function updatePermissionManagement(members,thread_id){
    var $userWrapper = $('.members-management-options-user-wrapper');
    $('.members-management-user').remove();
    displayConversationMembers(members,thread_id,$userWrapper);
  }

  //check if new messages
  function refreshMessages(messages,members,status,channels,origin){
    var thread_id = $('.msgs-wrapper').attr('data-thread_id');
    var username = $('#username-reference').text();
    var remove = '0';
    if(messages != undefined){
      generateMsgs(messages,thread_id,members,remove,status);
      if(origin === 'change-title'){remove = '5';}
      if(origin != 'checkForNewMessages'){refreshConversationsList(channels,remove);}
      updatePermissionManagement(members,thread_id);
    }else{
      //have to retrieve global data
      var path = 'Messenger/messenger_ajax.php';
      var limit = $('.msgs-wrapper .msg-container').length;
      var options = {action: 'retrieve-channel-global-data',thread_id:thread_id,username:username,limit:limit};
      ajaxCall(path, options, function(data){
        if(data){
          var data = JSON.parse(data);
          var members = transformData(data.members);
          var messages = transformData(data.messages);
          var totalMsgs = Number(data.total_msgs);
          $('.message-center-messages-container').attr('data-total-msgs',totalMsgs);
          var conversations = convertData(data.conversations);
          var status = data.isdev;
          generateMsgs(messages,thread_id,members,remove,status);
          refreshConversationsList(conversations,remove);
        }
      });
    }
  }

  //secret panel code
  var secret = "827966717379";// = robgio
  var input = "";
  var timer;
  var auth;

  function submitNewFeature(that,title,desc,link){
    if(title != '' && desc != ''){
      that.parent().velocity('fadeOut');
      $('.secret-add-new').trigger('click');
      var msg_body = (link === '' ? '-.-'+title+'-.- '+desc : '-.-'+title+'-.- '+desc+'-.-'+link);
      var username = $('#username-reference').text();
      var path = 'Messenger/messenger_ajax.php';
      var limit = $('.msgs-wrapper .msg-container').length+1;
      var options = {action: 'post-new-feature-message', thread_id:'1', msg_body:msg_body, username:username,limit:limit,title:title,description:desc,link:link};
      resetMasterTimeout(1500);
      ajaxCall(path, options, function(data){
        if(data){
          var data = JSON.parse(data);
          var messages = transformData(data.messages);
          var members = transformData(data.members);
          var status = data.isdev;
          var channels = convertData(data.channels);
          var origin = 'post-new-feature-message';
          refreshMessages(messages,members,status,channels,origin);
        }
      });
    }else{
      var message = '<p class="secret-alert-msg">'+langMsg71+'</p>';
      $('.secret-panel').append(message);
      setTimeout(function(){
        $('.secret-alert-msg').remove();
      }, 2000);
    }
  }

  function check_secret_code() {
    if(input == secret) {
      var $messageOverlay = $('<div>', {
        'class': 'mc-overlay'
      }),
      $secretPanel = $('<div>', {
        'class': 'secret-panel'
      }),
      $header = $('<div>', {
        'class': 'secret-header',
        'html': 'New features in REDCap<span> (dev only access)</span>'
      }),
      $newFeatCont = $('<div>', {
        'class': 'secret-new-features-container'
      }),
      $addAnother = $('<a>', {
        'class': 'secret-add-new',
        'text': 'add another'
      }),
      $close = $('<img>',{
        'class': 'mc-message-close-btn',
        'src': app_path_images+'close_button_black.png'
      }).bind('click', function(){
        toggleWindow();
      });
      var html = '<div class="secret-new-feat-container">'+
      '<p class="secret-new-feat-title">Title</p>'+
      '<input class="secret-new-feat-input" placeholder="ex. REDCap Messenger">'+
      '<p class="secret-new-feat-description">Description</p>'+
      '<textarea class="secret-new-feat-textarea" placeholder="ex.&#10;- secure messaging system&#10;- create and manage conversations&#10;- upload and download file within conversations"></textarea>'+
      '<p class="secret-new-feat-description">Link<span> (optional)</span></p>'+
      '<input class="secret-link" placeholder="www.redcapnewfeaturewhatever.com">'+
      '<button class="secret-submit-new-feat">Submit</button>'+
      '</div>';
      $('body').on('click', '.secret-add-new', function(){
        $newFeatCont.append(html);
      });
      $('body').on('click', '.secret-submit-new-feat', function(){
        var title = $(this).parent().find('.secret-new-feat-input').val();
        var desc = $(this).parent().find('.secret-new-feat-textarea').val();
        var descString = '';
        if(desc.charAt(0) === '-'){
          var descArray = desc.split('-');
          for (var i = 0; i < descArray.length; i++) {
            if(i>0){
              descString = descString+'-'+descArray[i]+'<br>';
            }
          }
        }else{
          descString = desc;
		}
        var link = $(this).parent().find('.secret-link').val();
        submitNewFeature($(this),title,descString,link);
      });
      $newFeatCont.append(html);
      $secretPanel.append($close).prepend($addAnother).append($header).append($newFeatCont);
      $('body').addClass('stop-scrolling').append($messageOverlay).append($secretPanel);
      $('.secret-panel').velocity('transition.slideDownIn',{duration:300,complete:function(){
        $('.secret-new-feat-input').focus();
      }});
    }
  }

  $(document).keyup(function(e) {
    input += e.which;
    clearTimeout(timer);
    timer = setTimeout(function() { input = ""; }, 1000);
    check_secret_code();
  });

  //add new 'what's new' is isDev is true
  $('body').on('click', '.add-new-whatsnew', function(){
    input = secret;
    $('.mc-container').remove();
    check_secret_code();
  });

  function getUniqueUsers(users){
    var array = users.split('-.-');
    var uniqueUsers = [];
    $.each(array, function(i, el){
        if($.inArray(el, uniqueUsers) === -1) uniqueUsers.push(el);
    });
    return uniqueUsers.toString();
  }
	
  function removeUsers(path,options,that){
    // ajax remove-users-from-conversation
    resetMasterTimeout(1500);
    ajaxCall(path, options, function(data){
      if(data){
        if(data === '0'){
          validationErrorMessage();
          closeAllConversationsMenus();
        }else{
          var data = JSON.parse(data);
          var messages = transformData(data.messages);
          var members = transformData(data.members);
          var status = data.isdev;
          var channels = convertData(data.channels);
          // var channels = transformData(data.channels);
          var origin = 'remove-users';
          refreshMessages(messages,members,status,channels,origin);
          //close panel and simpleDialog
          var triggerContainer = $('.mc-settings-close-btn');
          successNotification(triggerContainer);
        }
      }
    });
  }

  function addUsers(path,options,that){
    resetMasterTimeout(1500);
    ajaxCall(path, options, function(data){
      if(data){
        if(data === '0'){
          validationErrorMessage();
          closeAllConversationsMenus();
        }else{
          var data = JSON.parse(data);
          var messages = transformData(data.messages);
          var members = transformData(data.members);
          var status = data.isdev;
          var channels = convertData(data.channels);
          var origin = 'add-users';
          refreshMessages(messages,members,status,channels,origin);
          var triggerContainer = $('.mc-settings-close-btn');
          successNotification(triggerContainer);
        }
      }
    });
  }

  function validationErrorMessage(){
    alert(langMsg72);
  }

  function channelAction(title,invMsg,origin,thread_id){
  	invMsg = trim(invMsg);
  	title = trim(title);
    if(title == '' && origin == 'new-conversation'){
      $('.new-conv-title').velocity({scale:1.05},{duration:150,complete:function(){
        $('.new-conv-title').velocity({scale:1},{duration:150}).focus();
        $('.new-conv-title-tag .new-conv-tag-span').addClass('required-red');
      }});
      return;
    }
    var path = 'Messenger/messenger_ajax.php';
    var username = $('#username-reference').text();
    var remove = '1';
    //clean up users for ajax
    var users = $('.selected-participants').html().split('<img ');
	var arrayLength = users.length;
	var thisuser;
	var users2 = new Array();
	for (var i = 0; i < arrayLength; i++) {
		thisuser = trim(strip_tags('<img '+users[i]));
		if (thisuser != '') users2[i] = thisuser;
	}
	users = users2.join(',');
    title = (title == '' ? 'no-title' : title);
  	if (origin == 'add-new-users'){
  		invMsg = users.replace(/,/g, ', ')+' '+langMsg73;
  	} else {
  		invMsg = (invMsg == '' ? langMsg74+' "'+title+'" '+langMsg75 : invMsg);
  	}
    if(origin === 'new-conversation'){
      var projId = $('.new-conv-link-to-proj-select option:selected').attr('data-id');
      resetMasterTimeout(1500);
      var options = {action: 'create-new-conversation', title:title,users:users,username:username,msg:invMsg,pid:pid,proj_id:projId};
      ajaxCall(path, options, function(data){
        if(data){
          var data = JSON.parse(data);
          var channels = convertData(data.channels);
          var threadId = data.thread_id;
          refreshConversationsList(channels,remove,threadId);
        }
      });
    }else if(origin  === 'add-new-users'){
      invMsg = (invMsg === langMsg76 ? '' : invMsg);
      var limit = $('.msgs-wrapper .msg-container').length+1;
      var options = {action: 'post-channel-message',username:username,thread_id:thread_id,users:users,msg_body:invMsg,pid:pid,limit:limit};
      if(users != ''){
        var message = langMsg77;
        simpleDialog(message);
        var $confirm =  $('<button>',{
          'class': 'confirm-btn',
          'text': langMsg91
        }).bind('click', function(){
          var that = this;
          addUsers(path,options,that);
        }),
        $users = $('<p>',{
          'class': 'alert-users-list',
          'text': langMsg78+' '+options.users.replace(/,/g, ', ')
        });
        $('body').find('.ui-dialog-buttonpane .confirm-btn').remove();
        $('body').find('.ui-dialog').addClass('add-users-dialog');
        $('body').find('.ui-dialog-buttonpane').append($confirm);
        $('.ui-dialog-content').append($users);
      }else{
        simpleDialog(langMsg79);
      }
    }else if(origin  === 'remove-conversation-users'){
      var limit = $('.msgs-wrapper .msg-container').length+1;
      var options = {action: 'remove-users-from-conversation',thread_id:thread_id,users:users,username:username,limit:limit};
      if(users != ''){
        var allUsersLength = $('.new-conv-proj-participants .user').length;
        var selectedUsersLength = users.split(',').length;
        if(allUsersLength > selectedUsersLength){//check to be sure to leave at least one member in conversation
          simpleDialog(langMsg80);
          var $confirm =  $('<button>',{
            'class': 'confirm-btn',
            'text': langMsg81
          }).bind('click', function(){
            var that = this;
            removeUsers(path,options,that);
          }),
          $users = $('<p>',{
            'class': 'alert-users-list',
            'text': langMsg78+' '+options.users.replace(/,/g, ', ')
          });
          $('body').find('.ui-dialog-buttonpane .confirm-btn').remove();
          $('body').find('.ui-dialog').addClass('remove-users-dialog');
          $('body').find('.ui-dialog-buttonpane').append($confirm);
          $('.ui-dialog-content').append($users);
        }else{
          simpleDialog(langMsg82);
        }
      }else{
        simpleDialog(langMsg83);
      }
    }
  }

  function checkIfValid(e,that){
    var channelName = $(that).val();
    if(e.keyCode === 13 && channelName != ''){
      channelAction(channelName);
    }
  }

  function generateInfoMessage(e){
    var parentClass = $(e.target).parent().attr('class');
    var message = {content:'',title:''};
    switch(parentClass) {
		case 'new-conv-link-to-proj-tag':
		  message.content = langMsg85;
		  message.title = langMsg84;
		  break;
		// more here
    }
    return message;
  }

  $('body').on('click','.info-point',function(e){
    var message = generateInfoMessage(e);
    simpleDialog(message.content,message.title);
  });

  function hideCurrentMembers(members){
    for (var i = 0; i < members.length; i++) {
      $('.new-conv-proj-participants .user input[data-id="'+members[i].ui_id+'"]').parent().remove();
    }
  }

  function selectCurrentUser(currentUser){
    var username = $('.new-conv-proj-participants .user input[data-id="'+currentUser+'"]').attr('data-username');
    $('.new-conv-proj-participants .user input[data-id="'+currentUser+'"]').prop('checked',true);
    $('.new-conv-proj-participants .user input[data-id="'+currentUser+'"]').prev().addClass('user-selected');
    $('.selected-participants').append('<p class="new-participant" data-id="'+currentUser+'">'+username+'<img class="remove-participant" src="'+app_path_images+'cross_small.png"></p>');
  }

  function selectAllUsers(e){
    var $item = $(e.target);
    var action = $item.text();
    var origin = $item.parent().text().replace(langMsg86,'').replace(langMsg87,'');
    if(origin === langMsg88){
      var $users = $item.parent().parent().find('.user .proj-participants-cb');
    }else if(origin === langMsg89){
      var $users = $item.parent().parent().find('.user .participants-cb');
    }else if(origin === langMsg90){
      var $users = $item.parent().parent().find('.user .proj-participants-cb');
    }
    var id, username;
    $users.each(function(i,e){
      id = $(e).attr('data-id');
      username = $(e).attr('data-username');
      if(action === langMsg86){
        if(!$(e).prev().hasClass('user-selected')){
          $(e).prev().trigger('click');
        }
      }else{//unselect all
        if($(e).prev().hasClass('user-selected')){
          $(e).prev().trigger('click');
        }
      }
    });
    (action === langMsg86 ? $item.text(langMsg87) : $item.text(langMsg86));
  }

  function createUserPanel(origin,thread_id){
    switch(origin) {
      case 'add-new-users':
      var title = langMsg94,
          buttonText = langMsg91,
          allUsers = langMsg95,
          projUsers = langMsg96;
        break;
      case 'new-conversation':
      var title = langMsg97,
          buttonText = langMsg92,
          allUsers = langMsg89,
          projUsers = langMsg88;
        break;
      case 'remove-conversation-users':
      var title = langMsg98,
          buttonText = langMsg93,
          allUsers = langMsg90,
          projUsers = langMsg96;
        break;
      default: //conversations
      var title = 'empty';
    }
    if($('.message-section[data-section="channel"]').height() === 0){
      $('.message-center-expand[data-section="channel"]').trigger('click');
    }
    var $mcOverlay = $('<div>', {
      'class': 'mc-overlay'
    }),
    $ChannelPanel = $('<div>', {
      'class': 'new-channel-panel'
    }),
    $header = $('<div>', {
      'class': 'new-conv-header',
      'text': title
    }),
    $searchInput = $('<input>', {
      'class': 'new-conv-search-input ui-autocomplete-input',
      'id': 'top-new-conv-search-input',
      'placeholder': langMsg99
    }),
    $autocompleteWrapper = $('<div>', {
      'class': 'autocomplete-user-wrapper',
    }),
    $participants = $('<div>', {
      'class': 'new-conv-participants',
      'text': allUsers
    }),
    $projParticipants = $('<div>', {
      'class': 'new-conv-proj-participants'
    }),
    $invitationMsg = $('<textarea>', {
      'class': 'new-conv-invitation-msg',
      'placeholder': langMsg76
    }),
    $selectedParticipants = $('<div>', {
      'class': 'selected-participants'
    }),
    $input = $('<input>', {
      'class': 'new-conv-title',
      'placeholder': langMsg100
    }),
    $close = $('<img>',{
      'class': 'mc-settings-close-btn',
      src: app_path_images+'close_button_black.png'
    }).bind('click', function(){
      toggleWindow();
    }),
    $submit = $('<div>',{
      'class': 'new-conv-submit-btn',
      'text': buttonText
    }).bind('click', function(){
      channelAction($input.val(),$invitationMsg.val(),origin,thread_id);
    }),
    $cancel = $('<div>',{
      'class': 'new-conv-cancel-btn',
      'text': langMsg101
    }).bind('click', function(){
      $close.trigger('click');
    }),
    $titleTag = $('<h6>', {
      'class': 'new-conv-title-tag',
      'html': langMsg102+'<span class="new-conv-tag-span">'+langMsg103+'</span>'
    }),
    $userSearchTag = $('<h6>', {
      'class': 'new-conv-users-tag',
      'html': langMsg104
    }),
    $userListTag = $('<h6>', {
      'class': 'new-conv-user-list-tag',
      'html': langMsg105
    }),
    $selectedUsersTag = $('<h6>', {
      'class': 'new-conv-selected-users-tag',
      'html': langMsg106
    }),
    $invitationMsgTag = $('<h6>', {
      'class': 'new-conv-inivitation-tag',
      'html': langMsg108+'<span class="new-conv-tag-span">'+langMsg107+'</span>'
    }),
    $tieConvToProjText = $('<h6>', {
      'class': 'new-conv-link-to-proj-tag',
      'html': langMsg84+'<span class="new-conv-tag-span">'+langMsg107+'</span><span class="new-conv-explanation info-point">?</span>'
    }),
    $tieConvToProjSelect = $('<select>', {
      'class': 'new-conv-link-to-proj-select'
    });
    var username = $('#username-reference').text();
    var path = 'Messenger/messenger_ajax.php';
    //retrieve and populate users lists
    switch(origin) {
      case 'remove-conversation-users':
        var options = {action: 'retrieve-list-of-users-in-conv',thread_id:thread_id};
        ajaxCall(path, options, function(data){
          if(data){
            var convMembers = transformData(data);
            $projParticipants.append('<h4 class="box-in-box-delimiter">'+langMsg90+'</h4>');
            $('.box-in-box-delimiter').append('<span class="select-all-user-btn">'+langMsg86+'</span>');
            for (var i = 0; i < convMembers.length; i++) {
              // var checked = (username === convMembers[i].username ? 'checked' : '');
              if(convMembers[i].username != ''){
                var item = '<div class="user" data-username="'+convMembers[i].username+'">'+
                '<p class="username-p">'+convMembers[i].username+' <span class="first-last">('+convMembers[i].user_firstname+' '+convMembers[i].user_lastname+')</span>'+
                '<span class="user-list-cm user-list-cm-remove">&#10007;</span>'+
                '</p>'+
                '<input class="proj-participants-cb" data-username="'+convMembers[i].username+'" data-id="'+convMembers[i].ui_id+'" type="checkbox">'+
                '</div>';
                $projParticipants.append(item);
              }
            }
            $('.select-all-user-btn').bind('click',function(e){
              selectAllUsers(e);
            });
          }
        });
      break;
      case 'add-new-users':
        var options = {action: 'retrieve-list-of-proj-users-not-in-conv',thread_id:thread_id,pid:pid};
        ajaxCall(path, options, function(data){
          if(data){
            var data = JSON.parse(data);
            var allUsers = transformData(data.allUsers);
            var projUsers = transformData(data.projUsers);
            var members = transformData(data.members);
            if(projUsers != ''){
              $projParticipants.append('<h4 class="box-in-box-delimiter users-in-proj-delimeter">'+langMsg88+'</h4>');
              $('.users-in-proj-delimeter').append('<span class="select-all-user-btn">'+langMsg86+'</span>');
              for (var i = 0; i < projUsers.length; i++) {
                if(projUsers[i].username != ''){
                  var checked = (username === projUsers[i].username ? 'checked' : '');
                  var item = '<div class="user" data-username="'+projUsers[i].username+'">'+
                  '<p class="username-p">'+projUsers[i].username+' <span class="first-last">('+projUsers[i].user_firstname+' '+projUsers[i].user_lastname+')</span>'+
                  '<span class="user-list-cm">&#10003;</span>'+
                  '</p>'+
                  '<input class="proj-participants-cb" data-username="'+projUsers[i].username+'" data-id="'+projUsers[i].ui_id+'" type="checkbox" '+checked+'>'+
                  '</div>';
                  $projParticipants.append(item);
                }
              }
            }
            $projParticipants.append('<h4 class="box-in-box-delimiter all-users-delimeter">'+langMsg89+'</h4>');
            if(allUsers != ''){
              $('.all-users-delimeter').append('<span class="select-all-user-btn">'+langMsg86+'</span>');
              for (var i = 0; i < allUsers.length; i++) {
                if(allUsers[i].username != ''){
                  var checked = (username === allUsers[i].username ? 'checked' : '');
                  var item = '<div class="user" data-username="'+allUsers[i].username+'">'+
                  '<p class="username-p">'+allUsers[i].username+' <span class="first-last">('+allUsers[i].user_firstname+' '+allUsers[i].user_lastname+')</span>'+
                  '<span class="user-list-cm">&#10003;</span>'+
                  '</p>'+
                  '<input class="participants-cb" data-username="'+allUsers[i].username+'" data-id="'+allUsers[i].ui_id+'" type="checkbox" '+checked+'>'+
                  '</div>';
                  $projParticipants.append(item);
                }
              }
            }else{//in case no users are in any projects
              $projParticipants.append('<p>'+langMsg109+'</p>');
            }
            //if outside project can't use pid in sql query to find project members so use this instead
            (members != '' ? hideCurrentMembers(members) : '');
            $('.select-all-user-btn').bind('click',function(e){
              selectAllUsers(e);
            });
          }
        });
        break;
      case 'new-conversation':
        //create list of all users
        var options = {action: 'retrieve-list-of-all-users-in-project',pid:pid,username:username};
        ajaxCall(path, options, function(data){
          if(data){
            var data = JSON.parse(data);
            var allUsers = transformData(data.allUsers);
            var projUsers = transformData(data.projUsers);
            var currentUser = data.currentUser;
            var projectsList = convertData(data.project_list);
            if(projUsers != ''){
              $projParticipants.append('<h4 class="box-in-box-delimiter users-in-proj-delimeter">'+langMsg88+'</h4>');
              $('.users-in-proj-delimeter').append('<span class="select-all-user-btn">'+langMsg86+'</span>');
            }
            for (var i = 0; i < projUsers.length; i++) {
              if(projUsers[i].username != ''){
                var checked = (username === projUsers[i].username ? 'checked' : '');
                var selectedClass = (username === projUsers[i].username ? 'user-selected' : '');
                var item = '<div class="user" data-username="'+projUsers[i].username+'">'+
                '<p class="username-p '+selectedClass+'">'+projUsers[i].username+' <span class="first-last">('+projUsers[i].user_firstname+' '+projUsers[i].user_lastname+')</span>'+
                '<span class="user-list-cm">&#10003;</span>'+
                '</p>'+
                '<input class="proj-participants-cb" data-username="'+projUsers[i].username+'" data-id="'+projUsers[i].ui_id+'" type="checkbox" '+checked+'>'+
                '</div>';
                $projParticipants.append(item);
              }
            }
            $projParticipants.append('<h4 class="box-in-box-delimiter all-users-delimeter">'+langMsg89+'</h4>');
            $('.all-users-delimeter').append('<span class="select-all-user-btn">'+langMsg86+'</span>');
            if(allUsers != ''){
              for (var i = 0; i < allUsers.length; i++) {
                if(allUsers[i].username != ''){
                  var checked = (username === allUsers[i].username ? 'checked' : '');
                  var item = '<div class="user" data-username="'+allUsers[i].username+'">'+
                  '<p class="username-p">'+allUsers[i].username+' <span class="first-last">('+allUsers[i].user_firstname+' '+allUsers[i].user_lastname+')</span>'+
                  '<span class="user-list-cm">&#10003;</span>'+
                  '</p>'+
                  '<input class="participants-cb" data-username="'+allUsers[i].username+'" data-id="'+allUsers[i].ui_id+'" type="checkbox" '+checked+'>'+
                  '</div>';
                  $projParticipants.append(item);
                }
              }
            }else{//in case no users are in any projects
              $projParticipants.append('<p>'+langMsg109+'</p>');
            }
            (currentUser != '' ? selectCurrentUser(currentUser) : '');
            $('.select-all-user-btn').bind('click',function(e){
              selectAllUsers(e);
            });
          }
          if(projectsList.length){
            $('.new-conv-link-to-proj-select').append('<option data-id="none">'+langMsg110+'</option>');
            var current = '';
            for(var i = 0; i < projectsList.length; i++){
              if(pid){current = (pid === projectsList[i].project_id ? ' - current project' : '');}
              var $option = '<option data-id="'+projectsList[i].project_id+'">'+projectsList[i].project_name+'&nbsp; (pid: '+projectsList[i].project_id+')'+current+'</option>';
              $('.new-conv-link-to-proj-select').append($option);
            }
          }else{
            $('.new-conv-link-to-proj-select').replaceWith('<h4 class="new-conv-link-no-proj">'+langMsg111+'</h4>');
          }
        });
      break;
    }
    // var pid = getUrlParameter('pid');
    if(origin === 'new-conversation'){
      $ChannelPanel.append($header).append($userSearchTag).append($userListTag).append($searchInput).append($autocompleteWrapper).append($projParticipants).append($selectedUsersTag).append($selectedParticipants).append($titleTag).append($input).append($invitationMsgTag).append($invitationMsg).append($tieConvToProjText).append($tieConvToProjSelect).append($submit).append($cancel).append($close);
    }else if(origin === 'add-new-users'){
      $ChannelPanel.addClass('add-new-user-panel');
      $ChannelPanel.append($header).append($userSearchTag).append($userListTag).append($searchInput).append($autocompleteWrapper).append($projParticipants).append($selectedUsersTag).append($selectedParticipants).append($submit).append($cancel).append($close);
    }else if(origin === 'remove-conversation-users'){
      $ChannelPanel.addClass('remove-user-panel');
      $ChannelPanel.append($header).append($selectedUsersTag).append($userListTag).append($projParticipants).append($selectedParticipants).append($submit).append($cancel).append($close);
    }
    $('body').addClass('stop-scrolling').append($mcOverlay).append($ChannelPanel);
    $ChannelPanel.velocity('transition.slideLeftIn',{duration:300,complete:function(){
      $searchInput.focus();
    }});

    function userAutocomplete() {
      var term = $(this).val();
      $('.autocomplete-user-wrapper *').remove();
      var options = {action: 'retrieve-list-of-all-users-like', term:term};
      if(term != ''){
        ajaxCall(path, options, function(data){
          if(data){
            var data = transformData(data);
            if(data != undefined){
              for (var i = 0; i < data.length; i++) {
				var userFirstLast = trim(data[i].user_firstname+' '+data[i].user_lastname);
				if (userFirstLast != '') userFirstLast = ' ('+userFirstLast+')';
                var user = '<p data-id="'+data[i].ui_id+'" data-name="'+data[i].username+'" class="autocomplete-user-item">'+
					data[i].username+userFirstLast+'</p>';
				if($('input.proj-participants-cb[data-id="'+data[i].ui_id+'"]').parent().find('p.username-p.user-selected').length > 0
					|| $('input.participants-cb[data-id="'+data[i].ui_id+'"]').parent().find('p.username-p.user-selected').length > 0) {
                  continue;
                }
                $autocompleteWrapper.append(user);
              }
            }
          }
        });
        $autocompleteWrapper.velocity('fadeIn','fast');
      }else{
        $autocompleteWrapper.velocity('fadeOut','fast');
      }
    }
    $('#top-new-conv-search-input').keyup(debounce(userAutocomplete,500));
    $('#top-new-conv-search-input').blur(function(){
      $autocompleteWrapper.velocity('fadeOut','fast');
    });
  }

  $('body').on('click', '.message-center-create-new[data-section="channel"], .messaging-create-new-big', function(){
    closeAllConversationsMenus();
    $('.message-center-messages-container .close-btn-small').trigger('click');
    $('.channel-members-username').text('');//clear members usernames
    createUserPanel('new-conversation');
  });

  function closeAllConversationsMenus(){
    var container = '';
    if($('.conversations-search-menu').length > 0){//if opened
      container = $('.conversations-search-menu');
    }
    if($('.conversations-view-options').length > 0){//if opened
      container = $('.conversations-view-options');
    }
    if($('.members-management-options').length > 0){//if opened
      container = $('.members-management-options');
    }
    if($('.msgs-upload-form').length > 0){//if opened
      container = $('.msgs-upload-form');
    }
    if($('.img-preview-container').length > 0){//if opened
      container = $('.img-preview-container, .mc-overlay');
    }
    if($('.archived-conversations-menu').length > 0){//if opened
      container = $('.archived-conversations-menu');
    }
    if($('.tag-menu-wrapper').length > 0){//if opened
      container = $('.tag-menu-wrapper');
    }
    if(container != ''){
      container.velocity('transition.slideLeftOut',{duration:100,complete:function(){
        container.remove();
      }});
    }
	hideTagSuggest();
  }

  function searchCallback(msgId){
    var count = 0;
    if($('.msg-container[data-id="'+msgId+'"]').css('display') === 'block'){
      $('.msg-container[data-id="'+msgId+'"]').velocity("scroll",{container:$('.msgs-wrapper'),complete:function(){
        $('.msg-container[data-id="'+msgId+'"] .msg-body')
        .velocity({backgroundColor:'#ffd700'})
        .velocity({backgroundColor:'#f0f8ff'},{delay:500});
        count = 1;
      }});
    }else{
      setTimeout(function(){
        if(count === 0){
          searchCallback(msgId);
        }
      },2000);
    }
  }

  function openConversation(e){
    var threadId = ($(e.target).attr('class') != 'search-found' ? $(e.target).attr('thread-id') : $(e.target).parent().attr('thread-id'));
    var status = ($(e.target).attr('class') != 'search-found' ? $(e.target).attr('data-channel-status') : $(e.target).parent().attr('data-channel-status'));
    var title = ($(e.target).attr('class') != 'search-found' ? $(e.target).attr('data-channel-title') : $(e.target).parent().attr('data-channel-title'));
    if(status === 'active'){
		
    }else{
      $('.message-center-messages-container .close-btn-small').trigger('click');
    }
    toggleWindow(threadId,window.escapeHTML(html_entity_decode(title)),'no',function(data){
      var eClass = ($(e.target).attr('class') === 'search-found' ? $(e.target).parent().attr('class') : $(e.target).attr('class'));
      if(eClass === 'search-results-channel-msg-body'){
        var msgId = ($(e.target).attr('class') === 'search-found' ? $(e.target).parent().attr('msg-id') : $(e.target).attr('msg-id'));
        setTimeout(function(){
          searchCallback(msgId);
        }, 800);
      }
    });
  }

  function showInternalMessages(thread_id){
    var $wrapper = $('.search-results-channel-name-wrapper[data-thread_id="'+thread_id+'"]').find('.search-results-channel-msgs-wrapper');
    if($($wrapper).height() === 0){//if closed
      var newHeight = $('.search-results-channel-name-wrapper[data-thread_id="'+thread_id+'"] .search-results-channel-msg-body').length;
      $($wrapper).velocity({height:[newHeight*34,0]},{duration:150});
    }else{
      $($wrapper).velocity({height:[0,newHeight*34]},{duration:150});
    }
  }

  function appendMessagesInChannel(allMsgObject,thread_id,inputValue){
    var $messagesWrapper = $('<div>', {
      'class': 'search-results-channel-msgs-wrapper'
    }),
    $dropDownIcon = $('<img>', {
      'class': 'search-results-channel-msgs-drop-down',
      'src': app_path_images+'arrow_down_sm.png'
    }).bind('click', function(){
      showInternalMessages(thread_id);
    });
    for (var i = 0; i < allMsgObject.length; i++) {
      if(allMsgObject[i].thread_id === thread_id){
         var msgBody = parseSearchedMessageBody(allMsgObject[i].message_body,inputValue);
        var $messageWrapper = $('<div>', {
          'class': 'search-results-channel-msg-wrapper'
        }),
        $messageInfo = $('<p>', {
          'class': 'search-results-channel-msg-info',
          'text': langMsg113+' '+allMsgObject[i].username+' '+langMsg114+' '+allMsgObject[i].sent_time
        }),
        $messageBody = $('<p>', {
          'class': 'search-results-channel-msg-body',
          'html': msgBody,
          'thread-id': allMsgObject[i].thread_id,
          'msg-id': allMsgObject[i].message_id,
          'data-msg-status': checkMsgStatus(allMsgObject[i].message_body),
          'data-channel-status': (allMsgObject[i].channel_status === '0' ? 'active' : 'inactive'),
          'data-channel-title': window.escapeHTML(allMsgObject[i].channel_name)
        }).bind('click',function(e){
          openConversation(e);
        });
        $messageWrapper.append($messageBody).append($messageInfo);
        $messagesWrapper.append($messageWrapper);
      }
    }
    if($($messageBody).length > 0){
      $('.search-results-channel-name-wrapper[data-thread_id="'+thread_id+'"]').append($dropDownIcon).append($messagesWrapper);
    }
  }

  function highlightKeyword(inputValue){
    $('.search-results-channel-name-wrapper .search-results-channel-name, .search-results-channel-msg-body, .search-by-members-username').each(function(i,e){
      try {
		  var content = $(e).text();
		  var regex = new RegExp("(" + inputValue + ")","gi");
		  content = content.replace(regex,'<span class="search-found">'+inputValue+'</span>');
		  $(e).html(content);
		  if($(e).next().hasClass('search-results-channel-msgs-drop-down')){
			var threadId = $(e).parent().attr('data-thread_id');
			$('.search-results-channel-name-wrapper[data-thread_id="'+threadId+'"] .search-results-channel-msg-body').each(function(i,element){
			  var content = $(element).text();
			  var regex = new RegExp("(" + inputValue + ")","gi");
			  content = content.replace(regex,'<span class="search-found">'+inputValue+'</span>');
			  $(element).html(content);
			});
		  }
	  } catch(e) { }
    });
  }

  function buildArray(channels,messages){
    var threadIdToEsclude = [];
    var messageInConv = [];
    for (var i = 0; i < channels.length; i++) {
      threadIdToEsclude.push(channels[i].thread_id);
    }
    for (var i = 0; i < messages.length; i++) {
      var threadId = messages[i].thread_id;
      for (var k = 0; k < threadIdToEsclude.length; k++) {
        if(threadId === threadIdToEsclude[k]){
          messageInConv.push(messages[i]);
        }
      }
    }
    return messageInConv;
  }

  function checkIfTitleTooLong(oldTitle,status){
    if(oldTitle.length > 30){
      var newTitle = oldTitle.substr(0,30)+'...'+status;
      return newTitle;
    }else{
      return oldTitle+status;
    }
  }

  function showResults(data,searchMethod,inputValue){
    if(searchMethod === 'keyword'){
      var data = JSON.parse(data);
      var allMsgObject = convertData(data.messages);
      var obj = convertData(data.channels);
      if(allMsgObject){
        var msgsInConv = buildArray(obj,allMsgObject);
        var msgsNotInConv = diffArray(allMsgObject,msgsInConv);
      }
    }else{//members search method
      var obj = JSON.parse(data);
      var msgsInConv = '',
          msgsNotInConv = '',
          allMsgObject = '';
    }
    if(obj){
      $('.conversations-search-results-text-msg, .search-results-channel-name-wrapper, .search-results-channel-msg-wrapper, .search-by-members-list-wrapper').remove();
      $('.conversations-search-results-text').text('Conversations:');
      $('.search-results-channel-name-wrapper, .search-results-channel-msg-wrapper').remove();
      for (var i = 0; i < obj.length; i++) {
        var status = (obj[i].archived === '0'? '' : '(inactive)'),
        channelTitle = window.escapeHTML(checkIfTitleTooLong(obj[i].channel_name,status));
        var $channel = '<div class="search-results-channel-name-wrapper" data-channel-title="'+window.escapeHTML(obj[i].channel_name)+'" data-channel-status="'+(obj[i].archived === '0'? 'active' : 'inactive')+'" data-thread_id="'+obj[i].thread_id+'"><img src="'+app_path_images+'balloon_left.png" class="conversations-results-icon"><p thread-id="'+obj[i].thread_id+'" class="search-results-channel-name" data-channel-title="'+obj[i].channel_name+'" data-channel-status="'+(obj[i].archived === '0'? 'active' : 'inactive')+'">'+channelTitle+'</p></div>';
        $('.conversations-search-results').append($channel);
        (searchMethod === 'keyword' ? appendMessagesInChannel(allMsgObject,obj[i].thread_id,inputValue) : '');
      }
      $('.search-results-channel-name-wrapper').velocity('transition.flipYIn',{stagger:100,duration:200});
      $('.search-results-channel-name').bind('click',function(e){
        openConversation(e);
      });
      if(msgsNotInConv){
        if(msgsNotInConv.length === 0){
          $('.conversations-search-results-text-msg').remove();
        }else{
          $('.conversations-search-results').append('<p class="conversations-search-results-text conversations-search-results-text-msg">'+langMsg112+'</p>');
        }
        for (var i = 0; i < msgsNotInConv.length; i++) {
          var msgBody = parseSearchedMessageBody(msgsNotInConv[i].message_body,inputValue);
          var $messageWrapper = $('<div>', {
            'class': 'search-results-channel-msg-wrapper',
          }),
          $messageInfo = $('<p>', {
            'class': 'search-results-channel-msg-info',
            'text': langMsg113+' '+msgsNotInConv[i].username+langMsg114+' '+msgsNotInConv[i].sent_time+langMsg115+' '+msgsNotInConv[i].channel_name+(msgsNotInConv[i].channel_status === '1' ? langMsg116 : ''),
            'thread-id': msgsNotInConv[i].thread_id
          }),
          $messageBody = $('<p>', {
            'class': 'search-results-channel-msg-body',
            'html': msgBody,
            'thread-id': msgsNotInConv[i].thread_id,
            'msg-id': msgsNotInConv[i].message_id,
            'data-msg-status': checkMsgStatus(msgsNotInConv[i].message_body),
            'data-channel-status': (msgsNotInConv[i].channel_status === '0' ? 'active' : 'inactive'),
            'data-channel-title': window.escapeHTML(msgsNotInConv[i].channel_name)
          }).bind('click',function(e){
            openConversation(e);
          });
          $messageWrapper.append($messageBody).append($messageInfo);
          $('.conversations-search-results').append($messageWrapper);
        }
        $('.search-results-channel-msg-wrapper').velocity('transition.flipYIn',{stagger:10,duration:20});
      }else{
        $('.conversations-search-results-text-msg').remove();
      }
      (searchMethod === 'keyword' ? highlightKeyword(inputValue) : '');
    }else if(allMsgObject.length > 0){
      $('.conversations-search-results-text-msg, .search-results-channel-name-wrapper, .search-results-channel-msg-wrapper, .search-by-members-list-wrapper').remove();
      $('.conversations-search-results-text').text('Messages:');
      for (var i = 0; i < allMsgObject.length; i++) {
        var msgBody = parseSearchedMessageBody(window.escapeHTML(allMsgObject[i].message_body),inputValue);
        var $messageWrapper = $('<div>', {
          'class': 'search-results-channel-msg-wrapper',
        }),
        $messageInfo = $('<p>', {
          'class': 'search-results-channel-msg-info',
          'text': langMsg113+' '+allMsgObject[i].username+langMsg114+' '+allMsgObject[i].sent_time+langMsg115+' '+allMsgObject[i].channel_name+(allMsgObject[i].channel_status === '1' ? langMsg116 : ''),
          'thread-id': allMsgObject[i].thread_id
        }),
        $messageBody = $('<p>', {
          'class': 'search-results-channel-msg-body',
          'html': msgBody,
          'thread-id': allMsgObject[i].thread_id,
          'msg-id': allMsgObject[i].message_id,
          'data-msg-status': checkMsgStatus(allMsgObject[i].message_body),
          'data-channel-status': (allMsgObject[i].channel_status === '0' ? 'active' : 'inactive'),
          'data-channel-title': window.escapeHTML(allMsgObject[i].channel_name)
        });
        $messageWrapper.append($messageBody).append($messageInfo);
        $('.conversations-search-results').append($messageWrapper);
      }
      $('.search-results-channel-msg-wrapper').velocity('transition.flipYIn',{stagger:10,duration:20});
      $('.search-results-channel-msg-wrapper .search-results-channel-msg-body').bind('click',function(e){
        openConversation(e);
      });
      (searchMethod === 'keyword' ? highlightKeyword(inputValue) : '');
    }else{
      $('.conversations-search-results-text-msg, .search-results-channel-name-wrapper, .search-results-channel-msg-wrapper').remove();
      $('.conversations-search-results-text').text(langMsg147);
    }
  }

  function generateUserList(data){
    var $html = '';
    for(var i = 0; i < data.length; i++){
      var $username = '<p class="search-by-members-username" data-username="'+data[i].username+'">'+data[i].username+' ('+data[i].user_firstname+' '+data[i].user_lastname+') - '+data[i].user_email+'</p>';
      $html += $username;
    }
    return $html;
  }

  function searchByMembers(username){
    $('.conversations-search-input').val('').focus();
    $('.conversations-search-results-text-msg, .search-results-channel-name-wrapper, .search-results-channel-msg-wrapper, .search-by-members-list-wrapper').remove();
    $('.conversations-search-results-text').text(langMsg117);
    var inputValue = username,
        searchMethod = 'members',
        path = 'Messenger/messenger_ajax.php',
        options = {action: 'find-user-conversations',username:username};
    resetMasterTimeout(1500);
    ajaxCall(path, options, function(data){
      if(data){
        showResults(data,searchMethod,inputValue);
      }
    });
  }

  $('body').on('click','.search-by-members-username',function(e){
    var username = $(e.target).attr('data-username');
    searchByMembers(username);
  });

  function searchConversations(){
    var inputValue = $(this).val();
    var searchMethod = $(this).attr('data-search');
    var path = 'Messenger/messenger_ajax.php';
    if(searchMethod === 'keyword'){
      var username = $('#username-reference').text();
      var options = {action: 'find-conversations-like',value:inputValue,username:username,search_method:searchMethod};
      if(inputValue != ''){
        resetMasterTimeout(1500);
        ajaxCall(path, options, function(data){
          if(data){
            showResults(data,searchMethod,inputValue);
          }
        });
      }else{
        $('.conversations-search-results-text-msg, .search-results-channel-name-wrapper, .search-results-channel-msg-wrapper, .search-by-members-list-wrapper').remove();
        $('.conversations-search-results-text').text(langMsg117);
      }
    }else{//search by member
      $('.conversations-search-results-text-msg, .search-results-channel-name-wrapper, .search-results-channel-msg-wrapper, .search-by-members-list-wrapper').remove();
      var options = {action: 'find-conversations-like',value:inputValue,search_method:searchMethod};
      if(inputValue != '' && inputValue.length > 1){
        resetMasterTimeout(1500);
        ajaxCall(path, options, function(data){
          if(data){
            var data = transformData(data);
            if(data.length > 0){
              var $userListHtml = generateUserList(data);
              var $userListWrapper = $('<div>',{
                'class': 'search-by-members-list-wrapper'
              });
              $userListWrapper.append($userListHtml);
              $('.conversations-search-menu').append($userListWrapper);
              highlightKeyword(inputValue);
            }
          }
        });
      }else{
        $('.conversations-search-results-text-msg, .search-results-channel-name-wrapper, .search-results-channel-msg-wrapper, .search-by-members-list-wrapper').remove();
        $('.conversations-search-results-text').text(langMsg117);
      }
    }
  }

  function swapSearchMethod(that){
    var searchMethod = ($(that).hasClass('conversations-search-by-keyword') ? 'keyword' : 'members');
    $('.search-input-text').removeClass('search-input-text-selected');
    $(that).addClass('search-input-text-selected');
    var placeholder = (searchMethod === 'keyword' ? langMsg118 : langMsg119);
    $('.conversations-search-input').attr('placeholder',placeholder)
                                    .attr('data-search',searchMethod)
                                    .focus();
  }

  function createSearchPanel(that,e){
    if($('.conversations-search-menu').length === 0){//if closed
      //close other menus first
      closeAllConversationsMenus();
      var $icon = $(e.target);
      var pos = $icon.offset();
      var $searchMenu =  $('<div>',{
        'class': 'conversations-search-menu'
      }),
      $title =  $('<p>',{
        'class': 'conversations-search-title',
        'text': langMsg120
      }),
      $closeBtn = $('<img>',{
        'class': 'conversations-search-close-btn',
        'src': app_path_images+'close_button_black.png'
      }).bind('click', function(){
        closeAllConversationsMenus();
      }),
      $searchByKeywordText =  $('<div>',{
        'class': 'conversations-search-by-keyword search-input-text search-input-text-selected',
        'text': langMsg121
      }),
      $searchBymembers =  $('<div>',{
        'class': 'conversations-search-by-members search-input-text',
        'text': langMsg122
      }),
      $inputWrap =  $('<div>',{
        'class': 'conversations-input-wrap'
      }),
      $input = $('<input>', {
        'class': 'conversations-search-input',
        'placeholder': langMsg118,
        'data-search': 'keyword'
      }),
      $searchResultsText =  $('<p>',{
        'class': 'conversations-search-results-text',
        'text': langMsg117
      }),
      $searchResults =  $('<div>',{
        'class': 'conversations-search-results'
      });
      $inputWrap.append($input);
      $searchResults.append($searchResultsText);
      $searchMenu.append($closeBtn)
        .append($title)
        .append($searchByKeywordText)
        .append($searchBymembers)
        .append($inputWrap)
        .append($searchResults);
      $('body').append($searchMenu);
      $input.keyup(debounce(searchConversations,500));
      $('.search-input-text').bind('click', function(){
        swapSearchMethod(this);
      });
      $searchMenu.velocity('transition.slideLeftIn',{duration:100,complete:function(){
        $input.focus();
      }});
    }else{//if open already
      closeAllConversationsMenus();
    }
  }

  $('body').on('click', '.message-center-search-conversation', function(e){
    createSearchPanel(this,e);
  });

  function resurrectConversation(that){
    var thread_id = $(that).parent().attr('data-id'),
    title = $(that).parent().find('.archived-conv-title').text();
    simpleDialog(langMsg123+' "<b>'+title+'</b>" '+langMsg124,langMsg125);
    var $confirm =  $('<button>',{
      'class': 'confirm-btn',
      text: 'Restore'
    }).bind('click', function(){
      resetMasterTimeout(1500);
      var path = 'Messenger/messenger_ajax.php';
      var username = $('#username-reference').text();
      var options = {action: 'resurrect-archived-conversation',thread_id:thread_id,username:username};
      ajaxCall(path, options, function(data){
        if(data){
          if(data === '0'){
            validationErrorMessage();
            closeAllConversationsMenus();
          }else{
            var data = JSON.parse(data);
            var superUser = data.super_user;
            var channels = convertData(data.channels);
            refreshArchivedConversationsList(channels,superUser);
            //refresh normal conversation list ?
            var triggerContainer = $('.message-center-messages-container .close-btn-small');
            successNotification(triggerContainer);
          }
        }
      });
    });
    $('body').find('.ui-dialog-buttonpane .confirm-btn, .ui-dialog-buttonpane .input-confirm-delete').remove();
    $('body').find('.ui-dialog').addClass('remove-users-dialog');
    $('body').find('.ui-dialog-buttonpane').append($confirm);
  }

  function openArchivedConversation(that){
    var dataId = $(that).parent().attr('data-id'),
        title = $(that).parent().find('.archived-conv-title').text(),
        status = 'archived';
    if($('.message-center-messages-container').css('display') === 'block'){
      $('.message-center-messages-container .close-btn-small').trigger('click');
    }
    toggleWindow(dataId,title);
  }

  function downloadConverstionAsCsv(that){
    if($(that).parent().attr('data-id') != undefined){//from archived conversation menu
      var title = $(that).parent().find('.archived-conv-title').text(),
      thread_id = $(that).parent().attr('data-id');
    }else{//from conversation action icon
      var title = $('.message-center-messages-container .conversation-title').text(),
      thread_id = $(that).attr('data-id');
    }
    resetMasterTimeout(1500);
    var $iframe = '<iframe id="download-iframe" src="" style="visibility:hidden;"></iframe>';
    $('body').append($iframe);
    var path = 'Messenger/messenger_download_csv.php';
    var url = app_path_webroot+path+'?title='+title+'&thread_id='+thread_id;
    $('#download-iframe').attr('src',url);
    setTimeout(function(){
      $('#download-iframe').remove();
    },1000);
  }

  function refreshArchivedConversationsList(channels,superUser){
    function checkIfTooLong(title){
      var newTitle = (title.length > 36 ? (title.substr(0,34))+'...' : title);
      return newTitle;
    }
    $('.archived-conv-container').remove();
    if(channels != ''){
      var $icon = (superUser != 'pino' ? '<button class="btn btn-primaryrc btn-xs archived-conv-icon archived-conv-resurrect-icon">'+langMsg126+'</button>' : '');
      for (var i = 0; i < channels.length; i++) {
        var title = checkIfTooLong(channels[i].channel_name);
        var $html = '<div class="archived-conv-container clearfix" data-id="'+channels[i].thread_id+'">'+
        '<div class="archived-conv-title" data-tooltip="'+channels[i].channel_name+'">'+title+'</div>'+
        $icon+
        '<button class="btn btn-success btn-xs archived-conv-icon archived-conv-download">'+langMsg16+'</button>'+
        '</div>';
        $('.archived-conversations-menu').append($html);
        if(title.length > 36){
          instanciateHoverEffect('.archived-conv-container[data-id="'+channels[i].thread_id+'"] .archived-conv-title',1000);
        }
      }
      $('.archived-conv-resurrect-icon').bind('click',function(){
        resurrectConversation(this);
      });
      $('.archived-conv-download').bind('click',function(){
        downloadConverstionAsCsv(this);
      });
      $('.archived-conv-open, .archived-conv-title').bind('click',function(){
        openArchivedConversation(this);
      });
      instanciateHoverEffect('.archived-conv-icon',1000);
      $('.archived-conv-container').velocity('transition.flipYIn',{stagger:10,duration:50});
    }else{
      $('.archived-conversations-menu').append('<p class="no-archived-conv">'+langMsg127+'</p>');
    }
  }

  function createArchivedConvPanel(that,e){
    if($('.archived-conversations-menu').length === 0){//if closed
      $('.message-center-messages-container .close-btn-small').trigger('click');
      closeAllConversationsMenus();
      var username = $('#username-reference').text();
      var path = 'Messenger/messenger_ajax.php';
      var options = {action: 'get-archived-conversations',username:username};
      resetMasterTimeout(1500);
      ajaxCall(path, options, function(data){
        if(data){
          var data = JSON.parse(data);
          var superUser = data.super_user;
          var channels = convertData(data.channels);
          refreshArchivedConversationsList(channels,superUser);
        }
      });
      var $archivedMenu =  $('<div>',{
        'class': 'archived-conversations-menu'
      }),
      $title =  $('<p>',{
        'class': 'archived-conversations-title',
        'text': langMsg128
      }),
      $explanation =  $('<p>',{
        'class': 'archived-conversations-explnation',
        'text': langMsg129
      }),
      $closeBtn = $('<img>',{
        'class': 'conversations-search-close-btn',
        'src': app_path_images+'close_button_black.png'
      }).bind('click', function(){
        closeAllConversationsMenus();
      });
      $archivedMenu.append($closeBtn)
        .append($title)
        .append($explanation);
      $('body').append($archivedMenu);
      $archivedMenu.velocity('transition.slideLeftIn',{duration:100,complete:function(){
      }});
    }else{//if open already
      closeAllConversationsMenus();
    }
  }

  $('body').on('click', '.message-center-archived-conv', function(e){
    resetMasterTimeout(1500);
    createArchivedConvPanel(this,e);
  });

  function buildParticipantsList(operator,id,username,that){
    if(that.attr('class') === 'username-p velocity-animating'){
      var isChecked = that.next().prop('checked');
      (isChecked ? that.next().prop( "checked", false ) : that.next().prop( "checked", true ));
    }
    if(operator === 'add'){
      $('.selected-participants').append('<p class="new-participant" data-id="'+id+'">'+username+'<img class="remove-participant" src="'+app_path_images+'cross_small.png"></p>');
    }else{
      $('.new-participant[data-id="'+id+'"]').remove();
    }
  }

  function checkSelectAllText(usersClass,that){
    var usersArray = $('.'+usersClass);
    var selectedUsersArray = $('.'+usersClass+':checked');
    var totalLength = usersArray.length;
    var selectedLength = selectedUsersArray.length;
    var spanContainer;
    if($(that).next().attr('class') === 'proj-participants-cb' && $(that).find('span').hasClass('user-list-cm-remove')){
      spanContainer = 'box-in-box-delimiter';
    }else if($(that).next().attr('class') === 'participants-cb'){
      spanContainer = 'all-users-delimeter';
    }else{
      spanContainer = 'users-in-proj-delimeter';
    }
    if(totalLength === selectedLength){
      $('.'+spanContainer+' span').text(langMsg87);
    }
    if(selectedLength === 0){
      $('.'+spanContainer+' span').text(langMsg86);
    }
  }

  $('body').on('click', '.username-p', function(){
    $(this).velocity({scale:1.05},{duration:100}).velocity({scale:1},{duration:100});
    var id = $(this).next().attr('data-id'),
        username = $(this).parent().attr('data-username');
    var that = $(this);
    if($(this).next().is(':checked')){
      $(this).removeClass('user-selected');
      buildParticipantsList('remove',id,username,that);
    }else{
      buildParticipantsList('add',id,username,that);
      $(this).addClass('user-selected');
    }
    var usersClass = $(this).next().attr('class');
    checkSelectAllText(usersClass,this);
  });

  $('body').on('click', '.user .participants-cb, .user .proj-participants-cb', function(){
    var id = $(this).attr('data-id'),
        username = $(this).attr('data-username');
    var that = $(this);
    if($(this).is(':checked')){
      buildParticipantsList('add',id,username,that);
    }else{
      buildParticipantsList('remove',id,username,that);
    }
  });

  $('body').on('click', '.remove-participant', function(){
    var dataId = $(this).parent().attr('data-id');
    $(this).parent().remove();
    $('.new-conv-proj-participants input[data-id="'+dataId+'"]').trigger('click');
    $('.new-conv-proj-participants .user input[data-id="'+dataId+'"]').prev().removeClass('user-selected');
  });

  // this was inside userAutocomplete function
  $('body').on('click', '.autocomplete-user-item', function(){
    var dataId = $(this).attr('data-id'),
        text = $(this).attr('data-name');
    if($('.new-conv-proj-participants input[data-id="'+dataId+'"]').length !=0){
      $('.new-conv-proj-participants input[data-id="'+dataId+'"]').prev().trigger('click');
    }else{
      if($('.selected-participants .new-participant[data-id="'+dataId+'"]').length !=0){
        $('.selected-participants .new-participant[data-id="'+dataId+'"]').remove();
      }else{
        $('.selected-participants').append('<p class="new-participant" data-id="'+dataId+'">'+text+'<img class="remove-participant" src="'+app_path_images+'cross_small.png"></p>');
      }
    }
    $('.new-conv-search-input').val('').focus();
  });

  // var parentAttr;
  function newMsgAnim(item,newTitle){
    item.velocity({scale:1.3},{duration:400}).velocity({scale:1},{duration:500});

    var totalConvUnread = item.parent().attr('data-unread');
    if(item.parent().attr('data-updated') != '1'){
      var newTitle = 'roffo!';
      item.parent().attr('data-updated','1');
    }else if(item.parent().attr('data-updated') === '1'){
      item.next().next().text(totalConvUnread);
    }
    if(!item.hasClass('stop-rotating') || item.parent().attr('data-rotating') === 'rotating'){
      setTimeout(function(){
        newMsgAnim(item,newTitle);
      },3000);
    }
  }

  function resetVisualIndicators(newMessages,singleConvUnread,channels){
    refreshConversationsList(channels,'0');
    var totalUnread = 0,
        totalNotUnread = 0,
        totalConvUnread = 0,
        thread_id,
        openedThreadId = $('.msgs-wrapper').attr('data-thread_id'),
        counter = 0,
        type;
    for (var i = 0; i < newMessages.length; i++) {
      counter  = (newMessages[i].thread_id === openedThreadId ? counter+1 : counter+0);//if conversation is open set counter to be more than 0 to trigger refresh
      type = newMessages[i].type.toLowerCase();
      thread_id = newMessages[i].thread_id;
      totalUnread += (newMessages[i].unread*1);
      if(type === 'notification' && newMessages[i].unread != 0){
        totalNotUnread += (newMessages[i].unread*1);
        $('.mc-what-new-alert[data-thread="'+type+'"]').text(totalNotUnread).addClass('what-new-show');
      }else if(type === 'channel' && newMessages[i].unread != 0){
        totalConvUnread += (newMessages[i].unread*1);
        $('.mc-what-new-alert[data-thread="'+type+'"]').text(totalConvUnread).addClass('what-new-show');
      }
    }
    var obj =  {
      totalUnread:totalUnread,
      counter:counter
    };
    return obj;
  }

  function updateConversations(channels){
    var realChannels = [];
    for (var i = 0; i < channels.length; i++) {
      realChannels.push(channels[i].thread_id);
    }
    var screenChannels = $('.message-section[data-section="channel"] .mc-message');
    var screenChannelsArray = [];
    for (var i = 0; i < screenChannels.length; i++) {
      screenChannelsArray.push($(screenChannels[i]).attr('thread-id'));
    }

    var items = [];

    items = $.grep(screenChannelsArray,function(item){
     return $.inArray(item, realChannels) < 0;
    });
    for (var i = 0; i < items.length; i++) {
      $('.message-section[data-section="channel"] .mc-message[thread-id="'+items[i]+'"]').remove();
      if($('.message-center-messages-container[data-thread_id="'+items[i]+'"]').css('display') === 'block'){
        var removedThreadId = items[i],
        removedTitle = $('.conversation-title').text();
        simpleDialog(langMsg130+' "'+removedTitle+'" '+langMsg131);
        var path = 'Messenger/messenger_ajax.php';
        var username = $('#username-reference').text();
        var options = {action:'check-why-kicked',thread_id:items[i],username:username};
        ajaxCall(path, options, function(data){
          if(data){
            var msg = (data === 'deleted' ? langMsg132 : '');
            msg = (data === 'archived' ? langMsg133 : msg);
            msg = (data === 'removed' ? langMsg134 : msg);
            $('.ui-dialog-content').append('<p class="why-kicked-msg">'+msg+'</p>');
            if(data === 'archived'){
              var $openArchived =  $('<button>',{
                'class': 'confirm-btn open-archived',
                'text': langMsg135
              }).bind('click', function(){
                $('.ui-dialog-buttonset').find('button').trigger('click');
                toggleWindow(removedThreadId,removedTitle,'',function(data){
					
                });
              }),
              msg2 = langMsg136;
              $('.ui-dialog-content').append('<p class="why-kicked-msg">'+msg2+'</p>');
              $('body').find('.ui-dialog-buttonpane').append($openArchived);
            }
          }
        });
      }
      ($('.message-center-messages-container[data-thread_id="'+items[i]+'"]').css('display') === 'block' ? $('.close-btn-small').trigger('click') : '');
    }
  }

  function refreshNotificationsSection(newMessages){
    var type,
    thread_id,
    totalUnread = 0,
    totalNotUnread = 0,
    totalWhatIsNew = 0,
    totalNotifications = 0,
    totalConvUnread = 0;
    for (var i = 0; i < newMessages.length; i++) {
      type = newMessages[i].type.toLowerCase();
      thread_id = newMessages[i].thread_id;
      totalUnread += (newMessages[i].unread*1);
      if(type === 'notification' && newMessages[i].unread != 0){
        totalNotUnread += (newMessages[i].unread*1);
        if(thread_id === '1'){
          totalWhatIsNew += (newMessages[i].unread*1);
        }else{
          totalNotifications += (newMessages[i].unread*1);
        }
        $('.mc-what-new-alert[data-thread="'+type+'"]').text(totalNotUnread).addClass('what-new-show');
      }else if(type === 'channel' && newMessages[i].unread != 0){
        totalConvUnread += (newMessages[i].unread*1);
        $('.mc-what-new-alert[data-thread="'+type+'"]').text(totalConvUnread).addClass('what-new-show');
      }
    }
    totalWhatIsNew = (totalWhatIsNew != 0 ? totalWhatIsNew : '');
    totalNotifications = (totalNotifications != 0 ? totalNotifications : '');
    $('.message-section[data-section="notification"] .mc-message[thread-id="1"] .mc-message-badge').text(totalWhatIsNew);
    $('.message-section[data-section="notification"] .mc-message[thread-id="3"] .mc-message-badge').text(totalNotifications);
  }

  function checkIfOpenConversationDataChanged(newMessages,messages,members,status,channels){
    //first find out if open conversation changed
    var counter = 0,
    openedThreadId = $('.msgs-wrapper').attr('data-thread_id');
    for (var i = 0; i < newMessages.length; i++) {
      counter  = (newMessages[i].thread_id === openedThreadId ? counter+1 : counter+0);//if conversation is open set counter to be more than 0 to trigger refresh
    }
    if(counter > 0){//if open conversation changed
      checkIfConversationTitleChanged(channels);
      var origin = 'checkForNewMessages';
      refreshMessages(messages,members,status,channels,origin);
    }
  }

  function checkForNewMessages(trigger){
	// Do nothing if the auto-logout dialog is displayed  
	if ($('#redcapAutoLogoutDialog div.red').length) return;
	
    var username = $('#username-reference').text();
    var thread_id = $('.msgs-wrapper').attr('data-thread_id');
    var thread_msg = $('.msg-input-new').val();
    //define and send session variables
    var important = ($('.messaging-mark-as-important-cb').prop('checked') === true ? '1' : '0');
    var action_icons_state = $('.show-hide-action-icons').attr('data-state');
    // var conv_win_size = $('.message-center-expand').attr('data-open');
    var conv_win_size = $('.message-section[data-section="channel"]').height();
    var msg_container_top = $('.message-center-messages-container').css('top');
    var msg_wrapper_height = $('.msgs-wrapper').height();
    var msg_container_height = $('.message-center-messages-container').height();
    var message_center_container_height = $(window).height();
    var limit = $('.msgs-wrapper .msg-container').length;
    var taggedData = $('.msg-input-new-container').attr('data-tagged');
    //session variables end
    var path = 'Messenger/messenger_ajax.php';
    var options = {action: 'check-if-new-messages', username:username,thread_id:thread_id,thread_msg:thread_msg,important:important,action_icons_state:action_icons_state,conv_win_size:conv_win_size,msg_wrapper_height:msg_wrapper_height,msg_container_top:msg_container_top,message_center_container_height:message_center_container_height,msg_container_height:msg_container_height,limit:limit,tagged_data:taggedData};
    if(trigger === '1'){//if message center open
      ajaxCall(path, options, function(data){
        if(data && data != 'reload'){
          var updateDom = 0;
		  try {
			var data = JSON.parse(data);
		  } catch(e) {
			if (page != "DataEntry/index.php") {
				// Display error message at top if user has multiple tabs opened, which causes Messenger to throw a CSRF error
				if (!$('.multiple_browser_alert').length) {
					var mbaMargin = ($('.rcproject-navbar').css('display') == 'none') ? '' : 'margin-top:50px;';
					$('.mainwindow').before('<div class="multiple_browser_alert red" style="max-width:100%;'+mbaMargin+'">'+langMsg137+'</div>');
				}
				return;
			}
		  }
          var newMessages = transformData(data.newmessages);
          var singleConvUnread = transformData(data.singleconvunread);
          //if archived convs menu is open refresh it
          if($('.archived-conversations-menu').length != 0){
            var displayedChannels = $('.archived-conv-container').length;
            var superUser = data.super_user;
            var archivedChannels = convertData(data.archived_channels);
            if(displayedChannels != archivedChannels.length){
              refreshArchivedConversationsList(archivedChannels,superUser);
            }
          }
          var channels = convertData(data.channels);
          var status = data.isdev;
          maxFileUpload = data.maxuploadsize;
          if(thread_id != '' && newMessages != ''){
            updateDom = 1;
            var messages = transformData(data.messages);
            var members = transformData(data.members);
          }
          //LATER//
          $('.mc-what-new-alert').text('').removeClass('what-new-show');
          $('.new-message-total-badge').removeClass('new-message-total-badge-show');
          $('.navbar-user-messaging.newmsgs').removeClass('newmsgs');
          $('.message-section .mc-message.thread-selected .mc-message-badge').text('');
          var conversationNumber = $('.message-section[data-section="channel"] .mc-message').length;
          refreshConversationsList(channels,'2');
          if(newMessages != ''){
            $('.new-message-total-badge').addClass('new-message-total-badge-show');
            $('.navbar-user-messaging').addClass('newmsgs');
            //first thing refresh conversation list
            //then refresh notifications section
            refreshNotificationsSection(newMessages);
            checkIfOpenConversationDataChanged(newMessages,messages,members,status,channels);
          }
        }else{//if message center gets disabled
          window.location.reload(true);//no cache
        }//end of if data
      });
    }
    var masterTimer = ($('.message-center-container').attr('data_open') === '1' ? (develop ? 10000 : 30000) : 90000);//change here 10000 to 30000 to increase refresh if only the lists are open
    masterTimer = ($('.message-center-messages-container').css('display') === 'block' ? 10000 : masterTimer);
    checkIfNewTimeout = setTimeout(function(){
      checkForNewMessages('1');
    },masterTimer);
  }


  //start
  if($('.message-center-container').attr('data_open') === '1'){
    populateMessenger();
    checkForNewMessages('1');
  }else{
    populateMessenger();
    setTimeout(function(){
      checkForNewMessages('1');
    },90000);
  }

  function calculateIfPreview(fileType){
    if(fileType.substr(0,5) === 'image'){
      return '0';
    }else if(fileType.substr(0,5) === 'audio' || fileType === 'application/pdf'){
      return '1';
    }else{
      return '2';
    }
  }

  $('body').on('click', '.msg-upload-btn, .upload-file-button-container', function(){	
	// Does not work for IE9 and below, so give popup message
	if (isIE && IEv < 10) {
		simpleDialog('<div class="yellow">'+langMsg138+'</div>',langMsg139);
		return;
	}
    if($('.msgs-upload-form').length === 0){//if closed
      closeAllConversationsMenus();
      var thread_id = $('.message-center-messages-container').attr('data-thread_id');
      var username = $('#username-reference').text();
      var path = app_path_webroot+'Messenger/messenger_file_upload.php';
      var $uploadForm = '<form autocomplete="off" class="msgs-upload-form" action="'+path+'" method="post" enctype="multipart/form-data" target="upload-target">'+
      '<h4 class="msgs-upload-form-title">'+langMsg140+'</h4>'+
      '<input name="myfile" type="file" id="fileToUpload">'+
      '<input name="myfile_base64" type="hidden">'+
      '<input name="myfile_base64_edited" type="hidden" value="0">'+
      '<input name="username" type="hidden" value="'+username+'">'+
      '<input name="thread_id" type="hidden" value="'+thread_id+'">'+
      '<input name="message" type="hidden" value="" id="upload-optional-msg">'+
      '<input name="limit" type="hidden" value="" id="upload-mesages-limit">'+
      '<input name="important" type="hidden" value="0" id="upload-important">'+
      '<input name="file_type" type="hidden" value="" id="msgs-file-type">'+
      '<input name="blob" type="hidden" value="" id="msgs-file-blob">'+
      '<canvas id="my-canvas" class="hidden"></canvas>'+
      '<input name="file_name" type="hidden" value="" id="msgs-file-name">'+
      '<input name="file_preview" type="hidden" value="" id="msgs-file-preview">'+
      '<input type="hidden" name="redcap_csrf_token" value="'+redcap_csrf_token+'">'+
      '<div class="msgs-upload-progress">'+
      '<span class="msgs-upload-progress-text">Upload in progress</span>'+
      '<img class="msgs-upload-progress-image" src="'+app_path_images+'loader.gif">'+
      '</div>'+
      '<img id="preview-upload-img" class="hidden" src="">'+
      '<div id="msgs-upload-error-msgs"></div>'+
      '</form>';
      var $closeBtn = $('<img>',{
        'class': 'conversations-search-close-btn',
        'src': app_path_images+'close_button_black.png'
      }).bind('click', function(){
        closeAllConversationsMenus();
      }),
      $addText = $('<textarea>',{
        'class': 'msgs-upload-add-text',
        'placeholder': langMsg76,
        'text': $('.msg-input-new').val()
      }),
      $addTextLabel = $('<p>',{
        'class': 'msgs-upload-add-text-label',
        'html': 'Message <span class="msgs-upload-add-text-label-optional">'+langMsg107+'</span>'
      }),
      $maxSize = $('<span>',{
        'class': 'msgs-upload-max-size',
        'text': langMsg141+' '+maxFileUpload+' MB'
      }),
      $markAsImportant = '<div class="messaging-mark-as-important-container upload-file-mark-as-important"><p class="messaging-mark-as-important-text upload-file-mark-as-important-text">'+langMsg21+'</p><input class="messaging-mark-as-important-cb" type="checkbox"></div>';
      var $button = $('<div>',{
        'class': 'msgs-upload-btn',
        'text': langMsg142
      }).bind('click', function(){
        //remove error messages if present
        if($('#msgs-upload-error-msgs span').length != 0){
          $('#msgs-upload-error-msgs span').velocity('fadeOut',{duration:100});
        }
        var file_data = $('#fileToUpload').prop('files')[0];
        if(file_data){
          window.URL = window.URL || window.webkitURL;
          var name = file_data.name;
          var filePreview = calculateIfPreview(file_data.type);
          var file_type = (file_data.type).substr(0,5);
          var blob = $('#preview-upload-img').attr('data-image');//light weight (no gif supported)
        }else{
          var name = '',
          filePreview = '',
          file_type = '',
          blob = '';
        }
        if(name != ''){
          $('.msgs-upload-progress').velocity('fadeIn',{duration:100});
        }else{
          $('.msgs-upload-form #fileToUpload').velocity({backgroundColor:'#ffd700'});
          $('#msgs-upload-error-msgs').append('<span>'+langMsg143+'</span>');
          setTimeout(function(){
            $('.msgs-upload-form #fileToUpload').velocity({backgroundColor:'#f0f8ff'});
          },1000);
          return;
        }
        $('#msgs-file-name').attr('value',name);
        $('#msgs-file-type').attr('value',file_type);
        $('#msgs-file-preview').attr('value',filePreview);
        var optionalMsg = window.escapeHTML($('.msgs-upload-add-text').val());
        $('#upload-optional-msg').attr('value',optionalMsg);
        var limit = $('.msgs-wrapper .msg-container').length+1;
        $('#upload-mesages-limit').attr('value',limit);
        var important = ($('.upload-file-mark-as-important .messaging-mark-as-important-cb').prop('checked') ? 1 : 0);
        $('#upload-important').attr('value',important);
        resetMasterTimeout(2500);
        $('.msgs-upload-form').submit();
        $('.msg-input-new').val('');
      });
      $('body').append($uploadForm);
      $('#fileToUpload').bind('change',function(){
        $('#msgs-upload-error-msgs span').remove();
        window.URL = window.URL || window.webkitURL;
        var preview = document.querySelector('#preview-upload-img');
        var file = document.querySelector('#fileToUpload').files[0];
        var canvas = document.querySelector('#my-canvas');
        var reader  = new FileReader();
        reader.onloadend = function(){
          preview.src = reader.result;
          //rest is superfluous now but good to know
          var context = canvas.getContext('2d');
          var x = 0;
          var y = 0;
          var width = preview.width;
          var height = preview.height;
          canvas.width = width;
          canvas.height = height;
          context.drawImage(preview, 0, 0, width, height);
          var dataURL = canvas.toDataURL("image/jpeg", 0.9);
          $('#preview-upload-img').attr('data-image',dataURL.substr(23));
        }
        if(file.type.substr(0,5) === 'image'){
          $('#preview-upload-img').removeClass('hidden');
          reader.readAsDataURL(file);
        }else{
          $('#preview-upload-img').addClass('hidden');
          preview.src = "";
        }
      });
      $('.msgs-upload-form').append($addTextLabel).append($addText).append($markAsImportant).append($button).append($maxSize).append($closeBtn);
      $('.upload-file-mark-as-important-text').bind('click',function(){
        $(this).next().trigger('click');
      });
      $('.msgs-upload-form').velocity('transition.slideLeftIn',{duration:100});
      var $iframe = '<iframe id="upload-target" name="upload-target" src="'+app_path_webroot+'DataEntry/empty.php" visibility:hidden;"></iframe>';
      $('.msgs-upload-form').append($iframe);
    }
  });

  window.messagingUploadFileHandler =  function(result,messages,members,isdev,channels){
    if(result === 1){
      $('.msgs-upload-progress').velocity('fadeOut',{duration:100});
      closeAllConversationsMenus();
      var messages = transformData(messages);
      var members = transformData(members);
      var status = (isdev ? 'true' : 'false');
      var channels = convertData(channels);
      var origin = 'upload-file-handler';
      refreshMessages(messages,members,status,channels,origin);
    }else if(result === 0){
      $('.msgs-upload-form #fileToUpload').velocity({backgroundColor:'#ffd700'});
      $('#msgs-upload-error-msgs').append('<span>'+langMsg143+'</span>');
      setTimeout(function(){
        $('.msgs-upload-form #fileToUpload').velocity({backgroundColor:'#f0f8ff'});
        $('#msgs-upload-error-msgs span').velocity('fadeOut',{duration:100,complete:function(){
          $('#msgs-upload-error-msgs span').remove();
        }});
      },1000);
    }else{
      alert(woops);
    }
  }

  window.messagingUploadFileTooBig =  function(result){
    $('.msgs-upload-progress').velocity('fadeOut',{duration:100});
    if(result === 2){
      $('.msgs-upload-form #fileToUpload').velocity({backgroundColor:'#ffd700'});
      $('#msgs-upload-error-msgs').append('<span>'+langMsg144+'</span>');
      setTimeout(function(){
        $('.msgs-upload-form #fileToUpload').velocity({backgroundColor:'#f0f8ff'});
      },1000);
    }
  }

  function startFileDownload(id,path,options){
    ajaxCall(path, options, function(data){
      if(data){
        var $iframe = '<iframe id="download-iframe" src="" style="visibility:hidden;"></iframe>';
        $('body').append($iframe);
        var path = 'DataEntry/file_download.php';
        var url = app_path_webroot+path+'?doc_id_hash='+data+'&id='+id+'&origin=messaging';
        $('#download-iframe').attr('src',url);
        if($('.img-preview-container').length > 0){
          closeAllConversationsMenus();
        }
      }
    });
  }

  $('body').on('click', '.msgs-dowload-file', function(){
    var id = $(this).attr('data-id');
    var path = 'Messenger/messenger_ajax.php';
    var options = {action: 'get-doc-id',id:id};
	if ($(this).attr('data-url') != null) {
		var dataUrl = $(this).attr('data-url');
	} else {
		var dataUrl = $(this).find('img').attr('data-url');
	}
	if(dataUrl.indexOf('DataEntry/file_download.php') > -1){
      startFileDownload(id,path,options);
      return;
    }else{
      var $image = $('<img>',{
        'class': 'img-preview-image',
        'src': dataUrl
      });
    }
    var $overlay = $('<div>',{
      'class': 'mc-overlay',
    }),
    $container = $('<div>',{
      'class': 'img-preview-container'
    }),
    $title = $('<h4>',{
      'class': 'img-preview-title',
      'text': langMsg145
    }),
    $downloadBtn = $('<div>',{
      'class': 'img-preview-download-btn',
      'text': langMsg146
    }).bind('click',function(){
      startFileDownload(id,path,options);
    }),
    $downloadBtnEffect = $('<div>',{
      'class': 'btn-effect',
      'html': '&#42780;'
    }),
    $cancelBtn = $('<div>',{
      'class': 'img-preview-cancel-btn',
      'text': langMsg101
    }).bind('click',function(){
      closeAllConversationsMenus();
    }),
    $closeBtn = $('<img>',{
      'class': 'conversations-search-close-btn',
      'src': app_path_images+'close_button_black.png'
    }).bind('click', function(){
      closeAllConversationsMenus();
    });
    $downloadBtn.append($downloadBtnEffect);
    $container.append($closeBtn).append($title).append($image).append($cancelBtn).append($downloadBtn);
    $('body').append($overlay).append($container);
  });

  //notifications section open/close logic
  $('.message-center-show-hide').click(function(){
    if($(this).hasClass('notifications-open')){
      $(this).removeClass('notifications-open').addClass('notifications-close');
      $(this).velocity({rotateZ:180,duration:200});
      $('.message-section[data-section="notification"]').velocity({height:0,duration:200});
    }else{
      $(this).removeClass('notifications-close').addClass('notifications-open');
      $(this).velocity({rotateZ:0,duration:200});
      $('.message-section[data-section="notification"]').velocity({height:52,duration:200});
    }
  });  
	// Enable clicking on "Notifications" word in panel to open notifications subpanel
	$('.message-center-notifications-container .mc-section-title, .message-center-notifications-container .mc-what-new-alert').click(function(event){
		$(this).parent().find('.message-center-show-hide').trigger('click');
	});

  function displayOlderMessages(messages,thread_id,lastItemDataId){
    var $messages = $('.msgs-wrapper').find('.msg-container');
    //generate messages html
    var imageCount = 0;
    $('.msgs-wrapper *').remove();
    for (var i = 0; i < messages.length; i++) {
	  // Is an unread msg?
	  var unreadMsgClass = messages[i].unread == '1' ? 'unread-msg' : '';
      //date divider
      var messageDay = (messages[i].normalized_ts).substr(8,2);
      var messageMonth = (messages[i].normalized_ts).substr(5,2);
      var messageYear = (messages[i].normalized_ts).substr(0,4);
      if ($('.messages-date-recents[data-date="'+messageYear+messageMonth+messageDay+'"]').length === 0) {
		var dateDisplay = (messageYear+'-'+messageMonth+'-'+messageDay == today) ? "Today" : (messages[i].sent_time).split(' ')[0];
        $('.msgs-wrapper').append('<p class="messages-date-recents" data-date="'+messageYear+messageMonth+messageDay+'">'+dateDisplay+'</p>');
      }//date divider end
      switch(thread_id) {
        case '1':// what's new
        var title = '',
            $urgentSpan = '',
            image = '<img class="msg-user-image" src="'+app_path_images+'redcap-logo-letter.png">',
            msgBody = parseMessageData(thread_id,messages[i].message_body),
            name = langMsg65,
            link = '',
            download = '';
          break;
        case '3'://general notifications
         var title = '',
            image = '<img class="msg-user-image" src="'+app_path_images+'redcap-logo-letter.png">',
            msgBody = parseMessageData(thread_id,messages[i].message_body),
            name = langMsg66,
            link = '',
            download = '';
			// Set as important?
			var important = '0',
				msgBodyJson = JSON.parse(messages[i].message_body);
			if (msgBodyJson !== null && typeof(msgBodyJson[0].important) !== 'undefined') {
				important = msgBodyJson[0].important;
			}
			var $urgentSpan = (important ===  '1' ? '<span class="urgent-span">!</span>' : '');
          break;
        default: //conversations
        var str = (messages[i].user_firstvisit).substring(11);
        var arr = str.split(':');
        var bkColor = 'rgb('+(arr[0]*1)+'0,'+(arr[1]*1)+'0,'+(arr[2]*1)+'0)';
        var title ='',
            image = '<div class="channel-msg-user-image" style="background-color:'+bkColor+'">'+(messages[i].user_firstname).substring(1,0)+(messages[i].user_lastname).substring(1,0)+'</div>',
            msgBody = parseMessageData(thread_id,messages[i].message_body),
            name = messages[i].username,
            link = '',
            download = '';
            if($(msgBody).attr('data-anchor') != undefined || $(msgBody).attr('data-edited') === 'deleted'){
              var edit = '';
            }else{
              var edit = '<img class="dot-dot-dot" src="'+app_path_images+'dot_dot_dot.png">';
            }
		// Set as important?
		var important = '0',
			msgBodyJson = JSON.parse(messages[i].message_body);
		if (msgBodyJson !== null && typeof(msgBodyJson[0].important) !== 'undefined') {
			important = msgBodyJson[0].important;
		}
		var $urgentSpan = (important ===  '1' ? '<span class="urgent-span">!</span>' : '');
        if(messages[i].attachment_doc_id && $(msgBody).attr('data-edited') != 'deleted'){
          if(messages[i].stored_url){
            var attachmentData = JSON.parse(messages[i].stored_url);
            var fileName = attachmentData[0].doc_name;
            var action = attachmentData[0].action;
            var fileNameHtml = '<p class="uploaded-file-text">'+fileName+'</p>';
            if(action === 'iframe-preview' || action === 'start-download'){
              var dataUrl = app_path_webroot+'DataEntry/file_download.php?doc_id_hash='+messages[i].doc_id_hash+'&id='+messages[i].attachment_doc_id+'&instance=1&origin=messaging';
              download = '<div class="msgs-dowload-file" data-url="'+dataUrl+'" data-hash-id="'+messages[i].doc_id_hash+'" data-id="'+messages[i].attachment_doc_id+'">'+
              '<span>'+fileNameHtml+'</span>'+
              '</div>';
            }else{
			  // Manually set image height to improve auto-scrolling
			  var styleHeight = '';
			  if (typeof(attachmentData[0].img_height) !== 'undefined') {
				if (attachmentData[0].img_height > 100) {
					styleHeight = 'style="height:100px;"';
				} else {
					styleHeight = 'style="height:'+attachmentData[0].img_height+'px;"';
				}
			  }
              var usleep = 100000*imageCount;
              var dataUrl = app_path_webroot+'DataEntry/image_view.php?doc_id_hash='+messages[i].doc_id_hash+'&id='+messages[i].attachment_doc_id+'&instance=1&origin=messaging';
              imageCount += 1;
              download = '<div class="msgs-dowload-file" data-hash-id="'+messages[i].doc_id_hash+'" data-id="'+messages[i].attachment_doc_id+'">'+
              '<img class="msgs-image-preview" data-url="'+dataUrl+'" '+styleHeight+' src="'+dataUrl+'&usleep='+usleep+'">'+
              fileNameHtml+
              '</div>';
            }
          }
        }
      }
      var $html = '<div class="msg-container '+unreadMsgClass+'" data-id="'+messages[i].message_id+'">'+
      image+
      '<p class="msg-name">'+name+'</p>'+
      '<p class="msg-time" data-urgent="'+messages[i].urgent+'" data-time="'+messages[i].sent_time+'">'+messages[i].sent_time+$urgentSpan+'</p>'+
      edit+
      title+
      msgBody+
      link+
      download+
      '</div>';
      //append messages html
      $('.msgs-wrapper').append($html);
    }
	// Highlight any new messages
	var msgDivider = '<div class="msg-unread-divider">'+langMsg67+'</div>';
	$('.msgs-wrapper .unread-msg:first').before(msgDivider);
	$('.msgs-wrapper .unread-msg, .msgs-wrapper .msg-unread-divider').effect('highlight',{},10000);
	setTimeout(function(){
		$('.msgs-wrapper .msg-unread-divider').css('visibility','hidden');
	},10000);
	if ($('.msg-container[data-id="'+lastItemDataId+'"]').length) {
		var newTop = ($('.msg-container[data-id="'+lastItemDataId+'"]').position().top)-50;
		$('.msgs-wrapper').scrollTop(newTop);
	}
  }

  $('.msgs-wrapper').scroll(function() {
    var pos = $('.msgs-wrapper').scrollTop(),
        totalConvMsgs = $('.message-center-messages-container').attr('data-total-msgs'),
        displayMsgs = $('.msgs-wrapper .msg-container').length;
    if(pos === 0 && totalConvMsgs != displayMsgs){
      var thread_id = $('.msgs-wrapper').attr('data-thread_id');
      var username = $('#username-reference').text();
      var lastItemDataId = $('.msgs-wrapper .msg-container:first').attr('data-id');
      var limit = ($('.msgs-wrapper .msg-container').length)+10;
      var path = 'Messenger/messenger_ajax.php';
      var options = {action: 'retrieve-channel-global-data',thread_id:thread_id,username:username,limit:limit};
      resetMasterTimeout(2000);
      ajaxCall(path, options, function(data){
        if(data){
          var data = JSON.parse(data);
          var messages = transformData(data.messages);
          displayOlderMessages(messages,thread_id,lastItemDataId);
        }
      });
    }
  });

	// Open REDCap Messenger if passed a variable in query string
	if (getParameterByName('__messenger') == 'open' && $('.message-center-container').attr('data_open') === '0') {
		toggleMessagingContainer();	
	}

}

// Init REDCap Messenger
initializeMessenger();
calculateMessageWindowPosition();

});

function calculateMessageWindowPosition(){
	var WHeight = $(window).height();
	var messageWindowHdrHeight = 60; // px
	var minMsgWrapperHeight = 200; // px
	var inputContainerHeight = $('.msg-input-new-container').height(); // px
	var hasNavBar = ($('.navbar.navbar-light.fixed-top').css('display') != 'none');
	var MsgrPanelTopPos = hasNavBar ? $('.navbar.navbar-light.fixed-top').height() : 0;
	var MsgrPanelHeight = WHeight-MsgrPanelTopPos;
	// Set height and top position of entire Messenger panel
	$('.message-center-container').height(MsgrPanelHeight);
	$('.message-center-container').css('top',MsgrPanelTopPos+'px');
	// Get position conversation title list div
	var messageWindowTopPos = $('.message-center-channels-container').position().top + 156;
	var msgWrapperHeight = (MsgrPanelHeight-messageWindowTopPos-inputContainerHeight-messageWindowHdrHeight);
	// Set messages container to 200px min height
	if (msgWrapperHeight < minMsgWrapperHeight) {
		messageWindowTopPos -= (minMsgWrapperHeight - msgWrapperHeight);
		msgWrapperHeight = minMsgWrapperHeight;
	}
	// Set message container height/top
	$('.message-center-messages-container').height(WHeight - messageWindowTopPos);
	$('.message-center-messages-container').css('top',messageWindowTopPos+'px');
	// Set height of div containing all the messages in the open conversation
	$('.msgs-wrapper').height(msgWrapperHeight);
}