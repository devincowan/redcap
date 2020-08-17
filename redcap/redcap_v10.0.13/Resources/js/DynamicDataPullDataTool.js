(function($,window,document){

	var app = {
		// default options
		options: {
			url: location.href // it's meant to use the same url of the page where the script is loaded
		},

		_OnModalClosedCallback: false, // function to execute when the modal is closed

		// DOM elements
		elements: {},

		/**
		 * initialize the app
		 * @param {object} params 
		 */
		init: function(params) {
			this.options = $.extend({}, this.options, params);
			// get reference to DOM elements
			this._setDOMElements();
			// register handlers
			this._handleModal();
			this._handleImport();
			this._handleExport();
		},

		_setDOMElements: function() {
			this.elements.modal = document.getElementById('messageModal');
			this.elements.import = {
				button: document.getElementById('button-import'),
				fileInput: document.querySelector('#import-form input[type="file"]'),
				form: document.getElementById('import-form'),
			};
			this.elements.export = {
				button: document.getElementById('button-export'),
				form: document.getElementById('export-form'),
			};
		},

		/**
		 * AJAX upload of the settings file
		 * @param {object} form 
		 * @param {string} url 
		 */
		_ajaxFileUpload: function(form, url)
		{
			var self = this; // to maintain the scope inside the event listeners
			var formData = new FormData(form);
			$.ajax({
				url: url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
			})
			.done( function( data, textStatus, jqXHR ) {
				// on success reload the page to reflect data changes
				self._OnModalClosedCallback = (data && data.status==='success') ? self._reload : false;
				self._showModal(data);
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				var data = {
					status: "error", 
					message: errorThrown
				};
				self._OnModalClosedCallback = false;
				self._showModal(data);
			});
		},

		/**
		 * reload the page to reflect data changes.
		 * usually fired on successful import
		 */
		_reload: function() {
			location.reload();
		},
	
		_handleImport: function()
		{
			var self = this; // to maintain the scope inside the event listeners
			try{
				this.elements.import.button.addEventListener('click', function(e) {
					e.preventDefault();
					self.elements.import.fileInput.click();
				});
			}catch (e) { }
			
			// submit the import form when the file is selected
			this.elements.import.fileInput.addEventListener('change', function(e) {
				self._ajaxFileUpload(self.elements.import.form, self.options.url);
				// form.submit();
			});
	
			// check if a file has been selected before sending the form
			this.elements.import.form.addEventListener('submit', function(e) {
				e.preventDefault();
				if(fileInput.files.length == 0) {
					alert('select a file');
					return false;
				}
				return true;
			});
		},
	
		_handleExport: function()
		{
			var self = this;
			try{
				this.elements.export.button.addEventListener('click', function(e) {
					e.preventDefault();
					self.elements.export.form.submit();
				});
			}catch (e) { }
		},

		/**
		 * control the behaviour of the modal when closed
		 */
		_handleModal: function()
		{
			var self = this;
			$(this.elements.modal).on('hidden.bs.modal', function (e) {
				var callback = self._OnModalClosedCallback;
				if(typeof callback === 'function')
					callback();
			})
		},

		/**
		 * set modal title and body and show it
		 * @param {object} data {status, message}
		 */
		_showModal: function(data)
		{
			var title = data.status || '';
			var body = data.message || '';
			var titleElement = this.elements.modal.querySelector('.modal-title');
			var bodyElement = this.elements.modal.querySelector('.modal-body');
			titleElement.innerHTML = title.toUpperCase();
			bodyElement.innerHTML = body;
			// show modal
			$(this.elements.modal).modal();
		}
	};

	window.DDP_DataTool = app;

})(jQuery,window,document);