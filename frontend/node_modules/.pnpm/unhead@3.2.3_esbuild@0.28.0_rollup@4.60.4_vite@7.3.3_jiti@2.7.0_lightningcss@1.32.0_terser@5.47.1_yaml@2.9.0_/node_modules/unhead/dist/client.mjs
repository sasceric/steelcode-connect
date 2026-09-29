export { c as createHead } from './shared/unhead.C6wXFVPb.mjs';
export { c as createDomRenderer, r as renderDOMHead } from './shared/unhead.B2jza4FG.mjs';
import './shared/unhead.D7HkBzZn.mjs';
import './shared/unhead.1eoQpFT1.mjs';
import './shared/unhead.Bm4Y6XQI.mjs';
import 'hookable';
import './shared/unhead.Bb4d5b9h.mjs';
import './shared/unhead.DgxHWvUc.mjs';

function createDebouncedFn(callee, delayer) {
  let ctxId = 0;
  return () => {
    const delayFnCtxId = ++ctxId;
    delayer(() => {
      if (ctxId === delayFnCtxId) {
        callee();
      }
    });
  };
}

export { createDebouncedFn };
