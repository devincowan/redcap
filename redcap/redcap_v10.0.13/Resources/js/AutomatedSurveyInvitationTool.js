(function($,window,document)
{
    $(function(){
        AutomatedSurveyInvitationTool.init('#ASI-container');
    });

	var app = {

        // selectors
        remote_action_prefix: 'AutomatedSurveyInvitation',
        // DOM elements
        elements: {},
        container: null,
        // data
        data: {
            ASI: [],
        },
        translations: {},
        
        init: function(selector)
        {
            if(typeof langASI==='object') this.translations = langASI; // set translations dicrtionary
            var container = document.querySelector(selector);
            if(!container) return;
            this.container = container;
        },

        /**
         * get list of Automated Survey Invitations (ASI)
         */
        _getASIList: function()
        {
            var data = {
                "AutomatedSurveyInvitation-listASI": true,
            };

            return this._ajaxRequest('GET',data);
        },

        /**
         * get list of Automated Survey Invitations (ASI)
         */
        _getSurveyEnabledFormsList: function()
        {
            var data = {
                "AutomatedSurveyInvitation-listSurveyEnabledForms": true,
            };

            return this._ajaxRequest('GET',data);
        },

        /**
         * create the upload form and add it to the container
         */
        _createUploadForm: function()
        {
            // upload form
            var uploadForm = this._getUploadForm();
            this.elements.uploadForm = uploadForm;
            this.container.appendChild(uploadForm);
        },


        /**
         * open the "select file" dialog box
         */
        importFile: function()
        {
            if(!this.elements.uploadForm)
                this._createUploadForm();

            var fileInput = this.elements.uploadForm.querySelector('input[type="file"]');
            fileInput.click();
        },

        /**
         * import data from the selected file in this.elements.uploadForm
         */
        import: function()
        {
            // IE compatibility
            if(typeof window.FormData === 'undefined') {
                this.elements.uploadForm.submit();
            }

            var data = new FormData(this.elements.uploadForm);
            var upload_options = {
                processData: false,
                contentType: false,
            }
            this._ajaxRequest('POST',data, upload_options)
            .done(function(response){
                if (response.error) {
                    simpleDialog(response.message, null, null, null, 'window.location.reload();');
                } else {
                    var message = langASI.asi_upload1 + '\n\n';
                    var imported_ASI = response.data.map(function (element, index) {
                        var ids_string = [(index + 1) + ")", , '"<b>' + element.survey_title + '</b>"', '-', element.event_title].join(' ');
                        return ids_string;
                    })
                    message += imported_ASI.join("\n");
                    simpleDialog(nl2br(message), langASI.asi_upload2, null, null, 'window.location.reload();');
                }
            }).fail(function(response){
                var message = response.message || 'invalid file format';
                simpleDialog(nl2br(message));
            });
        },

        /**
         * submit the export request
         */
        export: function()
        {
            location.search += (location.search=="") ? "?AutomatedSurveyInvitation-export" : "&AutomatedSurveyInvitation-export";
        },

        showExportHelp: function()
        {
            simpleDialog(langASI.export_help_description,langASI.import_button,'asiImportHelpDlg',650,null,langASI.import_button1,
                "AutomatedSurveyInvitationTool.importFile()",langASI.import_button2);
            fitDialog($('#asiImportHelpDlg'));
            $('#asiImportHelpDlg').dialog().next().find('button:last').addClass('ui-priority-primary').prepend('<img src="'+app_path_images+'xls.gif"> ');
        },

        /**
         * submit the clone request
         * @param {object} from 
         * @param {object} to 
         */
        clone: function(from, to)
		{
            var data = {
                "AutomatedSurveyInvitation-clone": true,
                from: JSON.stringify(from),
                to: JSON.stringify(to)
            };

            return this._ajaxRequest('POST',data)
            .done(function(response){
                var message = langASI.asi_copied+'\n\n';
                message += ['<b>'+langASI.from, '</b>"'+response.from.survey_title+'"', '-', response.from.event_title].join(' ');
                message += '\n\n';
                message += '<b>'+langASI.to+'</b> \n';
                var cloned_ASI = response.to.map(function(element, index){
                    var ids_string = [(index+1)+")", '"'+element.survey_title+'"', '-', element.event_title].join(' ');
                    return ids_string;
                })
                message += cloned_ASI.join("\n");
                simpleDialog(nl2br(message),langASI.asi_copied,600,null,'window.location.reload();',null);
            });
        },

        /**
         * set the survey_id and event_id of the clonable form
         * show the clone dialog box
         * @param {int} survey_id 
         * @param {int} event_id
         */
        showCloneDialog: function(survey_id, event_id)
		{
            var self = this; // to maintain the scope inside the event listeners

            // helper function to update the from params
            var setFromParams = function() {
                var from_input = self.elements.cloneModal.querySelector('input[name="asi_from"]');
                from_input.setAttribute('data-survey-id', survey_id);
                from_input.setAttribute('data-event-id', event_id);
            };
            // helper function to open the dialog
            var show = function() {
                setFromParams();

                $(self.elements.cloneModal).dialog({
                    dialogClass: "no-close",
                    width: 700,
                    height: 500,
                    modal: true,
                    buttons: [
                        {
                            text: langASI.import_button1,
                            click: function(e) {
                                $(this).dialog('destroy');
                            }
                        },
                        {
                            text: langASI.asi_clone1,
                            click: function(e) {
                                var params = self._getParamsFromModal();
                                self.clone(params.from,params.to);
                            }
                        }
                    ],
                    open: function() {
                        $buttonPane = $(this).next();
                        $buttonPane.find('button:last').addClass('ui-priority-primary');
                    }
                });

                var survey_id = $('input[name="asi_from"]').attr('data-survey-id');
                var event_id = $('input[name="asi_from"]').attr('data-event-id');

                // Remove the survey we just saved from the list
                var surveyToClone = $('#asiLabel'+survey_id+'-'+event_id);
                $('#asiCopyFieldFromText').append( surveyToClone.html() );
                surveyToClone.parent().remove();
            };

            if(!this.elements.cloneModal)
            {
                // first call: get the data and create the clone dialog
                this._setModal().done(show);
            }else
            {
                show();
            }
        },

        /**
         * extract the clone params from the modal
         * 
         * @param {object} element DOM element
         */
        _getParamsFromModal: function()
		{
            // get from params
            var asi_from = this.elements.cloneModal.querySelector('[name="asi_from"]');
            var from_params = {
                survey_id: asi_from.getAttribute('data-survey-id'),
                event_id: asi_from.getAttribute('data-event-id'),
            };
            
            // get to params
            var asi_to = this.elements.cloneModal.querySelectorAll('[name="asi_to"]:checked');
            var to_params = [];
            for (var i = 0; i < asi_to.length; i++) {
                var item = asi_to[i];
                params = {
                    survey_id: item.getAttribute('data-survey-id'),
                    event_id: item.getAttribute('data-event-id'),
                };
                to_params.push(params);
            }

            var params = {
                from: from_params,
                to: to_params,
            };

            return params;
        },

        /**
         * load all data (ASI and Survey Enabled Instruments)
         * then create the modal
         */
        _setModal: function()
        {
            var self = this;
            return $.when(
                this._getASIList(),
                this._getSurveyEnabledFormsList()
            ).done(function(response1, response2){
                var data = {
                    ASI: response1.data,
                    SurveyEnabledForms: response2.data,
                }
                self._createModal(data);
            }).fail(function(response){
                alert(JSON.stringify(response));
            });
        },

        /**
         * create the modal dialog and register the event for the ok button
         */
        _createModal: function(data)
        {
            var base_data = {
                title: langASI.asi_clone_title,
                description: this.translations.clone_description || 'Clone to:',
                close_button: 'cancel',
                ok_button: 'clone',
                selectAll: this.translations.selectAll || 'select all',
                deselectAll: this.translations.deselectAll || 'deselect all'
            };
            var context = $.extend(base_data, data);

            // register a partial template to select/deselect all items
            var partialSelectTemplate = Handlebars.compile(selectTemplate);
            Handlebars.registerPartial('select-deselect', partialSelectTemplate);

            // compile and output the main template
            var mainTemplate = Handlebars.compile(dialogTemplate);
            var output = mainTemplate(context);
            var modalContainer = document.createElement('section'); // temporary container
            modalContainer.innerHTML = output;
            var modal = modalContainer.firstChild;
            this.elements.cloneModal = modal; // add the modal to elements for easy reference
            $(modal).hide();// hide the modal
            document.body.appendChild(modal); // add the modal to the DOM
        },

        /**
         * create a form to upload a file
         * the form includes a file field, the csrf token and the action that must be performed
         */
        _getUploadForm: function()
        {
            //create the form
            var form = document.createElement('form');
            form.setAttribute('action', location.href);
            form.setAttribute('method', 'POST');
            form.setAttribute('enctype', 'multipart/form-data');
            // csrftoken
            var csrf_token_input = document.createElement('input');
            csrf_token_input.setAttribute('type', 'hidden');
            csrf_token_input.setAttribute('name', 'redcap_csrf_token');
            csrf_token_input.setAttribute('value', this._get_csrf_token());
            form.appendChild(csrf_token_input);
            // set the import action
            var action_input = document.createElement('input');
            action_input.setAttribute('type', 'hidden');
            action_input.setAttribute('name', 'AutomatedSurveyInvitation-import');
            form.appendChild(action_input);
            // append the file input field
            var fileInput = this._getFileInput();
            form.appendChild(fileInput);
            // make form invisible
            this._setElementInvisible(form);
            return form;
        },

        /**
         * make a DOM element invisible
         * 
         * @param {object} element DOM element
         */
        _setElementInvisible: function(element)
        {
            // set it's style to be invisible
            element.style.opacity = 0;
            element.style.position = 'absolute';
            element.style.width = 0.1;
            element.style.height = 0.1;
            element.style.pointerEvents = 'none';
        },
        
        /**
         * create a file input element and register it's event handler
         */
        _getFileInput: function()
        {
            var fileInput = document.createElement('input');
            fileInput.setAttribute('type', 'file');
            fileInput.setAttribute('name', 'files');
            this._handleFileSelected(fileInput);
            return fileInput;
        },

        /**
         * upload the selected file
         * 
         * @param {object} DOM element 
         */
        _handleFileSelected: function(element)
        {
            if(!!!element) return;
            var self = this; // to maintain the scope inside the event listeners
            
            // submit the upload form as a file is seleted
			element.addEventListener('change', function(e) {
                e.preventDefault();
                self.import();
			});
        },

        /**
         * get a csrf token
         */
        _get_csrf_token: function ()
        {
            return window.redcap_csrf_token || '';
        },

        /**
         * send an ajax request
         * 
         * @param {string} method 
         * @param {object} params 
         */
        _ajaxRequest: function (method, data, options)
        {
            data.redcap_csrf_token = this._get_csrf_token(); // add the csrf token
            var dfd = $.Deferred();
            var base_params = {
                url: location.href,
                type: method,
                data: data,
                dataType: 'json',
            };
            var params = $.extend(base_params, options);
            $.ajax(params)
            .done( function( response, textStatus, jqXHR ) {
                // on success reload the page to reflect data changes
                dfd.resolve(response);
            }).fail( function( jqXHR, textStatus, errorThrown ) {
                var response = {
                    status: "error",
                    //message: jqXHR.message
                    message: errorThrown
                };
                dfd.reject(response);
            });
            return dfd;
        },


        /**
         * helper function to clone a JSON
         * 
         * @param {object} obj 
         */
        _cloneObject: function (obj) {
            var clone = JSON.parse(JSON.stringify(obj));
            return clone;
        },
    };
    
    /**
     * template for the clone modal dialog
     */
    var selectTemplate = [
    '<div class="mb-3">',
        '<a href="javascript:;" onclick="$(\'#asiCopyFieldSet input[type=checkbox]\').prop(\'checked\', true);" data-select-all="1">{{selectAll}}</a>',
        '&nbsp;&nbsp;',
        '<a href="javascript:;" onclick="$(\'#asiCopyFieldSet input[type=checkbox]\').prop(\'checked\', false);" data-select-all="0">{{deselectAll}}</a>',
    '</div>',
    ].join('');
    var dialogTemplate = [
    '<div title="{{ title }}">',
        '<input type="hidden" name="asi_from" data-survey-id="" data-event-id="" />',
        '<p>{{ description }}</p>',
        '<p class="my-3" id="asiCopyFieldFromText"><b>'+langASI.asi_clone3+'</b> </p>',
        '<fieldset id="asiCopyFieldSet" class="yellow" style="background-color:#FFFFD3;padding: 6px 15px;">',
            '<legend>',
                '<img src="'+app_path_images+'page_copy.png"> ',
                '<b>'+langASI.asi_clone2+'</b>',
            '</legend>',
            '<div>',
                '{{> select-deselect}}',
                '{{#SurveyEnabledForms}}',
                '{{#relationships.events}}',
                '<div>',
                    '<input type="checkbox" name="asi_to" id="checkbox{{../id}}:{{id}}" data-survey-id="{{../id}}" data-event-id="{{id}}" />',
                    '&nbsp',
                    '<label id="asiLabel{{../id}}-{{id}}" for="checkbox{{../id}}:{{id}}" >"<span style="color:#C00000;font-weight:600;">{{../title}}</span>" - {{title}}</label>',
                '</div>',
                '{{/relationships.events}}',
                '{{/SurveyEnabledForms}}',
            '</div>',
        '</fieldset>',
    '</div>',
    ].join('');

    window.AutomatedSurveyInvitationTool = app; // expose the app
    
    var enableMultiSelectToggle = function(element) {
        element.addEventListener('scroll', function(e){
            console.log(e);
        });
        var options = element.querySelectorAll('option');
        options.forEach(function(option) {
            option.addEventListener('mousedown', function(e){
                e.preventDefault();
                option.selected = !option.selected;
                option.focus();
            });
        });
    };

})(jQuery,window,document);