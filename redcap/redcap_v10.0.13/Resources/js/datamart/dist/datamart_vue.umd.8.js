((typeof self !== 'undefined' ? self : this)["webpackJsonpdatamart_vue"] = (typeof self !== 'undefined' ? self : this)["webpackJsonpdatamart_vue"] || []).push([[8],{

/***/ "0ea4":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"5effc531-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/pages/CreateRevision.vue?vue&type=template&id=7f70b49e&scoped=true&
var render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',{staticClass:"create-revision-page"},[_c('section',{staticClass:"main"},[_c('RevisionManagerView',{attrs:{"hidden_groups":_vm.revision_hidden_groups},scopedSlots:_vm._u([{key:"header",fn:function(){return [_c('MappingHelperButton',{staticClass:"ml-auto"})]},proxy:true}])})],1),_c('section',{staticClass:"footer"},[_c('button',{staticClass:"btn btn-secondary",attrs:{"type":"button"},on:{"click":_vm.goBack}},[_vm._v("Back")]),_c('ResetButton'),_c('SubmitButton',{on:{"dismissed":_vm.onDismissed}})],1)])}
var staticRenderFns = []


// CONCATENATED MODULE: ./src/pages/CreateRevision.vue?vue&type=template&id=7f70b49e&scoped=true&

// EXTERNAL MODULE: ./src/views/RevisionManagerView.vue + 44 modules
var RevisionManagerView = __webpack_require__("c63b");

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"5effc531-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/buttons/ResetButton.vue?vue&type=template&id=2e69bc40&scoped=true&
var ResetButtonvue_type_template_id_2e69bc40_scoped_true_render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('button',{staticClass:"btn btn-sm btn-info",attrs:{"disabled":!_vm.isDirty},on:{"click":_vm.onClick}},[_c('i',{staticClass:"fas fa-undo"}),_vm._v(" Reset")])}
var ResetButtonvue_type_template_id_2e69bc40_scoped_true_staticRenderFns = []


// CONCATENATED MODULE: ./src/components/buttons/ResetButton.vue?vue&type=template&id=2e69bc40&scoped=true&

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/buttons/ResetButton.vue?vue&type=script&lang=js&
//
//
//
//
/* harmony default export */ var ResetButtonvue_type_script_lang_js_ = ({
  name: 'ResetButton',
  computed: {
    isDirty: function isDirty() {
      return this.$store.getters['revision/isDirty'];
    }
  },
  methods: {
    onClick: function onClick() {
      // use this check instead of :disabled to improve performances

      /* if(!this.isDirty) {
        alert('change at something before submitting')
        return 
      } */
      this.$store.dispatch('revision/reset');
    }
  }
});
// CONCATENATED MODULE: ./src/components/buttons/ResetButton.vue?vue&type=script&lang=js&
 /* harmony default export */ var buttons_ResetButtonvue_type_script_lang_js_ = (ResetButtonvue_type_script_lang_js_); 
// EXTERNAL MODULE: ./node_modules/vue-loader/lib/runtime/componentNormalizer.js
var componentNormalizer = __webpack_require__("2877");

// CONCATENATED MODULE: ./src/components/buttons/ResetButton.vue





/* normalize component */

var component = Object(componentNormalizer["a" /* default */])(
  buttons_ResetButtonvue_type_script_lang_js_,
  ResetButtonvue_type_template_id_2e69bc40_scoped_true_render,
  ResetButtonvue_type_template_id_2e69bc40_scoped_true_staticRenderFns,
  false,
  null,
  "2e69bc40",
  null
  
)

/* harmony default export */ var ResetButton = (component.exports);
// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"5effc531-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/buttons/SubmitButton.vue?vue&type=template&id=75679e35&scoped=true&
var SubmitButtonvue_type_template_id_75679e35_scoped_true_render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('button',{staticClass:"btn btn-sm btn-success",attrs:{"disabled":!_vm.isValid || !_vm.isDirty},on:{"click":_vm.onClick}},[_c('i',{staticClass:"fas fa-file-export"}),_vm._v(" Submit")])}
var SubmitButtonvue_type_template_id_75679e35_scoped_true_staticRenderFns = []


// CONCATENATED MODULE: ./src/components/buttons/SubmitButton.vue?vue&type=template&id=75679e35&scoped=true&

// EXTERNAL MODULE: ./node_modules/core-js/modules/es.object.keys.js
var es_object_keys = __webpack_require__("b64b");

// EXTERNAL MODULE: ./node_modules/regenerator-runtime/runtime.js
var runtime = __webpack_require__("96cf");

// EXTERNAL MODULE: ./node_modules/@babel/runtime/helpers/esm/asyncToGenerator.js
var asyncToGenerator = __webpack_require__("1da1");

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/buttons/SubmitButton.vue?vue&type=script&lang=js&



//
//
//
//
/* harmony default export */ var SubmitButtonvue_type_script_lang_js_ = ({
  name: 'SubmitButton',
  computed: {
    isDirty: function isDirty() {
      return this.$store.getters['revision/isDirty'];
    },
    isValid: function isValid() {
      var errors = this.$store.state.validator.errors;
      return Object.keys(errors).length == 0;
    }
  },
  methods: {
    onClick: function onClick() {
      var _this = this;

      return Object(asyncToGenerator["a" /* default */])( /*#__PURE__*/regeneratorRuntime.mark(function _callee() {
        var isValid, revision, dismissal;
        return regeneratorRuntime.wrap(function _callee$(_context) {
          while (1) {
            switch (_context.prev = _context.next) {
              case 0:
                _context.prev = 0;
                _context.next = 3;
                return _this.$store.dispatch('revision/validate');

              case 3:
                isValid = _context.sent;

                if (isValid) {
                  _context.next = 6;
                  break;
                }

                return _context.abrupt("return");

              case 6:
                // close the modal
                // this.$store.dispatch('modal/setOpen', false)
                _this.$swal.fire({
                  icon: 'info',
                  title: 'sending data',
                  html: "<p class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i> Please wait.</p>",
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  showConfirmButton: false
                });

                _context.next = 9;
                return _this.$store.dispatch('revision/submit');

              case 9:
                revision = _context.sent;
                _context.next = 12;
                return _this.$swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: 'Your revision has been submitted!'
                });

              case 12:
                dismissal = _context.sent;

                _this.$emit('dismissed', dismissal);

                _context.next = 20;
                break;

              case 16:
                _context.prev = 16;
                _context.t0 = _context["catch"](0);
                console.log(_context.t0);

                _this.$swal.fire({
                  icon: 'error',
                  title: 'Error submitting your revision',
                  text: '' //timer: 1500

                });

              case 20:
              case "end":
                return _context.stop();
            }
          }
        }, _callee, null, [[0, 16]]);
      }))();
    }
  }
});
// CONCATENATED MODULE: ./src/components/buttons/SubmitButton.vue?vue&type=script&lang=js&
 /* harmony default export */ var buttons_SubmitButtonvue_type_script_lang_js_ = (SubmitButtonvue_type_script_lang_js_); 
// CONCATENATED MODULE: ./src/components/buttons/SubmitButton.vue





/* normalize component */

var SubmitButton_component = Object(componentNormalizer["a" /* default */])(
  buttons_SubmitButtonvue_type_script_lang_js_,
  SubmitButtonvue_type_template_id_75679e35_scoped_true_render,
  SubmitButtonvue_type_template_id_75679e35_scoped_true_staticRenderFns,
  false,
  null,
  "75679e35",
  null
  
)

/* harmony default export */ var SubmitButton = (SubmitButton_component.exports);
// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"5effc531-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/buttons/MappingHelperButton.vue?vue&type=template&id=4d4e83fb&scoped=true&
var MappingHelperButtonvue_type_template_id_4d4e83fb_scoped_true_render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('button',{staticClass:"btn btn-sm btn-info",attrs:{"type":"button"},on:{"click":_vm.onClick}},[_c('i',{staticClass:"fas fa-code-branch"}),_vm._v(" Use the Mapping Helper")])}
var MappingHelperButtonvue_type_template_id_4d4e83fb_scoped_true_staticRenderFns = []


// CONCATENATED MODULE: ./src/components/buttons/MappingHelperButton.vue?vue&type=template&id=4d4e83fb&scoped=true&

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/buttons/MappingHelperButton.vue?vue&type=script&lang=js&


//
//
//
//
/* harmony default export */ var MappingHelperButtonvue_type_script_lang_js_ = ({
  name: 'mapping-helper-button',
  data: function data() {
    return {};
  },
  computed: {
    mapping_helper_url: function mapping_helper_url() {
      var mapping_helper_url = this.$store.state.settings.settings.mapping_helper_url;
      return mapping_helper_url;
    }
  },
  methods: {
    onClick: function onClick() {
      var _this = this;

      return Object(asyncToGenerator["a" /* default */])( /*#__PURE__*/regeneratorRuntime.mark(function _callee() {
        var dismissalPromise, _yield$dismissalPromi, dismiss, value;

        return regeneratorRuntime.wrap(function _callee$(_context) {
          while (1) {
            switch (_context.prev = _context.next) {
              case 0:
                // location.href = this.mapping_helper_url
                dismissalPromise = _this.$swal.fire({
                  icon: 'info',
                  title: 'Mapping Helper',
                  text: 'You will be redirected to the Mapping Helper. Continue?',
                  allowOutsideClick: true,
                  allowEscapeKey: true,
                  showConfirmButton: true,
                  showCancelButton: true
                });
                _context.next = 3;
                return dismissalPromise;

              case 3:
                _yield$dismissalPromi = _context.sent;
                dismiss = _yield$dismissalPromi.dismiss;
                value = _yield$dismissalPromi.value;

                if (value === true) {
                  window.open(_this.mapping_helper_url, '_self');
                } else if (dismiss && dismiss === 'cancel') {
                  console.log('operation canceled by user');
                }

              case 7:
              case "end":
                return _context.stop();
            }
          }
        }, _callee);
      }))();
    }
  }
});
// CONCATENATED MODULE: ./src/components/buttons/MappingHelperButton.vue?vue&type=script&lang=js&
 /* harmony default export */ var buttons_MappingHelperButtonvue_type_script_lang_js_ = (MappingHelperButtonvue_type_script_lang_js_); 
// CONCATENATED MODULE: ./src/components/buttons/MappingHelperButton.vue





/* normalize component */

var MappingHelperButton_component = Object(componentNormalizer["a" /* default */])(
  buttons_MappingHelperButtonvue_type_script_lang_js_,
  MappingHelperButtonvue_type_template_id_4d4e83fb_scoped_true_render,
  MappingHelperButtonvue_type_template_id_4d4e83fb_scoped_true_staticRenderFns,
  false,
  null,
  "4d4e83fb",
  null
  
)

/* harmony default export */ var MappingHelperButton = (MappingHelperButton_component.exports);
// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/pages/CreateRevision.vue?vue&type=script&lang=js&
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//




/* harmony default export */ var CreateRevisionvue_type_script_lang_js_ = ({
  name: 'CreateRevisionView',
  data: function data() {
    return {
      revision_hidden_groups: [RevisionManagerView["b" /* settings_groups */].MRNS] // nothing hidden!

    };
  },
  components: {
    RevisionManagerView: RevisionManagerView["a" /* default */],
    ResetButton: ResetButton,
    SubmitButton: SubmitButton,
    MappingHelperButton: MappingHelperButton
  },
  methods: {
    goBack: function goBack() {
      this.$router.push({
        name: 'home'
      });
    },
    onDismissed: function onDismissed(dismissal) {
      this.goBack();
    }
  }
});
// CONCATENATED MODULE: ./src/pages/CreateRevision.vue?vue&type=script&lang=js&
 /* harmony default export */ var pages_CreateRevisionvue_type_script_lang_js_ = (CreateRevisionvue_type_script_lang_js_); 
// EXTERNAL MODULE: ./src/pages/CreateRevision.vue?vue&type=style&index=0&id=7f70b49e&scoped=true&lang=css&
var CreateRevisionvue_type_style_index_0_id_7f70b49e_scoped_true_lang_css_ = __webpack_require__("c039");

// CONCATENATED MODULE: ./src/pages/CreateRevision.vue






/* normalize component */

var CreateRevision_component = Object(componentNormalizer["a" /* default */])(
  pages_CreateRevisionvue_type_script_lang_js_,
  render,
  staticRenderFns,
  false,
  null,
  "7f70b49e",
  null
  
)

/* harmony default export */ var CreateRevision = __webpack_exports__["default"] = (CreateRevision_component.exports);

/***/ }),

/***/ "62ae":
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "c039":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_CreateRevision_vue_vue_type_style_index_0_id_7f70b49e_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__("62ae");
/* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_CreateRevision_vue_vue_type_style_index_0_id_7f70b49e_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_CreateRevision_vue_vue_type_style_index_0_id_7f70b49e_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__);
/* unused harmony reexport * */
 /* unused harmony default export */ var _unused_webpack_default_export = (_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_CreateRevision_vue_vue_type_style_index_0_id_7f70b49e_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ })

}]);
//# sourceMappingURL=datamart_vue.umd.8.js.map