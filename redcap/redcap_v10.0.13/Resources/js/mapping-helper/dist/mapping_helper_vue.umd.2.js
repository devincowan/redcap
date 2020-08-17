((typeof self !== 'undefined' ? self : this)["webpackJsonpmapping_helper_vue"] = (typeof self !== 'undefined' ? self : this)["webpackJsonpmapping_helper_vue"] || []).push([[2],{

/***/ "0336":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";

// EXPORTS
__webpack_require__.d(__webpack_exports__, "a", function() { return /* reexport */ Utils_formatDate; });

// UNUSED EXPORTS: Resource, Bundle, download

// EXTERNAL MODULE: ./src/libraries/FhirResource/Resource.js
var Resource = __webpack_require__("e6ff");

// EXTERNAL MODULE: ./src/libraries/FhirResource/Bundle.js + 6 modules
var Bundle = __webpack_require__("c368");

// EXTERNAL MODULE: ./node_modules/moment/moment.js
var moment = __webpack_require__("c1df");
var moment_default = /*#__PURE__*/__webpack_require__.n(moment);

// EXTERNAL MODULE: ./src/variables.js
var variables = __webpack_require__("7eac");

// CONCATENATED MODULE: ./src/libraries/Utils.js



var download = function download(filename, text) {
  var url = window.URL.createObjectURL(new Blob([text]));
  var link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', filename);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

var Utils_formatDate = function formatDate(date) {
  if (!date) return '';
  var date_string = moment_default()(date).format(variables["a" /* date_format */]); // date_format defined in variables

  return date_string;
};


// CONCATENATED MODULE: ./src/libraries/index.js




/***/ }),

/***/ "0a81":
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "1af6":
/***/ (function(module, exports, __webpack_require__) {

// 22.1.2.2 / 15.4.3.2 Array.isArray(arg)
var $export = __webpack_require__("63b6");

$export($export.S, 'Array', { isArray: __webpack_require__("9003") });


/***/ }),

/***/ "20fd":
/***/ (function(module, exports, __webpack_require__) {

"use strict";

var $defineProperty = __webpack_require__("d9f6");
var createDesc = __webpack_require__("aebd");

module.exports = function (object, index, value) {
  if (index in object) $defineProperty.f(object, index, createDesc(0, value));
  else object[index] = value;
};


/***/ }),

/***/ "21a6":
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(global) {var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_ARRAY__, __WEBPACK_AMD_DEFINE_RESULT__;(function(a,b){if(true)!(__WEBPACK_AMD_DEFINE_ARRAY__ = [], __WEBPACK_AMD_DEFINE_FACTORY__ = (b),
				__WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ?
				(__WEBPACK_AMD_DEFINE_FACTORY__.apply(exports, __WEBPACK_AMD_DEFINE_ARRAY__)) : __WEBPACK_AMD_DEFINE_FACTORY__),
				__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));else {}})(this,function(){"use strict";function b(a,b){return"undefined"==typeof b?b={autoBom:!1}:"object"!=typeof b&&(console.warn("Deprecated: Expected third argument to be a object"),b={autoBom:!b}),b.autoBom&&/^\s*(?:text\/\S*|application\/xml|\S*\/\S*\+xml)\s*;.*charset\s*=\s*utf-8/i.test(a.type)?new Blob(["\uFEFF",a],{type:a.type}):a}function c(b,c,d){var e=new XMLHttpRequest;e.open("GET",b),e.responseType="blob",e.onload=function(){a(e.response,c,d)},e.onerror=function(){console.error("could not download file")},e.send()}function d(a){var b=new XMLHttpRequest;b.open("HEAD",a,!1);try{b.send()}catch(a){}return 200<=b.status&&299>=b.status}function e(a){try{a.dispatchEvent(new MouseEvent("click"))}catch(c){var b=document.createEvent("MouseEvents");b.initMouseEvent("click",!0,!0,window,0,0,0,80,20,!1,!1,!1,!1,0,null),a.dispatchEvent(b)}}var f="object"==typeof window&&window.window===window?window:"object"==typeof self&&self.self===self?self:"object"==typeof global&&global.global===global?global:void 0,a=f.saveAs||("object"!=typeof window||window!==f?function(){}:"download"in HTMLAnchorElement.prototype?function(b,g,h){var i=f.URL||f.webkitURL,j=document.createElement("a");g=g||b.name||"download",j.download=g,j.rel="noopener","string"==typeof b?(j.href=b,j.origin===location.origin?e(j):d(j.href)?c(b,g,h):e(j,j.target="_blank")):(j.href=i.createObjectURL(b),setTimeout(function(){i.revokeObjectURL(j.href)},4E4),setTimeout(function(){e(j)},0))}:"msSaveOrOpenBlob"in navigator?function(f,g,h){if(g=g||f.name||"download","string"!=typeof f)navigator.msSaveOrOpenBlob(b(f,h),g);else if(d(f))c(f,g,h);else{var i=document.createElement("a");i.href=f,i.target="_blank",setTimeout(function(){e(i)})}}:function(a,b,d,e){if(e=e||open("","_blank"),e&&(e.document.title=e.document.body.innerText="downloading..."),"string"==typeof a)return c(a,b,d);var g="application/octet-stream"===a.type,h=/constructor/i.test(f.HTMLElement)||f.safari,i=/CriOS\/[\d]+/.test(navigator.userAgent);if((i||g&&h)&&"object"==typeof FileReader){var j=new FileReader;j.onloadend=function(){var a=j.result;a=i?a:a.replace(/^data:[^;]*;/,"data:attachment/file;"),e?e.location.href=a:location=a,e=null},j.readAsDataURL(a)}else{var k=f.URL||f.webkitURL,l=k.createObjectURL(a);e?e.location=l:location.href=l,e=null,setTimeout(function(){k.revokeObjectURL(l)},4E4)}});f.saveAs=a.saveAs=a, true&&(module.exports=a)});

//# sourceMappingURL=FileSaver.min.js.map
/* WEBPACK VAR INJECTION */}.call(this, __webpack_require__("c8ba")))

/***/ }),

/***/ "4678":
/***/ (function(module, exports, __webpack_require__) {

var map = {
	"./af": "2bfb",
	"./af.js": "2bfb",
	"./ar": "8e73",
	"./ar-dz": "a356",
	"./ar-dz.js": "a356",
	"./ar-kw": "423e",
	"./ar-kw.js": "423e",
	"./ar-ly": "1cfd",
	"./ar-ly.js": "1cfd",
	"./ar-ma": "0a84",
	"./ar-ma.js": "0a84",
	"./ar-sa": "8230",
	"./ar-sa.js": "8230",
	"./ar-tn": "6d83",
	"./ar-tn.js": "6d83",
	"./ar.js": "8e73",
	"./az": "485c",
	"./az.js": "485c",
	"./be": "1fc1",
	"./be.js": "1fc1",
	"./bg": "84aa",
	"./bg.js": "84aa",
	"./bm": "a7fa",
	"./bm.js": "a7fa",
	"./bn": "9043",
	"./bn.js": "9043",
	"./bo": "d26a",
	"./bo.js": "d26a",
	"./br": "6887",
	"./br.js": "6887",
	"./bs": "2554",
	"./bs.js": "2554",
	"./ca": "d716",
	"./ca.js": "d716",
	"./cs": "3c0d",
	"./cs.js": "3c0d",
	"./cv": "03ec",
	"./cv.js": "03ec",
	"./cy": "9797",
	"./cy.js": "9797",
	"./da": "0f14",
	"./da.js": "0f14",
	"./de": "b469",
	"./de-at": "b3eb",
	"./de-at.js": "b3eb",
	"./de-ch": "bb71",
	"./de-ch.js": "bb71",
	"./de.js": "b469",
	"./dv": "598a",
	"./dv.js": "598a",
	"./el": "8d47",
	"./el.js": "8d47",
	"./en-au": "0e6b",
	"./en-au.js": "0e6b",
	"./en-ca": "3886",
	"./en-ca.js": "3886",
	"./en-gb": "39a6",
	"./en-gb.js": "39a6",
	"./en-ie": "e1d3",
	"./en-ie.js": "e1d3",
	"./en-il": "73332",
	"./en-il.js": "73332",
	"./en-in": "ec2e",
	"./en-in.js": "ec2e",
	"./en-nz": "6f50",
	"./en-nz.js": "6f50",
	"./en-sg": "b7e9",
	"./en-sg.js": "b7e9",
	"./eo": "65db",
	"./eo.js": "65db",
	"./es": "898b",
	"./es-do": "0a3c",
	"./es-do.js": "0a3c",
	"./es-us": "55c9",
	"./es-us.js": "55c9",
	"./es.js": "898b",
	"./et": "ec18",
	"./et.js": "ec18",
	"./eu": "0ff2",
	"./eu.js": "0ff2",
	"./fa": "8df48",
	"./fa.js": "8df48",
	"./fi": "81e9",
	"./fi.js": "81e9",
	"./fil": "d69a",
	"./fil.js": "d69a",
	"./fo": "0721",
	"./fo.js": "0721",
	"./fr": "9f26",
	"./fr-ca": "d9f8",
	"./fr-ca.js": "d9f8",
	"./fr-ch": "0e49",
	"./fr-ch.js": "0e49",
	"./fr.js": "9f26",
	"./fy": "7118",
	"./fy.js": "7118",
	"./ga": "5120",
	"./ga.js": "5120",
	"./gd": "f6b46",
	"./gd.js": "f6b46",
	"./gl": "8840",
	"./gl.js": "8840",
	"./gom-deva": "aaf2",
	"./gom-deva.js": "aaf2",
	"./gom-latn": "0caa",
	"./gom-latn.js": "0caa",
	"./gu": "e0c5",
	"./gu.js": "e0c5",
	"./he": "c7aa",
	"./he.js": "c7aa",
	"./hi": "dc4d",
	"./hi.js": "dc4d",
	"./hr": "4ba9",
	"./hr.js": "4ba9",
	"./hu": "5b14",
	"./hu.js": "5b14",
	"./hy-am": "d6b6",
	"./hy-am.js": "d6b6",
	"./id": "5038",
	"./id.js": "5038",
	"./is": "0558",
	"./is.js": "0558",
	"./it": "6e98",
	"./it-ch": "6f12",
	"./it-ch.js": "6f12",
	"./it.js": "6e98",
	"./ja": "079e",
	"./ja.js": "079e",
	"./jv": "b540",
	"./jv.js": "b540",
	"./ka": "201b",
	"./ka.js": "201b",
	"./kk": "6d79",
	"./kk.js": "6d79",
	"./km": "e81d",
	"./km.js": "e81d",
	"./kn": "3e92",
	"./kn.js": "3e92",
	"./ko": "22f8",
	"./ko.js": "22f8",
	"./ku": "2421",
	"./ku.js": "2421",
	"./ky": "9609",
	"./ky.js": "9609",
	"./lb": "440c",
	"./lb.js": "440c",
	"./lo": "b29d",
	"./lo.js": "b29d",
	"./lt": "26f9",
	"./lt.js": "26f9",
	"./lv": "b97c",
	"./lv.js": "b97c",
	"./me": "293c",
	"./me.js": "293c",
	"./mi": "688b",
	"./mi.js": "688b",
	"./mk": "6909",
	"./mk.js": "6909",
	"./ml": "02fb",
	"./ml.js": "02fb",
	"./mn": "958b",
	"./mn.js": "958b",
	"./mr": "39bd",
	"./mr.js": "39bd",
	"./ms": "ebe4",
	"./ms-my": "6403",
	"./ms-my.js": "6403",
	"./ms.js": "ebe4",
	"./mt": "1b45",
	"./mt.js": "1b45",
	"./my": "8689",
	"./my.js": "8689",
	"./nb": "6ce3",
	"./nb.js": "6ce3",
	"./ne": "3a39",
	"./ne.js": "3a39",
	"./nl": "facd",
	"./nl-be": "db29",
	"./nl-be.js": "db29",
	"./nl.js": "facd",
	"./nn": "b84c",
	"./nn.js": "b84c",
	"./oc-lnc": "167b",
	"./oc-lnc.js": "167b",
	"./pa-in": "f3ff",
	"./pa-in.js": "f3ff",
	"./pl": "8d57",
	"./pl.js": "8d57",
	"./pt": "f260",
	"./pt-br": "d2d4",
	"./pt-br.js": "d2d4",
	"./pt.js": "f260",
	"./ro": "972c",
	"./ro.js": "972c",
	"./ru": "957c",
	"./ru.js": "957c",
	"./sd": "6784",
	"./sd.js": "6784",
	"./se": "ffff",
	"./se.js": "ffff",
	"./si": "eda5",
	"./si.js": "eda5",
	"./sk": "7be6",
	"./sk.js": "7be6",
	"./sl": "8155",
	"./sl.js": "8155",
	"./sq": "c8f3",
	"./sq.js": "c8f3",
	"./sr": "cf1e",
	"./sr-cyrl": "13e9",
	"./sr-cyrl.js": "13e9",
	"./sr.js": "cf1e",
	"./ss": "52bd",
	"./ss.js": "52bd",
	"./sv": "5fbd",
	"./sv.js": "5fbd",
	"./sw": "74dc",
	"./sw.js": "74dc",
	"./ta": "3de5",
	"./ta.js": "3de5",
	"./te": "5cbb",
	"./te.js": "5cbb",
	"./tet": "576c",
	"./tet.js": "576c",
	"./tg": "3b1b",
	"./tg.js": "3b1b",
	"./th": "10e8",
	"./th.js": "10e8",
	"./tl-ph": "0f38",
	"./tl-ph.js": "0f38",
	"./tlh": "cf75",
	"./tlh.js": "cf75",
	"./tr": "0e81",
	"./tr.js": "0e81",
	"./tzl": "cf51",
	"./tzl.js": "cf51",
	"./tzm": "c109",
	"./tzm-latn": "b53d",
	"./tzm-latn.js": "b53d",
	"./tzm.js": "c109",
	"./ug-cn": "6117",
	"./ug-cn.js": "6117",
	"./uk": "ada2",
	"./uk.js": "ada2",
	"./ur": "5294",
	"./ur.js": "5294",
	"./uz": "2e8c",
	"./uz-latn": "010e",
	"./uz-latn.js": "010e",
	"./uz.js": "2e8c",
	"./vi": "2921",
	"./vi.js": "2921",
	"./x-pseudo": "fd7e",
	"./x-pseudo.js": "fd7e",
	"./yo": "7f33",
	"./yo.js": "7f33",
	"./zh-cn": "5c3a",
	"./zh-cn.js": "5c3a",
	"./zh-hk": "49ab",
	"./zh-hk.js": "49ab",
	"./zh-mo": "3a6c",
	"./zh-mo.js": "3a6c",
	"./zh-tw": "90ea",
	"./zh-tw.js": "90ea"
};


function webpackContext(req) {
	var id = webpackContextResolve(req);
	return __webpack_require__(id);
}
function webpackContextResolve(req) {
	if(!__webpack_require__.o(map, req)) {
		var e = new Error("Cannot find module '" + req + "'");
		e.code = 'MODULE_NOT_FOUND';
		throw e;
	}
	return map[req];
}
webpackContext.keys = function webpackContextKeys() {
	return Object.keys(map);
};
webpackContext.resolve = webpackContextResolve;
module.exports = webpackContext;
webpackContext.id = "4678";

/***/ }),

/***/ "549b":
/***/ (function(module, exports, __webpack_require__) {

"use strict";

var ctx = __webpack_require__("d864");
var $export = __webpack_require__("63b6");
var toObject = __webpack_require__("241e");
var call = __webpack_require__("b0dc");
var isArrayIter = __webpack_require__("3702");
var toLength = __webpack_require__("b447");
var createProperty = __webpack_require__("20fd");
var getIterFn = __webpack_require__("7cd6");

$export($export.S + $export.F * !__webpack_require__("4ee1")(function (iter) { Array.from(iter); }), 'Array', {
  // 22.1.2.1 Array.from(arrayLike, mapfn = undefined, thisArg = undefined)
  from: function from(arrayLike /* , mapfn = undefined, thisArg = undefined */) {
    var O = toObject(arrayLike);
    var C = typeof this == 'function' ? this : Array;
    var aLen = arguments.length;
    var mapfn = aLen > 1 ? arguments[1] : undefined;
    var mapping = mapfn !== undefined;
    var index = 0;
    var iterFn = getIterFn(O);
    var length, result, step, iterator;
    if (mapping) mapfn = ctx(mapfn, aLen > 2 ? arguments[2] : undefined, 2);
    // if object isn't iterable or it's array with default iterator - use simple case
    if (iterFn != undefined && !(C == Array && isArrayIter(iterFn))) {
      for (iterator = iterFn.call(O), result = new C(); !(step = iterator.next()).done; index++) {
        createProperty(result, index, mapping ? call(iterator, mapfn, [step.value, index], true) : step.value);
      }
    } else {
      length = toLength(O.length);
      for (result = new C(length); length > index; index++) {
        createProperty(result, index, mapping ? mapfn(O[index], index) : O[index]);
      }
    }
    result.length = index;
    return result;
  }
});


/***/ }),

/***/ "54a1":
/***/ (function(module, exports, __webpack_require__) {

__webpack_require__("6c1c");
__webpack_require__("1654");
module.exports = __webpack_require__("95d5");


/***/ }),

/***/ "75fc":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";

// EXPORTS
__webpack_require__.d(__webpack_exports__, "a", function() { return /* binding */ _toConsumableArray; });

// EXTERNAL MODULE: ./node_modules/@babel/runtime-corejs2/core-js/array/is-array.js
var is_array = __webpack_require__("a745");
var is_array_default = /*#__PURE__*/__webpack_require__.n(is_array);

// CONCATENATED MODULE: ./node_modules/@babel/runtime-corejs2/helpers/esm/arrayLikeToArray.js
function _arrayLikeToArray(arr, len) {
  if (len == null || len > arr.length) len = arr.length;

  for (var i = 0, arr2 = new Array(len); i < len; i++) {
    arr2[i] = arr[i];
  }

  return arr2;
}
// CONCATENATED MODULE: ./node_modules/@babel/runtime-corejs2/helpers/esm/arrayWithoutHoles.js


function _arrayWithoutHoles(arr) {
  if (is_array_default()(arr)) return _arrayLikeToArray(arr);
}
// EXTERNAL MODULE: ./node_modules/@babel/runtime-corejs2/core-js/array/from.js
var from = __webpack_require__("774e");
var from_default = /*#__PURE__*/__webpack_require__.n(from);

// EXTERNAL MODULE: ./node_modules/@babel/runtime-corejs2/core-js/is-iterable.js
var is_iterable = __webpack_require__("c8bb");
var is_iterable_default = /*#__PURE__*/__webpack_require__.n(is_iterable);

// EXTERNAL MODULE: ./node_modules/@babel/runtime-corejs2/core-js/symbol.js
var symbol = __webpack_require__("67bb");
var symbol_default = /*#__PURE__*/__webpack_require__.n(symbol);

// CONCATENATED MODULE: ./node_modules/@babel/runtime-corejs2/helpers/esm/iterableToArray.js



function _iterableToArray(iter) {
  if (typeof symbol_default.a !== "undefined" && is_iterable_default()(Object(iter))) return from_default()(iter);
}
// CONCATENATED MODULE: ./node_modules/@babel/runtime-corejs2/helpers/esm/unsupportedIterableToArray.js


function _unsupportedIterableToArray(o, minLen) {
  if (!o) return;
  if (typeof o === "string") return _arrayLikeToArray(o, minLen);
  var n = Object.prototype.toString.call(o).slice(8, -1);
  if (n === "Object" && o.constructor) n = o.constructor.name;
  if (n === "Map" || n === "Set") return from_default()(o);
  if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen);
}
// CONCATENATED MODULE: ./node_modules/@babel/runtime-corejs2/helpers/esm/nonIterableSpread.js
function _nonIterableSpread() {
  throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
}
// CONCATENATED MODULE: ./node_modules/@babel/runtime-corejs2/helpers/esm/toConsumableArray.js




function _toConsumableArray(arr) {
  return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread();
}

/***/ }),

/***/ "774e":
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__("d2d5");

/***/ }),

/***/ "8c8a":
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "95d5":
/***/ (function(module, exports, __webpack_require__) {

var classof = __webpack_require__("40c3");
var ITERATOR = __webpack_require__("5168")('iterator');
var Iterators = __webpack_require__("481b");
module.exports = __webpack_require__("584a").isIterable = function (it) {
  var O = Object(it);
  return O[ITERATOR] !== undefined
    || '@@iterator' in O
    // eslint-disable-next-line no-prototype-builtins
    || Iterators.hasOwnProperty(classof(O));
};


/***/ }),

/***/ "95d6":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_DownloadPreview_vue_vue_type_style_index_0_id_ccab0d52_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__("8c8a");
/* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_DownloadPreview_vue_vue_type_style_index_0_id_ccab0d52_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_DownloadPreview_vue_vue_type_style_index_0_id_ccab0d52_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__);
/* unused harmony reexport * */
 /* unused harmony default export */ var _unused_webpack_default_export = (_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_DownloadPreview_vue_vue_type_style_index_0_id_ccab0d52_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ "a745":
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__("f410");

/***/ }),

/***/ "c8bb":
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__("54a1");

/***/ }),

/***/ "d2d5":
/***/ (function(module, exports, __webpack_require__) {

__webpack_require__("1654");
__webpack_require__("549b");
module.exports = __webpack_require__("584a").Array.from;


/***/ }),

/***/ "e1e7":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_ObservationTable_vue_vue_type_style_index_0_id_5cf657c3_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__("0a81");
/* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_ObservationTable_vue_vue_type_style_index_0_id_5cf657c3_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_ObservationTable_vue_vue_type_style_index_0_id_5cf657c3_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__);
/* unused harmony reexport * */
 /* unused harmony default export */ var _unused_webpack_default_export = (_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_node_modules_css_loader_index_js_ref_6_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_0_0_node_modules_vue_loader_lib_index_js_vue_loader_options_ObservationTable_vue_vue_type_style_index_0_id_5cf657c3_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ "e423":
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"6de6a0ba-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/observation/ObservationTable.vue?vue&type=template&id=5cf657c3&scoped=true&
var render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return (_vm.filtered_codings.length)?_c('div',[_c('section',[_c('p',{domProps:{"innerHTML":_vm._s(_vm.lang['mapping_helper_04'])}})]),_c('table',{staticClass:"table table-striped table-bordered"},[_c('thead',[_c('th',[_vm._v("Description (from EHR, not from REDCap mapping)")]),_c('th',[_vm._v("Code\n        ")]),_c('th',[_vm._v("System")]),_c('th',[_vm._v("Value")]),_c('th',[_vm._v("Date/time of service")]),_c('th',[_c('span',[_vm._v("Actions")]),_c('div',{directives:[{name:"show",rawName:"v-show",value:(_vm.exportable.length>0),expression:"exportable.length>0"}]},[_c('div',{staticClass:"btn-group",attrs:{"role":"group"}},[_c('button',{staticClass:"btn btn-sm",class:{
              'btn-success': _vm.codes_to_export.length>=1,
              'btn-info': _vm.codes_to_export.length<1
              },attrs:{"type":"button"},on:{"click":_vm.toggleExportableSelection}},[_c('span',[_vm._v(_vm._s((_vm.exportable.length===_vm.codes_to_export.length) ? "deselect all" : "select all")+" "),_c('i',{staticClass:"fas fa-check-square"})])]),_c('button',{staticClass:"btn btn-sm btn-primary",attrs:{"type":"button","disabled":_vm.codes_to_export.length<1},on:{"click":_vm.showPreview}},[_vm._m(0)])])])])]),_c('tbody',_vm._l((_vm.filtered_codings),function(coding,coding_index){return _c('tr',{key:coding_index},[_c('td',[_vm._v(_vm._s(coding.display))]),_c('td',[_c('div',[_c('span',[_vm._v(_vm._s(coding.code))])])]),_c('td',[_vm._v(_vm._s(coding.system))]),_c('td',[_vm._v(_vm._s(coding.value))]),_c('td',[_vm._v(_vm._s(_vm.formatDate(coding.date)))]),_c('td',[(_vm.isBlocklisted(coding.code))?_c('section',[_c('div',[_c('small',[_c('em',[_vm._v("(this code is not used in REDCap: "+_vm._s(_vm.isBlocklisted(coding.code))+")")])])])]):_vm._e(),(!_vm.isAvailableInREDCap(coding.code))?_c('section',[_c('button',{staticClass:"btn btn-sm btn-outline-warning",on:{"click":function($event){return _vm.displayNewCodeInfo(coding)}}},[_vm._v("\n              info "),_c('i',{staticClass:"fas fa-info-circle"})]),_c('div',[_c('small',{style:({color:'red'})},[_vm._v("(not available in REDCap)")])])]):_vm._e(),(_vm.isExportable(coding.code))?_c('section',[_c('button',{staticClass:"btn btn-sm",class:{
              'btn-success': _vm.isCodeSelected(coding.code),
              'btn-info': !_vm.isCodeSelected(coding.code)
              },attrs:{"type":"button"},on:{"click":function($event){return _vm.toggleSelect(coding.code)}}},[_vm._v(_vm._s(_vm.isCodeSelected(coding.code) ? 'deselect' : 'select'))]),_vm._m(1,true)]):_vm._e()])])}),0)])]):_vm._e()}
var staticRenderFns = [function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('span',[_vm._v("Export "),_c('i',{staticClass:"fas fa-download"})])},function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',[_c('small',[_c('em',[_vm._v("(not mapped in your project)")])])])}]


// CONCATENATED MODULE: ./src/components/observation/ObservationTable.vue?vue&type=template&id=5cf657c3&scoped=true&

// EXTERNAL MODULE: ./node_modules/regenerator-runtime/runtime.js
var runtime = __webpack_require__("96cf");

// EXTERNAL MODULE: ./node_modules/@babel/runtime-corejs2/helpers/esm/asyncToGenerator.js
var asyncToGenerator = __webpack_require__("3b8d");

// EXTERNAL MODULE: ./node_modules/core-js/modules/es6.array.iterator.js
var es6_array_iterator = __webpack_require__("cadf");

// EXTERNAL MODULE: ./node_modules/@babel/runtime-corejs2/helpers/esm/toConsumableArray.js + 5 modules
var toConsumableArray = __webpack_require__("75fc");

// EXTERNAL MODULE: ./node_modules/core-js/modules/web.dom.iterable.js
var web_dom_iterable = __webpack_require__("ac6a");

// EXTERNAL MODULE: external {"commonjs":"vue","commonjs2":"vue","root":"Vue"}
var external_commonjs_vue_commonjs2_vue_root_Vue_ = __webpack_require__("8bbf");
var external_commonjs_vue_commonjs2_vue_root_Vue_default = /*#__PURE__*/__webpack_require__.n(external_commonjs_vue_commonjs2_vue_root_Vue_);

// EXTERNAL MODULE: ./node_modules/file-saver/dist/FileSaver.min.js
var FileSaver_min = __webpack_require__("21a6");

// EXTERNAL MODULE: ./src/libraries/index.js + 1 modules
var libraries = __webpack_require__("0336");

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"6de6a0ba-vue-loader-template"}!./node_modules/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/DownloadPreview.vue?vue&type=template&id=ccab0d52&scoped=true&
var DownloadPreviewvue_type_template_id_ccab0d52_scoped_true_render = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('div',{staticClass:"download-preview"},[_vm._m(0),_c('ul',_vm._l((_vm.lines),function(line,index){return _c('li',{key:index},[_vm._v("\n      "+_vm._s(line)+"\n    ")])}),0)])}
var DownloadPreviewvue_type_template_id_ccab0d52_scoped_true_staticRenderFns = [function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('p',[_vm._v("The following data will be exported to a file."),_c('br'),_vm._v("\n  You can use the exported fields as a refrence for mapping new values in REDCap.")])}]


// CONCATENATED MODULE: ./src/components/DownloadPreview.vue?vue&type=template&id=ccab0d52&scoped=true&

// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/DownloadPreview.vue?vue&type=script&lang=js&
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
/* harmony default export */ var DownloadPreviewvue_type_script_lang_js_ = ({
  name: 'download-preview',
  props: {
    lines: {
      type: Array,
      default: function _default() {
        return [];
      }
    }
  }
});
// CONCATENATED MODULE: ./src/components/DownloadPreview.vue?vue&type=script&lang=js&
 /* harmony default export */ var components_DownloadPreviewvue_type_script_lang_js_ = (DownloadPreviewvue_type_script_lang_js_); 
// EXTERNAL MODULE: ./src/components/DownloadPreview.vue?vue&type=style&index=0&id=ccab0d52&scoped=true&lang=css&
var DownloadPreviewvue_type_style_index_0_id_ccab0d52_scoped_true_lang_css_ = __webpack_require__("95d6");

// EXTERNAL MODULE: ./node_modules/vue-loader/lib/runtime/componentNormalizer.js
var componentNormalizer = __webpack_require__("2877");

// CONCATENATED MODULE: ./src/components/DownloadPreview.vue






/* normalize component */

var component = Object(componentNormalizer["a" /* default */])(
  components_DownloadPreviewvue_type_script_lang_js_,
  DownloadPreviewvue_type_template_id_ccab0d52_scoped_true_render,
  DownloadPreviewvue_type_template_id_ccab0d52_scoped_true_staticRenderFns,
  false,
  null,
  "ccab0d52",
  null
  
)

/* harmony default export */ var DownloadPreview = (component.exports);
// CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--12-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/cache-loader/dist/cjs.js??ref--0-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/observation/ObservationTable.vue?vue&type=script&lang=js&





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
//
//
//
//
//
// blocklisted codes




/* harmony default export */ var ObservationTablevue_type_script_lang_js_ = ({
  name: 'ObservationTable',
  data: function data() {
    return {
      hide_blocklisted: false,
      show_groups: false,
      codes_to_export: [],
      internal_codes: null // internal state for the mapped codes

    };
  },
  props: {
    entries: {
      type: Array,
      default: function _default() {
        return [];
      }
    }
  },
  computed: {
    lang: function lang() {
      var lang = this.$store.state.settings.settings.lang;
      return lang;
    },
    codes_blocklist: function codes_blocklist() {
      var blocklisted_codes = this.$store.state.settings.settings.blocklisted_codes;
      return blocklisted_codes;
    },

    /**
     * get a list of the codings based on the selected codes
     */
    values_to_export: function values_to_export() {
      var _this = this;

      var codings = []; // keep track of the codes pushed in codings to avoid duplicates

      var track_codes = [];
      this.filtered_codings.forEach(function (coding) {
        if (_this.codes_to_export.indexOf(coding.code) >= 0) {
          // check for codes duplicates
          if (track_codes.indexOf(coding.code) < 0) {
            track_codes.push(coding.code);
            codings.push(coding);
          }
        }
      });
      return codings;
    },
    mapped_codes: function mapped_codes() {
      var codes = this.$store.getters['project/mappedCodes'];
      return codes;
    },

    /**
     * list of the mapped codes in REDCap
     */
    fhir_metadata_codes: function fhir_metadata_codes() {
      var codes = this.$store.state.fhir_metadata.codes;
      return codes;
    },

    /**
     * extract codings from entries
     */
    codings: function codings() {
      try {
        var entries = Object(toConsumableArray["a" /* default */])(this.entries);

        if (!Array.isArray(entries)) return;
        var codings = entries.reduce(function (all, entry) {
          return [].concat(Object(toConsumableArray["a" /* default */])(all), Object(toConsumableArray["a" /* default */])(entry.codings));
        }, []);
        return codings;
      } catch (error) {
        return [];
      }
    },

    /**
     * apply filters to codings (blocklist...)
     */
    filtered_codings: function filtered_codings() {
      var _this2 = this;

      var codings = this.codings;

      if (this.hide_blocklisted) {
        return codings.filter(function (coding) {
          return !_this2.isBlocklisted(coding.code);
        });
      }

      return codings;
    },

    /**
     * get a list of codes that could be exported
     */
    exportable: function exportable() {
      var _this3 = this;

      var codings = this.filtered_codings;
      var exportable = [];
      codings.forEach(function (coding) {
        var _coding$code = coding.code,
            code = _coding$code === void 0 ? '' : _coding$code;
        if (!_this3.isExportable(code)) return;
        if (exportable.indexOf(code) >= 0) return;
        exportable.push(code);
      });
      return exportable;
    }
  },
  methods: {
    toggleSelect: function toggleSelect(code) {
      var index = this.codes_to_export.indexOf(code);
      if (index < 0) this.codes_to_export.push(code);else this.codes_to_export.splice(index, 1);
    },
    isCodeSelected: function isCodeSelected(code) {
      return this.codes_to_export.indexOf(code) >= 0;
    },
    toggleExportableSelection: function toggleExportableSelection() {
      if (this.exportable.length === this.codes_to_export.length) {
        this.codes_to_export = [];
      } else {
        this.codes_to_export = Object(toConsumableArray["a" /* default */])(this.exportable);
      }
    },
    isAvailableInREDCap: function isAvailableInREDCap() {
      var code = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '';
      if (code.trim() == '') return true;
      if (this.isBlocklisted(code)) return true;
      var mapped_codes = this.fhir_metadata_codes;
      var mapped = mapped_codes.some(function (mapped_code) {
        return mapped_code === code;
      });
      return mapped;
    },

    /**
     * check if a code is blocklisted
     */
    isBlocklisted: function isBlocklisted() {
      var code = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '';
      var codes = this.codes_blocklist.map(function (element) {
        return element.code;
      });
      var index = codes.indexOf(code);
      if (index >= 0) return this.codes_blocklist[index].reason;else return false;
    },
    isMappedInProject: function isMappedInProject() {
      var code = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '';
      if (code.trim() == '') return true;
      if (this.isBlocklisted(code)) return true;
      var mapped_codes = this.mapped_codes;
      return mapped_codes.indexOf(code) >= 0;
    },
    isExportable: function isExportable(code) {
      var mapped_in_redcap = this.isAvailableInREDCap(code);
      var mapped_in_project = this.isMappedInProject(code);
      return mapped_in_redcap && !mapped_in_project;
    },
    displayNewCodeInfo: function displayNewCodeInfo(coding) {
      var code = coding.code;
      this.$swal.fire({
        title: "Code '".concat(code, "' not available"),
        icon: 'info',
        text: "".concat(this.lang['mapping_helper_03'])
      });
    },
    sendNotification: function () {
      var _sendNotification = Object(asyncToGenerator["a" /* default */])( /*#__PURE__*/regeneratorRuntime.mark(function _callee(coding) {
        var code, _this$$store$state$en, resource_type, interaction, mrn;

        return regeneratorRuntime.wrap(function _callee$(_context) {
          while (1) {
            switch (_context.prev = _context.next) {
              case 0:
                code = coding.code;
                _this$$store$state$en = this.$store.state.endpoint, resource_type = _this$$store$state$en.resource_type, interaction = _this$$store$state$en.interaction, mrn = _this$$store$state$en.mrn;
                _context.prev = 2;
                _context.next = 5;
                return this.$API.sendNotification({
                  code: code,
                  resource_type: resource_type,
                  interaction: interaction,
                  mrn: mrn
                });

              case 5:
                this.$swal.fire({
                  title: 'Success',
                  icon: 'success',
                  text: 'Your request has been sent to an admin.'
                });
                _context.next = 11;
                break;

              case 8:
                _context.prev = 8;
                _context.t0 = _context["catch"](2);
                this.$swal.fire({
                  title: 'Error',
                  icon: 'error',
                  text: 'There was an error sending your request.'
                });

              case 11:
              case "end":
                return _context.stop();
            }
          }
        }, _callee, this, [[2, 8]]);
      }));

      function sendNotification(_x) {
        return _sendNotification.apply(this, arguments);
      }

      return sendNotification;
    }(),

    /**
     * create the lisnes that will be exported
     */
    getLinesToExport: function getLinesToExport() {
      var lines = [];
      this.values_to_export.forEach(function (coding) {
        var line = "".concat(coding.code, "\t").concat(coding.display);
        lines.push(line);
      });
      return lines;
    },

    /**
     * show a preview of the text file that will be exported
     */
    showPreview: function () {
      var _showPreview = Object(asyncToGenerator["a" /* default */])( /*#__PURE__*/regeneratorRuntime.mark(function _callee2() {
        var lines, download_preview_component, properties, response, _response$value, response_value;

        return regeneratorRuntime.wrap(function _callee2$(_context2) {
          while (1) {
            switch (_context2.prev = _context2.next) {
              case 0:
                lines = this.getLinesToExport(); // Create a “subclass” of the base Vue constructor

                download_preview_component = external_commonjs_vue_commonjs2_vue_root_Vue_default.a.extend(DownloadPreview);
                properties = {
                  propsData: {
                    lines: lines
                  },
                  store: this.$store,
                  //pass along the store if you want
                  created: function created() {
                    console.log(this.$store);
                  }
                };
                _context2.next = 5;
                return this.$swal.fire({
                  icon: 'info',
                  title: 'Export fields',
                  confirmButtonText: 'Download',
                  component: download_preview_component,
                  component_args: properties
                });

              case 5:
                response = _context2.sent;
                _response$value = response.value, response_value = _response$value === void 0 ? false : _response$value;
                if (response_value) this.exportData(lines);

              case 8:
              case "end":
                return _context2.stop();
            }
          }
        }, _callee2, this);
      }));

      function showPreview() {
        return _showPreview.apply(this, arguments);
      }

      return showPreview;
    }(),

    /**
     * export data to file:
     * join array of lines using newline
     */
    exportData: function () {
      var _exportData = Object(asyncToGenerator["a" /* default */])( /*#__PURE__*/regeneratorRuntime.mark(function _callee3(lines) {
        var text, blob;
        return regeneratorRuntime.wrap(function _callee3$(_context3) {
          while (1) {
            switch (_context3.prev = _context3.next) {
              case 0:
                text = lines.join("\n");
                blob = new Blob([text], {
                  type: "text/plain;charset=utf-8"
                });
                Object(FileSaver_min["saveAs"])(blob, "fields.txt");

              case 3:
              case "end":
                return _context3.stop();
            }
          }
        }, _callee3);
      }));

      function exportData(_x2) {
        return _exportData.apply(this, arguments);
      }

      return exportData;
    }(),

    /**
     * formatDate from Utils
     */
    formatDate: libraries["a" /* formatDate */]
  }
});
// CONCATENATED MODULE: ./src/components/observation/ObservationTable.vue?vue&type=script&lang=js&
 /* harmony default export */ var observation_ObservationTablevue_type_script_lang_js_ = (ObservationTablevue_type_script_lang_js_); 
// EXTERNAL MODULE: ./src/components/observation/ObservationTable.vue?vue&type=style&index=0&id=5cf657c3&scoped=true&lang=css&
var ObservationTablevue_type_style_index_0_id_5cf657c3_scoped_true_lang_css_ = __webpack_require__("e1e7");

// CONCATENATED MODULE: ./src/components/observation/ObservationTable.vue






/* normalize component */

var ObservationTable_component = Object(componentNormalizer["a" /* default */])(
  observation_ObservationTablevue_type_script_lang_js_,
  render,
  staticRenderFns,
  false,
  null,
  "5cf657c3",
  null
  
)

/* harmony default export */ var ObservationTable = __webpack_exports__["default"] = (ObservationTable_component.exports);

/***/ }),

/***/ "f410":
/***/ (function(module, exports, __webpack_require__) {

__webpack_require__("1af6");
module.exports = __webpack_require__("584a").Array.isArray;


/***/ })

}]);
//# sourceMappingURL=mapping_helper_vue.umd.2.js.map