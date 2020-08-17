((typeof self !== 'undefined' ? self : this)["webpackJsonpmapping_helper_vue"] = (typeof self !== 'undefined' ? self : this)["webpackJsonpmapping_helper_vue"] || []).push([[12],{

/***/ "57ce":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_PatientTable_vue_vue_type_style_index_0_id_067d7df2_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__("e2ae");
/* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_PatientTable_vue_vue_type_style_index_0_id_067d7df2_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_PatientTable_vue_vue_type_style_index_0_id_067d7df2_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__);
/* unused harmony reexport * */
 /* unused harmony default export */ var _unused_webpack_default_export = (_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_PatientTable_vue_vue_type_style_index_0_id_067d7df2_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ "8b6d":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"6de6a0ba-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/patient/PatientTable.vue?vue&type=template&id=067d7df2&scoped=true&
var render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return (Object.keys(_vm.data).length)?_c('table',{staticClass:"table table-striped table-bordered"},[_vm._m(0),_c('tbody',_vm._l((_vm.data),function(value,key){return _c('tr',{key:key},[_c('td',[_vm._v(_vm._s(key))]),_c('td',[_vm._v(_vm._s(value))])])}),0)]):_vm._e()}
var staticRenderFns = [function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('thead',[_c('th',[_vm._v("key")]),_c('th',[_vm._v("value")])])}]


// CONCATENATED MODULE: ./src/components/patient/PatientTable.vue?vue&type=template&id=067d7df2&scoped=true&

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/patient/PatientTable.vue?vue&type=script&lang=js&
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
var table_headers = {
  'first_name': 'first name',
  'last_name': 'last name',
  'gender': 'gender',
  'gender_code': 'gender code',
  'ethnicity': 'ethnicity',
  'ethnicity_code': 'ethnicity code',
  'race': 'race',
  'race_code': 'race code',
  'birthdate': 'birthdate',
  'address_city': 'address city',
  'address_country': 'address country',
  'address_postal_code': 'address postal code',
  'address_state': 'address state',
  'address_line': 'address line',
  'phone_home': 'phone home',
  'phone_mobile': 'phone mobile',
  'email': 'email',
  'is_deceased': 'is deceased'
};
/* harmony default export */ var PatientTablevue_type_script_lang_js_ = ({
  name: 'PatientTable',
  data: function data() {
    return {
      table_headers: table_headers
    };
  },
  computed: {
    resource: function resource() {
      try {
        var _this$$store$state$re = this.$store.state.resource.resource,
            resource = _this$$store$state$re === void 0 ? {} : _this$$store$state$re;
        var resourceType = resource.metadata.resourceType; // make sure the resource is of type patient

        if (resourceType !== 'Patient') return {};
        return resource;
      } catch (error) {
        return {};
      }
    },
    data: function data() {
      var _this$resource$data = this.resource.data,
          data = _this$resource$data === void 0 ? {} : _this$resource$data;
      return data;
    }
  }
});
// CONCATENATED MODULE: ./src/components/patient/PatientTable.vue?vue&type=script&lang=js&
 /* harmony default export */ var patient_PatientTablevue_type_script_lang_js_ = (PatientTablevue_type_script_lang_js_); 
// EXTERNAL MODULE: ./src/components/patient/PatientTable.vue?vue&type=style&index=0&id=067d7df2&scoped=true&lang=css&
var PatientTablevue_type_style_index_0_id_067d7df2_scoped_true_lang_css_ = __webpack_require__("57ce");

// EXTERNAL MODULE: ./node_modules/vue-loader/lib/runtime/componentNormalizer.js
var componentNormalizer = __webpack_require__("2877");

// CONCATENATED MODULE: ./src/components/patient/PatientTable.vue






/* normalize component */

var component = Object(componentNormalizer["a" /* default */])(
  patient_PatientTablevue_type_script_lang_js_,
  render,
  staticRenderFns,
  false,
  null,
  "067d7df2",
  null
  
)

/* harmony default export */ var PatientTable = __webpack_exports__["default"] = (component.exports);

/***/ }),

/***/ "e2ae":
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ })

}]);
//# sourceMappingURL=mapping_helper_vue.umd.12.js.map