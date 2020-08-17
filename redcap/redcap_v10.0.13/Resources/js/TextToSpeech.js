var texttospeech_js_loaded = 1;

$(function(){
	// Add "speak" icon to all viable elements on the survey page
	addSpeakIconsToSurvey();
});

// Stop audio by providing the jQuery object of the icon that was originally clicked
function stopAudio(iconob, removeAudioObject) {
	if (removeAudioObject == null) removeAudioObject = true;
	if (removeAudioObject) $('#'+iconob.attr('stop')).remove();
	iconob.removeAttr('stop').attr('src', app_path_images+'speaker.gif');
}

// Take a jQuery object and speak its HTML contents via playAudio()
function playAudioObject(ob,iconob,event) {
	// Do not trigger on tab
	if (event.keyCode == 9) return;
	// Prevent mouseclick from [un]selecting the choice
	event.stopPropagation();
	// If using IE8 or lower, give user notice that this functionality doesn't work
	if (isIE && IEv < 9) {
		simpleDialog('We\'re sorry, but your web browser does not support the text-to-speech feature. You might consider upgrading your web browser to a more recent version.', 'ERROR: Browser not compatible');
		return;
	}
	// IBM Watson TTS cannot yet work on iOS or mobile devices for strange reasons due to WAV headers
	else if (isMobileDevice || isIOS || (/safari/.test(agt) && !/chrome/.test(agt)))
	{
		simpleDialog('We\'re sorry, but the text-to-speech feature is not currently supported on mobile devices, iOS, or in the Safari web browser. Our apologies for this inconvenience.', 'ERROR: Browser not compatible');
		return;
	}
	// Get the object of the img tag itself that was clicked
	var iconob = $(iconob);
	// If the icon clicked has a "stop" attribute, then we should remove the ID of "stop" from the DOM
	if (iconob.attr('stop') != null) {
		stopAudio(iconob);
		return;
	}
	// Change icon to pause icon
	iconob.attr('src', app_path_images+'pause.gif');
	// Is a section header?
	var isField = (ob.attr('id') != null && ob.attr('id').slice(-3) == '-tr');
	var isSectionHeader = (isField && ob.attr('id').slice(-6) == '-sh-tr');
	// Do green highlight on row (unless it's a section header
	if (isField && !isSectionHeader) {
		doGreenHighlight(ob);
	} else {
		$('form#form #questiontable tr td.greenhighlight').removeClass('greenhighlight');
	}
	// Get html and remove any text inside hidden html tags
	var t = ob.clone();
	t.hide().appendTo(document.body);
	if (isField) {
		// Remove MC choice options because they will be done separately.
		// Remove text from tags/classes that we don't want to include.
		// Remove piped field data for identifier fields.
		t.find('.autosug-instr, select, .piping_receiver_identifier, td a, .df, button, input, textarea, .sldrmsg, .questionnum, .questionnummatrix, audio, .choicevert, .choicehoriz, .sliderlabels, .note, .requiredlabel').remove();
	}
	var text = t.html();
	t.remove();
	// Clean text and remove all html tags
	text = strip_tags(text.replace(/&nbsp;/g, ' ').replace(/></g, '> <')).replace(/(?:\r\n|\r|\n)/g, ' ');
	// Speak text
	playAudio(text, iconob);
}

// Perform text-to-speech, and play the audio in the user's browser
function playAudio(text, iconob) {
	var delim = '|--RC--|';
	var thistext;
	var phrases = new Array;
	var phrases_length = new Array;
	var phrase_char_limit = 300; // Send the text in batches by splitting at a given length
	if (isIE) phrase_char_limit = 30000; // Due to incompatibility of IE with WAV, only do phrases in IE as single chunk
	var p = 0;
	// Make sure text ends with punction in order for it to be parsed correctly
	text = trim(text);
	if (text == "") {
		stopAudio(iconob, false);
		iconob.remove();
		return;
	}
	var lastletter = text.slice(-1);
	if (lastletter != '.' && lastletter != '!' && lastletter != '?') {
		text += '.';
	}
	// Parse into sentences and loop through them
	var sentences = text.match( /[^\.!\?]+[\.!\?]+/g );
	// Loop through sentences and split at X-character mark
	for (var i=0; i<sentences.length; i++) {
		// Split sentence if longer than phrase_char_limit characters
		var thissentence = wordwrap(trim(sentences[i]), phrase_char_limit, delim).split(delim);
		for (var k=0; k<thissentence.length; k++) {
			// Add sentence fragment to array of phrases
			phrases_length[p] = thissentence[k].length;
			phrases[p++] = thissentence[k];
		}
	}
	// Try to consolidate any short phrases to produce the smallest number of web requests
	do {
		var phrases2 = new Array;
		var loops = phrases.length;
		var p = 0;
		var mergedSentences = 0;
		for (var i=0; i<loops; i++) {
			var combine_them = false;
			if (i < loops-1) {
				var combinedsentence = phrases[i]+" "+phrases[i+1];
				combine_them = (combinedsentence.length <= phrase_char_limit);
			}
			if (combine_them) {
				phrases2[p++] = combinedsentence;
				mergedSentences++;
				i++;
			} else {
				phrases2[p++] = phrases[i];
			}
		}
		phrases = phrases2;
	} while (mergedSentences > 0);
	// Loop through all sentences/phrases and convert to URLs to play
	var urls = new Array();
	for (var i=0; i<phrases.length; i++) {
		thistext = phrases[i];
		// Get URL to call
		if (page == 'surveys/index.php') {
			var url = dirname(app_path_webroot.substring(0,app_path_webroot.length-1))+'/surveys/index.php?s='+getParameterByName('s')+'&__passthru='+encodeURIComponent('Surveys/speak.php')+'&q='+encodeURIComponent(thistext);
		} else {
			var url = app_path_webroot+'Surveys/speak.php?pid='+pid+'&q='+encodeURIComponent(thistext);
		}
		// Add URL to array
		urls[i] = url;
	}
	// Create id for each audio tag to place them in the audio container div
	var audio_item_id = "tts-item-"+Math.floor(Math.random()*10000000000000000);
	$('body').append('<div style="'+(isIE?'':'display:none;')+'" id="'+audio_item_id+'"></div>'); // Don't hide the div for IE or else it won't play
	// Add the "stop" attribute to the speaker icon
	iconob.attr('stop', audio_item_id);
	// Play audio: Loop through all urls and play their audio
	// Add MP3 URL as new Audio tag in the container
	var num_urls = urls.length;
	var sub_item_num_array = new Array();
	var url, sub_item_num, audio_sub_item_id, subitem_id, id_parts, next_id, audioElement;
	for (var i=0; i<num_urls; i++) {
		// Add loop num to array (to deal with setTimeout issues)
		sub_item_num_array[i] = i;
		// Put slight lag on the audio tags so that it doesn't appear as a bunch of simultaneous requests to the server and overwhelm it
		setTimeout(function(){
			// Get the URL
			url = urls.shift();
			// Get subitem id
			sub_item_num = sub_item_num_array.shift();
			audio_sub_item_id = audio_item_id+'-'+sub_item_num;
			if (isIE) {
				// Use embed tag for IE for compatibility
				audioElement = document.createElement("embed");
				audioElement.setAttribute("autostart", true);
				audioElement.setAttribute("enablejavascript", true);
				audioElement.setAttribute("loop", false);
				audioElement.setAttribute("width", "0");
				audioElement.setAttribute("height", "0");
				audioElement.setAttribute("id", audio_sub_item_id);
				audioElement.src = url;
				document.getElementById(audio_item_id).appendChild(audioElement);
				// Since we can't detect when embed stops, just do it after 5 seconds
				setTimeout(function(){
					stopAudio(iconob, false);
				},5000);
			} else {
				// Add audio tag to page (use regular JS since jQuery strangely causes a double HTTP request when adding audio element to DOM)
				audioElement = document.createElement("audio");
				audioElement.setAttribute("controls", "controls");
				audioElement.setAttribute("id", audio_sub_item_id);
				document.getElementById(audio_item_id).appendChild(audioElement);				
				sourceElement = document.createElement("source");
				sourceElement.src = url;
				document.getElementById(audio_sub_item_id).appendChild(sourceElement);
			}
			if (!isIE) {
				// Bind event for when audio gets to end
				$('#'+audio_sub_item_id).bind('ended', function() {
					subitem_id = $(this).attr('id');
					id_parts = subitem_id.split('-');
					next_id = audio_item_id+'-'+((id_parts[3]*1)+1);
					$(this).remove();
					if ($('#'+next_id).length) {
						// Another audio tag exists, so play it
						$('#'+next_id).trigger('play');
					} else {
						// No more audio tags, so remove the container to stop
						$('#'+audio_item_id).remove();
						// Set the icon img back to original
						stopAudio(iconob);
					}
				});
				// Play the first item
				if (sub_item_num == 0) $('#'+audio_sub_item_id).trigger('play');
			}
		},i*200);
	}
}

// Add "speak" icon to all viable elements on the survey page
function addSpeakIconsToSurvey(reloadSurveyTitleInstructions) {
	// Are we just reloading the icons for survey title and instructions?
	if (reloadSurveyTitleInstructions == null) reloadSurveyTitleInstructions = false;
	// Set first elements to add icons to
	var survey_obs = (reloadSurveyTitleInstructions) ? $('#surveyinstructions, #surveytitle') : $('#survey-login-instructions, #return_code_form_instructions, #return_code_completed_survey_div, #return_instructions, #codePopupReminderTextCode, #codePopupReminderText, #surveyacknowledgment, #surveyinstructions, #surveytitle');
	// Add "speak" icon for survey title, instructions, and acknowlegement
	survey_obs.each(function(event){
		// Get position of this element
		var ob = $(this);
		var pos = ob.position();
		var top = pos.top + 3;
		var left = pos.left - 40;
		// Get id
		var id = ob.attr('id');
		if (id == null) {
			// Survey title only
			top = top + 14;
			id = 'surveytitle td:first';
		}
		// Non-mobile only: Make icons more transparent
		var icon_class = "spkrplay";
		if (!isMobileDevice) {
			var icon_class = "spkrplay opacity50";
			// When mouseover area, make the image less transparent
			ob.mouseover(function(){
				$('.spkrplay', this).removeClass('opacity50');
			});
			ob.mouseout(function(){
				$('.spkrplay', this).addClass('opacity50');
			});
		}
		// Build icon's html
		if (ob.text() != "") {
			if (id == 'return_code_completed_survey_div' || id == 'codePopupReminderTextCode' || id == 'codePopupReminderText' || id == 'survey-login-instructions') {
				var btn = '<img src="'+app_path_images+'speaker.gif" class="'+icon_class+'" alt="Speak the displayed text." tabindex="0"  aria-hidden="true" '
						+ 'onkeydown="if(event.keyCode!=13&&event.keyCode!=9)return false;playAudioObject($(\'#'+id+'\'),this,event);" onclick="playAudioObject($(\'#'+id+'\'),this,event);">';
				ob.prepend(btn);
			} else if (isMobileDevice) {
				var btn = '<img src="'+app_path_images+'speaker.gif" class="'+icon_class+'" style="margin-right:5px;" alt="Speak the displayed text." tabindex="0" aria-hidden="true" '
						+ 'onkeydown="if(event.keyCode!=13&&event.keyCode!=9)return false;playAudioObject($(\'#'+id+'\'),this,event);" onclick="playAudioObject($(\'#'+id+'\'),this,event);">';
				ob.children().eq(0).prepend(btn);
			} else {
				var btn = '<img src="'+app_path_images+'speaker.gif" class="'+icon_class+'" style="position:absolute;top:'+top+'px;left:'+left+'px;" alt="Speak the displayed text." tabindex="0" aria-hidden="true" '
						+ 'onkeydown="if(event.keyCode!=13&&event.keyCode!=9)return false;playAudioObject($(\'#'+id+'\'),this,event);" onclick="playAudioObject($(\'#'+id+'\'),this,event);">';
				ob.append(btn);
			}
		}
	});

	// Stop here if just doing title and instructions
	if (reloadSurveyTitleInstructions) return;

	// Add "speak" icon for each question and each multiple choice option
	$('.sliderlabels-ne, .survey-login-error-msg, #survey_auth_form table tr .labelrc, .popup-contents, #questiontable tr, #questiontable .note, '
	 +'#questiontable .fileuploadlink, #questiontable select.x-form-field, #questiontable .choicevert, #questiontable .choicehoriz').each(function(event){
		// Get position of this element
		var ob = $(this);
		// Get id
		var id = ob.attr('id');
		var isField = (id != null && id.slice(-3) == '-tr');
		var targetTag = ob.prop('tagName').toLowerCase();
		// Ignore table rows that aren't fields
		if (targetTag == 'tr' && !isField) return;
		// Ignore submit buttons row
		if (id == '__SUBMITBUTTONS__-tr') return;
		var isSectionHeader = (isField && id.slice(-6) == '-sh-tr');
		// Non-mobile only: Make icons more transparent
		var icon_class = "spkrplay";
		if (!isMobileDevice) {
			var icon_class = "spkrplay opacity50";
			// When mouseover area, make the image less transparent
			ob.mouseover(function(){
				$('.spkrplay', this).removeClass('opacity50');
			});
			ob.mouseout(function(){
				$('.spkrplay', this).addClass('opacity50');
			});
		}
		// Build icon's html
		var firstCol = ob.find('td:first');
		if (isSectionHeader) {
			var style = 'vertical-align:middle;margin-right:5px;width:18px;height:18px;';
		} else if (!isField) {
			var style = 'margin-left:5px;width:14px;height:14px;vertical-align:middle;';
		} else {
			var style = 'display:block;' + (firstCol.text() == '' ? '' : 'margin-top:6px;');
		}
		if (isField) {
			// Field rows or Section Header rows
			var btn = '<img src="'+app_path_images+'speaker.gif" class="'+icon_class+'" style="'+style+'" alt="Speak the displayed text." tabindex="0" aria-hidden="true" '
					+ 'onkeydown="if(event.keyCode!=13&&event.keyCode!=9)return false;playAudioObject($(\'#'+id+'\'),this,event);" onclick="playAudioObject($(\'#'+id+'\'),this,event);">';
			if (isSectionHeader) {
				firstCol.prepend(btn);
			} else {
				firstCol.append(btn);
			}
		} else {
			// Non-fields and MC options
			if (id == null) {
				var id = "tts-"+Math.floor(Math.random()*10000000000000000);
				ob.attr('id', id);
			}
			var btn = '<img src="'+app_path_images+'speaker.gif" class="'+icon_class+'" style="'+style+'" alt="Speak the displayed text." tabindex="0" aria-hidden="true" '
					+ 'onkeydown="if(event.keyCode!=13&&event.keyCode!=9)return false;playAudioObject($(\'#'+id+'\'),this,event);" onclick="playAudioObject($(\'#'+id+'\'),this,event);">';
			if (targetTag == 'select') {
				if (ob.hasClass('rc-autocomplete')) {
					// If has auto-complete enabled, then place icon after auto-complete components
					$('button.rc-autocomplete:first', ob.parents('td:first')).after(btn);
				} else {
					ob.after(btn);
				}
			} else {
				ob.append(btn);
			}
		}
	});
}

function wordwrap(str, int_width, str_break, cut) {
  //  discuss at: http://phpjs.org/functions/wordwrap/
  // original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
  // improved by: Nick Callen
  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // improved by: Sakimori
  //  revised by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
  // bugfixed by: Michael Grier
  // bugfixed by: Feras ALHAEK
  //   example 1: wordwrap('Kevin van Zonneveld', 6, '|', true);
  //   returns 1: 'Kevin |van |Zonnev|eld'
  //   example 2: wordwrap('The quick brown fox jumped over the lazy dog.', 20, '<br />\n');
  //   returns 2: 'The quick brown fox <br />\njumped over the lazy<br />\n dog.'
  //   example 3: wordwrap('Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.');
  //   returns 3: 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod \ntempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim \nveniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea \ncommodo consequat.'

  var m = ((arguments.length >= 2) ? arguments[1] : 75);
  var b = ((arguments.length >= 3) ? arguments[2] : '\n');
  var c = ((arguments.length >= 4) ? arguments[3] : false);

  var i, j, l, s, r;

  str += '';

  if (m < 1) {
    return str;
  }

  for (i = -1, l = (r = str.split(/\r\n|\n|\r/)).length; ++i < l; r[i] += s) {
    for (s = r[i], r[i] = ''; s.length > m; r[i] += s.slice(0, j) + ((s = s.slice(j)).length ? b : '')) {
      j = c == 2 || (j = s.slice(0, m + 1).match(/\S*(\s)?$/))[1] ? m : j.input.length - j[0].length || c == 1 && m || j.input.length + (j = s.slice(m).match(/^\S*/))[0].length;
    }
  }

  return r.join('\n');
}