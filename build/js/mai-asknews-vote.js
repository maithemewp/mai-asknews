/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./src/js/mai-asknews-vote.js":
/*!************************************!*\
  !*** ./src/js/mai-asknews-vote.js ***!
  \************************************/
/***/ (() => {

document.addEventListener('DOMContentLoaded', function () {
  // Get the form element.
  var form = document.querySelector('.pm-vote__form');

  // If the form element does not exist, stop the script.
  if (!form) {
    return;
  }

  // On form submit.
  form.addEventListener('submit', function (event) {
    // Prevent the default form submission
    event.preventDefault();

    // Disable the button.
    event.submitter.disabled = true;

    // Get the selected element.
    var selectedEl = form.querySelector('.pm-outcome__selected');

    // If we have one, fade out then remove it.
    if (selectedEl) {
      selectedEl.remove();
    }

    // Collect form data.
    var formData = new FormData(form);

    // Add the team value from the button that was clicked.
    formData.append('team', event.submitter.value);

    // Store the original button text.
    var buttonText = '';

    // Loop through the child nodes to find the text node.
    event.submitter.childNodes.forEach(function (node) {
      if (Node.TEXT_NODE === node.nodeType) {
        // Store the text content.
        buttonText = node.textContent.trim();
        // Remove the text node.
        node.textContent = '';
      }
    });

    // Add a loading icon or text while waiting for the response.
    event.submitter.insertAdjacentHTML('afterbegin', '<span class="pm-loading-wrap"><svg class="pm-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Pro 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2024 Fonticons, Inc.--><path class="fa-secondary" opacity=".4" d="M478.7 364.6zm-22 6.1l-27.8-15.9a15.9 15.9 0 0 1 -6.9-19.2A184 184 0 1 1 256 72c5.9 0 11.7 .3 17.5 .8-.7-.1-1.5-.2-2.2-.2-8.5-.7-15.2-7.3-15.2-15.8v-32a16 16 0 0 1 15.3-16C266.2 8.5 261.2 8 256 8 119 8 8 119 8 256s111 248 248 248c98 0 182.4-57 222.7-139.4-4.1 7.9-14.2 10.6-22 6.1z"/><path class="fa-primary" d="M271.2 72.6c-8.5-.7-15.2-7.3-15.2-15.8V24.7c0-9.1 7.7-16.8 16.8-16.2C401.9 17.2 504 124.7 504 256a246 246 0 0 1 -25 108.2c-4 8.2-14.4 11-22.3 6.5l-27.8-15.9c-7.4-4.2-9.8-13.4-6.2-21.1A182.5 182.5 0 0 0 440 256c0-96.5-74.3-175.6-168.8-183.4z"/></svg> Saving...</span>');

    // Send the form data to the server using Fetch API.
    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    // .then( response => response.json() ) // Assuming the server returns JSON
    .then(function (data) {
      // Remove the loading icon.
      event.submitter.querySelector('.pm-loading-wrap').remove();

      // Restore original button text.
      event.submitter.insertAdjacentHTML('afterbegin', buttonText);

      // Enable the button.
      event.submitter.disabled = false;

      // If successful, add the selected element back.
      if (200 === data.status) {
        event.submitter.insertAdjacentHTML('beforeend', maiAskNewsVars.selected);
      }
    })["catch"](function (error) {
      console.error('Error:', error);

      // Remove the loading icon.
      event.submitter.querySelector('.pm-loading-wrap').remove();

      // Restore original button text.
      event.submitter.insertAdjacentHTML('afterbegin', buttonText);

      // Enable the button.
      event.submitter.disabled = false;
    });
  });
});

/***/ }),

/***/ "./src/css/mai-asknews.css":
/*!*********************************!*\
  !*** ./src/css/mai-asknews.css ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./build/css/mai-asknews.css":
/*!***********************************!*\
  !*** ./build/css/mai-asknews.css ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"/build/js/mai-asknews-vote": 0,
/******/ 			"build/css/mai-asknews.min": 0,
/******/ 			"build/css/mai-asknews": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunkmai_asknews"] = self["webpackChunkmai_asknews"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	__webpack_require__.O(undefined, ["build/css/mai-asknews.min","build/css/mai-asknews"], () => (__webpack_require__("./src/js/mai-asknews-vote.js")))
/******/ 	__webpack_require__.O(undefined, ["build/css/mai-asknews.min","build/css/mai-asknews"], () => (__webpack_require__("./src/css/mai-asknews.css")))
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["build/css/mai-asknews.min","build/css/mai-asknews"], () => (__webpack_require__("./build/css/mai-asknews.css")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;