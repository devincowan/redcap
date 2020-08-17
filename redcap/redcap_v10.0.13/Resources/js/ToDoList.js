// On pageload
$(function(){

  'use strict';

  $('body').addClass('todo-list-page');//add this class to the body to better target with css selectors

  //capitalize function
  function capitalize (text) {
    text = text.split(' ');
    var newText = '';
    for (var i = 0; i < text.length; i++) {
      newText += text[i].charAt(0).toUpperCase() + text[i].slice(1).toLowerCase() + ' ';
    }
    return newText.trim();
  }

  function closeIframe(id){
    $('.iframe-container[id='+id+']').fadeOut(200, function(){
		window.location.reload();
    });
  }

  $('.process-request-btn').on('click', function(){
    $('.iframe-container').fadeOut(200);
    var src = $(this).attr('data-src');
    var overlay = $(this).attr('data-overlay');
    var id = $(this).attr('data-req-num');

    var $iframeContainer = $('<div>', {
      'class': 'iframe-container',
      id: id,
      style: 'top: 75px; left: 25px; position: absolute; padding: 25px 0 0; background-color: darkgrey; border: 1px solid grey; box-shadow: 2px 2px 10px; width: 850px; height: 750px;'
    }),
    $iframe =  $('<iframe>',{
      'class': 'todo-iframe',
      id: 'my-iframe',
      style: 'width: 100%; height: 100%',
      src: src
    }),
    $info = $('<div>',{
      'class': 'iframe-request-info',
      style: 'position: absolute; top: 3px; left: 10px; font-size: 19px;',
      text: 'REQ #'+$(this).attr('data-req-num')+' - "'+$(this).attr('data-req-type')+'" request from '+$(this).attr('data-req-by')
    }),
    $close = $('<div>',{
      'class': 'trim-close-btn',
      style: 'position: absolute; top: 0px; right: 10px; font-size: 22px; cursor: pointer',
      text: 'x'
    }).bind('click', function(){
      closeIframe(id);
    });
    //append elements
    $iframeContainer.append($iframe).append($info).append($close);
    $('body').append($iframeContainer);
    $('.iframe-container').draggable().resizable();

    $('.todo-iframe').on('load', function () { //The function below executes once the iframe has finished loading
      // $(this).contents().find('body').append('<scr' + 'ipt>$( document ).ajaxSuccess(function(event, xhr, settings) {console.log(settings.url);});</scr' + 'ipt>');
      var $iframe = $('.todo-iframe').contents().find('body'),
          $closeBtn = '.ui-dialog .ui-dialog-titlebar-close',
          $cancelBtn = '.ui-dialog-buttonset .ui-button:first',
          $acceptBtn = '.ui-dialog-buttonset .ui-button:last',
          $cancelCopyBtn = '.cancel-copy',
          $createProjCancelBtn = '.create-project-cancel-btn';
      var $overlay = $('<div>', {
        'class': 'iframe-overlay'
      });
      $iframe.addClass('iframe').attr('id',id);
      if(overlay == 1){
        $iframe.append($overlay);
      }
      $iframe.on('mouseover', function(e){
        var id = $iframe.attr('id');
        $iframe.find($closeBtn).bind('click', function(){closeIframe(id)});
        $iframe.find($cancelBtn).bind('click', function(){closeIframe(id)});
        $iframe.find($cancelCopyBtn).bind('click', function(){closeIframe(id)});
        $iframe.find($createProjCancelBtn).bind('click', function(){closeIframe(id)});
      });

    });

  });//end of process-request-btn event

  $('.expand-btn').on('click', function(e){
    var that = this;
    // $($('.more-info-container')[0]).height(); from here!!
    if($(this).hasClass('open')){
      $(this).closest('.request-container').velocity({height:30});
      setTimeout(function(){
        $(that).removeClass('open');
      },100);
    }else{
      $(this).closest('.request-container').velocity({height:156});
      setTimeout(function(){
        $(that).addClass('open');
      },100);
    }
  });

  function cancelDelete(){
    $('.delete-alert, .overlay').fadeOut(200,function(){
      $('.delete-alert, .overlay').remove();
      $('body').removeClass('stop-scrolling');
    });
  }

  function confirmArchive(id){
    $.post(app_path_webroot+'ToDoList/todo_list_ajax.php',
    { action: 'archive-todo', id: id, status: 'archived' },
    function(data){
      if (data == '1'){
        location.reload();
      }
    });
  }

  $('.buttons-wrapper .delete-btn').on('click', function(){
    var id = $(this).closest('.buttons-wrapper').attr('data-id');
    var $alert =  $('<div>',{
      'class': 'delete-alert'
    }),
    $text =  $('<p>',{
      'class': 'text',
      text: 'Are you sure you want to permanently move this request to the Archived Requests list?',
      style: 'font-size: 14px'
    }),
    $overlay =  $('<div>',{
      'class': 'overlay'
    }),
    $confirm =  $('<button>',{
      'class': 'todo-confirm-btn',
      style: 'margin: 10px 20px 0 60px;',
      text: 'Yes'
    }).bind('click', function(){
      confirmArchive(id);
    }),
    $cancel =  $('<button>',{
      'class': 'cancel-btn',
      text: 'Cancel'
    }).bind('click', cancelDelete),
    $close = $('<div>',{
      'class': 'close-btn',
      text: 'x'
    }).bind('click', cancelDelete);
    $alert.append($close).append($text).append($confirm).append($cancel);
    $('body').append($overlay).addClass('stop-scrolling').append($alert);
  });

  function checkIfMultipleSelection(){
    if($( '.buttons-wrapper input').is(":checked")){
      return 1;
    }else{
      return 0;
    }
  }

  $('.buttons-wrapper .ignore-btn').on('click', function(){
    var id = $(this).closest('.buttons-wrapper').attr('data-id');
    var status = $(this).attr('data-status');
    $.post(app_path_webroot+'ToDoList/todo_list_ajax.php',
    { action: 'ignore-todo', id: id, status: status },
    function(data){
      if (data == '1'){
        location.reload();
      }
    });
  });

  //write comment
  function writeComment(id, comment){
    $.post(app_path_webroot+'ToDoList/todo_list_ajax.php',
    { action: 'write-comment', id: id, comment: comment },
    function(data){
      if (data == '1'){
        $('.write-comment-dialog .todo-confirm-btn').text('Success!');
        setTimeout(function(){
          location.reload();
        },1000);
      }
    });
  }

  function areYouSure(callBack){
    $('.yes-btn, .no-btn').remove();
    $('.erase-link').text('Are you sure?');
    var $yes =  $('<a>',{
  		'class': 'yes-btn',
      text: 'Yes'
  	}).bind('click', function(){
  		callBack('yes');
  	}),
    $no =  $('<a>',{
  		'class': 'no-btn',
      text: 'No'
  	}).bind('click', function(){
  		callBack('no');
  	});
    $('.ui-dialog-content').append($yes).append($no);
  }

  function eraseComment(){
    areYouSure(function(res){
      res === 'yes' ? $('.comment-text').val('').focus() : $('.comment-text').focus();
      $('.erase-link').text('Clear comment');
      $('.yes-btn, .no-btn').remove();
    });
  }

  function openWriteComment(id){
    var commentText = $('.todo-comment[data-id='+id+']').attr('data-comment');
    commentText = (commentText === 'None' ? '' : commentText);
  	simpleDialog('Write or edit a comment for this request.','Comment','todoCommentDialog',400);
  	var $input =  $('<textarea>',{
  		'class': 'comment-text',
      text: commentText
  	}),
    $erase =  $('<a>',{
  		'class': 'erase-link',
  		text: 'Clear comment'
  	}).bind('click', function(){
  		eraseComment();
  	}),
  	$confirm =  $('<button>',{
  		'class': 'todo-confirm-btn ui-button ui-corner-all ui-widget',
        'type': 'button',
  		text: 'Submit'
  	}).bind('click', function(){
  		writeComment(id, $($input).val());
  	});
  	$('body').find('.ui-dialog').addClass('write-comment-dialog');
    $('#todoCommentDialog').parent().find('.ui-dialog-buttonset').append($confirm);
  	$('.ui-dialog-content').append($input).append($erase);
  	$input.focus();
  }

  $('.buttons-wrapper .comment-btn, .balloon-icon, .todo-comment').on('click', function(){
    var id = $(this).closest('.buttons-wrapper').attr('data-id');
    id = ($(this).attr('class') != 'action-btn comment-btn' ? $(this).attr('data-id') : id);
    openWriteComment(id);
  });

  //tooltip functionality
  function showTooltip(e){
    var $button = $(e.target);
    var pos = $button.offset();
    var text = $button.attr('data-tooltip');
    var $tooltip =  $('<div>',{
      'class': 'button-tooltip',
      text: text,
      style: 'top: '+(pos.top-25)+'px;left: '+pos.left+'px;'
    });
    $('body').append($tooltip);
  }

  var timer;
  var delay = 1500;
  $('.buttons-wrapper button, .request-container .project-title, .request-container .mailto, .request-container .username-mailto, .request-container .comment-show').hover(function(e){
    // on mouse in, start a timeout
    timer = setTimeout(function() {
        showTooltip(e);
    }, delay);
  }, function() {
    // on mouse out, cancel the timer
    clearTimeout(timer);
    $('.button-tooltip').remove();
  });

  //sorting request
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
  $('.todo-label').on('click', function(e){
    var direction = $(this).attr('data-direction');
    var sort = $(this).attr('data-sort');
    window.location.href = app_path_webroot+'ToDoList/index.php?sort='+sort+'&page='+(getUrlParameter('page')==null?'1':getUrlParameter('page'))+'&direction='+direction;
  });

  function assignSortArrow(){
    var $sortArrow = $('<img>',{
      'class': 'sort-arrow',
      src: app_path_images+'bullet_arrow_up.png',
    });
    var sort = getUrlParameter('sort');
    if(sort != undefined){
      $('.labels-container .todo-label[data-sort='+sort+']').append($sortArrow);
    }else{
      //default sort
      $('.labels-container .todo-req-time').append($sortArrow);
    }
  }

  //assign sort arrow on page load
  assignSortArrow();

  function checkSortingDirection(){
    var lastDirection = getUrlParameter('direction');
    if(lastDirection != undefined){
      var direction = $('.todo-label .sort-arrow').parent().attr('data-direction');
      //assign correct arrow depending on last direction
      if(lastDirection === 'asc'){
        $('.todo-label .sort-arrow').attr('src', app_path_images+'bullet_arrow_up.png');
      }else{
        $('.todo-label .sort-arrow').attr('src', app_path_images+'bullet_arrow_down.png');
      }
      //check if needs to change direction
      if(direction === lastDirection){
        direction = (direction === 'asc' ? 'desc' : 'asc');
        $('.todo-label .sort-arrow').parent().attr('data-direction', direction);
      }
    }
  }

  //check what direction to assign to data-direction attribute
  checkSortingDirection();

  //setup cookie for collapsible sections
  function updateCollapsibleSections(collapseInfo){
    var infoArray = collapseInfo.split(",");
    var $arrowUp = $('.pending-title .collapse-arrow-up');
    for (var i = 0; i < $arrowUp.length; i++) {
      //change it only if need to be close
      if(infoArray[i] === 'close'){
          $($arrowUp[i]).addClass('hidden').removeClass('show');
          $($arrowUp[i]).next().addClass('show').removeClass('hidden');
          $($arrowUp[i]).parent().next().next().css({height:0});
      }
    }
  }

  function setCookie(name, value, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+d.toUTCString();
    document.cookie = name + "=" + value + "; " + expires;
  }

  function checkCookie() {
    var collapseInfo = getCookie('collapse-info');
    if (collapseInfo != "") {
      updateCollapsibleSections(collapseInfo);
    } else {
      setCookie('collapse-info', 'open,open', 3);
    }
  }

  checkCookie();

  //collapsible
  function getActualCollapsibleStatus(){
    var $arrowUp = $('.pending-title .collapse-arrow-up');
    var collapsibleStatus = ['-','-'];
    for (var i = 0; i < $arrowUp.length; i++) {
      if( $($arrowUp[i]).hasClass('hidden') ){
        collapsibleStatus[i] = 'close';
      }else{
        collapsibleStatus[i] = 'open';
      }
    }
    collapsibleStatus = collapsibleStatus.toString();
    return collapsibleStatus;
  }

  $('.pending-title .collapse-section-icon').on('click', function(e){
    var childClass = $(this).children().attr('class');
    if(childClass === 'collapse-up-arrow'){
      $(this).addClass('hidden').removeClass('show');
      $(this).next().addClass('show').removeClass('hidden');
      $(this).parent().next().next().velocity({height:0});
    }else{
      $(this).addClass('hidden').removeClass('show');
      $(this).prev().addClass('show').removeClass('hidden');
      var $list = $(this).parent().next().next();
      $list.velocity({height:($list.children().length*30)+'px'});
      setTimeout(function(){
        $list.css('height','initial');
      }, 500);
    }
    var collapsibleStatus = getActualCollapsibleStatus();
    setCookie('collapse-info', collapsibleStatus, 3);
  });

  //visual alert if too many requests
  function checkIfTooManyReq(){
    var $reqNumbers = $('.number-req-by-status');
    for (var i = 0; i < $reqNumbers.length; i++) {
      var text = $($reqNumbers[i]).text().replace('(','').replace(')','');
      if( (text*1) >= 1 ){
        $($reqNumbers[i]).css('color','#A00000');
      }
    }
  }

  checkIfTooManyReq();

  //Toggle activate email notifications
  $('.toggle-notifications-cm').on('click', function(e){
    var checked = this.checked;
    $.post(app_path_webroot+'ToDoList/todo_list_ajax.php',
    { action: 'toggle-notifications', checked: checked },
    function(data){
      if (data === '1'){
        var $saved = $('<div>',{
          'class': 'saved-notifications',
          text: 'Saved!'
        });
        $('.toggle-email-notifications-wrapper').append($saved);
        setTimeout(function(){
          $saved.velocity('transition.slideUpOut');
        }, 700);
      }
    });
  })

});
