((typeof self !== 'undefined' ? self : this)["webpackJsonpmapping_helper_vue"] = (typeof self !== 'undefined' ? self : this)["webpackJsonpmapping_helper_vue"] || []).push([[16],{

/***/ "3f78":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"6de6a0ba-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/observation/ObservationFields.vue?vue&type=template&id=7ae542e4&scoped=true&
var render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',[_c('label',{staticClass:"font-weight-bold",attrs:{"for":"observation-type"}},[_vm._v("Observation type")]),_c('select',{directives:[{name:"model",rawName:"v-model",value:(_vm.current_category),expression:"current_category"}],staticClass:"form-control",attrs:{"id":"observation-type"},on:{"change":function($event){var $$selectedVal = Array.prototype.filter.call($event.target.options,function(o){return o.selected}).map(function(o){var val = "_value" in o ? o._value : o.value;return val}); _vm.current_category=$event.target.multiple ? $$selectedVal : $$selectedVal[0]}}},[_c('option',{attrs:{"disabled":"","value":""}},[_vm._v("Please select a category")]),_vm._l((_vm.categories),function(category,index){return _c('option',{key:index,domProps:{"value":category}},[_vm._v(_vm._s(category))])})],2)])}
var staticRenderFns = []


// CONCATENATED MODULE: ./src/components/observation/ObservationFields.vue?vue&type=template&id=7ae542e4&scoped=true&

// EXTERNAL MODULE: ./src/variables.js
var variables = __webpack_require__("7eac");

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/observation/ObservationFields.vue?vue&type=script&lang=js&
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

/* harmony default export */ var ObservationFieldsvue_type_script_lang_js_ = ({
  name: 'ObservationFields',
  data: function data() {
    return {
      categories: variables["d" /* observation_categories */],
      current_category: ''
    };
  },
  props: {
    category: {
      type: String,
      default: variables["d" /* observation_categories */][0]
    }
  },
  created: function created() {
    this.current_category = this.category;
  },
  methods: {
    getQuery: function getQuery() {
      var category = this.current_category || variables["d" /* observation_categories */][0];
      return {
        category: category
      };
    }
  }
});
// CONCATENATED MODULE: ./src/components/observation/ObservationFields.vue?vue&type=script&lang=js&
 /* harmony default export */ var observation_ObservationFieldsvue_type_script_lang_js_ = (ObservationFieldsvue_type_script_lang_js_); 
// EXTERNAL MODULE: ./node_modules/vue-loader/lib/runtime/componentNormalizer.js
var componentNormalizer = __webpack_require__("2877");

// CONCATENATED MODULE: ./src/components/observation/ObservationFields.vue





/* normalize component */

var component = Object(componentNormalizer["a" /* default */])(
  observation_ObservationFieldsvue_type_script_lang_js_,
  render,
  staticRenderFns,
  false,
  null,
  "7ae542e4",
  null
  
)

/* harmony default export */ var ObservationFields = __webpack_exports__["default"] = (component.exports);

/***/ })

}]);
//# sourceMappingURL=mapping_helper_vue.common.16.js.map