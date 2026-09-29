import process from 'node:process';globalThis._importMeta_={url:import.meta.url,env:process.env};import { tmpdir } from 'node:os';
import { defineEventHandler, handleCacheHeaders, splitCookiesString, createEvent, fetchWithEvent, isEvent, eventHandler, setHeaders, createError, sendRedirect, proxyRequest, getRequestHeader, setResponseHeaders, setResponseStatus, send, getRequestHeaders, setResponseHeader, appendResponseHeader, getRequestURL, getResponseHeader, getResponseStatus, getCookie, setCookie, sanitizeStatusCode, removeResponseHeader, getRouterParam, getQuery as getQuery$1, readBody, createApp, createRouter as createRouter$1, toNodeListener, lazyEventHandler, deleteCookie, getResponseStatusText } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/h3@1.15.11/node_modules/h3/dist/index.mjs';
import { Server } from 'node:http';
import { resolve, dirname, join } from 'node:path';
import crypto$1 from 'node:crypto';
import { parentPort, threadId } from 'node:worker_threads';
import { escapeHtml } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/@vue+shared@3.5.40/node_modules/@vue/shared/dist/shared.cjs.js';
import viteNodeEntry_mjs from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/@nuxt+vite-builder@4.4.8_00ac5874dc1ab996ce95dcc8d4923245/node_modules/@nuxt/vite-builder/dist/vite-node-entry.mjs';
import { viteNodeFetch } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/@nuxt+vite-builder@4.4.8_00ac5874dc1ab996ce95dcc8d4923245/node_modules/@nuxt/vite-builder/dist/vite-node.mjs';
import { sub } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/date-fns@4.4.0/node_modules/date-fns/index.js';
import { createRenderer, getRequestDependencies, getPreloadLinks, getPrefetchLinks } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/vue-bundle-renderer@2.2.0/node_modules/vue-bundle-renderer/dist/runtime.mjs';
import { parseURL, withoutBase, joinURL, getQuery, withQuery, joinRelativeURL, withTrailingSlash, withoutTrailingSlash, parsePath, withLeadingSlash, decodePath, parseQuery, encodePath } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/ufo@1.6.4/node_modules/ufo/dist/index.mjs';
import destr, { destr as destr$1 } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/destr@2.0.5/node_modules/destr/dist/index.mjs';
import { createHooks } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/hookable@5.5.3/node_modules/hookable/dist/index.mjs';
import { createFetch, Headers as Headers$1 } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/ofetch@1.5.1/node_modules/ofetch/dist/node.mjs';
import { fetchNodeRequestHandler, callNodeRequestHandler } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/node-mock-http@1.0.4/node_modules/node-mock-http/dist/index.mjs';
import { createStorage, defineDriver, prefixStorage } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/unstorage@1.17.5_db0@0.3.4_ioredis@5.10.1_supports-color@10.2.2_/node_modules/unstorage/dist/index.mjs';
import unstorage_47drivers_47fs from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/unstorage@1.17.5_db0@0.3.4_ioredis@5.10.1_supports-color@10.2.2_/node_modules/unstorage/drivers/fs.mjs';
import fsDriver from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/unstorage@1.17.5_db0@0.3.4_ioredis@5.10.1_supports-color@10.2.2_/node_modules/unstorage/drivers/fs-lite.mjs';
import lruCache from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/unstorage@1.17.5_db0@0.3.4_ioredis@5.10.1_supports-color@10.2.2_/node_modules/unstorage/drivers/lru-cache.mjs';
import { digest, hash as hash$1 } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/ohash@2.0.11/node_modules/ohash/dist/index.mjs';
import { klona } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/klona@2.0.6/node_modules/klona/dist/index.mjs';
import defu, { defuFn, createDefu } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/defu@6.1.7/node_modules/defu/dist/defu.mjs';
import { snakeCase } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/scule@1.3.0/node_modules/scule/dist/index.mjs';
import { getContext } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/unctx@2.5.0/node_modules/unctx/dist/index.mjs';
import { toRouteMatcher, createRouter } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/radix3@1.1.2/node_modules/radix3/dist/index.mjs';
import { readFile } from 'node:fs/promises';
import consola, { consola as consola$1 } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/consola@3.4.2/node_modules/consola/dist/index.mjs';
import { ErrorParser } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/youch-core@0.3.3/node_modules/youch-core/build/index.js';
import { Youch } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/youch@4.1.1/node_modules/youch/build/index.js';
import { SourceMapConsumer } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/source-map@0.7.6/node_modules/source-map/source-map.js';
import { createRouterMatcher } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/vue-router@5.2.0_@vue+compiler-sfc@3.5.40_esbuild@0.28.0_rollup@4.60.4_vite@7.3.3_jiti@_2fdc67fd4959a2bc827fb725de90ad45/node_modules/vue-router/vue-router.node.mjs';
import { AsyncLocalStorage } from 'node:async_hooks';
import { stringify, uneval } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/devalue@5.8.1/node_modules/devalue/index.js';
import { captureRawStackTrace, parseRawStackTrace } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/errx@0.1.0/node_modules/errx/dist/index.js';
import { isVNode, isRef, toValue } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/vue@3.5.40_typescript@6.0.3/node_modules/vue/index.mjs';
import _wH6JrtIxmaSoA8lCPWFnE9z4lQeXW6H5z3l5aymEQw from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/@nuxt+vite-builder@4.4.8_00ac5874dc1ab996ce95dcc8d4923245/node_modules/@nuxt/vite-builder/dist/fix-stacktrace.mjs';
import { promises } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname as dirname$1, resolve as resolve$1 } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/pathe@2.0.3/node_modules/pathe/dist/index.mjs';
import { getIcons } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/@iconify+utils@3.1.3/node_modules/@iconify/utils/lib/index.js';
import { collections } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/.nuxt/nuxt-icon-server-bundle.mjs';
import { createHead as createHead$1, propsToString, renderSSRHead } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/unhead@2.1.15/node_modules/unhead/dist/server.mjs';
import { renderToString } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/vue@3.5.40_typescript@6.0.3/node_modules/vue/server-renderer/index.mjs';
import { walkResolver } from 'file:///Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/unhead@2.1.15/node_modules/unhead/dist/utils.mjs';

const serverAssets = [{"baseName":"server","dir":"/Users/suadasceric/Projects/steelcode-connect/frontend/server/assets"}];

const assets$1 = createStorage();

for (const asset of serverAssets) {
  assets$1.mount(asset.baseName, unstorage_47drivers_47fs({ base: asset.dir, ignore: (asset?.ignore || []) }));
}

// @ts-check


/**
 * @param {string} item
 */
function normalizeFsKey (item) {
  const safe = item.replace(/[^\w.-]/g, '_');
  const prefix = safe.slice(0, 20);
  const hash = crypto$1.createHash('sha256').update(item).digest('hex');
  return `${prefix}-${hash}`
}

const _47Users_47suadasceric_47Projects_47steelcode_45connect_47frontend_47node_modules_47_46pnpm_47_64nuxt_43nitro_45server_644_464_468__64babel_43plugin_45syntax_45typescript_647_4628_466__64babel_43core_647_4629_460_supp_f1b913d161660510662219a0b5f80400_47node_modules_47_64nuxt_47nitro_45server_47dist_47runtime_47utils_47cache_45driver_46js = defineDriver(
  /**
   * @param {{ base?: string }} opts
   */
  (opts) => {
    const fs = fsDriver({ base: opts.base });
    const lru = lruCache({ max: 1000 });

    return {
      ...fs, // fall back to file system - only the bottom three methods are used in renderer
      async setItem (key, value, opts) {
        await Promise.all([
          fs.setItem?.(normalizeFsKey(key), value, opts),
          lru.setItem?.(key, value, opts),
        ]);
      },
      async hasItem (key, opts) {
        return await lru.hasItem(key, opts) || await fs.hasItem(normalizeFsKey(key), opts)
      },
      async getItem (key, opts) {
        return await lru.getItem(key, opts) || await fs.getItem(normalizeFsKey(key), opts)
      },
    }
  },
);

const storage$1 = createStorage({});

storage$1.mount('/assets', assets$1);

storage$1.mount('root', unstorage_47drivers_47fs({"driver":"fs","readOnly":true,"base":"/Users/suadasceric/Projects/steelcode-connect/frontend","watchOptions":{"ignored":[null]}}));
storage$1.mount('src', unstorage_47drivers_47fs({"driver":"fs","readOnly":true,"base":"/Users/suadasceric/Projects/steelcode-connect/frontend/server","watchOptions":{"ignored":[null]}}));
storage$1.mount('cache:nuxt:payload', _47Users_47suadasceric_47Projects_47steelcode_45connect_47frontend_47node_modules_47_46pnpm_47_64nuxt_43nitro_45server_644_464_468__64babel_43plugin_45syntax_45typescript_647_4628_466__64babel_43core_647_4629_460_supp_f1b913d161660510662219a0b5f80400_47node_modules_47_64nuxt_47nitro_45server_47dist_47runtime_47utils_47cache_45driver_46js({"driver":"/Users/suadasceric/Projects/steelcode-connect/frontend/node_modules/.pnpm/@nuxt+nitro-server@4.4.8_@babel+plugin-syntax-typescript@7.28.6_@babel+core@7.29.0_supp_f1b913d161660510662219a0b5f80400/node_modules/@nuxt/nitro-server/dist/runtime/utils/cache-driver.js","base":"/Users/suadasceric/Projects/steelcode-connect/frontend/.nuxt/cache/nuxt/payload"}));
storage$1.mount('build', unstorage_47drivers_47fs({"driver":"fs","readOnly":false,"base":"/Users/suadasceric/Projects/steelcode-connect/frontend/.nuxt"}));
storage$1.mount('cache', unstorage_47drivers_47fs({"driver":"fs","readOnly":false,"base":"/Users/suadasceric/Projects/steelcode-connect/frontend/.nuxt/cache"}));
storage$1.mount('data', unstorage_47drivers_47fs({"driver":"fs","base":"/Users/suadasceric/Projects/steelcode-connect/frontend/.data/kv"}));

function useStorage(base = "") {
  return base ? prefixStorage(storage$1, base) : storage$1;
}

const Hasher = /* @__PURE__ */ (() => {
  class Hasher2 {
    buff = "";
    #context = /* @__PURE__ */ new Map();
    write(str) {
      this.buff += str;
    }
    dispatch(value) {
      const type = value === null ? "null" : typeof value;
      return this[type](value);
    }
    object(object) {
      if (object && typeof object.toJSON === "function") {
        return this.object(object.toJSON());
      }
      const objString = Object.prototype.toString.call(object);
      let objType = "";
      const objectLength = objString.length;
      objType = objectLength < 10 ? "unknown:[" + objString + "]" : objString.slice(8, objectLength - 1);
      objType = objType.toLowerCase();
      let objectNumber = null;
      if ((objectNumber = this.#context.get(object)) === void 0) {
        this.#context.set(object, this.#context.size);
      } else {
        return this.dispatch("[CIRCULAR:" + objectNumber + "]");
      }
      if (typeof Buffer !== "undefined" && Buffer.isBuffer && Buffer.isBuffer(object)) {
        this.write("buffer:");
        return this.write(object.toString("utf8"));
      }
      if (objType !== "object" && objType !== "function" && objType !== "asyncfunction") {
        if (this[objType]) {
          this[objType](object);
        } else {
          this.unknown(object, objType);
        }
      } else {
        const keys = Object.keys(object).sort();
        const extraKeys = [];
        this.write("object:" + (keys.length + extraKeys.length) + ":");
        const dispatchForKey = (key) => {
          this.dispatch(key);
          this.write(":");
          this.dispatch(object[key]);
          this.write(",");
        };
        for (const key of keys) {
          dispatchForKey(key);
        }
        for (const key of extraKeys) {
          dispatchForKey(key);
        }
      }
    }
    array(arr, unordered) {
      unordered = unordered === void 0 ? false : unordered;
      this.write("array:" + arr.length + ":");
      if (!unordered || arr.length <= 1) {
        for (const entry of arr) {
          this.dispatch(entry);
        }
        return;
      }
      const contextAdditions = /* @__PURE__ */ new Map();
      const entries = arr.map((entry) => {
        const hasher = new Hasher2();
        hasher.dispatch(entry);
        for (const [key, value] of hasher.#context) {
          contextAdditions.set(key, value);
        }
        return hasher.toString();
      });
      this.#context = contextAdditions;
      entries.sort();
      return this.array(entries, false);
    }
    date(date) {
      return this.write("date:" + date.toJSON());
    }
    symbol(sym) {
      return this.write("symbol:" + sym.toString());
    }
    unknown(value, type) {
      this.write(type);
      if (!value) {
        return;
      }
      this.write(":");
      if (value && typeof value.entries === "function") {
        return this.array(
          [...value.entries()],
          true
          /* ordered */
        );
      }
    }
    error(err) {
      return this.write("error:" + err.toString());
    }
    boolean(bool) {
      return this.write("bool:" + bool);
    }
    string(string) {
      this.write("string:" + string.length + ":");
      this.write(string);
    }
    function(fn) {
      this.write("fn:");
      if (isNativeFunction(fn)) {
        this.dispatch("[native]");
      } else {
        this.dispatch(fn.toString());
      }
    }
    number(number) {
      return this.write("number:" + number);
    }
    null() {
      return this.write("Null");
    }
    undefined() {
      return this.write("Undefined");
    }
    regexp(regex) {
      return this.write("regex:" + regex.toString());
    }
    arraybuffer(arr) {
      this.write("arraybuffer:");
      return this.dispatch(new Uint8Array(arr));
    }
    url(url) {
      return this.write("url:" + url.toString());
    }
    map(map) {
      this.write("map:");
      const arr = [...map];
      return this.array(arr, false);
    }
    set(set) {
      this.write("set:");
      const arr = [...set];
      return this.array(arr, false);
    }
    bigint(number) {
      return this.write("bigint:" + number.toString());
    }
  }
  for (const type of [
    "uint8array",
    "uint8clampedarray",
    "unt8array",
    "uint16array",
    "unt16array",
    "uint32array",
    "unt32array",
    "float32array",
    "float64array"
  ]) {
    Hasher2.prototype[type] = function(arr) {
      this.write(type + ":");
      return this.array([...arr], false);
    };
  }
  function isNativeFunction(f) {
    if (typeof f !== "function") {
      return false;
    }
    return Function.prototype.toString.call(f).slice(
      -15
      /* "[native code] }".length */
    ) === "[native code] }";
  }
  return Hasher2;
})();
function serialize(object) {
  const hasher = new Hasher();
  hasher.dispatch(object);
  return hasher.buff;
}
function hash(value) {
  return digest(typeof value === "string" ? value : serialize(value)).replace(/[-_]/g, "").slice(0, 10);
}

function defaultCacheOptions() {
  return {
    name: "_",
    base: "/cache",
    swr: true,
    maxAge: 1
  };
}
function defineCachedFunction(fn, opts = {}) {
  opts = { ...defaultCacheOptions(), ...opts };
  const pending = {};
  const group = opts.group || "nitro/functions";
  const name = opts.name || fn.name || "_";
  const integrity = opts.integrity || hash([fn, opts]);
  const validate = opts.validate || ((entry) => entry.value !== void 0);
  async function get(key, resolver, shouldInvalidateCache, event) {
    const cacheKey = [opts.base, group, name, key + ".json"].filter(Boolean).join(":").replace(/:\/$/, ":index");
    let entry = await useStorage().getItem(cacheKey).catch((error) => {
      console.error(`[cache] Cache read error.`, error);
      useNitroApp().captureError(error, { event, tags: ["cache"] });
    }) || {};
    if (typeof entry !== "object") {
      entry = {};
      const error = new Error("Malformed data read from cache.");
      console.error("[cache]", error);
      useNitroApp().captureError(error, { event, tags: ["cache"] });
    }
    const ttl = (opts.maxAge ?? 0) * 1e3;
    if (ttl) {
      entry.expires = Date.now() + ttl;
    }
    const expired = shouldInvalidateCache || entry.integrity !== integrity || ttl && Date.now() - (entry.mtime || 0) > ttl || validate(entry) === false;
    const _resolve = async () => {
      const isPending = pending[key];
      if (!isPending) {
        if (entry.value !== void 0 && (opts.staleMaxAge || 0) >= 0 && opts.swr === false) {
          entry.value = void 0;
          entry.integrity = void 0;
          entry.mtime = void 0;
          entry.expires = void 0;
        }
        pending[key] = Promise.resolve(resolver());
      }
      try {
        entry.value = await pending[key];
      } catch (error) {
        if (!isPending) {
          delete pending[key];
        }
        throw error;
      }
      if (!isPending) {
        entry.mtime = Date.now();
        entry.integrity = integrity;
        delete pending[key];
        if (validate(entry) !== false) {
          let setOpts;
          if (opts.maxAge && !opts.swr) {
            setOpts = { ttl: opts.maxAge };
          }
          const promise = useStorage().setItem(cacheKey, entry, setOpts).catch((error) => {
            console.error(`[cache] Cache write error.`, error);
            useNitroApp().captureError(error, { event, tags: ["cache"] });
          });
          if (event?.waitUntil) {
            event.waitUntil(promise);
          }
        }
      }
    };
    const _resolvePromise = expired ? _resolve() : Promise.resolve();
    if (entry.value === void 0) {
      await _resolvePromise;
    } else if (expired && event && event.waitUntil) {
      event.waitUntil(_resolvePromise);
    }
    if (opts.swr && validate(entry) !== false) {
      _resolvePromise.catch((error) => {
        console.error(`[cache] SWR handler error.`, error);
        useNitroApp().captureError(error, { event, tags: ["cache"] });
      });
      return entry;
    }
    return _resolvePromise.then(() => entry);
  }
  return async (...args) => {
    const shouldBypassCache = await opts.shouldBypassCache?.(...args);
    if (shouldBypassCache) {
      return fn(...args);
    }
    const key = await (opts.getKey || getKey)(...args);
    const shouldInvalidateCache = await opts.shouldInvalidateCache?.(...args);
    const entry = await get(
      key,
      () => fn(...args),
      shouldInvalidateCache,
      args[0] && isEvent(args[0]) ? args[0] : void 0
    );
    let value = entry.value;
    if (opts.transform) {
      value = await opts.transform(entry, ...args) || value;
    }
    return value;
  };
}
function cachedFunction(fn, opts = {}) {
  return defineCachedFunction(fn, opts);
}
function getKey(...args) {
  return args.length > 0 ? hash(args) : "";
}
function escapeKey(key) {
  return String(key).replace(/\W/g, "");
}
function defineCachedEventHandler(handler, opts = defaultCacheOptions()) {
  const variableHeaderNames = (opts.varies || []).filter(Boolean).map((h) => h.toLowerCase()).sort();
  const _opts = {
    ...opts,
    getKey: async (event) => {
      const customKey = await opts.getKey?.(event);
      if (customKey) {
        return escapeKey(customKey);
      }
      const _path = event.node.req.originalUrl || event.node.req.url || event.path;
      let _pathname;
      try {
        _pathname = escapeKey(decodeURI(parseURL(_path).pathname)).slice(0, 16) || "index";
      } catch {
        _pathname = "-";
      }
      const _hashedPath = `${_pathname}.${hash(_path)}`;
      const _headers = variableHeaderNames.map((header) => [header, event.node.req.headers[header]]).map(([name, value]) => `${escapeKey(name)}.${hash(value)}`);
      return [_hashedPath, ..._headers].join(":");
    },
    validate: (entry) => {
      if (!entry.value) {
        return false;
      }
      if (entry.value.code >= 400) {
        return false;
      }
      if (entry.value.body === void 0) {
        return false;
      }
      if (entry.value.headers.etag === "undefined" || entry.value.headers["last-modified"] === "undefined") {
        return false;
      }
      return true;
    },
    group: opts.group || "nitro/handlers",
    integrity: opts.integrity || hash([handler, opts])
  };
  const _cachedHandler = cachedFunction(
    async (incomingEvent) => {
      const variableHeaders = {};
      for (const header of variableHeaderNames) {
        const value = incomingEvent.node.req.headers[header];
        if (value !== void 0) {
          variableHeaders[header] = value;
        }
      }
      const reqProxy = cloneWithProxy(incomingEvent.node.req, {
        headers: variableHeaders
      });
      const resHeaders = {};
      let _resSendBody;
      const resProxy = cloneWithProxy(incomingEvent.node.res, {
        statusCode: 200,
        writableEnded: false,
        writableFinished: false,
        headersSent: false,
        closed: false,
        getHeader(name) {
          return resHeaders[name];
        },
        setHeader(name, value) {
          resHeaders[name] = value;
          return this;
        },
        getHeaderNames() {
          return Object.keys(resHeaders);
        },
        hasHeader(name) {
          return name in resHeaders;
        },
        removeHeader(name) {
          delete resHeaders[name];
        },
        getHeaders() {
          return resHeaders;
        },
        end(chunk, arg2, arg3) {
          if (typeof chunk === "string") {
            _resSendBody = chunk;
          }
          if (typeof arg2 === "function") {
            arg2();
          }
          if (typeof arg3 === "function") {
            arg3();
          }
          return this;
        },
        write(chunk, arg2, arg3) {
          if (typeof chunk === "string") {
            _resSendBody = chunk;
          }
          if (typeof arg2 === "function") {
            arg2(void 0);
          }
          if (typeof arg3 === "function") {
            arg3();
          }
          return true;
        },
        writeHead(statusCode, headers2) {
          this.statusCode = statusCode;
          if (headers2) {
            if (Array.isArray(headers2) || typeof headers2 === "string") {
              throw new TypeError("Raw headers  is not supported.");
            }
            for (const header in headers2) {
              const value = headers2[header];
              if (value !== void 0) {
                this.setHeader(
                  header,
                  value
                );
              }
            }
          }
          return this;
        }
      });
      const event = createEvent(reqProxy, resProxy);
      event.fetch = (url, fetchOptions) => fetchWithEvent(event, url, fetchOptions, {
        fetch: useNitroApp().localFetch
      });
      event.$fetch = (url, fetchOptions) => fetchWithEvent(event, url, fetchOptions, {
        fetch: globalThis.$fetch
      });
      event.waitUntil = incomingEvent.waitUntil;
      event.context = incomingEvent.context;
      event.context.cache = {
        options: _opts
      };
      const body = await handler(event) || _resSendBody;
      const headers = event.node.res.getHeaders();
      headers.etag = String(
        headers.Etag || headers.etag || `W/"${hash(body)}"`
      );
      headers["last-modified"] = String(
        headers["Last-Modified"] || headers["last-modified"] || (/* @__PURE__ */ new Date()).toUTCString()
      );
      const cacheControl = [];
      if (opts.swr) {
        if (opts.maxAge) {
          cacheControl.push(`s-maxage=${opts.maxAge}`);
        }
        if (opts.staleMaxAge) {
          cacheControl.push(`stale-while-revalidate=${opts.staleMaxAge}`);
        } else {
          cacheControl.push("stale-while-revalidate");
        }
      } else if (opts.maxAge) {
        cacheControl.push(`max-age=${opts.maxAge}`);
      }
      if (cacheControl.length > 0) {
        headers["cache-control"] = cacheControl.join(", ");
      }
      const cacheEntry = {
        code: event.node.res.statusCode,
        headers,
        body
      };
      return cacheEntry;
    },
    _opts
  );
  return defineEventHandler(async (event) => {
    if (opts.headersOnly) {
      if (handleCacheHeaders(event, { maxAge: opts.maxAge })) {
        return;
      }
      return handler(event);
    }
    const response = await _cachedHandler(
      event
    );
    if (event.node.res.headersSent || event.node.res.writableEnded) {
      return response.body;
    }
    if (handleCacheHeaders(event, {
      modifiedTime: new Date(response.headers["last-modified"]),
      etag: response.headers.etag,
      maxAge: opts.maxAge
    })) {
      return;
    }
    event.node.res.statusCode = response.code;
    for (const name in response.headers) {
      const value = response.headers[name];
      if (name === "set-cookie") {
        event.node.res.appendHeader(
          name,
          splitCookiesString(value)
        );
      } else {
        if (value !== void 0) {
          event.node.res.setHeader(name, value);
        }
      }
    }
    return response.body;
  });
}
function cloneWithProxy(obj, overrides) {
  return new Proxy(obj, {
    get(target, property, receiver) {
      if (property in overrides) {
        return overrides[property];
      }
      return Reflect.get(target, property, receiver);
    },
    set(target, property, value, receiver) {
      if (property in overrides) {
        overrides[property] = value;
        return true;
      }
      return Reflect.set(target, property, value, receiver);
    }
  });
}
const cachedEventHandler = defineCachedEventHandler;

const defineAppConfig = (config) => config;

const appConfig0 = defineAppConfig({
  ui: {
    colors: {
      primary: "blue",
      neutral: "zinc"
    }
  }
});

const inlineAppConfig = {
  "nuxt": {},
  "ui": {
    "colors": {
      "primary": "green",
      "secondary": "blue",
      "success": "green",
      "info": "blue",
      "warning": "yellow",
      "error": "red",
      "neutral": "slate"
    },
    "icons": {
      "arrowDown": "i-lucide-arrow-down",
      "arrowLeft": "i-lucide-arrow-left",
      "arrowRight": "i-lucide-arrow-right",
      "arrowUp": "i-lucide-arrow-up",
      "caution": "i-lucide-circle-alert",
      "check": "i-lucide-check",
      "chevronDoubleLeft": "i-lucide-chevrons-left",
      "chevronDoubleRight": "i-lucide-chevrons-right",
      "chevronDown": "i-lucide-chevron-down",
      "chevronLeft": "i-lucide-chevron-left",
      "chevronRight": "i-lucide-chevron-right",
      "chevronUp": "i-lucide-chevron-up",
      "close": "i-lucide-x",
      "copy": "i-lucide-copy",
      "copyCheck": "i-lucide-copy-check",
      "dark": "i-lucide-moon",
      "drag": "i-lucide-grip-vertical",
      "ellipsis": "i-lucide-ellipsis",
      "error": "i-lucide-circle-x",
      "external": "i-lucide-arrow-up-right",
      "eye": "i-lucide-eye",
      "eyeOff": "i-lucide-eye-off",
      "file": "i-lucide-file",
      "folder": "i-lucide-folder",
      "folderOpen": "i-lucide-folder-open",
      "hash": "i-lucide-hash",
      "info": "i-lucide-info",
      "light": "i-lucide-sun",
      "loading": "i-lucide-loader-circle",
      "menu": "i-lucide-menu",
      "minus": "i-lucide-minus",
      "panelClose": "i-lucide-panel-left-close",
      "panelOpen": "i-lucide-panel-left-open",
      "plus": "i-lucide-plus",
      "reload": "i-lucide-rotate-ccw",
      "search": "i-lucide-search",
      "stop": "i-lucide-square",
      "star": "i-lucide-star",
      "success": "i-lucide-circle-check",
      "system": "i-lucide-monitor",
      "tip": "i-lucide-lightbulb",
      "upload": "i-lucide-upload",
      "warning": "i-lucide-triangle-alert"
    },
    "tv": {
      "twMergeConfig": {}
    }
  },
  "icon": {
    "provider": "server",
    "class": "",
    "aliases": {},
    "iconifyApiEndpoint": "https://api.iconify.design",
    "localApiEndpoint": "/api/_nuxt_icon",
    "fallbackToApi": true,
    "cssSelectorPrefix": "i-",
    "cssWherePseudo": true,
    "cssLayer": "base",
    "mode": "css",
    "attrs": {
      "aria-hidden": true
    },
    "collections": [
      "academicons",
      "akar-icons",
      "ant-design",
      "arcticons",
      "basil",
      "bi",
      "bitcoin-icons",
      "bpmn",
      "brandico",
      "bx",
      "bxl",
      "bxs",
      "bytesize",
      "carbon",
      "catppuccin",
      "cbi",
      "charm",
      "ci",
      "cib",
      "cif",
      "cil",
      "circle-flags",
      "circum",
      "clarity",
      "codex",
      "codicon",
      "covid",
      "cryptocurrency",
      "cryptocurrency-color",
      "cuida",
      "dashicons",
      "devicon",
      "devicon-plain",
      "dinkie-icons",
      "duo-icons",
      "ei",
      "el",
      "emojione",
      "emojione-monotone",
      "emojione-v1",
      "entypo",
      "entypo-social",
      "eos-icons",
      "ep",
      "et",
      "eva",
      "f7",
      "fa",
      "fa-brands",
      "fa-regular",
      "fa-solid",
      "fa6-brands",
      "fa6-regular",
      "fa6-solid",
      "fa7-brands",
      "fa7-regular",
      "fa7-solid",
      "fad",
      "famicons",
      "fe",
      "feather",
      "file-icons",
      "flag",
      "flagpack",
      "flat-color-icons",
      "flat-ui",
      "flowbite",
      "fluent",
      "fluent-color",
      "fluent-emoji",
      "fluent-emoji-flat",
      "fluent-emoji-high-contrast",
      "fluent-mdl2",
      "fontelico",
      "fontisto",
      "formkit",
      "foundation",
      "fxemoji",
      "gala",
      "game-icons",
      "garden",
      "geo",
      "gg",
      "gis",
      "gravity-ui",
      "gridicons",
      "grommet-icons",
      "guidance",
      "healthicons",
      "heroicons",
      "heroicons-outline",
      "heroicons-solid",
      "hugeicons",
      "humbleicons",
      "ic",
      "icomoon-free",
      "icon-park",
      "icon-park-outline",
      "icon-park-solid",
      "icon-park-twotone",
      "iconamoon",
      "iconoir",
      "icons8",
      "il",
      "ion",
      "iwwa",
      "ix",
      "jam",
      "la",
      "lets-icons",
      "line-md",
      "lineicons",
      "logos",
      "ls",
      "lsicon",
      "lucide",
      "lucide-lab",
      "mage",
      "majesticons",
      "maki",
      "map",
      "marketeq",
      "material-icon-theme",
      "material-symbols",
      "material-symbols-light",
      "mdi",
      "mdi-light",
      "medical-icon",
      "memory",
      "meteocons",
      "meteor-icons",
      "mi",
      "mingcute",
      "mono-icons",
      "mynaui",
      "nimbus",
      "nonicons",
      "noto",
      "noto-v1",
      "nrk",
      "octicon",
      "oi",
      "ooui",
      "openmoji",
      "oui",
      "pajamas",
      "pepicons",
      "pepicons-pencil",
      "pepicons-pop",
      "pepicons-print",
      "ph",
      "picon",
      "pixel",
      "pixelarticons",
      "prime",
      "proicons",
      "ps",
      "qlementine-icons",
      "quill",
      "radix-icons",
      "raphael",
      "ri",
      "rivet-icons",
      "roentgen",
      "si",
      "si-glyph",
      "sidekickicons",
      "simple-icons",
      "simple-line-icons",
      "skill-icons",
      "solar",
      "stash",
      "streamline",
      "streamline-block",
      "streamline-color",
      "streamline-cyber",
      "streamline-cyber-color",
      "streamline-emojis",
      "streamline-flex",
      "streamline-flex-color",
      "streamline-freehand",
      "streamline-freehand-color",
      "streamline-kameleon-color",
      "streamline-logos",
      "streamline-pixel",
      "streamline-plump",
      "streamline-plump-color",
      "streamline-sharp",
      "streamline-sharp-color",
      "streamline-stickies-color",
      "streamline-ultimate",
      "streamline-ultimate-color",
      "subway",
      "svg-spinners",
      "system-uicons",
      "tabler",
      "tdesign",
      "teenyicons",
      "temaki",
      "token",
      "token-branded",
      "topcoat",
      "twemoji",
      "typcn",
      "uil",
      "uim",
      "uis",
      "uit",
      "uiw",
      "unjs",
      "vaadin",
      "vs",
      "vscode-icons",
      "websymbol",
      "weui",
      "whh",
      "wi",
      "wpf",
      "zmdi",
      "zondicons"
    ],
    "fetchTimeout": 1500
  }
};

const appConfig = defuFn(appConfig0, inlineAppConfig);

function getEnv(key, opts) {
  const envKey = snakeCase(key).toUpperCase();
  return destr(
    process.env[opts.prefix + envKey] ?? process.env[opts.altPrefix + envKey]
  );
}
function _isObject(input) {
  return typeof input === "object" && !Array.isArray(input);
}
function applyEnv(obj, opts, parentKey = "") {
  for (const key in obj) {
    const subKey = parentKey ? `${parentKey}_${key}` : key;
    const envValue = getEnv(subKey, opts);
    if (_isObject(obj[key])) {
      if (_isObject(envValue)) {
        obj[key] = { ...obj[key], ...envValue };
        applyEnv(obj[key], opts, subKey);
      } else if (envValue === void 0) {
        applyEnv(obj[key], opts, subKey);
      } else {
        obj[key] = envValue ?? obj[key];
      }
    } else {
      obj[key] = envValue ?? obj[key];
    }
    if (opts.envExpansion && typeof obj[key] === "string") {
      obj[key] = _expandFromEnv(obj[key]);
    }
  }
  return obj;
}
const envExpandRx = /\{\{([^{}]*)\}\}/g;
function _expandFromEnv(value) {
  return value.replace(envExpandRx, (match, key) => {
    return process.env[key] || match;
  });
}

const _inlineRuntimeConfig = {
  "app": {
    "baseURL": "/",
    "buildId": "dev",
    "buildAssetsDir": "/_nuxt/",
    "cdnURL": ""
  },
  "nitro": {
    "envPrefix": "NUXT_",
    "routeRules": {
      "/__nuxt_error": {
        "cache": false
      },
      "/_fonts/**": {
        "headers": {
          "cache-control": "public, max-age=31536000, immutable"
        },
        "cache": {
          "maxAge": 31536000
        }
      },
      "/_nuxt/builds/meta/**": {
        "headers": {
          "cache-control": "public, max-age=31536000, immutable"
        }
      },
      "/_nuxt/builds/**": {
        "headers": {
          "cache-control": "public, max-age=1, immutable"
        }
      }
    }
  },
  "public": {
    "i18n": {
      "baseUrl": "",
      "defaultLocale": "bs",
      "rootRedirect": "",
      "redirectStatusCode": 302,
      "skipSettingLocaleOnNavigate": false,
      "locales": [
        {
          "code": "bs",
          "name": "Bosanski",
          "language": ""
        },
        {
          "code": "en",
          "name": "English",
          "language": ""
        },
        {
          "code": "de",
          "name": "Deutsch",
          "language": ""
        }
      ],
      "detectBrowserLanguage": {
        "alwaysRedirect": false,
        "cookieCrossOrigin": false,
        "cookieDomain": "",
        "cookieKey": "steelcode_locale",
        "cookieSecure": false,
        "fallbackLocale": "",
        "redirectOn": "root",
        "useCookie": true
      },
      "experimental": {
        "localeDetector": "",
        "typedPages": true,
        "typedOptionsAndMessages": false,
        "alternateLinkCanonicalQueries": true,
        "devCache": false,
        "cacheLifetime": "",
        "stripMessagesPayload": false,
        "preload": false,
        "strictSeo": false,
        "nitroContextDetection": true,
        "httpCacheDuration": 10,
        "compactRoutes": false,
        "prerenderMessages": false
      },
      "domainLocales": {
        "bs": {
          "domain": ""
        },
        "en": {
          "domain": ""
        },
        "de": {
          "domain": ""
        }
      }
    }
  },
  "apiInternalBase": "http://127.0.0.1:8000",
  "icon": {
    "serverKnownCssClasses": []
  }
};
const envOptions = {
  prefix: "NITRO_",
  altPrefix: _inlineRuntimeConfig.nitro.envPrefix ?? process.env.NITRO_ENV_PREFIX ?? "_",
  envExpansion: _inlineRuntimeConfig.nitro.envExpansion ?? process.env.NITRO_ENV_EXPANSION ?? false
};
const _sharedRuntimeConfig = _deepFreeze(
  applyEnv(klona(_inlineRuntimeConfig), envOptions)
);
function useRuntimeConfig(event) {
  if (!event) {
    return _sharedRuntimeConfig;
  }
  if (event.context.nitro.runtimeConfig) {
    return event.context.nitro.runtimeConfig;
  }
  const runtimeConfig = klona(_inlineRuntimeConfig);
  applyEnv(runtimeConfig, envOptions);
  event.context.nitro.runtimeConfig = runtimeConfig;
  return runtimeConfig;
}
const _sharedAppConfig = _deepFreeze(klona(appConfig));
function useAppConfig(event) {
  {
    return _sharedAppConfig;
  }
}
function _deepFreeze(object) {
  const propNames = Object.getOwnPropertyNames(object);
  for (const name of propNames) {
    const value = object[name];
    if (value && typeof value === "object") {
      _deepFreeze(value);
    }
  }
  return Object.freeze(object);
}
new Proxy(/* @__PURE__ */ Object.create(null), {
  get: (_, prop) => {
    console.warn(
      "Please use `useRuntimeConfig()` instead of accessing config directly."
    );
    const runtimeConfig = useRuntimeConfig();
    if (prop in runtimeConfig) {
      return runtimeConfig[prop];
    }
    return void 0;
  }
});

getContext("nitro-app", {
  asyncContext: false,
  AsyncLocalStorage: void 0
});

function isPathInScope(pathname, base) {
  let canonical;
  try {
    const pre = pathname.replace(/%2f/gi, "/").replace(/%5c/gi, "\\");
    canonical = new URL(pre, "http://_").pathname;
  } catch {
    return false;
  }
  return !base || canonical === base || canonical.startsWith(base + "/");
}

const config = useRuntimeConfig();
const _routeRulesMatcher = toRouteMatcher(
  createRouter({ routes: config.nitro.routeRules })
);
function createRouteRulesHandler(ctx) {
  return eventHandler((event) => {
    const routeRules = getRouteRules(event);
    if (routeRules.headers) {
      setHeaders(event, routeRules.headers);
    }
    if (routeRules.redirect) {
      let target = routeRules.redirect.to;
      if (target.endsWith("/**")) {
        let targetPath = event.path;
        const strpBase = routeRules.redirect._redirectStripBase;
        if (strpBase) {
          if (!isPathInScope(event.path.split("?")[0], strpBase)) {
            throw createError({ statusCode: 400 });
          }
          targetPath = withoutBase(targetPath, strpBase);
        } else if (targetPath.startsWith("//")) {
          targetPath = targetPath.replace(/^\/+/, "/");
        }
        target = joinURL(target.slice(0, -3), targetPath);
      } else if (event.path.includes("?")) {
        const query = getQuery(event.path);
        target = withQuery(target, query);
      }
      return sendRedirect(event, target, routeRules.redirect.statusCode);
    }
    if (routeRules.proxy) {
      let target = routeRules.proxy.to;
      if (target.endsWith("/**")) {
        let targetPath = event.path;
        const strpBase = routeRules.proxy._proxyStripBase;
        if (strpBase) {
          if (!isPathInScope(event.path.split("?")[0], strpBase)) {
            throw createError({ statusCode: 400 });
          }
          targetPath = withoutBase(targetPath, strpBase);
        } else if (targetPath.startsWith("//")) {
          targetPath = targetPath.replace(/^\/+/, "/");
        }
        target = joinURL(target.slice(0, -3), targetPath);
      } else if (event.path.includes("?")) {
        const query = getQuery(event.path);
        target = withQuery(target, query);
      }
      return proxyRequest(event, target, {
        fetch: ctx.localFetch,
        ...routeRules.proxy
      });
    }
  });
}
function getRouteRules(event) {
  event.context._nitro = event.context._nitro || {};
  if (!event.context._nitro.routeRules) {
    event.context._nitro.routeRules = getRouteRulesForPath(
      withoutBase(event.path.split("?")[0], useRuntimeConfig().app.baseURL)
    );
  }
  return event.context._nitro.routeRules;
}
function getRouteRulesForPath(path) {
  return defu({}, ..._routeRulesMatcher.matchAll(path).reverse());
}

function _captureError(error, type) {
  console.error(`[${type}]`, error);
  useNitroApp().captureError(error, { tags: [type] });
}
function trapUnhandledNodeErrors() {
  process.on(
    "unhandledRejection",
    (error) => _captureError(error, "unhandledRejection")
  );
  process.on(
    "uncaughtException",
    (error) => _captureError(error, "uncaughtException")
  );
}
function joinHeaders(value) {
  return Array.isArray(value) ? value.join(", ") : String(value);
}
function normalizeFetchResponse(response) {
  if (!response.headers.has("set-cookie")) {
    return response;
  }
  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers: normalizeCookieHeaders(response.headers)
  });
}
function normalizeCookieHeader(header = "") {
  return splitCookiesString(joinHeaders(header));
}
function normalizeCookieHeaders(headers) {
  const outgoingHeaders = new Headers();
  for (const [name, header] of headers) {
    if (name === "set-cookie") {
      for (const cookie of normalizeCookieHeader(header)) {
        outgoingHeaders.append("set-cookie", cookie);
      }
    } else {
      outgoingHeaders.set(name, joinHeaders(header));
    }
  }
  return outgoingHeaders;
}

function isJsonRequest(event) {
	
	if (hasReqHeader(event, "accept", "text/html")) {
		return false;
	}
	return hasReqHeader(event, "accept", "application/json") || hasReqHeader(event, "user-agent", "curl/") || hasReqHeader(event, "user-agent", "httpie/") || hasReqHeader(event, "sec-fetch-mode", "cors") || event.path.startsWith("/api/") || event.path.endsWith(".json");
}
function hasReqHeader(event, name, includes) {
	const value = getRequestHeader(event, name);
	return !!(value && typeof value === "string" && value.toLowerCase().includes(includes));
}

const iframeStorageBridge = (nonce) => `
(function () {
  const NONCE = ${JSON.stringify(nonce)};
  const memoryStore = Object.create(null);

  const post = (type, payload) => {
    window.parent.postMessage({ type, nonce: NONCE, ...payload }, '*');
  };

  const isValid = (data) => data && data.nonce === NONCE;

  const mockStorage = {
    getItem(key) {
      return Object.hasOwn(memoryStore, key)
        ? memoryStore[key]
        : null;
    },
    setItem(key, value) {
      const v = String(value);
      memoryStore[key] = v;
      post('storage-set', { key, value: v });
    },
    removeItem(key) {
      delete memoryStore[key];
      post('storage-remove', { key });
    },
    clear() {
      for (const key of Object.keys(memoryStore))
        delete memoryStore[key];
      post('storage-clear', {});
    },
    key(index) {
      const keys = Object.keys(memoryStore);
      return keys[index] ?? null;
    },
    get length() {
      return Object.keys(memoryStore).length;
    }
  };

  const defineLocalStorage = () => {
    try {
      Object.defineProperty(window, 'localStorage', {
        value: mockStorage,
        writable: false,
        configurable: true
      });
    } catch {
      window.localStorage = mockStorage;
    }
  };

  defineLocalStorage();

  window.addEventListener('message', (event) => {
    const data = event.data;
    if (!isValid(data) || data.type !== 'storage-sync-data') return;

    const incoming = data.data || {};
    for (const key of Object.keys(incoming))
      memoryStore[key] = incoming[key];

    if (typeof window.initTheme === 'function')
      window.initTheme();
    window.dispatchEvent(new Event('storage-ready'));
  });

  // Clipboard API is unavailable in data: URL iframe, so we use postMessage
  document.addEventListener('DOMContentLoaded', function() {
    window.copyErrorMessage = function(button) {
      post('clipboard-copy', { text: button.dataset.errorText });
      button.classList.add('copied');
      setTimeout(function() { button.classList.remove('copied'); }, 2000);
    };
  });

  post('storage-sync-request', {});
})();
`;
const parentStorageBridge = (nonce) => `
(function () {
  const host = document.querySelector('nuxt-error-overlay');
  if (!host) return;

  const NONCE = ${JSON.stringify(nonce)};
  const isValid = (data) => data && data.nonce === NONCE;

  // Handle clipboard copy from iframe
  window.addEventListener('message', function(e) {
    if (isValid(e.data) && e.data.type === 'clipboard-copy') {
      navigator.clipboard.writeText(e.data.text).catch(function() {});
    }
  });

  const collectLocalStorage = () => {
    const all = {};
    for (let i = 0; i < localStorage.length; i++) {
      const k = localStorage.key(i);
      if (k != null) all[k] = localStorage.getItem(k);
    }
    return all;
  };

  const attachWhenReady = () => {
    const root = host.shadowRoot;
    if (!root)
      return false;
    const iframe = root.getElementById('frame');
    if (!iframe || !iframe.contentWindow)
      return false;

    const handlers = {
      'storage-set': (d) => localStorage.setItem(d.key, d.value),
      'storage-remove': (d) => localStorage.removeItem(d.key),
      'storage-clear': () => localStorage.clear(),
      'storage-sync-request': () => {
        iframe.contentWindow.postMessage({
          type: 'storage-sync-data',
          data: collectLocalStorage(),
          nonce: NONCE
        }, '*');
      }
    };

    window.addEventListener('message', (event) => {
      const data = event.data;
      if (!isValid(data)) return;
      const fn = handlers[data.type];
      if (fn) fn(data);
    });

    return true;
  };

  if (attachWhenReady())
    return;

  const obs = new MutationObserver(() => {
    if (attachWhenReady())
      obs.disconnect();
  });

  obs.observe(host, { childList: true, subtree: true });
})();
`;
const errorCSS = `
:host {
  --preview-width: 240px;
  --preview-height: 180px;
  --base-width: 1200px;
  --base-height: 900px;
  --z-base: 999999998;
  --error-pip-left: auto;
  --error-pip-top: auto;
  --error-pip-right: 5px;
  --error-pip-bottom: 5px;
  --error-pip-origin: bottom right;
  --app-preview-left: auto;
  --app-preview-top: auto;
  --app-preview-right: 5px;
  --app-preview-bottom: 5px;
  all: initial;
  display: contents;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}
#frame {
  position: fixed;
  left: 0;
  top: 0;
  width: 100vw;
  height: 100vh;
  border: none;
  z-index: var(--z-base);
}
#frame[inert] {
  left: var(--error-pip-left);
  top: var(--error-pip-top);
  right: var(--error-pip-right);
  bottom: var(--error-pip-bottom);
  width: var(--base-width);
  height: var(--base-height);
  transform: scale(calc(240 / 1200));
  transform-origin: var(--error-pip-origin);
  overflow: hidden;
  border-radius: calc(1200 * 8px / 240);
}
#preview {
  position: fixed;
  left: var(--app-preview-left);
  top: var(--app-preview-top);
  right: var(--app-preview-right);
  bottom: var(--app-preview-bottom);
  width: var(--preview-width);
  height: var(--preview-height);
  overflow: hidden;
  border-radius: 6px;
  pointer-events: none;
  z-index: var(--z-base);
  background: white;
  display: none;
}
#preview iframe {
  transform-origin: var(--error-pip-origin);
}
#frame:not([inert]) + #preview {
  display: block;
}
#toggle {
  position: fixed;
  left: var(--app-preview-left);
  top: var(--app-preview-top);
  right: calc(var(--app-preview-right) - 3px);
  bottom: calc(var(--app-preview-bottom) - 3px);
  width: var(--preview-width);
  height: var(--preview-height);
  background: none;
  border: 3px solid #00DC82;
  border-radius: 8px;
  cursor: pointer;
  opacity: 0.8;
  transition: opacity 0.2s, box-shadow 0.2s;
  z-index: calc(var(--z-base) + 1);
  display: flex;
  align-items: center;
  justify-content: center;
}
#toggle:hover,
#toggle:focus {
  opacity: 1;
  box-shadow: 0 0 20px rgba(0, 220, 130, 0.6);
}
#toggle:focus-visible {
  outline: 3px solid #00DC82;
  outline-offset: 0;
  box-shadow: 0 0 24px rgba(0, 220, 130, 0.8);
}
#frame[inert] ~ #toggle {
  left: var(--error-pip-left);
  top: var(--error-pip-top);
  right: calc(var(--error-pip-right) - 3px);
  bottom: calc(var(--error-pip-bottom) - 3px);
  cursor: grab;
}
:host(.dragging) #frame[inert] ~ #toggle {
  cursor: grabbing;
}
#frame:not([inert]) ~ #toggle,
#frame:not([inert]) + #preview {
  cursor: grab;
}
:host(.dragging-preview) #frame:not([inert]) ~ #toggle,
:host(.dragging-preview) #frame:not([inert]) + #preview {
  cursor: grabbing;
}

#pip-close {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: none;
  background: rgba(0, 0, 0, 0.75);
  color: #fff;
  font-size: 16px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
  pointer-events: auto;
}
#pip-close:focus-visible {
  outline: 2px solid #00DC82;
  outline-offset: 2px;
}

#pip-restore {
  position: fixed;
  right: 16px;
  bottom: 16px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 2px solid #00DC82;
  background: #111;
  color: #fff;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  z-index: calc(var(--z-base) + 2);
  cursor: grab;
}
#pip-restore:focus-visible {
  outline: 2px solid #00DC82;
  outline-offset: 2px;
}
:host(.dragging-restore) #pip-restore {
  cursor: grabbing;
}

#frame[hidden],
#toggle[hidden],
#preview[hidden],
#pip-restore[hidden],
#pip-close[hidden] {
  display: none !important;
}

@media (prefers-reduced-motion: reduce) {
  #toggle {
    transition: none;
  }
}
`;
function webComponentScript(base64HTML, startMinimized) {
	return `
(function () {
  try {
    // =========================
    // Host + Shadow
    // =========================
    const host = document.querySelector('nuxt-error-overlay');
    if (!host)
      return;
    const shadow = host.attachShadow({ mode: 'open' });

    // =========================
    // DOM helpers
    // =========================
    const el = (tag) => document.createElement(tag);
    const on = (node, type, fn, opts) => node.addEventListener(type, fn, opts);
    const hide = (node, v) => node.toggleAttribute('hidden', !!v);
    const setVar = (name, value) => host.style.setProperty(name, value);
    const unsetVar = (name) => host.style.removeProperty(name);

    // =========================
    // Create DOM
    // =========================
    const style = el('style');
    style.textContent = ${JSON.stringify(errorCSS)};

    const iframe = el('iframe');
    iframe.id = 'frame';
    iframe.src = 'data:text/html;base64,${base64HTML}';
    iframe.title = 'Detailed error stack trace';
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-top-navigation-by-user-activation');

    const preview = el('div');
    preview.id = 'preview';

    const toggle = el('div');
    toggle.id = 'toggle';
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('role', 'button');
    toggle.setAttribute('tabindex', '0');
    toggle.innerHTML = '<span class="sr-only">Toggle detailed error view</span>';

    const liveRegion = el('div');
    liveRegion.setAttribute('role', 'status');
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.className = 'sr-only';

    const pipCloseButton = el('button');
    pipCloseButton.id = 'pip-close';
    pipCloseButton.setAttribute('type', 'button');
    pipCloseButton.setAttribute('aria-label', 'Hide error preview overlay');
    pipCloseButton.innerHTML = '&times;';
    pipCloseButton.hidden = true;
    toggle.appendChild(pipCloseButton);

    const pipRestoreButton = el('button');
    pipRestoreButton.id = 'pip-restore';
    pipRestoreButton.setAttribute('type', 'button');
    pipRestoreButton.setAttribute('aria-label', 'Show error overlay');
    pipRestoreButton.innerHTML = '<span aria-hidden="true">⟲</span><span>Show error overlay</span>';
    pipRestoreButton.hidden = true;

    // Order matters: #frame + #preview adjacency
    shadow.appendChild(style);
    shadow.appendChild(liveRegion);
    shadow.appendChild(iframe);
    shadow.appendChild(preview);
    shadow.appendChild(toggle);
    shadow.appendChild(pipRestoreButton);

    // =========================
    // Constants / keys
    // =========================
    const POS_KEYS = {
      position: 'nuxt-error-overlay:position',
      hiddenPretty: 'nuxt-error-overlay:error-pip:hidden',
      hiddenPreview: 'nuxt-error-overlay:app-preview:hidden'
    };

    const CSS_VARS = {
      pip: {
        left: '--error-pip-left',
        top: '--error-pip-top',
        right: '--error-pip-right',
        bottom: '--error-pip-bottom'
      },
      preview: {
        left: '--app-preview-left',
        top: '--app-preview-top',
        right: '--app-preview-right',
        bottom: '--app-preview-bottom'
      }
    };

    const MIN_GAP = 5;
    const DRAG_THRESHOLD = 2;

    // =========================
    // Local storage safe access + state
    // =========================
    let storageReady = true;
    let isPrettyHidden = false;
    let isPreviewHidden = false;

    const safeGet = (k) => {
      try {
        return localStorage.getItem(k);
      } catch {
        return null;
      }
    };

    const safeSet = (k, v) => {
      if (!storageReady) 
        return;
      try {
        localStorage.setItem(k, v);
      } catch {}
    };

    // =========================
    // Sizing helpers
    // =========================
    const vvSize = () => {
      const v = window.visualViewport;
      return v ? { w: v.width, h: v.height } : { w: window.innerWidth, h: window.innerHeight };
    };

    const previewSize = () => {
      const styles = getComputedStyle(host);
      const w = parseFloat(styles.getPropertyValue('--preview-width')) || 240;
      const h = parseFloat(styles.getPropertyValue('--preview-height')) || 180;
      return { w, h };
    };

    const sizeForTarget = (target) => {
      if (!target)
        return previewSize();
      const rect = target.getBoundingClientRect();
      if (rect.width && rect.height)
        return { w: rect.width, h: rect.height };
      return previewSize();
    };

    // =========================
    // Dock model + offset/alignment calculations
    // =========================
    const dock = { edge: null, offset: null, align: null, gap: null };

    const maxOffsetFor = (edge, size) => {
      const vv = vvSize();
      if (edge === 'left' || edge === 'right')
        return Math.max(MIN_GAP, vv.h - size.h - MIN_GAP);
      return Math.max(MIN_GAP, vv.w - size.w - MIN_GAP);
    };

    const clampOffset = (edge, value, size) => {
      const max = maxOffsetFor(edge, size);
      return Math.min(Math.max(value, MIN_GAP), max);
    };

    const updateDockAlignment = (size) => {
      if (!dock.edge || dock.offset == null)
        return;
      const max = maxOffsetFor(dock.edge, size);
      if (dock.offset <= max / 2) {
        dock.align = 'start';
        dock.gap = dock.offset;
      } else {
        dock.align = 'end';
        dock.gap = Math.max(0, max - dock.offset);
      }
    };

    const appliedOffsetFor = (size) => {
      if (!dock.edge || dock.offset == null)
        return null;
      const max = maxOffsetFor(dock.edge, size);

      if (dock.align === 'end' && typeof dock.gap === 'number') {
        return clampOffset(dock.edge, max - dock.gap, size);
      }
      if (dock.align === 'start' && typeof dock.gap === 'number') {
        return clampOffset(dock.edge, dock.gap, size);
      }
      return clampOffset(dock.edge, dock.offset, size);
    };

    const nearestEdgeAt = (x, y) => {
      const { w, h } = vvSize();
      const d = { left: x, right: w - x, top: y, bottom: h - y };
      return Object.keys(d).reduce((a, b) => (d[a] < d[b] ? a : b));
    };

    const cornerDefaultDock = () => {
      const vv = vvSize();
      const size = previewSize();
      const offset = Math.max(MIN_GAP, vv.w - size.w - MIN_GAP);
      return { edge: 'bottom', offset };
    };

    const currentTransformOrigin = () => {
      if (!dock.edge) return null;
      if (dock.edge === 'left' || dock.edge === 'top')
        return 'top left';
      if (dock.edge === 'right')
        return 'top right';
      return 'bottom left';
    };

    // =========================
    // Persist / load dock
    // =========================
    const loadDock = () => {
      const raw = safeGet(POS_KEYS.position);
      if (!raw)
        return;
      try {
        const parsed = JSON.parse(raw);
        const { edge, offset, align, gap } = parsed || {};
        if (!['left', 'right', 'top', 'bottom'].includes(edge))
          return;
        if (typeof offset !== 'number')
          return;

        dock.edge = edge;
        dock.offset = clampOffset(edge, offset, previewSize());
        dock.align = align === 'start' || align === 'end' ? align : null;
        dock.gap = typeof gap === 'number' ? gap : null;

        if (!dock.align || dock.gap == null)
          updateDockAlignment(previewSize());
      } catch {}
    };

    const persistDock = () => {
      if (!dock.edge || dock.offset == null)
        return; 
      safeSet(POS_KEYS.position, JSON.stringify({
        edge: dock.edge,
        offset: dock.offset,
        align: dock.align,
        gap: dock.gap
      }));
    };

    // =========================
    // Apply dock
    // =========================
    const dockToVars = (vars) => ({
      set: (side, v) => host.style.setProperty(vars[side], v),
      clear: (side) => host.style.removeProperty(vars[side])
    });

    const dockToEl = (node) => ({
      set: (side, v) => { node.style[side] = v; },
      clear: (side) => { node.style[side] = ''; }
    });

    const applyDock = (target, size, opts) => {
      if (!dock.edge || dock.offset == null) {
        target.clear('left');
        target.clear('top');
        target.clear('right');
        target.clear('bottom');
        return;
      }

      target.set('left', 'auto');
      target.set('top', 'auto');
      target.set('right', 'auto');
      target.set('bottom', 'auto');

      const applied = appliedOffsetFor(size);

      if (dock.edge === 'left') {
        target.set('left', MIN_GAP + 'px');
        target.set('top', applied + 'px');
      } else if (dock.edge === 'right') {
        target.set('right', MIN_GAP + 'px');
        target.set('top', applied + 'px');
      } else if (dock.edge === 'top') {
        target.set('top', MIN_GAP + 'px');
        target.set('left', applied + 'px');
      } else {
        target.set('bottom', MIN_GAP + 'px');
        target.set('left', applied + 'px');
      }

      if (!opts || opts.persist !== false)
        persistDock();
    };

    const applyDockAll = (opts) => {
      applyDock(dockToVars(CSS_VARS.pip), previewSize(), opts);
      applyDock(dockToVars(CSS_VARS.preview), previewSize(), opts);
      applyDock(dockToEl(pipRestoreButton), sizeForTarget(pipRestoreButton), opts);
    };

    const repaintToDock = () => {
      if (!dock.edge || dock.offset == null)
        return;
      const origin = currentTransformOrigin();
      if (origin)
        setVar('--error-pip-origin', origin);
      else 
        unsetVar('--error-pip-origin');
      applyDockAll({ persist: false });
    };

    // =========================
    // Hidden state + UI
    // =========================
    const loadHidden = () => {
      const rawPretty = safeGet(POS_KEYS.hiddenPretty);
      if (rawPretty != null)
        isPrettyHidden = rawPretty === '1' || rawPretty === 'true';
      const rawPreview = safeGet(POS_KEYS.hiddenPreview);
      if (rawPreview != null)
        isPreviewHidden = rawPreview === '1' || rawPreview === 'true';
    };

    const setPrettyHidden = (v) => {
      isPrettyHidden = !!v;
      safeSet(POS_KEYS.hiddenPretty, isPrettyHidden ? '1' : '0');
      updateUI();
    };

    const setPreviewHidden = (v) => {
      isPreviewHidden = !!v;
      safeSet(POS_KEYS.hiddenPreview, isPreviewHidden ? '1' : '0');
      updateUI();
    };

    const isMinimized = () => iframe.hasAttribute('inert');

    const setMinimized = (v) => {
      if (v) {
        iframe.setAttribute('inert', '');
        toggle.setAttribute('aria-expanded', 'false');
      } else {
        iframe.removeAttribute('inert');
        toggle.setAttribute('aria-expanded', 'true');
      }
    };

    const setRestoreLabel = (kind) => {
      if (kind === 'pretty') {
        pipRestoreButton.innerHTML = '<span aria-hidden="true">⟲</span><span>Show error overlay</span>';
        pipRestoreButton.setAttribute('aria-label', 'Show error overlay');
      } else {
        pipRestoreButton.innerHTML = '<span aria-hidden="true">⟲</span><span>Show error page</span>';
        pipRestoreButton.setAttribute('aria-label', 'Show error page');
      }
    };

    const updateUI = () => {
      const minimized = isMinimized();
      const showPiP = minimized && !isPrettyHidden;
      const showPreview = !minimized && !isPreviewHidden;
      const pipHiddenByUser = minimized && isPrettyHidden;
      const previewHiddenByUser = !minimized && isPreviewHidden;
      const showToggle = minimized ? showPiP : showPreview;
      const showRestore = pipHiddenByUser || previewHiddenByUser;

      hide(iframe, pipHiddenByUser);
      hide(preview, !showPreview);
      hide(toggle, !showToggle);
      hide(pipCloseButton, !showToggle);
      hide(pipRestoreButton, !showRestore);

      pipCloseButton.setAttribute('aria-label', minimized ? 'Hide error overlay' : 'Hide error page preview');

      if (pipHiddenByUser)
        setRestoreLabel('pretty');
      else if (previewHiddenByUser)
        setRestoreLabel('preview');

      host.classList.toggle('pip-hidden', isPrettyHidden);
      host.classList.toggle('preview-hidden', isPreviewHidden);
    };

    // =========================
    // Preview snapshot
    // =========================
    const updatePreview = () => {
      try {
        let previewIframe = preview.querySelector('iframe');
        if (!previewIframe) {
          previewIframe = el('iframe');
          previewIframe.style.cssText = 'width: 1200px; height: 900px; transform: scale(0.2); transform-origin: top left; border: none;';
          previewIframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');
          preview.appendChild(previewIframe);
        }

        const doctype = document.doctype ? '<!DOCTYPE ' + document.doctype.name + '>' : '';
        const cleanedHTML = document.documentElement.outerHTML
          .replace(/<nuxt-error-overlay[^>]*>.*?<\\/nuxt-error-overlay>/gs, '')
          .replace(/<script[^>]*>.*?<\\/script>/gs, '');

        const iframeDoc = previewIframe.contentDocument || previewIframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(doctype + cleanedHTML);
        iframeDoc.close();
      } catch (err) {
        console.error('Failed to update preview:', err);
      }
    };

    // =========================
    // View toggling
    // =========================
    const toggleView = () => {
      if (isMinimized()) {
        updatePreview();
        setMinimized(false);
        liveRegion.textContent = 'Showing detailed error view';
        setTimeout(() => { 
          try { 
            iframe.contentWindow.focus();
          } catch {}
        }, 100);
      } else {
        setMinimized(true);
        liveRegion.textContent = 'Showing error page';
        repaintToDock();
        void iframe.offsetWidth;
      }
      updateUI();
    };

    // =========================
    // Dragging (unified, rAF throttled)
    // =========================
    let drag = null;
    let rafId = null;
    let suppressToggleClick = false;
    let suppressRestoreClick = false;

    const beginDrag = (e) => {
      if (drag) 
        return;

      if (!dock.edge || dock.offset == null) {
        const def = cornerDefaultDock();
        dock.edge = def.edge;
        dock.offset = def.offset;
        updateDockAlignment(previewSize());
      }

      const isRestoreTarget = e.currentTarget === pipRestoreButton;

      drag = {
        kind: isRestoreTarget ? 'restore' : (isMinimized() ? 'pip' : 'preview'),
        pointerId: e.pointerId,
        startX: e.clientX,
        startY: e.clientY,
        lastX: e.clientX,
        lastY: e.clientY,
        moved: false,
        target: e.currentTarget
      };

      drag.target.setPointerCapture(e.pointerId);

      if (drag.kind === 'restore')
        host.classList.add('dragging-restore');
      else 
        host.classList.add(drag.kind === 'pip' ? 'dragging' : 'dragging-preview');

      e.preventDefault();
    };

    const moveDrag = (e) => {
      if (!drag || drag.pointerId !== e.pointerId)
        return;

      drag.lastX = e.clientX;
      drag.lastY = e.clientY;
      
      const dx = drag.lastX - drag.startX;
      const dy = drag.lastY - drag.startY;

      if (!drag.moved && (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD)) {
        drag.moved = true;
      }

      if (!drag.moved)
        return;
      if (rafId)
        return;

      rafId = requestAnimationFrame(() => {
        rafId = null;

        const edge = nearestEdgeAt(drag.lastX, drag.lastY);
        const size = sizeForTarget(drag.target);

        let offset;
        if (edge === 'left' || edge === 'right') {
          const top = drag.lastY - (size.h / 2);
          offset = clampOffset(edge, Math.round(top), size);
        } else {
          const left = drag.lastX - (size.w / 2);
          offset = clampOffset(edge, Math.round(left), size);
        }

        dock.edge = edge;
        dock.offset = offset;
        updateDockAlignment(size);

        const origin = currentTransformOrigin();
        setVar('--error-pip-origin', origin || 'bottom right');

        applyDockAll({ persist: false });
      });
    };

    const endDrag = (e) => {
      if (!drag || drag.pointerId !== e.pointerId)
        return;

      const endedKind = drag.kind;
      drag.target.releasePointerCapture(e.pointerId);

      if (endedKind === 'restore')
        host.classList.remove('dragging-restore');
      else 
        host.classList.remove(endedKind === 'pip' ? 'dragging' : 'dragging-preview');

      const didMove = drag.moved;
      drag = null;

      if (didMove) {
        persistDock();
        if (endedKind === 'restore')
          suppressRestoreClick = true;
        else 
          suppressToggleClick = true;
        e.preventDefault();
        e.stopPropagation();
      }
    };

    const bindDragTarget = (node) => {
      on(node, 'pointerdown', beginDrag);
      on(node, 'pointermove', moveDrag);
      on(node, 'pointerup', endDrag);
      on(node, 'pointercancel', endDrag);
    };

    bindDragTarget(toggle);
    bindDragTarget(pipRestoreButton);

    // =========================
    // Events (toggle / close / restore)
    // =========================
    on(toggle, 'click', (e) => {
      if (suppressToggleClick) {
        e.preventDefault();
        suppressToggleClick = false;
        return;
      }
      toggleView();
    });

    on(toggle, 'keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleView();
      }
    });

    on(pipCloseButton, 'click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (isMinimized())
        setPrettyHidden(true);
      else
        setPreviewHidden(true);
    });

    on(pipCloseButton, 'pointerdown', (e) => {
      e.stopPropagation();
    });

    on(pipRestoreButton, 'click', (e) => {
      if (suppressRestoreClick) {
        e.preventDefault();
        suppressRestoreClick = false;
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      if (isMinimized()) 
        setPrettyHidden(false);
      else 
        setPreviewHidden(false);
    });

    // =========================
    // Lifecycle: load / sync / repaint
    // =========================
    const loadState = () => {
      loadDock();
      loadHidden();

      if (isPrettyHidden && !isMinimized())
        setMinimized(true);

      updateUI();
      repaintToDock();
    };

    loadState();

    on(window, 'storage-ready', () => {
      storageReady = true;
      loadState();
    });

    const onViewportChange = () => repaintToDock();

    on(window, 'resize', onViewportChange);

    if (window.visualViewport) {
      on(window.visualViewport, 'resize', onViewportChange);
      on(window.visualViewport, 'scroll', onViewportChange);
    }

    // initial preview
    setTimeout(updatePreview, 100);

    // initial minimized option
    if (${startMinimized}) {
      setMinimized(true);
      repaintToDock();
      void iframe.offsetWidth;
      updateUI();
    }
  } catch (err) {
    console.error('Failed to initialize Nuxt error overlay:', err);
  }
})();
`;
}
function generateErrorOverlayHTML(html, options) {
	const nonce = Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, "0")).join("");
	const errorPage = html.replace("<head>", `<head><script>${iframeStorageBridge(nonce)}<\/script>`);
	const base64HTML = Buffer.from(errorPage, "utf8").toString("base64");
	return `
    <script>${parentStorageBridge(nonce)}<\/script>
    <nuxt-error-overlay></nuxt-error-overlay>
    <script>${webComponentScript(base64HTML, options?.startMinimized ?? false)}<\/script>
  `;
}

const errorHandler$0 = (async function errorhandler(error, event, { defaultHandler }) {
	if (event.handled || isJsonRequest(event)) {
		
		return;
	}
	
	const defaultRes = await defaultHandler(error, event, { json: true });
	
	const status = error.status || error.statusCode || 500;
	if (status === 404 && defaultRes.status === 302) {
		setResponseHeaders(event, defaultRes.headers);
		setResponseStatus(event, defaultRes.status, defaultRes.statusText);
		return send(event, JSON.stringify(defaultRes.body, null, 2));
	}
	if (typeof defaultRes.body !== "string" && Array.isArray(defaultRes.body.stack)) {
		
		defaultRes.body.stack = defaultRes.body.stack.join("\n");
	}
	const errorObject = defaultRes.body;
	
	const url = new URL(errorObject.url);
	errorObject.url = withoutBase(url.pathname, useRuntimeConfig(event).app.baseURL) + url.search + url.hash;
	
	errorObject.message = error.unhandled ? errorObject.message || "Server Error" : error.message || errorObject.message || "Server Error";
	
	errorObject.data ||= error.data;
	errorObject.statusText ||= error.statusText || error.statusMessage;
	delete defaultRes.headers["content-type"];
	delete defaultRes.headers["content-security-policy"];
	setResponseHeaders(event, defaultRes.headers);
	
	const reqHeaders = getRequestHeaders(event);
	
	const isRenderingError = event.path.startsWith("/__nuxt_error") || !!reqHeaders["x-nuxt-error"];
	
	const res = isRenderingError ? null : await useNitroApp().localFetch(withQuery(joinURL(useRuntimeConfig(event).app.baseURL, "/__nuxt_error"), errorObject), {
		headers: {
			...reqHeaders,
			"x-nuxt-error": "true"
		},
		redirect: "manual"
	}).catch(() => null);
	if (event.handled) {
		return;
	}
	
	if (!res) {
		const { template } = await Promise.resolve().then(function () { return error500; });
		{
			
			errorObject.description = errorObject.message;
		}
		setResponseHeader(event, "Content-Type", "text/html;charset=UTF-8");
		return send(event, template(errorObject));
	}
	const html = await res.text();
	for (const [header, value] of res.headers.entries()) {
		if (header === "set-cookie") {
			appendResponseHeader(event, header, value);
			continue;
		}
		setResponseHeader(event, header, value);
	}
	setResponseStatus(event, res.status && res.status !== 200 ? res.status : defaultRes.status, res.statusText || defaultRes.statusText);
	if (!globalThis._importMeta_.test && typeof html === "string") {
		const prettyResponse = await defaultHandler(error, event, { json: false });
		if (typeof prettyResponse.body === "string") {
			return send(event, html.replace("</body>", `${generateErrorOverlayHTML(prettyResponse.body, { startMinimized: 300 <= status && status < 500 })}</body>`));
		}
	}
	return send(event, html);
});

function defineNitroErrorHandler(handler) {
  return handler;
}

const errorHandler$1 = defineNitroErrorHandler(
  async function defaultNitroErrorHandler(error, event) {
    const res = await defaultHandler(error, event);
    if (!event.node?.res.headersSent) {
      setResponseHeaders(event, res.headers);
    }
    setResponseStatus(event, res.status, res.statusText);
    return send(
      event,
      typeof res.body === "string" ? res.body : JSON.stringify(res.body, null, 2)
    );
  }
);
async function defaultHandler(error, event, opts) {
  const isSensitive = error.unhandled || error.fatal;
  const statusCode = error.statusCode || 500;
  const statusMessage = error.statusMessage || "Server Error";
  const url = getRequestURL(event, { xForwardedHost: true, xForwardedProto: true });
  if (statusCode === 404) {
    const baseURL = "/";
    if (/^\/[^/]/.test(baseURL) && !url.pathname.startsWith(baseURL)) {
      const redirectTo = `${baseURL}${url.pathname.slice(1)}${url.search}`;
      return {
        status: 302,
        statusText: "Found",
        headers: { location: redirectTo },
        body: `Redirecting...`
      };
    }
  }
  await loadStackTrace(error).catch(consola.error);
  const youch = new Youch();
  if (isSensitive && !opts?.silent) {
    const tags = [error.unhandled && "[unhandled]", error.fatal && "[fatal]"].filter(Boolean).join(" ");
    const ansiError = await (await youch.toANSI(error)).replaceAll(process.cwd(), ".");
    consola.error(
      `[request error] ${tags} [${event.method}] ${url}

`,
      ansiError
    );
  }
  const useJSON = opts?.json ?? !getRequestHeader(event, "accept")?.includes("text/html");
  const headers = {
    "content-type": useJSON ? "application/json" : "text/html",
    // Prevent browser from guessing the MIME types of resources.
    "x-content-type-options": "nosniff",
    // Prevent error page from being embedded in an iframe
    "x-frame-options": "DENY",
    // Prevent browsers from sending the Referer header
    "referrer-policy": "no-referrer",
    // Disable the execution of any js
    "content-security-policy": "script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self';"
  };
  if (statusCode === 404 || !getResponseHeader(event, "cache-control")) {
    headers["cache-control"] = "no-cache";
  }
  const body = useJSON ? {
    error: true,
    url,
    statusCode,
    statusMessage,
    message: error.message,
    data: error.data,
    stack: error.stack?.split("\n").map((line) => line.trim())
  } : await youch.toHTML(error, {
    request: {
      url: url.href,
      method: event.method,
      headers: getRequestHeaders(event)
    }
  });
  return {
    status: statusCode,
    statusText: statusMessage,
    headers,
    body
  };
}
async function loadStackTrace(error) {
  if (!(error instanceof Error)) {
    return;
  }
  const parsed = await new ErrorParser().defineSourceLoader(sourceLoader).parse(error);
  const stack = error.message + "\n" + parsed.frames.map((frame) => fmtFrame(frame)).join("\n");
  Object.defineProperty(error, "stack", { value: stack });
  if (error.cause) {
    await loadStackTrace(error.cause).catch(consola.error);
  }
}
async function sourceLoader(frame) {
  if (!frame.fileName || frame.fileType !== "fs" || frame.type === "native") {
    return;
  }
  if (frame.type === "app") {
    const rawSourceMap = await readFile(`${frame.fileName}.map`, "utf8").catch(() => {
    });
    if (rawSourceMap) {
      const consumer = await new SourceMapConsumer(rawSourceMap);
      const originalPosition = consumer.originalPositionFor({ line: frame.lineNumber, column: frame.columnNumber });
      if (originalPosition.source && originalPosition.line) {
        frame.fileName = resolve(dirname(frame.fileName), originalPosition.source);
        frame.lineNumber = originalPosition.line;
        frame.columnNumber = originalPosition.column || 0;
      }
    }
  }
  const contents = await readFile(frame.fileName, "utf8").catch(() => {
  });
  return contents ? { contents } : void 0;
}
function fmtFrame(frame) {
  if (frame.type === "native") {
    return frame.raw;
  }
  const src = `${frame.fileName || ""}:${frame.lineNumber}:${frame.columnNumber})`;
  return frame.functionName ? `at ${frame.functionName} (${src}` : `at ${src}`;
}

const errorHandlers = [errorHandler$0, errorHandler$1];

async function errorHandler(error, event) {
  for (const handler of errorHandlers) {
    try {
      await handler(error, event, { defaultHandler });
      if (event.handled) {
        return; // Response handled
      }
    } catch(error) {
      // Handler itself thrown, log and continue
      console.error(error);
    }
  }
  // H3 will handle fallback
}

const script$1 = `
if (!window.__NUXT_DEVTOOLS_TIME_METRIC__) {
  Object.defineProperty(window, '__NUXT_DEVTOOLS_TIME_METRIC__', {
    value: {},
    enumerable: false,
    configurable: true,
  })
}
window.__NUXT_DEVTOOLS_TIME_METRIC__.appInit = Date.now()
`;

const _hISa2eAaFbgIf8ZiB_NCryNg526yte7pO0mNdhgA5sA = (function(nitro) {
  nitro.hooks.hook("render:html", (htmlContext) => {
    htmlContext.head.push(`<script>${script$1}<\/script>`);
  });
});

/*!
  * shared v11.4.8
  * (c) 2026 kazuya kawaguchi
  * Released under the MIT License.
  */
/**
 * Original Utilities
 * written by kazuya kawaguchi
 */
const _create = Object.create;
const create = (obj = null) => _create(obj);
/* eslint-enable */
/**
 * Useful Utilities By Evan you
 * Modified by kazuya kawaguchi
 * MIT License
 * https://github.com/vuejs/vue-next/blob/master/packages/shared/src/index.ts
 * https://github.com/vuejs/vue-next/blob/master/packages/shared/src/codeframe.ts
 */
const isArray = Array.isArray;
const isFunction = (val) => typeof val === 'function';
const isString = (val) => typeof val === 'string';
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const isObject = (val) => val !== null && typeof val === 'object';
const objectToString = Object.prototype.toString;
const toTypeString = (value) => objectToString.call(value);

const isNotObjectOrIsArray = (val) => !isObject(val) || isArray(val);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function deepCopy(src, des) {
    // src and des should both be objects, and none of them can be a array
    if (isNotObjectOrIsArray(src) || isNotObjectOrIsArray(des)) {
        throw new Error('Invalid value');
    }
    const stack = [{ src, des }];
    while (stack.length) {
        const { src, des } = stack.pop();
        // using `Object.keys` which skips prototype properties
        Object.keys(src).forEach(key => {
            if (key === '__proto__') {
                return;
            }
            // if src[key] is an object/array, set des[key]
            // to empty object/array to prevent setting by reference
            if (isObject(src[key]) && !isObject(des[key])) {
                des[key] = Array.isArray(src[key]) ? [] : create();
            }
            if (isNotObjectOrIsArray(des[key]) || isNotObjectOrIsArray(src[key])) {
                // replace with src[key] when:
                // src[key] or des[key] is not an object, or
                // src[key] or des[key] is an array
                des[key] = src[key];
            }
            else {
                // src[key] and des[key] are both objects, merge them
                stack.push({ src: src[key], des: des[key] });
            }
        });
    }
}

const __nuxtMock = { runWithContext: async (fn) => await fn() };
const merger = createDefu((obj, key, value) => {
  if (key === "messages" || key === "datetimeFormats" || key === "numberFormats") {
    obj[key] ??= create(null);
    deepCopy(value, obj[key]);
    return true;
  }
});
async function loadVueI18nOptions(vueI18nConfigs) {
  const nuxtApp = __nuxtMock;
  let vueI18nOptions = { messages: create(null) };
  for (const configFile of vueI18nConfigs) {
    const resolver = await configFile().then((x) => isModule(x) ? x.default : x);
    const resolved = isFunction(resolver) ? await nuxtApp.runWithContext(() => resolver()) : resolver;
    vueI18nOptions = merger(create(null), resolved, vueI18nOptions);
  }
  vueI18nOptions.fallbackLocale ??= false;
  return vueI18nOptions;
}
const isModule = (val) => toTypeString(val) === "[object Module]";
async function getLocaleMessages(locale, loader) {
  const nuxtApp = __nuxtMock;
  try {
    const getter = await nuxtApp.runWithContext(loader.load).then((x) => isModule(x) ? x.default : x);
    return isFunction(getter) ? await nuxtApp.runWithContext(() => getter(locale)) : getter;
  } catch (e) {
    throw new Error(`Failed loading locale (${locale}): ` + e.message, { cause: e });
  }
}
async function getLocaleMessagesMerged(locale, loaders = []) {
  const nuxtApp = __nuxtMock;
  const messages = await Promise.all(
    loaders.map((loader) => nuxtApp.runWithContext(() => getLocaleMessages(locale, loader)))
  );
  const merged = {};
  for (const message of messages) {
    deepCopy(message, merged);
  }
  return merged;
}

var nav$2 = {
	home: "Početna",
	integrations: "Integracije",
	catalogue: "Katalog",
	products: "Proizvodi",
	categories: "Kategorije",
	manufacturers: "Proizvođači",
	attributes: "Atributi",
	customFields: "Prilagođena polja",
	suppliers: "Dobavljači",
	inventory: "Zalihe",
	stock: "Zalihe proizvoda",
	warehouses: "Skladišta",
	transfers: "Transferi",
	company: "Kompanija",
	details: "Detalji",
	addresses: "Adrese",
	paymentMethods: "Načini plaćanja",
	billing: "Naplata",
	pricingPlans: "Paketi",
	settings: "Postavke",
	shop: "Trgovina",
	taxes: "Porezi",
	units: "Jedinice",
	deliveryTimes: "Vremena dostave",
	general: "Opće",
	profile: "Profil",
	translations: "Prijevodi",
	members: "Članovi",
	notifications: "Obavijesti",
	security: "Sigurnost",
	help: "Pomoć i podrška"
};
var common$2 = {
	saveChanges: "Sačuvaj promjene",
	save: "Sačuvaj",
	saved: "Sačuvano",
	cancel: "Odustani",
	create: "Kreiraj",
	"delete": "Obriši",
	edit: "Uredi",
	add: "Dodaj",
	"default": "Zadano",
	language: "Jezik",
	email: "E-mail",
	phone: "Telefon",
	error: "Izmjene nije moguće sačuvati",
	website: "Web stranica",
	loading: "Učitavanje…",
	changesSaved: "Vaše promjene su sačuvane.",
	requiredField: "Ovo polje je obavezno.",
	requiredFields: "Popunite obavezna polja: {fields}.",
	invalidField: "Vrijednost nije ispravna.",
	invalidFields: "Ispravite sljedeća polja: {fields}.",
	tryAgain: "Provjerite unesene podatke i pokušajte ponovo.",
	totalResults: "Ukupno: {count}",
	rowsPerPage: "Redova po stranici"
};
var table$2 = {
	columns: "Kolone"
};
var catalogue$2 = {
	title: "Katalog",
	comingSoon: "Ovaj modul se trenutno priprema.",
	addOption: "Dodaj opciju",
	addOptionDescription: "Definišite opciju koja stvara varijante.",
	optionName: "Naziv opcije",
	optionValues: "Vrijednosti",
	optionValuesDescription: "Unesite vrijednosti odvojene zarezom.",
	optionCreated: "Opcija je kreirana",
	optionCreateFailed: "Kreiranje opcije nije uspjelo.",
	generateVariants: "Generiši {count} varijanti",
	variantGenerateFailed: "Generisanje varijanti nije uspjelo."
};
var products$2 = {
	product: "Proizvod",
	name: "Naziv proizvoda",
	manufacturer: "Proizvođač",
	price: "Cijena",
	stock: "Zaliha",
	selectManufacturer: "Odaberite proizvođača",
	searchManufacturers: "Pretraži proizvođače…",
	status: "Status",
	updated: "Ažurirano",
	search: "Pretraži proizvode…",
	allStatuses: "Svi statusi",
	active: "Aktivan",
	draft: "Nacrt",
	archived: "Arhiviran",
	add: "Dodaj proizvod",
	addDescription: "Kreirajte proizvod, a zatim dopunite detalje u uređivaču.",
	open: "Otvori proizvod",
	created: "Proizvod je kreiran",
	createFailed: "Kreiranje proizvoda nije uspjelo.",
	back: "Nazad na proizvode",
	general: "Opće",
	generalDescription: "Osnovne informacije o proizvodu.",
	variants: "Varijante",
	variantsDescription: "SKU i identifikatori varijanti proizvoda.",
	addVariant: "Dodaj varijantu",
	addVariantDescription: "Dodajte prodajnu varijantu s jedinstvenim SKU-om.",
	variantCreated: "Varijanta je kreirana",
	variantCreateFailed: "Kreiranje varijante nije uspjelo.",
	sku: "SKU",
	ean: "EAN",
	variantName: "Naziv varijante",
	productNumber: "Broj proizvoda",
	searchVariants: "Pretraži varijante…",
	bulkDeleteVariants: "Obriši odabrano ({count})",
	deleteVariantsTitle: "Obrisati varijante?",
	deleteVariantsDescription: "Obrisat ćete {count} varijanti proizvoda.",
	variantsDeleted: "Varijante su obrisane",
	variantsDeleteFailed: "Varijante nisu obrisane.",
	bulkDelete: "Obriši odabrano ({count})",
	deleteTitle: "Obrisati proizvode?",
	deleteDescription: "Obrisat ćete {count} proizvoda i njihove varijante.",
	deleted: "Proizvodi su obrisani",
	deleteFailed: "Proizvodi nisu mogli biti obrisani.",
	openVariants: "Otvori varijante",
	media: "Mediji",
	prices: "Cijene",
	shortDescription: "Kratki opis",
	description: "Opis",
	updatedSuccess: "Proizvod je ažuriran",
	updateFailed: "Ažuriranje proizvoda nije uspjelo.",
	tabPreparation: "Ovaj dio proizvoda je sljedeći korak implementacije."
};
var categories$2 = {
	description: "Organizujte kategorije povlačenjem ispod druge kategorije.",
	emptyTitle: "Još nema kategorija",
	newCategory: "Nova kategorija",
	addChild: "Dodaj podkategoriju",
	addBefore: "Dodaj prije",
	addAfter: "Dodaj poslije",
	treeTipTitle: "Organizujte katalog",
	treeTipDescription: "Povucite kategoriju na drugu kategoriju da je pretvorite u podkategoriju. Koristite meni sa tri tačke za dodavanje prije, poslije ili unutar kategorije.",
	coverImage: "Naslovna slika",
	visible: "Vidljivo",
	products: "Proizvodi",
	selectProducts: "Odaberite proizvode",
	searchProducts: "Pretraži proizvode…",
	search: "Pretraži proizvođače",
	noProductsSelected: "Nema odabranih proizvoda",
	noProductsSelectedDescription: "Odaberite proizvode iznad da ih dodijelite ovoj kategoriji.",
	removeProduct: "Ukloni iz kategorije",
	removeSelectedProducts: "Ukloni odabrano ({count})",
	customFieldsHint: "Prilagođena polja kategorije bit će prikazana ovdje prema dodijeljenim skupovima.",
	selectLoaded: "Odaberi učitane kategorije",
	selectCategory: "Odaberi {name}",
	dragCategory: "Povuci {name}",
	expand: "Proširi kategoriju",
	collapse: "Skupi kategoriju",
	dropHere: "Premjesti ovdje",
	bulkDelete: "Obriši odabrano ({count})",
	deleteTitle: "Obrisati kategorije?",
	deleteDescription: "Obrisat ćete {count} odabranih kategorija.",
	deleted: "Kategorije obrisane",
	deleteFailed: "Kategorije nije moguće obrisati."
};
var manufacturers$2 = {
	add: "Dodaj proizvođača",
	createFailed: "Proizvođača nije moguće sačuvati.",
	name: "Naziv proizvođača",
	general: "Detalji proizvođača",
	logo: "Logo",
	products: "Proizvodi",
	selectProducts: "Odaberite proizvode",
	searchProducts: "Pretraži proizvode…",
	noProductsSelected: "Nema odabranih proizvoda",
	noProductsSelectedDescription: "Odaberite proizvode iznad da ih dodijelite ovom proizvođaču.",
	removeProduct: "Ukloni od proizvođača",
	removeSelectedProducts: "Ukloni odabrano ({count})"
};
var productCategories$2 = {
	label: "Kategorije",
	description: "Dodijelite proizvod jednoj ili više kategorija kataloga.",
	select: "Odaberite kategorije"
};
var integrations$2 = {
	title: "Integracije",
	available: "Dostupne integracije",
	installed: "Instalirane integracije",
	installedOn: "Instalirano: {date}",
	add: "Dodaj integraciju",
	name: "Naziv integracije",
	configuration: "Konfiguracija",
	backToIntegrations: "Nazad na integracije",
	importTab: "Uvoz",
	mappingTab: "Mapiranje",
	historyTab: "Historija",
	importProducts: "Uvezi proizvode",
	importCatalogue: "Uvezi katalog",
	importQueuedStatus: "Čekanje na radnik…",
	importProgress: "{processed} od {total}",
	importProgressWithPercent: "Uvezeno proizvoda: {processed}/{total} ({percentage}%)",
	importFailed: "Uvoz proizvoda nije moguće staviti u red čekanja.",
	importScope: "Obuhvat uvoza",
	importScopeDescription: "Odaberite podržane Shopware podatke o proizvodu koji će biti uključeni u svaki uvoz.",
	areaProducts: "Proizvodi",
	areaProductsDescription: "Osnovni podaci o proizvodu i identifikatori se uvijek uvoze.",
	areaTranslations: "Prijevodi",
	areaTranslationsDescription: "Nazivi, opisi, SEO sadržaj i prevedena prilagođena polja.",
	areaManufacturers: "Proizvođači",
	areaManufacturersDescription: "Kreirajte i dodijelite proizvođače iz Shopwarea.",
	areaTaxes: "Porezi",
	areaTaxesDescription: "Kreirajte i dodijelite poreze iz Shopware stopa poreza.",
	areaUnits: "Jedinice",
	areaUnitsDescription: "Jedinice mjere i referentne količine proizvoda.",
	areaDeliveryTimes: "Rokovi isporuke",
	areaDeliveryTimesDescription: "Reference rokova isporuke koje koriste uvezeni proizvodi.",
	areaPrices: "Cijene",
	areaPricesDescription: "Redovne bruto/neto cijene, kataloške cijene i valute.",
	areaVariants: "Varijante",
	areaVariantsDescription: "Uvezite podređene proizvode ispod njihovog Shopware roditelja.",
	areaCustomFields: "Prilagođena polja",
	areaCustomFieldsDescription: "Sačuvajte vrijednosti prilagođenih polja Shopware proizvoda.",
	areaProperties: "Svojstva",
	areaPropertiesDescription: "Grupe svojstava, vrijednosti i dodjele svojstava proizvodima.",
	areaTags: "Oznake",
	areaTagsDescription: "Oznake i dodjele oznaka proizvodima.",
	areaCategories: "Kategorije",
	areaCategoriesDescription: "Stablo kategorija i dodjele kategorija proizvodima.",
	areaChannelPublications: "Vidljivost prodajnog kanala",
	areaChannelPublicationsDescription: "Vidljivost proizvoda na svakom uvezenom prodajnom kanalu.",
	areaProductDownloads: "Preuzimanja proizvoda",
	areaProductDownloadsDescription: "Dokumenti proizvoda za preuzimanje, uključujući PDF-ove.",
	areaCrossSellings: "Preporuke proizvoda",
	areaCrossSellingsDescription: "Shopware grupe unakrsne prodaje i dodijeljeni proizvodi.",
	importPipelineTitle: "Redoslijed uvoza",
	importPipelineDescription: "Prodajni kanali, valute, jedinice, oznake, porezi, rokovi isporuke, proizvođači, svojstva, prilagođena polja i kategorije se sinhronizuju prije proizvoda, varijanti i cijena.",
	productMatching: "Povezivanje postojećih proizvoda",
	productMatchingDescription: "Izvorni ID sprječava duplikate. SKU i EAN su opcionalne zamjene za proizvode kreirane prije integracije.",
	matchExternalId: "ID Shopware proizvoda",
	matchExternalIdDescription: "Uvijek se koristi prvi putem sačuvanog izvornog mapiranja.",
	matchSku: "Broj proizvoda → SKU",
	matchSkuDescription: "Povežite postojeći proizvod sa istim SKU-om.",
	matchEan: "EAN",
	matchEanDescription: "Povežite postojeći proizvod sa istim EAN-om.",
	canonicalMapping: "Mapiranje kanonskih polja",
	importHistory: "Historija uvoza",
	importHistoryDescription: "Posljednja pokretanja uvoza proizvoda za ovu konekciju.",
	logsTab: "Zapisi",
	importLog: "Zapis uvoza",
	importLogDescription: "Dijagnostički zapisi za odabrano pokretanje uvoza. Vjerodajnice i izvorni podaci se nikada ne zapisuju.",
	viewLog: "Prikaži zapis",
	noLogEntries: "Za ovaj uvoz još nema zapisanih stavki.",
	noImportHistory: "Još nije pokrenut nijedan uvoz.",
	importSummary: "Kreirano: {created} · Ažurirano: {updated} · Neuspjelo: {failed}",
	cancelImport: "Otkaži uvoz",
	cancelImportTitle: "Otkažite ovaj uvoz?",
	cancelImportDescription: "Već uvezeni podaci će ostati. Radnik će stati nakon trenutne stavke.",
	cancelImportFailed: "Otkazivanje uvoza nije moguće.",
	importCancelled: "Uvoz je otkazan",
	importStages: {
		queued: "Čeka radnik",
		preparing: "Priprema uvoza",
		media: "Sinhronizacija medija",
		salesChannels: "Sinhronizacija prodajnih kanala",
		currencies: "Sinhronizacija valuta",
		units: "Sinhronizacija jedinica",
		tags: "Sinhronizacija oznaka",
		translations: "Sinhronizacija prijevoda",
		taxes: "Sinhronizacija poreza",
		deliveryTimes: "Sinhronizacija rokova isporuke",
		manufacturers: "Sinhronizacija proizvođača",
		properties: "Sinhronizacija svojstava",
		customFields: "Sinhronizacija prilagođenih polja",
		categories: "Sinhronizacija kategorija",
		productRelations: "Sinhronizacija veza proizvoda",
		products: "Uvoz proizvoda",
		seoUrls: "Sinhronizacija SEO URL-ova",
		completed: "Uvoz je završen",
		failed: "Uvoz nije uspio",
		cancelled: "Uvoz je otkazan"
	},
	importStatus: {
		queued: "Na čekanju",
		running: "U toku",
		completed: "Završeno",
		failed: "Neuspješno",
		cancelled: "Otkazano"
	},
	direction: "Način korištenja",
	source: "Uvoz iz platforme",
	channel: "Objava na platformu",
	baseUrl: "URL platforme",
	shopId: "ID trgovine",
	sellerId: "ID prodavača",
	accessKeyId: "ID pristupnog ključa",
	secretAccessKey: "Tajni pristupni ključ",
	consumerKey: "Korisnički ključ",
	consumerSecret: "Korisnička tajna",
	accessToken: "Pristupni token",
	apiKey: "API ključ",
	clientId: "Client ID",
	clientSecret: "Client secret",
	syncAvailable: "Automatska sinhronizacija i ručni uvoz proizvoda.",
	test: "Testiraj",
	testFailed: "Testiranje integracije nije uspjelo.",
	updateFailed: "Ažuriranje integracije nije uspjelo.",
	remove: "Ukloni integraciju",
	removeTitle: "Ukloniti integraciju?",
	removeDescription: "Trajno ćete ukloniti integraciju {name} i njene pristupne podatke.",
	removeFailed: "Uklanjanje integracije nije uspjelo.",
	none: "Još nema instaliranih integracija.",
	saved: "Integracija je sačuvana",
	tested: "Konfiguracija integracije je testirana"
};
var integrationLog$2 = {
	queued: "Shopware uvoz kataloga je stavljen na čekanje.",
	preparing: "Priprema Shopware uvoza kataloga.",
	stageStarted: "Pokrenuta je faza uvoza.",
	mediaFailed: "Shopware sliku nije moguće uvesti.",
	productsStarted: "Pokrenut je uvoz Shopware proizvoda.",
	productFailed: "Shopware proizvod nije moguće uvesti.",
	completed: "Shopware uvoz kataloga je završen.",
	failed: "Shopware uvoz kataloga nije uspio.",
	cancelled: "Shopware uvoz kataloga je otkazan."
};
var auth$2 = {
	signIn: "Prijava",
	signOut: "Odjava",
	signInDescription: "Pristupite svom SteelCode Connect radnom prostoru.",
	email: "E-mail",
	password: "Lozinka",
	forgotPassword: "Zaboravili ste lozinku?",
	resetPassword: "Resetujte lozinku",
	resetDescription: "Unesite svoju e-mail adresu i poslat ćemo vam upute za resetovanje.",
	sendResetInstructions: "Pošalji upute za resetovanje",
	resetSent: "Ako aktivni račun odgovara toj e-mail adresi, upute za resetovanje su poslane.",
	backToSignIn: "Nazad na prijavu",
	chooseNewPassword: "Odaberite novu lozinku",
	newPasswordDescription: "Nova lozinka mora sadržavati najmanje 8 znakova.",
	newPassword: "Nova lozinka",
	passwordReset: "Lozinka je resetovana",
	unableToRequestReset: "Slanje zahtjeva za resetovanje nije moguće",
	unableToResetPassword: "Resetovanje lozinke nije moguće",
	requestNewResetLink: "Zatražite novi link za resetovanje.",
	newHere: "Novi ste u SteelCode Connectu?",
	createAccount: "Kreirajte račun",
	createWorkspace: "Kreirajte svoj radni prostor",
	createWorkspaceDescription: "Pokrenite račun svoje kompanije u SteelCode Connectu.",
	companyName: "Naziv kompanije",
	alreadyHaveAccount: "Već imate račun?",
	unableToSignIn: "Prijava nije moguća",
	unableToCreateAccount: "Kreiranje računa nije moguće"
};
var company$2 = {
	details: "Detalji kompanije",
	detailsDescription: "Informacije o kompaniji koje se koriste u cijelom radnom prostoru.",
	companyName: "Naziv kompanije",
	oib: "Company ID",
	pdv: "VAT ID",
	addresses: "Adrese",
	addressesDescription: "Upravljajte adresama kompanije i adresama za naplatu.",
	addAddress: "Dodaj adresu",
	paymentMethods: "Načini plaćanja",
	paymentMethodsDescription: "Kartice se čuvaju samo kao maskirane reference.",
	addCard: "Dodaj karticu",
	billing: "Naplata",
	billingDescription: "Vaša pretplata, način plaćanja i računi.",
	currentPlan: "Trenutni paket",
	noPlan: "Paket nije odabran",
	changePlan: "Promijeni paket",
	paymentMethod: "Način plaćanja",
	noPaymentMethod: "Zadani način plaćanja nije odabran",
	managePaymentMethods: "Upravljaj načinima plaćanja",
	invoices: "Računi",
	invoicesDescription: "Otvorite generirane PDF račune.",
	openPdf: "Otvori PDF",
	noInvoices: "Još nema računa.",
	pricingPlans: "Paketi",
	pricingDescription: "Odaberite paket koji odgovara vašoj kompaniji.",
	monthly: "Mjesečno",
	annual: "Godišnje · uštedite 10%",
	month: "mjesec",
	year: "godina",
	unlimitedProducts: "Neograničeno proizvoda",
	upToProducts: "Do {count} proizvoda",
	selectPlan: "Odaberi paket",
	planUpdated: "Paket je ažuriran",
	unableToSelectPlan: "Odabir paketa nije moguć"
};
var settings$2 = {
	defaultSnippetLanguage: "Zadani jezik prijevoda",
	defaultSnippetLanguageDescription: "Novi prevedeni sadržaj se prvo kreira na ovom jeziku.",
	snippetLanguages: "Jezici prijevoda",
	snippetLanguagesDescription: "Uključite jezike dostupne za prijevode kataloga i sadržaja.",
	title: "Postavke",
	general: "Opće",
	generalDescription: "Postavke cijelog radnog prostora.",
	generalEmpty: "Opće postavke radnog prostora bit će dostupne ovdje.",
	profile: "Profil",
	profileDescription: "Upravljajte ličnim informacijama svog računa.",
	firstName: "Ime",
	lastName: "Prezime",
	identityDescription: "Koristi se za prepoznavanje u vašem radnom prostoru.",
	jobTitle: "Radna pozicija",
	jobTitleDescription: "Vaša uloga ili pozicija u kompaniji.",
	emailDescription: "Vaša e-mail adresa koristi se za prijavu.",
	phoneDescription: "Kontakt telefon za vaš račun.",
	languageDescription: "Odaberite jezik koji se koristi za vaš račun.",
	profileUpdated: "Profil je ažuriran",
	profileSaved: "Vaše postavke su sačuvane."
};
var productEditor$2 = {
	extensions: "Proširenja",
	extensionsDescription: "Shopware prilagođena polja i WooCommerce metapodaci se ovdje čuvaju bez gubitka podataka.",
	productType: "Vrsta proizvoda",
	physical: "Fizički",
	digital: "Digitalni",
	service: "Usluga",
	manufacturerNumber: "Broj proizvođača",
	taxRate: "Porezna stopa",
	shippingClass: "Klasa dostave",
	deliveryTime: "Vrijeme dostave",
	featured: "Istaknuto"
};
var productExtensions$2 = {
	namespace: "Imenski prostor",
	fieldKey: "Ključ polja",
	value: "Vrijednost",
	add: "Dodaj proširenje",
	description: "Sačuvajte podatke specifične za konektor bez izmjene strukture proizvoda.",
	valueDescription: "Običan tekst ili ispravan JSON.",
	empty: "Nema dodanih podataka proširenja.",
	created: "Vrijednost proširenja je dodana",
	createFailed: "Dodavanje vrijednosti proširenja nije uspjelo.",
	deleted: "Vrijednost proširenja je uklonjena",
	deleteFailed: "Uklanjanje vrijednosti proširenja nije uspjelo."
};
var productPrices$2 = {
	description: "Sačuvajte prodajne, nabavne i kataloške cijene u svakoj podržanoj valuti.",
	add: "Dodaj cijenu",
	empty: "Nema dodanih cijena.",
	currency: "Valuta",
	type: "Vrsta",
	"default": "Prodajna cijena",
	purchase: "Nabavna cijena",
	list: "Kataloška cijena",
	net: "Neto iznos (najmanja jedinica)",
	gross: "Bruto iznos (najmanja jedinica)",
	listGross: "Kataloški bruto iznos (najmanja jedinica)",
	tax: "Porezna stopa",
	quantity: "Količina",
	quantityStart: "Količina od",
	quantityEnd: "Količina do",
	created: "Cijena je dodana",
	createFailed: "Dodavanje cijene nije uspjelo."
};
var productVariantGeneration$2 = {
	open: "Generiši varijante",
	description: "Odaberite atribute i vrijednosti, zatim kreirajte sve kombinacije proizvoda."
};
var productSections$2 = {
	deliverability: "Isporuka",
	deliverabilityDescription: "Ograničenja kupovine i postavke dostave. Zalihe se vode po skladištu.",
	deliveryTime: "Vrijeme dostave",
	restockTime: "Vrijeme ponovne nabavke u danima",
	minPurchaseQuantity: "Minimalna količina narudžbe",
	purchaseSteps: "Korak kupovine",
	maxPurchaseQuantity: "Maksimalna količina narudžbe",
	clearanceSale: "Rasprodaja",
	freeShipping: "Besplatna dostava",
	labelling: "Označavanje",
	labellingDescription: "Identifikacija proizvoda i fizičke dimenzije.",
	weight: "Težina (g)",
	length: "Dužina (mm)",
	width: "Širina (mm)",
	height: "Visina (mm)",
	visibilityStructure: "Vidljivost i struktura",
	visibilityStructureDescription: "Kako kupci i integracije pronalaze ovaj proizvod.",
	searchKeywords: "Ključne riječi za pretragu"
};
var productRegularPrice$2 = {
	title: "Redovna cijena",
	description: "Standardna prodajna cijena koja se koristi kada nema naprednog pravila.",
	advancedDescription: "Cijene po valuti, količinskim nivoima, kataloške i nabavne cijene.",
	saved: "Redovna cijena je sačuvana",
	saveFailed: "Spremanje redovne cijene nije uspjelo."
};
var productRegularPriceFields$2 = {
	gross: "Cijena (bruto)",
	net: "Cijena (neto)",
	purchaseGross: "Nabavna cijena (bruto)",
	purchaseNet: "Nabavna cijena (neto)",
	listGross: "Kataloška cijena (bruto)",
	listNet: "Kataloška cijena (neto)",
	cheapestGross: "Najniža cijena (zadnjih 30 dana, bruto)",
	cheapestNet: "Najniža cijena (zadnjih 30 dana, neto)"
};
var productPriceValidation$2 = {
	required: "Porezna stopa, bruto i neto cijena su obavezni.",
	requiredField: "Ovo polje je obavezno.",
	requiredFields: "Popunite obavezna polja: {fields}."
};
var productPriceLabels$2 = {
	gross: "Bruto cijena",
	net: "Neto cijena"
};
var productAdvancedPrice$2 = {
	add: "Dodaj naprednu cijenu",
	description: "Kreirajte pravilo cijene koje zamjenjuje redovnu cijenu za određeni raspon količine.",
	empty: "Nema dodanih pravila napredne cijene.",
	quantity: "Količina",
	quantityFrom: "Količina od",
	quantityTo: "Količina do",
	price: "Cijena",
	priceType: "Vrsta cijene",
	listPrice: "Kataloška cijena",
	cheapestPrice: "Najniža cijena (zadnjih 30 dana)",
	listNet: "Kataloška cijena (neto)",
	listGross: "Kataloška cijena (bruto)",
	validity: "Važenje",
	validFrom: "Važi od",
	validUntil: "Važi do"
};
var productAdvancedPriceLabels$2 = {
	gross: "bruto",
	net: "neto"
};
var productAdvancedPriceTab$2 = {
	title: "Napredno određivanje cijena",
	addRule: "Dodaj pravilo cijene"
};
var productGallery$2 = {
	title: "Galerija",
	description: "Dodajte slike proizvoda, odaberite naslovnu sliku i odredite redoslijed prikaza.",
	upload: "Dodaj slike",
	empty: "Galerija je prazna",
	emptyDescription: "Dodajte slike proizvoda u JPEG, PNG, WebP, GIF ili AVIF formatu.",
	cover: "Naslovna",
	setCover: "Postavi naslovnu",
	moveLeft: "Pomjeri lijevo",
	moveRight: "Pomjeri desno",
	uploaded: "Slike proizvoda su dodane",
	uploadFailed: "Dodavanje slika nije uspjelo.",
	deleted: "Slika proizvoda je obrisana",
	deleteFailed: "Brisanje slike nije uspjelo.",
	orderFailed: "Redoslijed slika nije sačuvan.",
	duplicateTitle: "Slika već postoji",
	duplicateDescription: "Odaberite da li želite zamijeniti postojeću sliku ili dodati novu pod drugim nazivom.",
	replace: "Zamijeni",
	addWithNewName: "Dodaj s drugim nazivom"
};
var productRelations$2 = {
	title: "Povezani sadržaj",
	downloads: "Preuzimanja",
	downloadsDescription: "Datoteke povezane s ovim proizvodom iz uvezanog kataloga.",
	noDownloads: "Nema povezanih datoteka za preuzimanje",
	openDownload: "Otvori {name}",
	crossSellings: "Unakrsna prodaja",
	crossSellingsDescription: "Grupe proizvoda koje se prikazuju uz ovaj proizvod.",
	noCrossSellings: "Nema grupa unakrsne prodaje",
	productCount: "{count} proizvoda",
	inactive: "Neaktivno",
	dynamicStream: "Dinamički stream",
	noAssignedProducts: "Nema dodijeljenih proizvoda."
};
var propertyGroups$2 = {
	title: "Grupe svojstava",
	description: "Definišite zajednička svojstva proizvoda i vrijednosti za specifikacije i varijante.",
	addGroup: "Dodaj grupu",
	addProperty: "Dodaj svojstvo",
	switchToDefaultToAdd: "Prebacite se na {language} da dodate novo svojstvo.",
	name: "Naziv",
	code: "Kod",
	codeDescription: "Stabilan tehnički identifikator. Ako ostane prazan, generiše se iz naziva.",
	displayType: "Način prikaza",
	text: "Tekst",
	color: "Boja",
	image: "Slika",
	initialProperties: "Početna svojstva",
	initialPropertiesDescription: "Odvojite vrijednosti zarezom, npr. Crna, Plava, Crvena.",
	filterable: "Dostupno kao filter",
	colorHex: "HEX boja",
	empty: "Nema grupa svojstava",
	emptyDescription: "Kreirajte prvu grupu, npr. Boja, Veličina ili Materijal.",
	search: "Pretraži svojstva",
	noProperties: "Nema dodanih svojstava.",
	created: "Grupa svojstava je dodana",
	propertyCreated: "Svojstvo je dodano",
	propertyDeleted: "Svojstvo je obrisano",
	confirmDelete: "Obrisati svojstvo {name}?",
	createFailed: "Spremanje svojstva nije uspjelo."
};
var productProperties$2 = {
	title: "Svojstva",
	description: "Dodijelite svojstva i vrijednosti ovom proizvodu. Varijante se biraju samo u generatoru varijanti.",
	emptyTitle: "Prvo kreirajte grupu svojstava",
	emptyDescription: "Grupe i vrijednosti se dijele između svojstava, filtera i varijanti.",
	openGroups: "Otvori grupe svojstava",
	configure: "Podesi svojstva",
	noneAssigned: "Nisu dodijeljena svojstva",
	noneAssignedDescription: "Dodajte grupe svojstava i njihove vrijednosti koje će biti prikazane na detalju proizvoda.",
	modalDescription: "Odaberite grupe svojstava i vrijednosti za ovaj proizvod.",
	selectGroup: "Grupe svojstava",
	searchValues: "Pretraži vrijednosti",
	searchAdded: "Pretraži dodana svojstva…",
	noValues: "Nema odgovarajućih vrijednosti.",
	selectedCount: "Odabrano: {count}",
	property: "Svojstvo",
	propertyValues: "Vrijednosti svojstva",
	selectAll: "Odaberi sve",
	selectRow: "Odaberi red",
	bulkDelete: "Obriši odabrano ({count})",
	removeTitle: "Obrisati svojstva?",
	removeDescription: "Obrisat ćete {count} dodijeljenih vrijednosti svojstava s proizvoda.",
	removed: "Svojstva su obrisana",
	removeFailed: "Svojstva nisu obrisana.",
	saved: "Svojstva su sačuvana",
	saveFailed: "Svojstva nisu sačuvana."
};
var productCustomFields$2 = {
	title: "Prilagođena polja",
	description: "Dodatni podaci proizvoda koji nisu dio standardnog kataloga.",
	add: "Dodaj polje",
	addDescription: "Dodajte prilagođenu vrijednost proizvoda.",
	empty: "Nema aktivnih prilagođenih polja za proizvode.",
	emptySet: "Ovaj skup još nema prilagođenih polja.",
	uncategorized: "Nekategorizovano",
	uncategorizedDescription: "Metapodaci integracija bez definisanog skupa prilagođenih polja, uključujući WooCommerce meta podatke.",
	manage: "Upravljaj prilagođenim poljima"
};
var productSalesChannels$2 = {
	title: "Vidljivost prodajnih kanala",
	description: "Odredite gdje će proizvod biti dostupan za svaki povezani prodajni kanal.",
	hidden: "Skriveno",
	link: "Samo direktni link",
	search: "Pretraga",
	all: "Sve",
	sync: "Sinhronizuj prodajne kanale",
	synced: "Prodajni kanali su sinhronizovani",
	syncFailed: "Prodajni kanali nisu sinhronizovani"
};
var productUnits$2 = {
	title: "Jedinice",
	unit: "Jedinica",
	purchaseUnit: "Kupovna jedinica",
	referenceUnit: "Referentna jedinica",
	packUnit: "Pakovna jedinica",
	packUnitPlural: "Pakovna jedinica – množina"
};
var productSeo$2 = {
	title: "SEO",
	description: "Podesite sadržaj za prikaz proizvoda u pretraživačima.",
	url: "SEO URL",
	metaTitle: "Meta naslov",
	metaDescription: "Meta opis",
	keywords: "Meta ključne riječi"
};
var shopReferences$2 = {
	title: "Postavke trgovine",
	taxes: "Porezi",
	units: "Jedinice",
	deliveryTimes: "Vremena dostave",
	searchTaxes: "Pretraži poreze",
	searchUnits: "Pretraži jedinice",
	searchDeliveryTimes: "Pretraži vremena dostave",
	name: "Naziv",
	rate: "Stopa",
	code: "Kod",
	symbol: "Oznaka",
	min: "Minimum",
	max: "Maksimum",
	unit: "Jedinica"
};
var customFields$2 = {
	title: "Prilagođena polja",
	description: "Definišite skupove, tipove i prevedene oznake polja za katalog.",
	newSet: "Novi skup polja",
	addField: "Dodaj polje",
	label: "Oznaka",
	technicalName: "Tehnički naziv",
	technicalValue: "Tehnička vrijednost",
	type: "Vrsta",
	products: "Dodijeli proizvodima",
	multiSelect: "Višestruki odabir",
	addOption: "Dodaj opciju",
	noFields: "Ovaj skup još nema polja.",
	fields: "Prilagođena polja",
	search: "Pretraži polja…",
	bulkDelete: "Obriši odabrano ({count})",
	deleteDescription: "Obrisat ćete {count} prilagođenih polja.",
	deleted: "Prilagođena polja su obrisana",
	rowsPerPage: "Redova po stranici",
	emptyTitle: "Nema skupova prilagođenih polja",
	emptyDescription: "Kreirajte skup, zatim dodajte polja koja će se prikazivati na proizvodima.",
	created: "Skup prilagođenih polja je kreiran",
	fieldCreated: "Prilagođeno polje je kreirano"
};
var customFieldsExtra$2 = {
	setDescription: "Kreirajte skup polja i odredite gdje će biti dostupan.",
	fieldDescription: "Podesite tip, tehnička pravila i dostupnost polja.",
	position: "Pozicija",
	manageLabels: "Upravljaj oznakama na svim jezicima administracije",
	assignTo: "Dodijeli entitetima",
	entity: "Entitet",
	availableInCart: "Dostupno u korpi",
	allowStoreApi: "Mijenjanje putem Store API-ja",
	visibleStoreApi: "Vidljivo putem Store API-ja",
	relations: {
		product: "Proizvodi",
		category: "Kategorije",
		manufacturer: "Proizvođači",
		customer: "Kupci",
		order: "Narudžbe",
		property_group: "Grupe svojstava",
		property: "Svojstva",
		media: "Mediji"
	}
};
var customFieldConfig$2 = {
	helpText: "Pomoćni tekst",
	placeholder: "Rezervirani tekst",
	numberType: "Vrsta broja",
	float: "Decimalni broj",
	integer: "Cijeli broj",
	min: "Minimalna vrijednost",
	max: "Maksimalna vrijednost",
	step: "Korak",
	dateType: "Vrsta datuma",
	date: "Samo datum",
	datetime: "Datum i vrijeme",
	time: "Vrijeme",
	defaultValue: "Zadana vrijednost",
	defaultActive: "Aktivno po zadanom",
	required: "Obavezno polje",
	searchable: "Uključi u pretragu"
};
var customFieldTypes$2 = {
	text: "Tekstualno polje",
	editor: "Uređivač teksta",
	number: "Broj",
	date: "Datum i vrijeme",
	checkbox: "Potvrdni okvir",
	"switch": "Prekidač",
	select: "Polje odabira",
	entity: "Odabir entiteta",
	media: "Medij",
	color: "Birač boje",
	price: "Polje cijene"
};
var productExtra$2 = {
	releaseDate: "Datum i vrijeme objave",
	releaseDatePlaceholder: "Odaberite datum i vrijeme",
	time: "Vrijeme",
	tags: "Oznake",
	tagsPlaceholder: "Upišite oznaku i pritisnite Enter",
	keywordsPlaceholder: "Upišite ključnu riječ i pritisnite Enter"
};
var inventoryMovements$2 = {
	title: "Promjene zaliha",
	description: "Historija ručnih promjena zaliha proizvoda.",
	empty: "Nema evidentiranih promjena zaliha.",
	date: "Datum",
	change: "Promjena"
};
var productSpecifications$2 = {
	title: "Specifikacije",
	description: "Tehničke mjere i dimenzije proizvoda."
};
var inventory$2 = {
	stock: "Zaliha",
	stockByWarehouse: "Zaliha po skladištu",
	availableStock: "Dostupna zaliha",
	reservedStock: "Rezervisana zaliha",
	unavailableStock: "Nedostupna zaliha",
	incomingStock: "Zaliha u dolasku",
	warehouse: "Skladište",
	adjustStock: "Podesi zalihu",
	adjustStockDescription: "Postavite fizičku zalihu u odabranom skladištu. Promjena se bilježi u historiji zaliha.",
	note: "Napomena",
	stockUpdated: "Zaliha je ažurirana",
	stockUpdateFailed: "Zaliha nije ažurirana.",
	stockDescription: "Pregled fizičke i dostupne zalihe svih proizvoda.",
	warehousesDescription: "Upravljajte skladištima iz kojih se računa dostupna zaliha.",
	addWarehouse: "Dodaj skladište",
	searchStock: "Pretraži zalihe…",
	searchWarehouses: "Pretraži skladišta…",
	code: "Kod",
	status: "Status",
	active: "Aktivno",
	inactive: "Neaktivno",
	fulfillment: "Isporuka",
	fulfillmentEnabled: "Koristi se za isporuku",
	fulfillmentDisabled: "Ne koristi se za isporuku",
	fulfillmentDescription: "Uključite skladište kada se zaliha dodjeljuje za isporuku.",
	fulfillmentPriority: "Prioritet isporuke",
	warehouseSaved: "Skladište je sačuvano",
	warehouseSaveFailed: "Skladište nije sačuvano."
};
var inventoryTransfers$2 = {
	title: "Transferi",
	create: "Kreiraj transfer",
	created: "Nacrt transfera je kreiran",
	createValidation: "Odaberite različita skladišta i najmanje jedan proizvod.",
	selectWarehouse: "Odaberite skladište",
	searchWarehouses: "Pretraži skladišta…",
	products: "Proizvodi",
	selectProducts: "Odaberite proizvode",
	date: "Datum",
	source: "Iz skladišta",
	destination: "U skladište",
	items: "Stavke",
	send: "Pošalji transfer",
	receive: "Primi transfer",
	sendSuccess: "Transfer je poslan",
	receiveSuccess: "Transfer je primljen",
	cancel: "Otkaži transfer",
	cancelSuccess: "Transfer otkazan",
	cancelDescription: "Nacrt transfera bit će otkazan bez promjene zaliha.",
	sendDescription: "Slanjem se količina uklanja iz izvornog skladišta dok se ne zaprimi.",
	receiveDescription: "Prijemom se količina dodaje odredišnom skladištu.",
	empty: "Nema transfera",
	emptyDescription: "Kreirajte transfer za premještanje zalihe između skladišta.",
	status: {
		draft: "Nacrt",
		in_transit: "U tranzitu",
		received: "Primljeno",
		cancelled: "Otkazano"
	}
};
var suppliers$2 = {
	name: "Naziv dobavljača",
	search: "Pretraži dobavljače…",
	add: "Dodaj dobavljača",
	edit: "Uredi dobavljača",
	saved: "Dobavljač je sačuvan",
	saveFailed: "Dobavljač nije sačuvan.",
	loadFailed: "Dobavljači nisu učitani",
	empty: "Nema dobavljača",
	emptyDescription: "Dodajte dobavljača za pripremu narudžbenica.",
	contactName: "Kontakt osoba",
	street: "Adresa",
	postalCode: "Poštanski broj",
	city: "Grad",
	country: "Država"
};
var inventoryCounts$2 = {
	title: "Inventurni popisi",
	create: "Kreiraj popis",
	created: "Nacrt popisa kreiran",
	createValidation: "Odaberite skladište i najmanje jedan proizvod.",
	selectWarehouse: "Odaberi skladište",
	date: "Datum",
	expected: "Očekivano",
	counted: "Prebrojano",
	variance: "Razlika",
	post: "Proknjiži popis",
	postDescription: "Zaliha će se prilagoditi prebrojanim količinama. Ako se zaliha u međuvremenu promijenila, knjiženje će biti odbijeno.",
	posted: "Popis proknjižen",
	empty: "Nema inventurnih popisa",
	emptyDescription: "Kreirajte popis za usklađivanje fizičke zalihe.",
	status: {
		draft: "Nacrt",
		posted: "Proknjiženo"
	}
};
var purchasing$2 = {
	validFrom: "Važi od",
	validUntil: "Važi do",
	noValidityDate: "Bez vremenskog ograničenja",
	clearDate: "Ukloni datum",
	purchaseUnit: "Nabavna jedinica",
	stockUnitsPerPurchaseUnit: "Jedinica zalihe po nabavnoj jedinici",
	stockUnits: "jedinica zalihe",
	editOrder: "Uredi nacrt",
	downloadPdf: "Preuzmi PDF",
	emailOrder: "Pošalji narudžbenicu e-poštom",
	emailDescription: "Poslati ovaj PDF na {email}? Ovo šalje stvarnu e-poštu.",
	emailedAt: "Posljednji put poslano",
	damageResolution: "Rješavanje oštećenja",
	damageNote: "Napomena o rješenju",
	saveResolution: "Sačuvaj rješenje",
	damageStatus: {
		open: "Otvoren zahtjev",
		returned: "Vraćeno dobavljaču",
		credited: "Dobavljač odobrio povrat",
		written_off: "Otpisano",
		replaced: "Zamjena dogovorena"
	},
	offers: "Ponude dobavljača",
	orders: "Narudžbenice",
	addOffer: "Dodaj ponudu",
	editOffer: "Uredi ponudu",
	offerSaved: "Ponuda dobavljača sačuvana",
	offerValidation: "Odaberite dobavljača i proizvod te ispravnu cijenu i minimalnu količinu.",
	searchOffers: "Pretraži broj proizvoda ili dobavljača…",
	noOffers: "Nema ponuda dobavljača",
	noOffersDescription: "Dodajte nabavne cijene i rokove isporuke prije kreiranja narudžbenica.",
	productNumber: "Broj proizvoda",
	supplierSku: "Šifra dobavljača",
	unitCost: "Nabavna cijena",
	currency: "Valuta",
	minimumQuantity: "Minimalna količina",
	leadTime: "Rok isporuke (dana)",
	preferred: "Preferirani dobavljač",
	selectSupplier: "Odaberi dobavljača",
	selectProduct: "Odaberi proizvod",
	createOrder: "Kreiraj narudžbenicu",
	noOrders: "Nema narudžbenica",
	noOrdersDescription: "Kreirajte narudžbenicu iz aktivnih ponuda dobavljača.",
	orderValidation: "Odaberite dobavljača, skladište i proizvode s odgovarajućom minimalnom količinom i valutom.",
	orderCreated: "Nacrt narudžbenice kreiran",
	orderUpdated: "Narudžbenica ažurirana",
	orderDetail: "Narudžbenica",
	view: "Prikaži detalje",
	send: "Označi kao poslano",
	receive: "Zaprimi robu",
	cancel: "Otkaži narudžbenicu",
	sendDescription: "Otvorene količine bit će evidentirane kao dolazna zaliha. E-pošta se ne šalje; pošaljite PDF zasebno ili koristite opciju slanja dobavljaču.",
	cancelDescription: "Preostale dolazne količine bit će uklonjene. Već zaprimljena zaliha ostaje.",
	receipts: "Prijemi",
	receiptValidation: "Unesite valjanu ispravnu ili oštećenu količinu, ne veću od otvorene.",
	receiptSaved: "Prijem robe sačuvan",
	ordered: "Naručeno",
	received: "Ispravno primljeno",
	damaged: "Oštećeno",
	outstanding: "Otvoreno",
	total: "Ukupno",
	status: {
		draft: "Nacrt",
		sent: "Poslano",
		partially_received: "Djelimično primljeno",
		received: "Primljeno",
		cancelled: "Otkazano"
	}
};
var productNavigation$2 = {
	backToParent: "Nazad na glavni proizvod"
};
var languages$2 = {
	bs: "Bosanski",
	en: "Engleski",
	de: "Njemački"
};
const locale_bs_46json_044b03a0 = {
	nav: nav$2,
	common: common$2,
	table: table$2,
	catalogue: catalogue$2,
	products: products$2,
	categories: categories$2,
	manufacturers: manufacturers$2,
	productCategories: productCategories$2,
	integrations: integrations$2,
	integrationLog: integrationLog$2,
	auth: auth$2,
	company: company$2,
	settings: settings$2,
	productEditor: productEditor$2,
	productExtensions: productExtensions$2,
	productPrices: productPrices$2,
	productVariantGeneration: productVariantGeneration$2,
	productSections: productSections$2,
	productRegularPrice: productRegularPrice$2,
	productRegularPriceFields: productRegularPriceFields$2,
	productPriceValidation: productPriceValidation$2,
	productPriceLabels: productPriceLabels$2,
	productAdvancedPrice: productAdvancedPrice$2,
	productAdvancedPriceLabels: productAdvancedPriceLabels$2,
	productAdvancedPriceTab: productAdvancedPriceTab$2,
	productGallery: productGallery$2,
	productRelations: productRelations$2,
	propertyGroups: propertyGroups$2,
	productProperties: productProperties$2,
	productCustomFields: productCustomFields$2,
	productSalesChannels: productSalesChannels$2,
	productUnits: productUnits$2,
	productSeo: productSeo$2,
	shopReferences: shopReferences$2,
	customFields: customFields$2,
	customFieldsExtra: customFieldsExtra$2,
	customFieldConfig: customFieldConfig$2,
	customFieldTypes: customFieldTypes$2,
	productExtra: productExtra$2,
	inventoryMovements: inventoryMovements$2,
	productSpecifications: productSpecifications$2,
	inventory: inventory$2,
	inventoryTransfers: inventoryTransfers$2,
	suppliers: suppliers$2,
	inventoryCounts: inventoryCounts$2,
	purchasing: purchasing$2,
	productNavigation: productNavigation$2,
	languages: languages$2
};

var nav$1 = {
	home: "Home",
	integrations: "Integrations",
	catalogue: "Catalogue",
	products: "Products",
	categories: "Categories",
	manufacturers: "Manufacturers",
	attributes: "Attributes",
	customFields: "Custom fields",
	suppliers: "Suppliers",
	inventory: "Inventory",
	stock: "Stock",
	warehouses: "Warehouses",
	transfers: "Transfers",
	stockCounts: "Stock counts",
	company: "Company",
	details: "Details",
	addresses: "Addresses",
	paymentMethods: "Payment methods",
	billing: "Billing",
	pricingPlans: "Pricing plans",
	settings: "Settings",
	shop: "Shop",
	taxes: "Taxes",
	units: "Units",
	deliveryTimes: "Delivery times",
	general: "General",
	profile: "Profile",
	translations: "Translations",
	members: "Members",
	notifications: "Notifications",
	security: "Security",
	help: "Help & Support"
};
var common$1 = {
	saveChanges: "Save changes",
	save: "Save",
	saved: "Saved",
	cancel: "Cancel",
	create: "Create",
	"delete": "Delete",
	edit: "Edit",
	add: "Add",
	"default": "Default",
	language: "Language",
	email: "Email",
	phone: "Phone",
	error: "Unable to save changes",
	website: "Website",
	loading: "Loading…",
	changesSaved: "Your changes have been saved.",
	requiredField: "This field is required.",
	requiredFields: "Complete the required fields: {fields}.",
	invalidField: "This value is invalid.",
	invalidFields: "Correct the following fields: {fields}.",
	tryAgain: "Check the entered data and try again.",
	totalResults: "{count} results",
	rowsPerPage: "Rows per page"
};
var table$1 = {
	columns: "Columns"
};
var catalogue$1 = {
	title: "Catalogue",
	comingSoon: "This module is being prepared.",
	addOption: "Add option",
	addOptionDescription: "Define an option that creates variants.",
	optionName: "Option name",
	optionValues: "Values",
	optionValuesDescription: "Enter comma-separated values.",
	optionCreated: "Option created",
	optionCreateFailed: "Unable to create option.",
	generateVariants: "Generate {count} variants",
	variantGenerateFailed: "Unable to generate variants."
};
var products$1 = {
	product: "Product",
	name: "Product name",
	manufacturer: "Manufacturer",
	price: "Price",
	stock: "Stock",
	selectManufacturer: "Select manufacturer",
	searchManufacturers: "Search manufacturers…",
	status: "Status",
	updated: "Updated",
	search: "Search products…",
	allStatuses: "All statuses",
	active: "Active",
	draft: "Draft",
	archived: "Archived",
	add: "Add product",
	addDescription: "Create a product, then complete its details in the editor.",
	open: "Open product",
	created: "Product created",
	createFailed: "Unable to create product.",
	back: "Back to products",
	general: "General",
	generalDescription: "Core product information.",
	variants: "Variants",
	variantsDescription: "Product SKU and variant identifiers.",
	addVariant: "Add variant",
	addVariantDescription: "Add a sellable variant with a unique SKU.",
	variantCreated: "Variant created",
	variantCreateFailed: "Unable to create variant.",
	sku: "SKU",
	ean: "EAN",
	variantName: "Variant name",
	productNumber: "Product number",
	searchVariants: "Search variants…",
	bulkDeleteVariants: "Delete selected ({count})",
	deleteVariantsTitle: "Delete variants?",
	deleteVariantsDescription: "You will delete {count} product variants.",
	variantsDeleted: "Variants deleted",
	variantsDeleteFailed: "Variants could not be deleted.",
	bulkDelete: "Delete selected ({count})",
	deleteTitle: "Delete products?",
	deleteDescription: "You will delete {count} products and their variants.",
	deleted: "Products deleted",
	deleteFailed: "Products could not be deleted.",
	openVariants: "Open variants",
	media: "Media",
	prices: "Prices",
	shortDescription: "Short description",
	description: "Description",
	updatedSuccess: "Product updated",
	updateFailed: "Unable to update the product.",
	tabPreparation: "This product area is the next implementation step."
};
var categories$1 = {
	description: "Organize categories by dragging them below another category.",
	emptyTitle: "No categories yet",
	newCategory: "New category",
	addChild: "Add child category",
	addBefore: "Add before",
	addAfter: "Add after",
	treeTipTitle: "Organize your catalogue",
	treeTipDescription: "Drag a category onto another category to make it a child. Use the three-dot menu to add a category before, after, or inside it.",
	coverImage: "Cover image",
	visible: "Visible",
	products: "Products",
	selectProducts: "Select products",
	searchProducts: "Search products…",
	search: "Search manufacturers",
	noProductsSelected: "No products selected",
	noProductsSelectedDescription: "Select products above to assign them to this category.",
	removeProduct: "Remove from category",
	removeSelectedProducts: "Remove selected ({count})",
	customFieldsHint: "Category custom fields from assigned sets will appear here.",
	selectLoaded: "Select loaded categories",
	selectCategory: "Select {name}",
	dragCategory: "Drag {name}",
	expand: "Expand category",
	collapse: "Collapse category",
	dropHere: "Move here",
	bulkDelete: "Delete selected ({count})",
	deleteTitle: "Delete categories?",
	deleteDescription: "You will delete {count} selected categories.",
	deleted: "Categories deleted",
	deleteFailed: "Categories could not be deleted."
};
var manufacturers$1 = {
	add: "Add manufacturer",
	createFailed: "Manufacturer could not be saved.",
	name: "Manufacturer name",
	general: "Manufacturer details",
	logo: "Logo",
	products: "Products",
	selectProducts: "Select products",
	searchProducts: "Search products…",
	noProductsSelected: "No products selected",
	noProductsSelectedDescription: "Select products above to assign them to this manufacturer.",
	removeProduct: "Remove from manufacturer",
	removeSelectedProducts: "Remove selected ({count})"
};
var productCategories$1 = {
	label: "Categories",
	description: "Assign this product to one or more catalogue categories.",
	select: "Select categories"
};
var integrations$1 = {
	title: "Integrations",
	available: "Available integrations",
	installed: "Installed integrations",
	installedOn: "Installed: {date}",
	add: "Add integration",
	name: "Integration name",
	configuration: "Configuration",
	backToIntegrations: "Back to integrations",
	importTab: "Import",
	mappingTab: "Mapping",
	historyTab: "History",
	importProducts: "Import products",
	importCatalogue: "Import catalogue",
	importQueuedStatus: "Waiting for worker…",
	importProgress: "{processed} of {total}",
	importProgressWithPercent: "{processed}/{total} products imported ({percentage}%)",
	importFailed: "Product import could not be queued.",
	importScope: "Import scope",
	importScopeDescription: "Choose the supported Shopware product data to include in each import.",
	areaProducts: "Products",
	areaProductsDescription: "Core product information and identifiers are always imported.",
	areaTranslations: "Translations",
	areaTranslationsDescription: "Names, descriptions, SEO content and translated custom fields.",
	areaManufacturers: "Manufacturers",
	areaManufacturersDescription: "Create and assign manufacturers from Shopware.",
	areaTaxes: "Taxes",
	areaTaxesDescription: "Create and assign taxes from Shopware tax rates.",
	areaUnits: "Units",
	areaUnitsDescription: "Units of measurement and product reference quantities.",
	areaDeliveryTimes: "Delivery times",
	areaDeliveryTimesDescription: "Delivery-time references used by imported products.",
	areaPrices: "Prices",
	areaPricesDescription: "Regular gross/net prices, list prices and currencies.",
	areaVariants: "Variants",
	areaVariantsDescription: "Import child products below their Shopware parent product.",
	areaCustomFields: "Custom fields",
	areaCustomFieldsDescription: "Store Shopware product custom-field values.",
	areaProperties: "Properties",
	areaPropertiesDescription: "Property groups, values and product property assignments.",
	areaTags: "Tags",
	areaTagsDescription: "Tags and product tag assignments.",
	areaCategories: "Categories",
	areaCategoriesDescription: "Category tree and product category assignments.",
	areaChannelPublications: "Sales-channel visibility",
	areaChannelPublicationsDescription: "Product visibility in each imported sales channel.",
	areaProductDownloads: "Product downloads",
	areaProductDownloadsDescription: "Downloadable product documents, including PDFs.",
	areaCrossSellings: "Product recommendations",
	areaCrossSellingsDescription: "Shopware cross-selling groups and assigned products.",
	importPipelineTitle: "Import order",
	importPipelineDescription: "Sales channels, currencies, units, tags, taxes, delivery times, manufacturers, properties, custom fields and categories are synchronized before products, variants and prices.",
	productMatching: "Existing product matching",
	productMatchingDescription: "Source IDs prevent duplicates. SKU and EAN are optional fallback matches for products created before the integration.",
	matchExternalId: "Shopware product ID",
	matchExternalIdDescription: "Always used first through the stored source mapping.",
	matchSku: "Product number → SKU",
	matchSkuDescription: "Match an existing product with the same SKU.",
	matchEan: "EAN",
	matchEanDescription: "Match an existing product with the same EAN.",
	canonicalMapping: "Canonical field mapping",
	importHistory: "Import history",
	importHistoryDescription: "The latest product import runs for this connection.",
	logsTab: "Logs",
	importLog: "Import log",
	importLogDescription: "Diagnostic entries for the selected import run. Credentials and source payloads are never logged.",
	viewLog: "View log",
	noLogEntries: "No log entries have been recorded for this import.",
	noImportHistory: "No imports have been run yet.",
	importSummary: "Created: {created} · Updated: {updated} · Failed: {failed}",
	cancelImport: "Cancel import",
	cancelImportTitle: "Cancel this import?",
	cancelImportDescription: "Already imported records will be kept. The worker will stop after its current item.",
	cancelImportFailed: "Unable to cancel the import.",
	importCancelled: "Import cancelled",
	importStages: {
		queued: "Waiting for worker",
		preparing: "Preparing import",
		media: "Synchronizing media",
		salesChannels: "Synchronizing sales channels",
		currencies: "Synchronizing currencies",
		units: "Synchronizing units",
		tags: "Synchronizing tags",
		translations: "Synchronizing translations",
		taxes: "Synchronizing taxes",
		deliveryTimes: "Synchronizing delivery times",
		manufacturers: "Synchronizing manufacturers",
		properties: "Synchronizing properties",
		customFields: "Synchronizing custom fields",
		categories: "Synchronizing categories",
		productRelations: "Synchronizing product relations",
		products: "Importing products",
		seoUrls: "Synchronizing SEO URLs",
		completed: "Import completed",
		failed: "Import failed",
		cancelled: "Import cancelled"
	},
	importStatus: {
		queued: "Queued",
		running: "Running",
		completed: "Completed",
		failed: "Failed",
		cancelled: "Cancelled"
	},
	direction: "Usage",
	source: "Import from platform",
	channel: "Publish to platform",
	baseUrl: "Platform URL",
	shopId: "Shop ID",
	sellerId: "Seller ID",
	accessKeyId: "Access key ID",
	secretAccessKey: "Secret access key",
	consumerKey: "Consumer key",
	consumerSecret: "Consumer secret",
	accessToken: "Access token",
	apiKey: "API key",
	clientId: "Client ID",
	clientSecret: "Client secret",
	syncAvailable: "Automatic synchronization and manual product import.",
	test: "Test",
	testFailed: "Integration test failed.",
	updateFailed: "Unable to update the integration.",
	remove: "Remove integration",
	removeTitle: "Remove integration?",
	removeDescription: "This permanently removes {name} and its credentials.",
	removeFailed: "Unable to remove the integration.",
	none: "No integrations installed yet.",
	saved: "Integration saved",
	tested: "Integration configuration tested"
};
var integrationLog$1 = {
	queued: "Shopware catalogue import queued.",
	preparing: "Preparing Shopware catalogue import.",
	stageStarted: "Started import phase.",
	mediaFailed: "A Shopware image could not be imported.",
	productsStarted: "Started Shopware product import.",
	productFailed: "A Shopware product could not be imported.",
	completed: "Shopware catalogue import completed.",
	failed: "Shopware catalogue import failed.",
	cancelled: "Shopware catalogue import cancelled."
};
var auth$1 = {
	signIn: "Sign in",
	signOut: "Sign out",
	signInDescription: "Access your SteelCode Connect workspace.",
	email: "Email",
	password: "Password",
	forgotPassword: "Forgot password?",
	resetPassword: "Reset password",
	resetDescription: "Enter your email address and we will send reset instructions.",
	sendResetInstructions: "Send reset instructions",
	resetSent: "If an active account matches that email address, reset instructions have been sent.",
	backToSignIn: "Back to sign in",
	chooseNewPassword: "Choose a new password",
	newPasswordDescription: "Your new password must contain at least 8 characters.",
	newPassword: "New password",
	passwordReset: "Password reset",
	unableToRequestReset: "Unable to request password reset",
	unableToResetPassword: "Unable to reset password",
	requestNewResetLink: "Please request a new reset link.",
	newHere: "New to SteelCode Connect?",
	createAccount: "Create an account",
	createWorkspace: "Create your workspace",
	createWorkspaceDescription: "Start your SteelCode Connect company account.",
	companyName: "Company name",
	alreadyHaveAccount: "Already have an account?",
	unableToSignIn: "Unable to sign in",
	unableToCreateAccount: "Unable to create account"
};
var company$1 = {
	details: "Company details",
	detailsDescription: "Company information used across your workspace.",
	companyName: "Company name",
	oib: "Company ID",
	pdv: "VAT ID",
	addresses: "Addresses",
	addressesDescription: "Manage company and billing addresses.",
	addAddress: "Add address",
	paymentMethods: "Payment methods",
	paymentMethodsDescription: "Cards are stored as masked references only.",
	addCard: "Add card",
	billing: "Billing",
	billingDescription: "Your subscription, payment method and invoices.",
	currentPlan: "Current plan",
	noPlan: "No plan selected",
	changePlan: "Change plan",
	paymentMethod: "Payment method",
	noPaymentMethod: "No default payment method",
	managePaymentMethods: "Manage payment methods",
	invoices: "Invoices",
	invoicesDescription: "Open your generated invoice PDFs.",
	openPdf: "Open PDF",
	noInvoices: "No invoices yet.",
	pricingPlans: "Pricing plans",
	pricingDescription: "Choose the plan that fits your company.",
	monthly: "Monthly",
	annual: "Annual · save 10%",
	month: "month",
	year: "year",
	unlimitedProducts: "Unlimited products",
	upToProducts: "Up to {count} products",
	selectPlan: "Select plan",
	planUpdated: "Plan updated",
	unableToSelectPlan: "Unable to select plan"
};
var settings$1 = {
	defaultSnippetLanguage: "Default snippets language",
	defaultSnippetLanguageDescription: "New translated content is created in this language first.",
	snippetLanguages: "Snippet languages",
	snippetLanguagesDescription: "Enable the languages available for catalogue and content translations.",
	title: "Settings",
	general: "General",
	generalDescription: "Workspace-wide settings.",
	generalEmpty: "General workspace settings will be added here as they become available.",
	profile: "Profile",
	profileDescription: "Manage the personal information for your account.",
	firstName: "First name",
	lastName: "Last name",
	identityDescription: "Used to identify you across your workspace.",
	jobTitle: "Job title",
	jobTitleDescription: "Your role or position in the company.",
	emailDescription: "Your email address is used to sign in.",
	phoneDescription: "A contact number for your account.",
	languageDescription: "Choose the language used for your account.",
	profileUpdated: "Profile updated",
	profileSaved: "Your settings have been saved."
};
var productEditor$1 = {
	extensions: "Extensions",
	extensionsDescription: "Shopware custom fields and WooCommerce metadata are stored here without data loss.",
	productType: "Product type",
	physical: "Physical",
	digital: "Digital",
	service: "Service",
	manufacturerNumber: "Manufacturer number",
	taxRate: "Tax rate",
	shippingClass: "Shipping class",
	deliveryTime: "Delivery time",
	featured: "Featured"
};
var productExtensions$1 = {
	namespace: "Namespace",
	fieldKey: "Field key",
	value: "Value",
	add: "Add extension",
	description: "Store connector-specific data without changing the product schema.",
	valueDescription: "Plain text or valid JSON.",
	empty: "No extension data has been added.",
	created: "Extension value added",
	createFailed: "Unable to add the extension value.",
	deleted: "Extension value removed",
	deleteFailed: "Unable to remove the extension value."
};
var productPrices$1 = {
	description: "Store selling, purchase and list prices in each supported currency.",
	add: "Add price",
	empty: "No prices have been added.",
	currency: "Currency",
	type: "Type",
	"default": "Selling price",
	purchase: "Purchase price",
	list: "List price",
	net: "Net amount (minor units)",
	gross: "Gross amount (minor units)",
	listGross: "List gross amount (minor units)",
	tax: "Tax rate",
	quantity: "Quantity",
	quantityStart: "Quantity from",
	quantityEnd: "Quantity to",
	created: "Price added",
	createFailed: "Unable to add the price."
};
var productVariantGeneration$1 = {
	open: "Generate variants",
	description: "Choose attributes and values, then create all product combinations."
};
var productSections$1 = {
	deliverability: "Deliverability",
	deliverabilityDescription: "Purchase constraints and shipping settings. Stock is managed per warehouse.",
	deliveryTime: "Delivery time",
	restockTime: "Restock time in days",
	minPurchaseQuantity: "Minimum order quantity",
	purchaseSteps: "Purchase steps",
	maxPurchaseQuantity: "Maximum order quantity",
	clearanceSale: "Clearance sale",
	freeShipping: "Free shipping",
	labelling: "Labelling",
	labellingDescription: "Product identification and physical dimensions.",
	weight: "Weight (g)",
	length: "Length (mm)",
	width: "Width (mm)",
	height: "Height (mm)",
	visibilityStructure: "Visibility & structure",
	visibilityStructureDescription: "How customers and integrations discover this product.",
	searchKeywords: "Search keywords"
};
var productRegularPrice$1 = {
	title: "Regular price",
	description: "The standard selling price used when no advanced rule applies.",
	advancedDescription: "Currency-specific, quantity-tier, list and purchase prices.",
	saved: "Regular price saved",
	saveFailed: "Unable to save the regular price."
};
var productRegularPriceFields$1 = {
	gross: "Price (gross)",
	net: "Price (net)",
	purchaseGross: "Purchase price (gross)",
	purchaseNet: "Purchase price (net)",
	listGross: "List price (gross)",
	listNet: "List price (net)",
	cheapestGross: "Cheapest price (last 30 days, gross)",
	cheapestNet: "Cheapest price (last 30 days, net)"
};
var productPriceValidation$1 = {
	required: "Tax rate, gross price and net price are required.",
	requiredField: "This field is required.",
	requiredFields: "Complete the required fields: {fields}."
};
var productPriceLabels$1 = {
	gross: "Gross price",
	net: "Net price"
};
var productAdvancedPrice$1 = {
	add: "Add advanced price",
	description: "Create a price rule that overrides the regular price for a quantity range.",
	empty: "No advanced price rules have been added.",
	quantity: "Quantity",
	quantityFrom: "Quantity from",
	quantityTo: "Quantity to",
	price: "Price",
	listPrice: "List price",
	listNet: "List price (net)",
	listGross: "List price (gross)",
	validity: "Validity",
	validFrom: "Valid from",
	validUntil: "Valid until"
};
var productAdvancedPriceInline = {
	rule: "Rule",
	allCustomers: "All customers"
};
var productAdvancedPriceLabels$1 = {
	gross: "gross",
	net: "net"
};
var productAdvancedPriceTab$1 = {
	title: "Advanced pricing",
	addRule: "Add price rule"
};
var productGallery$1 = {
	title: "Gallery",
	description: "Add product images, select a cover image and set their display order.",
	upload: "Add images",
	empty: "The gallery is empty",
	emptyDescription: "Add product images in JPEG, PNG, WebP, GIF or AVIF format.",
	cover: "Cover",
	setCover: "Set as cover",
	moveLeft: "Move left",
	moveRight: "Move right",
	uploaded: "Product images added",
	uploadFailed: "Product images could not be uploaded.",
	deleted: "Product image deleted",
	deleteFailed: "Product image could not be deleted.",
	orderFailed: "Image order could not be saved.",
	duplicateTitle: "Image already exists",
	duplicateDescription: "Choose whether to replace the existing image or add a new copy with a different filename.",
	replace: "Replace",
	addWithNewName: "Add with new name"
};
var productRelations$1 = {
	title: "Related content",
	downloads: "Downloads",
	downloadsDescription: "Files attached to this product from the connected catalogue.",
	noDownloads: "No downloads attached",
	openDownload: "Open {name}",
	crossSellings: "Cross-selling",
	crossSellingsDescription: "Product groups shown alongside this product.",
	noCrossSellings: "No cross-selling groups",
	productCount: "{count} products",
	inactive: "Inactive",
	dynamicStream: "Dynamic stream",
	noAssignedProducts: "No products have been assigned."
};
var propertyGroups$1 = {
	title: "Property groups",
	description: "Define shared product properties and values for specifications and variants.",
	addGroup: "Add group",
	addProperty: "Add property",
	switchToDefaultToAdd: "Switch to {language} to add a new property.",
	name: "Name",
	code: "Code",
	codeDescription: "Stable technical identifier. It is generated from the name when left empty.",
	displayType: "Display type",
	text: "Text",
	color: "Color",
	image: "Image",
	initialProperties: "Initial properties",
	initialPropertiesDescription: "Separate values with commas, for example Black, Blue, Red.",
	filterable: "Available as a filter",
	colorHex: "HEX color",
	empty: "No property groups",
	emptyDescription: "Create the first group, for example Color, Size or Material.",
	search: "Search properties",
	noProperties: "No properties added.",
	created: "Property group added",
	propertyCreated: "Property added",
	propertyDeleted: "Property deleted",
	confirmDelete: "Delete property {name}?",
	createFailed: "Property could not be saved."
};
var productProperties$1 = {
	title: "Properties",
	description: "Assign properties and values to this product. Variants are selected only in the variant generator.",
	emptyTitle: "Create a property group first",
	emptyDescription: "Groups and values are shared between properties, filters and variants.",
	openGroups: "Open property groups",
	configure: "Configure properties",
	noneAssigned: "No properties assigned",
	noneAssignedDescription: "Add property groups and values that should be shown on the product detail page.",
	modalDescription: "Select property groups and values for this product.",
	selectGroup: "Property groups",
	searchValues: "Search values",
	searchAdded: "Search added properties…",
	noValues: "No matching values.",
	selectedCount: "Selected: {count}",
	property: "Property",
	propertyValues: "Property value",
	selectAll: "Select all",
	selectRow: "Select row",
	bulkDelete: "Delete selected ({count})",
	removeTitle: "Delete properties?",
	removeDescription: "You will remove {count} assigned property values from this product.",
	removed: "Properties deleted",
	removeFailed: "Properties could not be deleted.",
	saved: "Properties saved",
	saveFailed: "Properties could not be saved."
};
var productCustomFields$1 = {
	title: "Custom fields",
	description: "Additional product data that is not part of the standard catalogue.",
	add: "Add field",
	addDescription: "Add a custom product value.",
	empty: "There are no active custom fields for products.",
	emptySet: "This set has no custom fields yet.",
	uncategorized: "Uncategorized",
	uncategorizedDescription: "Integration metadata without an assigned custom field set, including WooCommerce metadata.",
	manage: "Manage custom fields"
};
var productSalesChannels$1 = {
	title: "Sales channel visibility",
	description: "Choose where this product is available for each connected sales channel.",
	hidden: "Hidden",
	link: "Direct link only",
	search: "Search",
	all: "All",
	sync: "Sync sales channels",
	synced: "Sales channels synchronized",
	syncFailed: "Sales channels could not be synchronized"
};
var productUnits$1 = {
	title: "Units",
	unit: "Unit",
	purchaseUnit: "Purchase unit",
	referenceUnit: "Reference unit",
	packUnit: "Packaging unit",
	packUnitPlural: "Packaging unit plural"
};
var productSeo$1 = {
	title: "SEO",
	description: "Configure how this product appears in search engines.",
	url: "SEO URL",
	metaTitle: "Meta title",
	metaDescription: "Meta description",
	keywords: "Meta keywords"
};
var shopReferences$1 = {
	title: "Shop settings",
	taxes: "Taxes",
	units: "Units",
	deliveryTimes: "Delivery times",
	searchTaxes: "Search taxes",
	searchUnits: "Search units",
	searchDeliveryTimes: "Search delivery times",
	name: "Name",
	rate: "Rate",
	code: "Code",
	symbol: "Symbol",
	min: "Minimum",
	max: "Maximum",
	unit: "Unit"
};
var customFields$1 = {
	title: "Custom fields",
	description: "Define sets, types and translated labels for catalogue fields.",
	newSet: "New field set",
	addField: "Add field",
	label: "Label",
	technicalName: "Technical name",
	technicalValue: "Technical value",
	type: "Type",
	products: "Assign to products",
	multiSelect: "Multiple selection",
	addOption: "Add option",
	noFields: "This set has no fields yet.",
	fields: "Custom fields",
	search: "Search fields…",
	bulkDelete: "Delete selected ({count})",
	deleteDescription: "You will delete {count} custom fields.",
	deleted: "Custom fields deleted",
	rowsPerPage: "Rows per page",
	emptyTitle: "No custom field sets",
	emptyDescription: "Create a set, then add fields that appear on products.",
	created: "Custom field set created",
	fieldCreated: "Custom field created"
};
var customFieldsExtra$1 = {
	setDescription: "Create a field set and choose where it is available.",
	fieldDescription: "Configure the type, technical rules and availability.",
	position: "Position",
	manageLabels: "Manage labels in all administration languages",
	assignTo: "Assign to entities",
	entity: "Entity",
	availableInCart: "Available in shopping carts",
	allowStoreApi: "Modifiable via Store API",
	visibleStoreApi: "Visible in Store API",
	relations: {
		product: "Products",
		category: "Categories",
		manufacturer: "Manufacturers",
		customer: "Customers",
		order: "Orders",
		property_group: "Property groups",
		property: "Properties",
		media: "Media"
	}
};
var customFieldConfig$1 = {
	helpText: "Help text",
	placeholder: "Placeholder",
	numberType: "Number type",
	float: "Decimal number",
	integer: "Integer",
	min: "Minimum value",
	max: "Maximum value",
	step: "Step",
	dateType: "Date type",
	date: "Date only",
	datetime: "Date and time",
	time: "Time",
	defaultValue: "Default value",
	defaultActive: "Active by default",
	required: "Required field",
	searchable: "Include in search"
};
var customFieldTypes$1 = {
	text: "Text field",
	editor: "Text editor",
	number: "Number",
	date: "Date and time",
	checkbox: "Checkbox",
	"switch": "Active switch",
	select: "Select field",
	entity: "Entity select",
	media: "Media field",
	color: "Colour picker",
	price: "Price field"
};
var productExtra$1 = {
	releaseDate: "Release date and time",
	releaseDatePlaceholder: "Select date and time",
	time: "Time",
	tags: "Tags",
	tagsPlaceholder: "Enter a tag and press Enter",
	keywordsPlaceholder: "Enter a keyword and press Enter"
};
var inventoryMovements$1 = {
	title: "Stock movements",
	description: "History of manual product stock adjustments.",
	empty: "No stock movements recorded.",
	date: "Date",
	change: "Change"
};
var productSpecifications$1 = {
	title: "Specifications",
	description: "Technical measurements and product dimensions."
};
var inventory$1 = {
	stock: "Stock",
	stockByWarehouse: "Stock by warehouse",
	availableStock: "Available stock",
	reservedStock: "Reserved stock",
	unavailableStock: "Unavailable stock",
	incomingStock: "Incoming stock",
	warehouse: "Warehouse",
	adjustStock: "Adjust stock",
	adjustStockDescription: "Set physical stock in the selected warehouse. The change is recorded in stock history.",
	note: "Note",
	stockUpdated: "Stock updated",
	stockUpdateFailed: "Stock could not be updated.",
	stockDescription: "Overview of physical and available stock for all products.",
	warehousesDescription: "Manage warehouses used to calculate available stock.",
	addWarehouse: "Add warehouse",
	searchStock: "Search stock…",
	searchWarehouses: "Search warehouses…",
	code: "Code",
	status: "Status",
	active: "Active",
	inactive: "Inactive",
	fulfillment: "Fulfilment",
	fulfillmentEnabled: "Used for fulfilment",
	fulfillmentDisabled: "Not used for fulfilment",
	fulfillmentDescription: "Include this warehouse when stock is allocated for fulfilment.",
	fulfillmentPriority: "Fulfilment priority",
	warehouseSaved: "Warehouse saved",
	warehouseSaveFailed: "Warehouse could not be saved."
};
var inventoryTransfers$1 = {
	title: "Transfers",
	create: "Create transfer",
	created: "Transfer draft created",
	createValidation: "Choose different warehouses and at least one product.",
	selectWarehouse: "Select warehouse",
	searchWarehouses: "Search warehouses…",
	products: "Products",
	selectProducts: "Select products",
	date: "Date",
	source: "From warehouse",
	destination: "To warehouse",
	items: "Items",
	send: "Send transfer",
	receive: "Receive transfer",
	sendSuccess: "Transfer sent",
	receiveSuccess: "Transfer received",
	cancel: "Cancel transfer",
	cancelSuccess: "Transfer cancelled",
	cancelDescription: "This draft transfer will be cancelled without changing stock.",
	sendDescription: "Sending removes these quantities from the source warehouse until they are received.",
	receiveDescription: "Receiving adds these quantities to the destination warehouse.",
	empty: "No transfers yet",
	emptyDescription: "Create a transfer to move stock between warehouses.",
	status: {
		draft: "Draft",
		in_transit: "In transit",
		received: "Received",
		cancelled: "Cancelled"
	}
};
var suppliers$1 = {
	name: "Supplier name",
	search: "Search suppliers…",
	add: "Add supplier",
	edit: "Edit supplier",
	saved: "Supplier saved",
	saveFailed: "Supplier could not be saved.",
	loadFailed: "Suppliers could not be loaded",
	empty: "No suppliers yet",
	emptyDescription: "Add a supplier to prepare purchase orders.",
	contactName: "Contact person",
	street: "Street address",
	postalCode: "Postal code",
	city: "City",
	country: "Country"
};
var inventoryCounts$1 = {
	title: "Stock counts",
	create: "Create count",
	created: "Count draft created",
	createValidation: "Choose a warehouse and at least one product.",
	selectWarehouse: "Select warehouse",
	date: "Date",
	expected: "Expected",
	counted: "Counted",
	variance: "Variance",
	post: "Post count",
	postDescription: "This adjusts stock to the counted quantities. Posting is rejected if stock changed after the count was created.",
	posted: "Count posted",
	empty: "No stock counts yet",
	emptyDescription: "Create a count to reconcile physical stock.",
	status: {
		draft: "Draft",
		posted: "Posted"
	}
};
var purchasing$1 = {
	validFrom: "Valid from",
	validUntil: "Valid until",
	noValidityDate: "No date limit",
	clearDate: "Clear date",
	purchaseUnit: "Purchase unit",
	stockUnitsPerPurchaseUnit: "Stock units per purchase unit",
	stockUnits: "stock units",
	editOrder: "Edit draft",
	downloadPdf: "Download PDF",
	emailOrder: "Email PO to supplier",
	emailDescription: "Send this PDF to {email}? This sends a real email.",
	emailedAt: "Last emailed",
	damageResolution: "Damage resolution",
	damageNote: "Resolution note",
	saveResolution: "Save resolution",
	damageStatus: {
		open: "Open claim",
		returned: "Returned to supplier",
		credited: "Credited by supplier",
		written_off: "Written off",
		replaced: "Replacement agreed"
	},
	offers: "Supplier offers",
	orders: "Purchase orders",
	addOffer: "Add offer",
	editOffer: "Edit offer",
	offerSaved: "Supplier offer saved",
	offerValidation: "Choose a supplier and product, with a valid cost and minimum quantity.",
	searchOffers: "Search product or supplier SKU…",
	noOffers: "No supplier offers yet",
	noOffersDescription: "Add product costs and lead times from your suppliers before creating purchase orders.",
	productNumber: "Product number",
	supplierSku: "Supplier SKU",
	unitCost: "Unit cost",
	currency: "Currency",
	minimumQuantity: "Minimum quantity",
	leadTime: "Lead time (days)",
	preferred: "Preferred supplier",
	selectSupplier: "Select supplier",
	selectProduct: "Select product",
	createOrder: "Create purchase order",
	noOrders: "No purchase orders yet",
	noOrdersDescription: "Create an order from active supplier offers.",
	orderValidation: "Choose a supplier, warehouse, and products that meet their minimum quantities and use one currency.",
	orderCreated: "Purchase order draft created",
	orderUpdated: "Purchase order updated",
	orderDetail: "Purchase order",
	view: "View details",
	send: "Mark as sent",
	receive: "Receive goods",
	cancel: "Cancel order",
	sendDescription: "This confirms the order and adds its open quantities to incoming stock. It does not send an email; use Email PO to supplier separately or deliver the PDF yourself.",
	cancelDescription: "Outstanding incoming quantities will be removed. Already received stock remains unchanged.",
	receipts: "Receipts",
	receiptValidation: "Enter a valid good or damaged quantity, no more than the outstanding amount.",
	receiptSaved: "Goods receipt saved",
	ordered: "Ordered",
	received: "Good received",
	damaged: "Damaged",
	outstanding: "Outstanding",
	total: "Total",
	status: {
		draft: "Draft",
		sent: "Sent",
		partially_received: "Partially received",
		received: "Received",
		cancelled: "Cancelled"
	}
};
var productNavigation$1 = {
	backToParent: "Back to parent product"
};
var languages$1 = {
	bs: "Bosnian",
	en: "English",
	de: "German"
};
const locale_en_46json_603da42c = {
	nav: nav$1,
	common: common$1,
	table: table$1,
	catalogue: catalogue$1,
	products: products$1,
	categories: categories$1,
	manufacturers: manufacturers$1,
	productCategories: productCategories$1,
	integrations: integrations$1,
	integrationLog: integrationLog$1,
	auth: auth$1,
	company: company$1,
	settings: settings$1,
	productEditor: productEditor$1,
	productExtensions: productExtensions$1,
	productPrices: productPrices$1,
	productVariantGeneration: productVariantGeneration$1,
	productSections: productSections$1,
	productRegularPrice: productRegularPrice$1,
	productRegularPriceFields: productRegularPriceFields$1,
	productPriceValidation: productPriceValidation$1,
	productPriceLabels: productPriceLabels$1,
	productAdvancedPrice: productAdvancedPrice$1,
	productAdvancedPriceInline: productAdvancedPriceInline,
	productAdvancedPriceLabels: productAdvancedPriceLabels$1,
	productAdvancedPriceTab: productAdvancedPriceTab$1,
	productGallery: productGallery$1,
	productRelations: productRelations$1,
	propertyGroups: propertyGroups$1,
	productProperties: productProperties$1,
	productCustomFields: productCustomFields$1,
	productSalesChannels: productSalesChannels$1,
	productUnits: productUnits$1,
	productSeo: productSeo$1,
	shopReferences: shopReferences$1,
	customFields: customFields$1,
	customFieldsExtra: customFieldsExtra$1,
	customFieldConfig: customFieldConfig$1,
	customFieldTypes: customFieldTypes$1,
	productExtra: productExtra$1,
	inventoryMovements: inventoryMovements$1,
	productSpecifications: productSpecifications$1,
	inventory: inventory$1,
	inventoryTransfers: inventoryTransfers$1,
	suppliers: suppliers$1,
	inventoryCounts: inventoryCounts$1,
	purchasing: purchasing$1,
	productNavigation: productNavigation$1,
	languages: languages$1
};

var nav = {
	home: "Startseite",
	integrations: "Integrationen",
	catalogue: "Katalog",
	products: "Produkte",
	categories: "Kategorien",
	manufacturers: "Hersteller",
	attributes: "Attribute",
	customFields: "Benutzerdefinierte Felder",
	suppliers: "Lieferanten",
	inventory: "Bestand",
	stock: "Produktbestand",
	warehouses: "Lager",
	transfers: "Umlagerungen",
	company: "Unternehmen",
	details: "Details",
	addresses: "Adressen",
	paymentMethods: "Zahlungsmethoden",
	billing: "Abrechnung",
	pricingPlans: "Tarife",
	settings: "Einstellungen",
	shop: "Shop",
	taxes: "Steuern",
	units: "Einheiten",
	deliveryTimes: "Lieferzeiten",
	general: "Allgemein",
	profile: "Profil",
	translations: "Übersetzungen",
	members: "Mitglieder",
	notifications: "Benachrichtigungen",
	security: "Sicherheit",
	help: "Hilfe & Support"
};
var common = {
	saveChanges: "Änderungen speichern",
	save: "Speichern",
	saved: "Gespeichert",
	cancel: "Abbrechen",
	create: "Erstellen",
	"delete": "Löschen",
	edit: "Bearbeiten",
	add: "Hinzufügen",
	"default": "Standard",
	language: "Sprache",
	email: "E-Mail",
	phone: "Telefon",
	error: "Änderungen konnten nicht gespeichert werden",
	website: "Website",
	loading: "Laden…",
	changesSaved: "Ihre Änderungen wurden gespeichert.",
	requiredField: "Dieses Feld ist erforderlich.",
	requiredFields: "Füllen Sie die Pflichtfelder aus: {fields}.",
	invalidField: "Dieser Wert ist ungültig.",
	invalidFields: "Korrigieren Sie die folgenden Felder: {fields}.",
	tryAgain: "Prüfen Sie die eingegebenen Daten und versuchen Sie es erneut.",
	totalResults: "{count} Ergebnisse",
	rowsPerPage: "Zeilen pro Seite"
};
var table = {
	columns: "Spalten"
};
var catalogue = {
	title: "Katalog",
	comingSoon: "Dieses Modul wird derzeit vorbereitet.",
	addOption: "Option hinzufügen",
	addOptionDescription: "Definieren Sie eine Option, die Varianten erzeugt.",
	optionName: "Optionsname",
	optionValues: "Werte",
	optionValuesDescription: "Geben Sie durch Kommas getrennte Werte ein.",
	optionCreated: "Option erstellt",
	optionCreateFailed: "Option kann nicht erstellt werden.",
	generateVariants: "{count} Varianten generieren",
	variantGenerateFailed: "Varianten können nicht generiert werden."
};
var products = {
	product: "Produkt",
	name: "Produktname",
	manufacturer: "Hersteller",
	price: "Preis",
	stock: "Bestand",
	selectManufacturer: "Hersteller auswählen",
	searchManufacturers: "Hersteller suchen…",
	status: "Status",
	updated: "Aktualisiert",
	search: "Produkte suchen…",
	allStatuses: "Alle Status",
	active: "Aktiv",
	draft: "Entwurf",
	archived: "Archiviert",
	add: "Produkt hinzufügen",
	addDescription: "Erstellen Sie ein Produkt und vervollständigen Sie die Details im Editor.",
	open: "Produkt öffnen",
	created: "Produkt erstellt",
	createFailed: "Produkt kann nicht erstellt werden.",
	back: "Zurück zu Produkten",
	general: "Allgemein",
	generalDescription: "Grundlegende Produktinformationen.",
	variants: "Varianten",
	variantsDescription: "Produkt-SKU und Variantenkennungen.",
	addVariant: "Variante hinzufügen",
	addVariantDescription: "Fügen Sie eine verkaufbare Variante mit eindeutiger SKU hinzu.",
	variantCreated: "Variante erstellt",
	variantCreateFailed: "Variante kann nicht erstellt werden.",
	sku: "SKU",
	ean: "EAN",
	variantName: "Variantenname",
	productNumber: "Produktnummer",
	searchVariants: "Varianten suchen…",
	bulkDeleteVariants: "Auswahl löschen ({count})",
	deleteVariantsTitle: "Varianten löschen?",
	deleteVariantsDescription: "Sie löschen {count} Produktvarianten.",
	variantsDeleted: "Varianten gelöscht",
	variantsDeleteFailed: "Varianten konnten nicht gelöscht werden.",
	bulkDelete: "Auswahl löschen ({count})",
	deleteTitle: "Produkte löschen?",
	deleteDescription: "Sie löschen {count} Produkte und deren Varianten.",
	deleted: "Produkte gelöscht",
	deleteFailed: "Produkte konnten nicht gelöscht werden.",
	openVariants: "Varianten öffnen",
	media: "Medien",
	prices: "Preise",
	shortDescription: "Kurzbeschreibung",
	description: "Beschreibung",
	updatedSuccess: "Produkt aktualisiert",
	updateFailed: "Produkt kann nicht aktualisiert werden.",
	tabPreparation: "Dieser Produktbereich ist der nächste Implementierungsschritt."
};
var categories = {
	description: "Organisieren Sie Kategorien, indem Sie sie unter eine andere Kategorie ziehen.",
	emptyTitle: "Noch keine Kategorien",
	newCategory: "Neue Kategorie",
	addChild: "Unterkategorie hinzufügen",
	addBefore: "Davor hinzufügen",
	addAfter: "Danach hinzufügen",
	treeTipTitle: "Katalog organisieren",
	treeTipDescription: "Ziehen Sie eine Kategorie auf eine andere, um sie zur Unterkategorie zu machen. Verwenden Sie das Drei-Punkte-Menü zum Hinzufügen davor, danach oder innerhalb.",
	coverImage: "Titelbild",
	visible: "Sichtbar",
	products: "Produkte",
	selectProducts: "Produkte auswählen",
	searchProducts: "Produkte suchen…",
	search: "Hersteller suchen",
	noProductsSelected: "Keine Produkte ausgewählt",
	noProductsSelectedDescription: "Wählen Sie oben Produkte aus, um sie dieser Kategorie zuzuordnen.",
	removeProduct: "Aus Kategorie entfernen",
	removeSelectedProducts: "Ausgewählte entfernen ({count})",
	customFieldsHint: "Kategorie-Benutzerfelder aus zugewiesenen Gruppen werden hier angezeigt.",
	selectLoaded: "Geladene Kategorien auswählen",
	selectCategory: "{name} auswählen",
	dragCategory: "{name} ziehen",
	expand: "Kategorie aufklappen",
	collapse: "Kategorie zuklappen",
	dropHere: "Hierher verschieben",
	bulkDelete: "Ausgewählte löschen ({count})",
	deleteTitle: "Kategorien löschen?",
	deleteDescription: "Sie löschen {count} ausgewählte Kategorien.",
	deleted: "Kategorien gelöscht",
	deleteFailed: "Kategorien konnten nicht gelöscht werden."
};
var manufacturers = {
	add: "Hersteller hinzufügen",
	createFailed: "Der Hersteller konnte nicht gespeichert werden.",
	name: "Herstellername",
	general: "Herstellerdetails",
	logo: "Logo",
	products: "Produkte",
	selectProducts: "Produkte auswählen",
	searchProducts: "Produkte suchen…",
	noProductsSelected: "Keine Produkte ausgewählt",
	noProductsSelectedDescription: "Wählen Sie oben Produkte aus, um sie diesem Hersteller zuzuordnen.",
	removeProduct: "Vom Hersteller entfernen",
	removeSelectedProducts: "Ausgewählte entfernen ({count})"
};
var productCategories = {
	label: "Kategorien",
	description: "Ordnen Sie dieses Produkt einer oder mehreren Katalogkategorien zu.",
	select: "Kategorien auswählen"
};
var integrations = {
	title: "Integrationen",
	available: "Verfügbare Integrationen",
	installed: "Installierte Integrationen",
	installedOn: "Installiert: {date}",
	add: "Integration hinzufügen",
	name: "Name der Integration",
	configuration: "Konfiguration",
	backToIntegrations: "Zurück zu Integrationen",
	importTab: "Import",
	mappingTab: "Zuordnung",
	historyTab: "Verlauf",
	importProducts: "Produkte importieren",
	importCatalogue: "Katalog importieren",
	importQueuedStatus: "Warten auf Worker…",
	importProgress: "{processed} von {total}",
	importProgressWithPercent: "{processed}/{total} Produkte importiert ({percentage}%)",
	importFailed: "Der Produktimport konnte nicht in die Warteschlange gestellt werden.",
	importScope: "Importumfang",
	importScopeDescription: "Wählen Sie die unterstützten Shopware-Produktdaten für jeden Import aus.",
	areaProducts: "Produkte",
	areaProductsDescription: "Produktstammdaten und Kennungen werden immer importiert.",
	areaTranslations: "Übersetzungen",
	areaTranslationsDescription: "Namen, Beschreibungen, SEO-Inhalte und übersetzte Benutzerfelder.",
	areaManufacturers: "Hersteller",
	areaManufacturersDescription: "Hersteller aus Shopware erstellen und zuordnen.",
	areaTaxes: "Steuern",
	areaTaxesDescription: "Steuern aus Shopware-Steuersätzen erstellen und zuordnen.",
	areaUnits: "Einheiten",
	areaUnitsDescription: "Maßeinheiten und Produkt-Referenzmengen.",
	areaDeliveryTimes: "Lieferzeiten",
	areaDeliveryTimesDescription: "Lieferzeitreferenzen, die von importierten Produkten verwendet werden.",
	areaPrices: "Preise",
	areaPricesDescription: "Reguläre Brutto-/Nettopreise, Listenpreise und Währungen.",
	areaVariants: "Varianten",
	areaVariantsDescription: "Untergeordnete Produkte unter ihrem Shopware-Hauptprodukt importieren.",
	areaCustomFields: "Benutzerfelder",
	areaCustomFieldsDescription: "Shopware-Benutzerfeldwerte am Produkt speichern.",
	areaProperties: "Eigenschaften",
	areaPropertiesDescription: "Eigenschaftsgruppen, Werte und Produktzuweisungen.",
	areaTags: "Tags",
	areaTagsDescription: "Tags und Produkt-Tag-Zuweisungen.",
	areaCategories: "Kategorien",
	areaCategoriesDescription: "Kategoriestruktur und Produktkategoriezuweisungen.",
	areaChannelPublications: "Sichtbarkeit im Vertriebskanal",
	areaChannelPublicationsDescription: "Produktsichtbarkeit in jedem importierten Vertriebskanal.",
	areaProductDownloads: "Produkt-Downloads",
	areaProductDownloadsDescription: "Herunterladbare Produktdokumente, einschließlich PDFs.",
	areaCrossSellings: "Produktempfehlungen",
	areaCrossSellingsDescription: "Shopware-Cross-Selling-Gruppen und zugeordnete Produkte.",
	importPipelineTitle: "Importreihenfolge",
	importPipelineDescription: "Vertriebskanäle, Währungen, Einheiten, Tags, Steuern, Lieferzeiten, Hersteller, Eigenschaften, Benutzerfelder und Kategorien werden vor Produkten, Varianten und Preisen synchronisiert.",
	productMatching: "Abgleich vorhandener Produkte",
	productMatchingDescription: "Quell-IDs verhindern Duplikate. SKU und EAN sind optionale Ausweichabgleiche für Produkte vor der Integration.",
	matchExternalId: "Shopware-Produkt-ID",
	matchExternalIdDescription: "Wird immer zuerst über die gespeicherte Quellzuordnung verwendet.",
	matchSku: "Produktnummer → SKU",
	matchSkuDescription: "Ein vorhandenes Produkt mit derselben SKU abgleichen.",
	matchEan: "EAN",
	matchEanDescription: "Ein vorhandenes Produkt mit derselben EAN abgleichen.",
	canonicalMapping: "Kanonische Feldzuordnung",
	importHistory: "Importverlauf",
	importHistoryDescription: "Die letzten Produktimportläufe für diese Verbindung.",
	logsTab: "Protokolle",
	importLog: "Importprotokoll",
	importLogDescription: "Diagnoseeinträge für den ausgewählten Importlauf. Zugangsdaten und Quelldaten werden nie protokolliert.",
	viewLog: "Protokoll anzeigen",
	noLogEntries: "Für diesen Import wurden noch keine Protokolleinträge erfasst.",
	noImportHistory: "Es wurden noch keine Importe ausgeführt.",
	importSummary: "Erstellt: {created} · Aktualisiert: {updated} · Fehlgeschlagen: {failed}",
	cancelImport: "Import abbrechen",
	cancelImportTitle: "Diesen Import abbrechen?",
	cancelImportDescription: "Bereits importierte Daten bleiben erhalten. Der Worker stoppt nach dem aktuellen Element.",
	cancelImportFailed: "Import kann nicht abgebrochen werden.",
	importCancelled: "Import abgebrochen",
	importStages: {
		queued: "Wartet auf Worker",
		preparing: "Import wird vorbereitet",
		media: "Medien werden synchronisiert",
		salesChannels: "Vertriebskanäle werden synchronisiert",
		currencies: "Währungen werden synchronisiert",
		units: "Einheiten werden synchronisiert",
		tags: "Tags werden synchronisiert",
		translations: "Übersetzungen werden synchronisiert",
		taxes: "Steuern werden synchronisiert",
		deliveryTimes: "Lieferzeiten werden synchronisiert",
		manufacturers: "Hersteller werden synchronisiert",
		properties: "Eigenschaften werden synchronisiert",
		customFields: "Benutzerfelder werden synchronisiert",
		categories: "Kategorien werden synchronisiert",
		productRelations: "Produktbeziehungen werden synchronisiert",
		products: "Produkte werden importiert",
		seoUrls: "SEO-URLs werden synchronisiert",
		completed: "Import abgeschlossen",
		failed: "Import fehlgeschlagen",
		cancelled: "Import abgebrochen"
	},
	importStatus: {
		queued: "In Warteschlange",
		running: "Wird ausgeführt",
		completed: "Abgeschlossen",
		failed: "Fehlgeschlagen",
		cancelled: "Abgebrochen"
	},
	direction: "Verwendung",
	source: "Von Plattform importieren",
	channel: "Auf Plattform veröffentlichen",
	baseUrl: "Plattform-URL",
	shopId: "Shop-ID",
	sellerId: "Verkäufer-ID",
	accessKeyId: "Zugriffsschlüssel-ID",
	secretAccessKey: "Geheimer Zugriffsschlüssel",
	consumerKey: "Consumer-Key",
	consumerSecret: "Consumer-Secret",
	accessToken: "Zugriffstoken",
	apiKey: "API-Schlüssel",
	clientId: "Client-ID",
	clientSecret: "Client-Secret",
	syncAvailable: "Automatische Synchronisierung und manueller Produktimport.",
	test: "Testen",
	testFailed: "Integrationstest fehlgeschlagen.",
	updateFailed: "Integration kann nicht aktualisiert werden.",
	remove: "Integration entfernen",
	removeTitle: "Integration entfernen?",
	removeDescription: "{name} und die Zugangsdaten werden dauerhaft entfernt.",
	removeFailed: "Integration kann nicht entfernt werden.",
	none: "Noch keine Integrationen installiert.",
	saved: "Integration gespeichert",
	tested: "Integrationskonfiguration getestet"
};
var integrationLog = {
	queued: "Der Shopware-Katalogimport wurde in die Warteschlange gestellt.",
	preparing: "Shopware-Katalogimport wird vorbereitet.",
	stageStarted: "Importphase wurde gestartet.",
	mediaFailed: "Ein Shopware-Bild konnte nicht importiert werden.",
	productsStarted: "Shopware-Produktimport wurde gestartet.",
	productFailed: "Ein Shopware-Produkt konnte nicht importiert werden.",
	completed: "Shopware-Katalogimport wurde abgeschlossen.",
	failed: "Shopware-Katalogimport ist fehlgeschlagen.",
	cancelled: "Shopware-Katalogimport wurde abgebrochen."
};
var auth = {
	signIn: "Anmelden",
	signOut: "Abmelden",
	signInDescription: "Greifen Sie auf Ihren SteelCode Connect-Arbeitsbereich zu.",
	email: "E-Mail",
	password: "Passwort",
	forgotPassword: "Passwort vergessen?",
	resetPassword: "Passwort zurücksetzen",
	resetDescription: "Geben Sie Ihre E-Mail-Adresse ein und wir senden Ihnen Anweisungen zum Zurücksetzen.",
	sendResetInstructions: "Anweisungen senden",
	resetSent: "Wenn ein aktives Konto zu dieser E-Mail-Adresse gehört, wurden Anweisungen gesendet.",
	backToSignIn: "Zurück zur Anmeldung",
	chooseNewPassword: "Neues Passwort wählen",
	newPasswordDescription: "Ihr neues Passwort muss mindestens 8 Zeichen enthalten.",
	newPassword: "Neues Passwort",
	passwordReset: "Passwort zurückgesetzt",
	unableToRequestReset: "Passwortzurücksetzung kann nicht angefordert werden",
	unableToResetPassword: "Passwort kann nicht zurückgesetzt werden",
	requestNewResetLink: "Bitte fordern Sie einen neuen Link zum Zurücksetzen an.",
	newHere: "Neu bei SteelCode Connect?",
	createAccount: "Konto erstellen",
	createWorkspace: "Arbeitsbereich erstellen",
	createWorkspaceDescription: "Erstellen Sie Ihr SteelCode Connect-Unternehmenskonto.",
	companyName: "Unternehmensname",
	alreadyHaveAccount: "Sie haben bereits ein Konto?",
	unableToSignIn: "Anmeldung nicht möglich",
	unableToCreateAccount: "Konto konnte nicht erstellt werden"
};
var company = {
	details: "Unternehmensdetails",
	detailsDescription: "Unternehmensinformationen für Ihren gesamten Arbeitsbereich.",
	companyName: "Unternehmensname",
	oib: "Company ID",
	pdv: "VAT ID",
	addresses: "Adressen",
	addressesDescription: "Verwalten Sie Unternehmens- und Rechnungsadressen.",
	addAddress: "Adresse hinzufügen",
	paymentMethods: "Zahlungsmethoden",
	paymentMethodsDescription: "Karten werden nur als maskierte Referenzen gespeichert.",
	addCard: "Karte hinzufügen",
	billing: "Abrechnung",
	billingDescription: "Ihr Abonnement, Ihre Zahlungsmethode und Rechnungen.",
	currentPlan: "Aktueller Tarif",
	noPlan: "Kein Tarif ausgewählt",
	changePlan: "Tarif ändern",
	paymentMethod: "Zahlungsmethode",
	noPaymentMethod: "Keine Standard-Zahlungsmethode",
	managePaymentMethods: "Zahlungsmethoden verwalten",
	invoices: "Rechnungen",
	invoicesDescription: "Öffnen Sie Ihre generierten PDF-Rechnungen.",
	openPdf: "PDF öffnen",
	noInvoices: "Noch keine Rechnungen.",
	pricingPlans: "Tarife",
	pricingDescription: "Wählen Sie den passenden Tarif für Ihr Unternehmen.",
	monthly: "Monatlich",
	annual: "Jährlich · 10% sparen",
	month: "Monat",
	year: "Jahr",
	unlimitedProducts: "Unbegrenzte Produkte",
	upToProducts: "Bis zu {count} Produkte",
	selectPlan: "Tarif wählen",
	planUpdated: "Tarif aktualisiert",
	unableToSelectPlan: "Tarif kann nicht ausgewählt werden"
};
var settings = {
	defaultSnippetLanguage: "Standard-Snippet-Sprache",
	defaultSnippetLanguageDescription: "Neue übersetzte Inhalte werden zuerst in dieser Sprache erstellt.",
	snippetLanguages: "Snippet-Sprachen",
	snippetLanguagesDescription: "Aktivieren Sie die Sprachen für Katalog- und Inhaltsübersetzungen.",
	title: "Einstellungen",
	general: "Allgemein",
	generalDescription: "Arbeitsbereichsweite Einstellungen.",
	generalEmpty: "Allgemeine Arbeitsbereichseinstellungen werden hier verfügbar sein.",
	profile: "Profil",
	profileDescription: "Verwalten Sie die persönlichen Angaben Ihres Kontos.",
	firstName: "Vorname",
	lastName: "Nachname",
	identityDescription: "Zur Identifizierung in Ihrem Arbeitsbereich verwendet.",
	jobTitle: "Berufsbezeichnung",
	jobTitleDescription: "Ihre Rolle oder Position im Unternehmen.",
	emailDescription: "Ihre E-Mail-Adresse wird für die Anmeldung verwendet.",
	phoneDescription: "Eine Telefonnummer für Ihr Konto.",
	languageDescription: "Wählen Sie die Sprache für Ihr Konto.",
	profileUpdated: "Profil aktualisiert",
	profileSaved: "Ihre Einstellungen wurden gespeichert."
};
var productEditor = {
	extensions: "Erweiterungen",
	extensionsDescription: "Shopware-Custom-Fields und WooCommerce-Metadaten werden hier ohne Datenverlust gespeichert.",
	productType: "Produkttyp",
	physical: "Physisch",
	digital: "Digital",
	service: "Dienstleistung",
	manufacturerNumber: "Herstellernummer",
	taxRate: "Steuersatz",
	shippingClass: "Versandklasse",
	deliveryTime: "Lieferzeit",
	featured: "Hervorgehoben"
};
var productExtensions = {
	namespace: "Namensraum",
	fieldKey: "Feldschlüssel",
	value: "Wert",
	add: "Erweiterung hinzufügen",
	description: "Speichern Sie verbindungsspezifische Daten, ohne das Produktschema zu ändern.",
	valueDescription: "Klartext oder gültiges JSON.",
	empty: "Keine Erweiterungsdaten vorhanden.",
	created: "Erweiterungswert hinzugefügt",
	createFailed: "Der Erweiterungswert konnte nicht hinzugefügt werden.",
	deleted: "Erweiterungswert entfernt",
	deleteFailed: "Der Erweiterungswert konnte nicht entfernt werden."
};
var productPrices = {
	description: "Speichern Sie Verkaufs-, Einkaufs- und Listenpreise in jeder unterstützten Währung.",
	add: "Preis hinzufügen",
	empty: "Keine Preise vorhanden.",
	currency: "Währung",
	type: "Typ",
	"default": "Verkaufspreis",
	purchase: "Einkaufspreis",
	list: "Listenpreis",
	net: "Nettobetrag (kleinste Einheit)",
	gross: "Bruttobetrag (kleinste Einheit)",
	listGross: "Listen-Bruttobetrag (kleinste Einheit)",
	tax: "Steuersatz",
	quantity: "Menge",
	quantityStart: "Menge ab",
	quantityEnd: "Menge bis",
	created: "Preis hinzugefügt",
	createFailed: "Preis konnte nicht hinzugefügt werden."
};
var productVariantGeneration = {
	open: "Varianten erzeugen",
	description: "Wählen Sie Attribute und Werte und erstellen Sie anschließend alle Produktkombinationen."
};
var productSections = {
	deliverability: "Lieferbarkeit",
	deliverabilityDescription: "Kaufbeschränkungen und Versandeinstellungen. Der Bestand wird je Lager verwaltet.",
	deliveryTime: "Lieferzeit",
	restockTime: "Wiederbeschaffungszeit in Tagen",
	minPurchaseQuantity: "Mindestbestellmenge",
	purchaseSteps: "Bestellschritte",
	maxPurchaseQuantity: "Maximale Bestellmenge",
	clearanceSale: "Abverkauf",
	freeShipping: "Kostenloser Versand",
	labelling: "Kennzeichnung",
	labellingDescription: "Produktidentifikation und physische Abmessungen.",
	weight: "Gewicht (g)",
	length: "Länge (mm)",
	width: "Breite (mm)",
	height: "Höhe (mm)",
	visibilityStructure: "Sichtbarkeit & Struktur",
	visibilityStructureDescription: "Wie Kunden und Integrationen dieses Produkt finden.",
	searchKeywords: "Suchbegriffe"
};
var productRegularPrice = {
	title: "Regulärer Preis",
	description: "Der Standardverkaufspreis, wenn keine erweiterte Regel gilt.",
	advancedDescription: "Währungsspezifische Preise, Mengenstaffeln sowie Listen- und Einkaufspreise.",
	saved: "Regulärer Preis gespeichert",
	saveFailed: "Der reguläre Preis konnte nicht gespeichert werden."
};
var productRegularPriceFields = {
	gross: "Preis (brutto)",
	net: "Preis (netto)",
	purchaseGross: "Einkaufspreis (brutto)",
	purchaseNet: "Einkaufspreis (netto)",
	listGross: "Listenpreis (brutto)",
	listNet: "Listenpreis (netto)",
	cheapestGross: "Niedrigster Preis (letzte 30 Tage, brutto)",
	cheapestNet: "Niedrigster Preis (letzte 30 Tage, netto)"
};
var productPriceValidation = {
	required: "Steuersatz, Brutto- und Nettopreis sind erforderlich.",
	requiredField: "Dieses Feld ist erforderlich.",
	requiredFields: "Füllen Sie die Pflichtfelder aus: {fields}."
};
var productPriceLabels = {
	gross: "Bruttopreis",
	net: "Nettopreis"
};
var productAdvancedPrice = {
	add: "Erweiterten Preis hinzufügen",
	description: "Erstellen Sie eine Preisregel, die den regulären Preis für einen Mengenbereich überschreibt.",
	empty: "Keine erweiterten Preisregeln vorhanden.",
	quantity: "Menge",
	quantityFrom: "Menge ab",
	quantityTo: "Menge bis",
	price: "Preis",
	listPrice: "Listenpreis",
	listNet: "Listenpreis (netto)",
	listGross: "Listenpreis (brutto)",
	validity: "Gültigkeit",
	validFrom: "Gültig ab",
	validUntil: "Gültig bis"
};
var productAdvancedPriceLabels = {
	gross: "brutto",
	net: "netto"
};
var productAdvancedPriceTab = {
	title: "Erweiterte Preisgestaltung",
	addRule: "Preisregel hinzufügen"
};
var productGallery = {
	title: "Galerie",
	description: "Produktbilder hinzufügen, ein Titelbild auswählen und die Reihenfolge festlegen.",
	upload: "Bilder hinzufügen",
	empty: "Die Galerie ist leer",
	emptyDescription: "Fügen Sie Produktbilder im Format JPEG, PNG, WebP, GIF oder AVIF hinzu.",
	cover: "Titelbild",
	setCover: "Als Titelbild festlegen",
	moveLeft: "Nach links verschieben",
	moveRight: "Nach rechts verschieben",
	uploaded: "Produktbilder hinzugefügt",
	uploadFailed: "Produktbilder konnten nicht hochgeladen werden.",
	deleted: "Produktbild gelöscht",
	deleteFailed: "Produktbild konnte nicht gelöscht werden.",
	orderFailed: "Die Bildreihenfolge konnte nicht gespeichert werden.",
	duplicateTitle: "Bild existiert bereits",
	duplicateDescription: "Wählen Sie, ob das vorhandene Bild ersetzt oder eine neue Kopie mit anderem Dateinamen hinzugefügt werden soll.",
	replace: "Ersetzen",
	addWithNewName: "Mit anderem Namen hinzufügen"
};
var productRelations = {
	title: "Verknüpfte Inhalte",
	downloads: "Downloads",
	downloadsDescription: "Dateien, die diesem Produkt im verbundenen Katalog zugeordnet sind.",
	noDownloads: "Keine Downloads zugeordnet",
	openDownload: "{name} öffnen",
	crossSellings: "Cross-Selling",
	crossSellingsDescription: "Produktgruppen, die zusammen mit diesem Produkt angezeigt werden.",
	noCrossSellings: "Keine Cross-Selling-Gruppen",
	productCount: "{count} Produkte",
	inactive: "Inaktiv",
	dynamicStream: "Dynamischer Stream",
	noAssignedProducts: "Keine Produkte zugeordnet."
};
var propertyGroups = {
	title: "Eigenschaftsgruppen",
	description: "Definieren Sie gemeinsame Produkteigenschaften und Werte für Spezifikationen und Varianten.",
	addGroup: "Gruppe hinzufügen",
	addProperty: "Eigenschaft hinzufügen",
	switchToDefaultToAdd: "Wechseln Sie zu {language}, um eine neue Eigenschaft hinzuzufügen.",
	name: "Name",
	code: "Code",
	codeDescription: "Stabile technische Kennung. Bei leerem Feld wird sie aus dem Namen erzeugt.",
	displayType: "Darstellungsart",
	text: "Text",
	color: "Farbe",
	image: "Bild",
	initialProperties: "Anfangseigenschaften",
	initialPropertiesDescription: "Werte mit Kommas trennen, zum Beispiel Schwarz, Blau, Rot.",
	filterable: "Als Filter verfügbar",
	colorHex: "HEX-Farbe",
	empty: "Keine Eigenschaftsgruppen",
	emptyDescription: "Erstellen Sie die erste Gruppe, zum Beispiel Farbe, Größe oder Material.",
	search: "Eigenschaften suchen",
	noProperties: "Keine Eigenschaften hinzugefügt.",
	created: "Eigenschaftsgruppe hinzugefügt",
	propertyCreated: "Eigenschaft hinzugefügt",
	propertyDeleted: "Eigenschaft gelöscht",
	confirmDelete: "Eigenschaft {name} löschen?",
	createFailed: "Eigenschaft konnte nicht gespeichert werden."
};
var productProperties = {
	title: "Eigenschaften",
	description: "Weisen Sie diesem Produkt Eigenschaften und Werte zu. Varianten werden nur im Variantengenerator ausgewählt.",
	emptyTitle: "Erstellen Sie zuerst eine Eigenschaftsgruppe",
	emptyDescription: "Gruppen und Werte werden zwischen Eigenschaften, Filtern und Varianten geteilt.",
	openGroups: "Eigenschaftsgruppen öffnen",
	configure: "Eigenschaften konfigurieren",
	noneAssigned: "Keine Eigenschaften zugewiesen",
	noneAssignedDescription: "Fügen Sie Eigenschaftsgruppen und Werte hinzu, die auf der Produktdetailseite angezeigt werden sollen.",
	modalDescription: "Wählen Sie Eigenschaftsgruppen und Werte für dieses Produkt.",
	selectGroup: "Eigenschaftsgruppen",
	searchValues: "Werte suchen",
	searchAdded: "Hinzugefügte Eigenschaften suchen…",
	noValues: "Keine passenden Werte.",
	selectedCount: "Ausgewählt: {count}",
	property: "Eigenschaft",
	propertyValues: "Eigenschaftswert",
	selectAll: "Alle auswählen",
	selectRow: "Zeile auswählen",
	bulkDelete: "Auswahl löschen ({count})",
	removeTitle: "Eigenschaften löschen?",
	removeDescription: "Sie entfernen {count} zugewiesene Eigenschaftswerte von diesem Produkt.",
	removed: "Eigenschaften gelöscht",
	removeFailed: "Eigenschaften konnten nicht gelöscht werden.",
	saved: "Eigenschaften gespeichert",
	saveFailed: "Eigenschaften konnten nicht gespeichert werden."
};
var productCustomFields = {
	title: "Benutzerdefinierte Felder",
	description: "Zusätzliche Produktdaten, die nicht Teil des Standardkatalogs sind.",
	add: "Feld hinzufügen",
	addDescription: "Benutzerdefinierten Produktwert hinzufügen.",
	empty: "Es gibt keine aktiven benutzerdefinierten Produktfelder.",
	emptySet: "Diese Gruppe enthält noch keine benutzerdefinierten Felder.",
	uncategorized: "Nicht kategorisiert",
	uncategorizedDescription: "Integrations-Metadaten ohne zugewiesene Feldgruppe, einschließlich WooCommerce-Metadaten.",
	manage: "Benutzerdefinierte Felder verwalten"
};
var productSalesChannels = {
	title: "Sichtbarkeit in Verkaufskanälen",
	description: "Legen Sie die Verfügbarkeit dieses Produkts für jeden verbundenen Verkaufskanal fest.",
	hidden: "Ausgeblendet",
	link: "Nur direkter Link",
	search: "Suche",
	all: "Alle",
	sync: "Verkaufskanäle synchronisieren",
	synced: "Verkaufskanäle synchronisiert",
	syncFailed: "Verkaufskanäle konnten nicht synchronisiert werden"
};
var productUnits = {
	title: "Einheiten",
	unit: "Einheit",
	purchaseUnit: "Verkaufseinheit",
	referenceUnit: "Referenzeinheit",
	packUnit: "Verpackungseinheit",
	packUnitPlural: "Verpackungseinheit Mehrzahl"
};
var productSeo = {
	title: "SEO",
	description: "Legen Sie fest, wie dieses Produkt in Suchmaschinen erscheint.",
	url: "SEO-URL",
	metaTitle: "Meta-Titel",
	metaDescription: "Meta-Beschreibung",
	keywords: "Meta-Schlüsselwörter"
};
var shopReferences = {
	title: "Shop-Einstellungen",
	taxes: "Steuern",
	units: "Einheiten",
	deliveryTimes: "Lieferzeiten",
	searchTaxes: "Steuern suchen",
	searchUnits: "Einheiten suchen",
	searchDeliveryTimes: "Lieferzeiten suchen",
	name: "Name",
	rate: "Steuersatz",
	code: "Code",
	symbol: "Symbol",
	min: "Minimum",
	max: "Maximum",
	unit: "Einheit"
};
var customFields = {
	title: "Benutzerdefinierte Felder",
	description: "Definieren Sie Gruppen, Typen und übersetzte Feldbezeichnungen.",
	newSet: "Neue Feldgruppe",
	addField: "Feld hinzufügen",
	label: "Bezeichnung",
	technicalName: "Technischer Name",
	technicalValue: "Technischer Wert",
	type: "Typ",
	products: "Produkten zuweisen",
	multiSelect: "Mehrfachauswahl",
	addOption: "Option hinzufügen",
	noFields: "Diese Gruppe enthält noch keine Felder.",
	fields: "Benutzerdefinierte Felder",
	search: "Felder suchen…",
	bulkDelete: "Auswahl löschen ({count})",
	deleteDescription: "Sie löschen {count} benutzerdefinierte Felder.",
	deleted: "Benutzerdefinierte Felder gelöscht",
	rowsPerPage: "Zeilen pro Seite",
	emptyTitle: "Keine Feldgruppen",
	emptyDescription: "Erstellen Sie eine Gruppe und fügen Sie Produktfelder hinzu.",
	created: "Feldgruppe erstellt",
	fieldCreated: "Benutzerdefiniertes Feld erstellt"
};
var customFieldsExtra = {
	setDescription: "Erstellen Sie eine Feldgruppe und wählen Sie deren Verfügbarkeit.",
	fieldDescription: "Konfigurieren Sie Typ, technische Regeln und Verfügbarkeit.",
	position: "Position",
	manageLabels: "Bezeichnungen in allen Administrationssprachen verwalten",
	assignTo: "Entitäten zuweisen",
	entity: "Entität",
	availableInCart: "In Warenkörben verfügbar",
	allowStoreApi: "Über Store API änderbar",
	visibleStoreApi: "In Store API sichtbar",
	relations: {
		product: "Produkte",
		category: "Kategorien",
		manufacturer: "Hersteller",
		customer: "Kunden",
		order: "Bestellungen",
		property_group: "Eigenschaftsgruppen",
		property: "Eigenschaften",
		media: "Medien"
	}
};
var customFieldConfig = {
	helpText: "Hilfetext",
	placeholder: "Platzhalter",
	numberType: "Zahlentyp",
	float: "Dezimalzahl",
	integer: "Ganzzahl",
	min: "Minimalwert",
	max: "Maximalwert",
	step: "Schritt",
	dateType: "Datumstyp",
	date: "Nur Datum",
	datetime: "Datum und Uhrzeit",
	time: "Uhrzeit",
	defaultValue: "Standardwert",
	defaultActive: "Standardmäßig aktiv",
	required: "Pflichtfeld",
	searchable: "In Suche einschließen"
};
var customFieldTypes = {
	text: "Textfeld",
	editor: "Texteditor",
	number: "Zahl",
	date: "Datum und Uhrzeit",
	checkbox: "Kontrollkästchen",
	"switch": "Aktiver Schalter",
	select: "Auswahlfeld",
	entity: "Entitätsauswahl",
	media: "Medienfeld",
	color: "Farbauswahl",
	price: "Preisfeld"
};
var productExtra = {
	releaseDate: "Veröffentlichungsdatum und -zeit",
	releaseDatePlaceholder: "Datum und Uhrzeit wählen",
	time: "Uhrzeit",
	tags: "Schlagwörter",
	tagsPlaceholder: "Schlagwort eingeben und Enter drücken",
	keywordsPlaceholder: "Schlagwort eingeben und Enter drücken"
};
var inventoryMovements = {
	title: "Bestandsbewegungen",
	description: "Historie manueller Bestandsanpassungen für dieses Produkt.",
	empty: "Keine Bestandsbewegungen vorhanden.",
	date: "Datum",
	change: "Änderung"
};
var productSpecifications = {
	title: "Spezifikationen",
	description: "Technische Maße und Produktabmessungen."
};
var inventory = {
	stock: "Bestand",
	stockByWarehouse: "Bestand nach Lager",
	availableStock: "Verfügbarer Bestand",
	reservedStock: "Reservierter Bestand",
	unavailableStock: "Nicht verfügbarer Bestand",
	incomingStock: "Zulaufbestand",
	warehouse: "Lager",
	adjustStock: "Bestand anpassen",
	adjustStockDescription: "Legen Sie den physischen Bestand im ausgewählten Lager fest. Die Änderung wird in der Bestandshistorie gespeichert.",
	note: "Notiz",
	stockUpdated: "Bestand aktualisiert",
	stockUpdateFailed: "Bestand konnte nicht aktualisiert werden.",
	stockDescription: "Übersicht des physischen und verfügbaren Bestands aller Produkte.",
	warehousesDescription: "Verwalten Sie Lager, die zur Berechnung des verfügbaren Bestands verwendet werden.",
	addWarehouse: "Lager hinzufügen",
	searchStock: "Bestand suchen…",
	searchWarehouses: "Lager suchen…",
	code: "Code",
	status: "Status",
	active: "Aktiv",
	inactive: "Inaktiv",
	fulfillment: "Auftragsabwicklung",
	fulfillmentEnabled: "Für die Auftragsabwicklung verwenden",
	fulfillmentDisabled: "Nicht für die Auftragsabwicklung verwenden",
	fulfillmentDescription: "Dieses Lager bei der Bestandszuordnung für die Auftragsabwicklung berücksichtigen.",
	fulfillmentPriority: "Priorität der Auftragsabwicklung",
	warehouseSaved: "Lager gespeichert",
	warehouseSaveFailed: "Lager konnte nicht gespeichert werden."
};
var inventoryTransfers = {
	title: "Umlagerungen",
	create: "Umlagerung erstellen",
	created: "Entwurf der Umlagerung erstellt",
	createValidation: "Wählen Sie unterschiedliche Lager und mindestens ein Produkt.",
	selectWarehouse: "Lager auswählen",
	searchWarehouses: "Lager suchen…",
	products: "Produkte",
	selectProducts: "Produkte auswählen",
	date: "Datum",
	source: "Von Lager",
	destination: "Zu Lager",
	items: "Positionen",
	send: "Umlagerung senden",
	receive: "Umlagerung empfangen",
	sendSuccess: "Umlagerung gesendet",
	receiveSuccess: "Umlagerung empfangen",
	cancel: "Umlagerung stornieren",
	cancelSuccess: "Umlagerung storniert",
	cancelDescription: "Dieser Entwurf wird ohne Bestandsänderung storniert.",
	sendDescription: "Beim Versand wird die Menge vom Quelllager abgezogen, bis sie empfangen wird.",
	receiveDescription: "Beim Empfang wird die Menge dem Ziellager hinzugefügt.",
	empty: "Noch keine Umlagerungen",
	emptyDescription: "Erstellen Sie eine Umlagerung, um Bestand zwischen Lagern zu bewegen.",
	status: {
		draft: "Entwurf",
		in_transit: "Unterwegs",
		received: "Empfangen",
		cancelled: "Storniert"
	}
};
var suppliers = {
	name: "Lieferantenname",
	search: "Lieferanten suchen…",
	add: "Lieferant hinzufügen",
	edit: "Lieferant bearbeiten",
	saved: "Lieferant gespeichert",
	saveFailed: "Lieferant konnte nicht gespeichert werden.",
	loadFailed: "Lieferanten konnten nicht geladen werden",
	empty: "Noch keine Lieferanten",
	emptyDescription: "Fügen Sie einen Lieferanten für Bestellungen hinzu.",
	contactName: "Kontaktperson",
	street: "Straße",
	postalCode: "Postleitzahl",
	city: "Stadt",
	country: "Land"
};
var inventoryCounts = {
	title: "Inventuren",
	create: "Inventur erstellen",
	created: "Inventurentwurf erstellt",
	createValidation: "Wählen Sie ein Lager und mindestens ein Produkt.",
	selectWarehouse: "Lager auswählen",
	date: "Datum",
	expected: "Erwartet",
	counted: "Gezählt",
	variance: "Differenz",
	post: "Inventur buchen",
	postDescription: "Der Bestand wird auf die gezählten Mengen angepasst. Wenn sich der Bestand inzwischen geändert hat, wird die Buchung abgelehnt.",
	posted: "Inventur gebucht",
	empty: "Noch keine Inventuren",
	emptyDescription: "Erstellen Sie eine Inventur, um den physischen Bestand abzugleichen.",
	status: {
		draft: "Entwurf",
		posted: "Gebucht"
	}
};
var purchasing = {
	validFrom: "Gültig ab",
	validUntil: "Gültig bis",
	noValidityDate: "Keine Datumsgrenze",
	clearDate: "Datum löschen",
	purchaseUnit: "Einkaufseinheit",
	stockUnitsPerPurchaseUnit: "Bestandseinheiten pro Einkaufseinheit",
	stockUnits: "Bestandseinheiten",
	editOrder: "Entwurf bearbeiten",
	downloadPdf: "PDF herunterladen",
	emailOrder: "Bestellung per E-Mail senden",
	emailDescription: "Diese PDF an {email} senden? Dies versendet eine echte E-Mail.",
	emailedAt: "Zuletzt per E-Mail gesendet",
	damageResolution: "Schadensabwicklung",
	damageNote: "Notiz zur Abwicklung",
	saveResolution: "Abwicklung speichern",
	damageStatus: {
		open: "Offener Anspruch",
		returned: "An Lieferanten zurückgesendet",
		credited: "Vom Lieferanten gutgeschrieben",
		written_off: "Abgeschrieben",
		replaced: "Ersatz vereinbart"
	},
	offers: "Lieferantenangebote",
	orders: "Bestellungen",
	addOffer: "Angebot hinzufügen",
	editOffer: "Angebot bearbeiten",
	offerSaved: "Lieferantenangebot gespeichert",
	offerValidation: "Lieferant, Produkt, Preis und Mindestmenge prüfen.",
	searchOffers: "Produkt- oder Lieferantennummer suchen…",
	noOffers: "Noch keine Lieferantenangebote",
	noOffersDescription: "Erfassen Sie Einkaufspreise und Lieferzeiten, bevor Sie Bestellungen anlegen.",
	productNumber: "Produktnummer",
	supplierSku: "Lieferantenartikelnummer",
	unitCost: "Stückpreis",
	currency: "Währung",
	minimumQuantity: "Mindestmenge",
	leadTime: "Lieferzeit (Tage)",
	preferred: "Bevorzugter Lieferant",
	selectSupplier: "Lieferant auswählen",
	selectProduct: "Produkt auswählen",
	createOrder: "Bestellung erstellen",
	noOrders: "Noch keine Bestellungen",
	noOrdersDescription: "Erstellen Sie eine Bestellung aus aktiven Lieferantenangeboten.",
	orderValidation: "Lieferant, Lager und Produkte mit passender Mindestmenge und Währung auswählen.",
	orderCreated: "Bestellentwurf erstellt",
	orderUpdated: "Bestellung aktualisiert",
	orderDetail: "Bestellung",
	view: "Details ansehen",
	send: "Als versendet markieren",
	receive: "Wareneingang buchen",
	cancel: "Bestellung stornieren",
	sendDescription: "Offene Mengen werden als erwarteter Bestand erfasst. Es wird keine E-Mail gesendet; senden Sie die PDF separat per E-Mail oder selbst.",
	cancelDescription: "Offene erwartete Mengen werden entfernt. Bereits gebuchter Bestand bleibt unverändert.",
	receipts: "Wareneingänge",
	receiptValidation: "Gültige Gut- oder Schadmenge eingeben, höchstens die offene Menge.",
	receiptSaved: "Wareneingang gespeichert",
	ordered: "Bestellt",
	received: "Gut eingegangen",
	damaged: "Beschädigt",
	outstanding: "Offen",
	total: "Gesamt",
	status: {
		draft: "Entwurf",
		sent: "Gesendet",
		partially_received: "Teilweise erhalten",
		received: "Erhalten",
		cancelled: "Storniert"
	}
};
var productNavigation = {
	backToParent: "Zurück zum Hauptprodukt"
};
var languages = {
	bs: "Bosnisch",
	en: "Englisch",
	de: "Deutsch"
};
const locale_de_46json_5190eca1 = {
	nav: nav,
	common: common,
	table: table,
	catalogue: catalogue,
	products: products,
	categories: categories,
	manufacturers: manufacturers,
	productCategories: productCategories,
	integrations: integrations,
	integrationLog: integrationLog,
	auth: auth,
	company: company,
	settings: settings,
	productEditor: productEditor,
	productExtensions: productExtensions,
	productPrices: productPrices,
	productVariantGeneration: productVariantGeneration,
	productSections: productSections,
	productRegularPrice: productRegularPrice,
	productRegularPriceFields: productRegularPriceFields,
	productPriceValidation: productPriceValidation,
	productPriceLabels: productPriceLabels,
	productAdvancedPrice: productAdvancedPrice,
	productAdvancedPriceLabels: productAdvancedPriceLabels,
	productAdvancedPriceTab: productAdvancedPriceTab,
	productGallery: productGallery,
	productRelations: productRelations,
	propertyGroups: propertyGroups,
	productProperties: productProperties,
	productCustomFields: productCustomFields,
	productSalesChannels: productSalesChannels,
	productUnits: productUnits,
	productSeo: productSeo,
	shopReferences: shopReferences,
	customFields: customFields,
	customFieldsExtra: customFieldsExtra,
	customFieldConfig: customFieldConfig,
	customFieldTypes: customFieldTypes,
	productExtra: productExtra,
	inventoryMovements: inventoryMovements,
	productSpecifications: productSpecifications,
	inventory: inventory,
	inventoryTransfers: inventoryTransfers,
	suppliers: suppliers,
	inventoryCounts: inventoryCounts,
	purchasing: purchasing,
	productNavigation: productNavigation,
	languages: languages
};

const config_i18n_46config_46ts_97f0f5de = () => ({ fallbackLocale: "bs" });

// @ts-nocheck
const localeCodes =  [
  "bs",
  "en",
  "de"
];
const localeLoaders = {
  bs: [
    {
      key: "locale_bs_46json_044b03a0",
      load: () => Promise.resolve(locale_bs_46json_044b03a0),
      cache: true
    }
  ],
  en: [
    {
      key: "locale_en_46json_603da42c",
      load: () => Promise.resolve(locale_en_46json_603da42c),
      cache: true
    }
  ],
  de: [
    {
      key: "locale_de_46json_5190eca1",
      load: () => Promise.resolve(locale_de_46json_5190eca1),
      cache: true
    }
  ]
};
const vueI18nConfigs = [
  () => Promise.resolve(config_i18n_46config_46ts_97f0f5de)
];
const normalizedLocales = [
  {
    code: "bs",
    name: "Bosanski",
    language: undefined
  },
  {
    code: "en",
    name: "English",
    language: undefined
  },
  {
    code: "de",
    name: "Deutsch",
    language: undefined
  }
];

const setupVueI18nOptions = async (defaultLocale) => {
  const options = await loadVueI18nOptions(vueI18nConfigs);
  options.locale = defaultLocale || options.locale || "en-US";
  options.defaultLocale = defaultLocale;
  options.fallbackLocale ??= false;
  options.messages ??= {};
  for (const locale of localeCodes) {
    options.messages[locale] ??= {};
  }
  return options;
};

function defineNitroPlugin(def) {
  return def;
}

function defineRenderHandler(render) {
  const runtimeConfig = useRuntimeConfig();
  return eventHandler(async (event) => {
    const nitroApp = useNitroApp();
    const ctx = { event, render, response: void 0 };
    await nitroApp.hooks.callHook("render:before", ctx);
    if (!ctx.response) {
      if (event.path === `${runtimeConfig.app.baseURL}favicon.ico`) {
        setResponseHeader(event, "Content-Type", "image/x-icon");
        return send(
          event,
          "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
        );
      }
      ctx.response = await ctx.render(event);
      if (!ctx.response) {
        const _currentStatus = getResponseStatus(event);
        setResponseStatus(event, _currentStatus === 200 ? 500 : _currentStatus);
        return send(
          event,
          "No response returned from render handler: " + event.path
        );
      }
    }
    await nitroApp.hooks.callHook("render:response", ctx.response, ctx);
    if (ctx.response.headers) {
      setResponseHeaders(event, ctx.response.headers);
    }
    if (ctx.response.statusCode || ctx.response.statusMessage) {
      setResponseStatus(
        event,
        ctx.response.statusCode,
        ctx.response.statusMessage
      );
    }
    return ctx.response.body;
  });
}

const scheduledTasks = false;

const tasks = {
  
};

const __runningTasks__ = {};
async function runTask(name, {
  payload = {},
  context = {}
} = {}) {
  if (__runningTasks__[name]) {
    return __runningTasks__[name];
  }
  if (!(name in tasks)) {
    throw createError({
      message: `Task \`${name}\` is not available!`,
      statusCode: 404
    });
  }
  if (!tasks[name].resolve) {
    throw createError({
      message: `Task \`${name}\` is not implemented!`,
      statusCode: 501
    });
  }
  const handler = await tasks[name].resolve();
  const taskEvent = { name, payload, context };
  __runningTasks__[name] = handler.run(taskEvent);
  try {
    const res = await __runningTasks__[name];
    return res;
  } finally {
    delete __runningTasks__[name];
  }
}

function buildAssetsDir() {
	
	return useRuntimeConfig().app.buildAssetsDir;
}
function buildAssetsURL(...path) {
	return joinRelativeURL(publicAssetsURL(), buildAssetsDir(), ...path);
}
function publicAssetsURL(...path) {
	
	const app = useRuntimeConfig().app;
	const publicBase = app.cdnURL || app.baseURL;
	return path.length ? joinRelativeURL(publicBase, ...path) : publicBase;
}

function parseAcceptLanguage(value) {
  return value.split(",").map((tag) => tag.split(";")[0]).filter(
    (tag) => !(tag === "*" || tag === "")
  );
}
function createPathIndexLanguageParser(index = 0) {
  return (path) => {
    const rawPath = typeof path === "string" ? path : path.pathname;
    const normalizedPath = rawPath.split("?")[0];
    const parts = normalizedPath.split("/");
    if (parts[0] === "") {
      parts.shift();
    }
    return parts.length > index ? parts[index] || "" : "";
  };
}

function useRuntimeI18n(nuxtApp, event) {
  {
    const getRuntimeConfig = useRuntimeConfig;
    return getRuntimeConfig(event).public.i18n;
  }
}
function useI18nDetection(nuxtApp) {
  const detectBrowserLanguage = useRuntimeI18n().detectBrowserLanguage;
  const detect = detectBrowserLanguage || {};
  return {
    ...detect,
    enabled: !!detectBrowserLanguage,
    cookieKey: detect.cookieKey || "i18n_redirected"
  };
}
function resolveRootRedirect(config) {
  if (!config) {
    return void 0;
  }
  return {
    path: "/" + (isString(config) ? config : config.path).replace(/^\//, ""),
    code: !isString(config) && config.statusCode || 302
  };
}
function toArray(value) {
  return Array.isArray(value) ? value : [value];
}

function createLocaleConfigs(fallbackLocale) {
  const localeConfigs = {};
  for (const locale of localeCodes) {
    const fallbacks = getFallbackLocaleCodes(fallbackLocale, [locale]);
    const cacheable = isLocaleWithFallbacksCacheable(locale, fallbacks);
    localeConfigs[locale] = { fallbacks, cacheable };
  }
  return localeConfigs;
}
function getFallbackLocaleCodes(fallback, locales) {
  if (fallback === false) {
    return [];
  }
  if (isArray(fallback)) {
    return fallback;
  }
  let fallbackLocales = [];
  if (isString(fallback)) {
    if (locales.every((locale) => locale !== fallback)) {
      fallbackLocales.push(fallback);
    }
    return fallbackLocales;
  }
  const targets = [...locales, "default"];
  for (const locale of targets) {
    if (locale in fallback == false) {
      continue;
    }
    fallbackLocales = [...fallbackLocales, ...fallback[locale].filter(Boolean)];
  }
  return fallbackLocales;
}
function isLocaleCacheable(locale) {
  return localeLoaders[locale] != null && localeLoaders[locale].every((loader) => loader.cache !== false);
}
function isLocaleWithFallbacksCacheable(locale, fallbackLocales) {
  return isLocaleCacheable(locale) && fallbackLocales.every((fallbackLocale) => isLocaleCacheable(fallbackLocale));
}
function getDefaultLocaleForDomain(host, locales = normalizedLocales) {
  return locales.find((l) => !!l.defaultForDomains?.includes(host))?.code;
}
const isSupportedLocale = (locale) => localeCodes.includes(locale || "");

function useI18nContext(event) {
  if (event.context.nuxtI18n == null) {
    throw new Error("Nuxt I18n server context has not been set up yet.");
  }
  return event.context.nuxtI18n;
}
function tryUseI18nContext(event) {
  return event.context.nuxtI18n;
}
const getHost = (event) => getRequestURL(event, { xForwardedHost: true }).host;
async function initializeI18nContext(event) {
  const runtimeI18n = useRuntimeI18n(void 0, event);
  const defaultLocale = runtimeI18n.defaultLocale || "";
  const options = await setupVueI18nOptions(getDefaultLocaleForDomain(getHost(event)) || defaultLocale);
  const localeConfigs = createLocaleConfigs(options.fallbackLocale);
  const ctx = createI18nContext();
  ctx.vueI18nOptions = options;
  ctx.localeConfigs = localeConfigs;
  event.context.nuxtI18n = ctx;
  return ctx;
}
function createI18nContext() {
  return {
    messages: {},
    slp: {},
    localeConfigs: {},
    trackMap: {},
    vueI18nOptions: void 0,
    trackKey(key, locale) {
      this.trackMap[locale] ??= /* @__PURE__ */ new Set();
      this.trackMap[locale].add(key);
    }
  };
}

const appHead = {"meta":[{"name":"viewport","content":"width=device-width, initial-scale=1"},{"charset":"utf-8"}],"link":[],"style":[],"script":[],"noscript":[]};

const appRootTag = "div";

const appRootAttrs = {"id":"__nuxt","class":"isolate"};

const appTeleportTag = "div";

const appTeleportAttrs = {"id":"teleports"};

const appSpaLoaderTag = "div";

const appSpaLoaderAttrs = {"id":"__nuxt-loader"};

const appId = "nuxt-app";

const separator = "___";
const createTrailingSlashFormatter = (trailingSlash) => trailingSlash ? withTrailingSlash : withoutTrailingSlash;
const pathLanguageParser = createPathIndexLanguageParser(0);
const getLocaleFromRoutePath = (path) => pathLanguageParser(path);
const getLocaleFromRouteName = (name) => name.split(separator).at(1) ?? "";
function normalizeInput(input) {
  return typeof input !== "object" ? String(input) : String(input?.name || input?.path || "");
}
function getLocaleFromRoute(route) {
  const input = normalizeInput(route);
  if (input[0] === "/") {
    return getLocaleFromRoutePath(input);
  }
  const fromName = getLocaleFromRouteName(input);
  if (fromName) {
    return fromName;
  }
  if (typeof route === "object" && route?.path) {
    return getLocaleFromRoutePath(String(route.path));
  }
  return "";
}

function matchBrowserLocale(locales, browserLocales) {
  const matchedLocales = [];
  for (const [index, browserCode] of browserLocales.entries()) {
    const matchedLocale = locales.find((l) => l.language?.toLowerCase() === browserCode.toLowerCase());
    if (matchedLocale) {
      matchedLocales.push({ code: matchedLocale.code, score: 1 - index / browserLocales.length });
      break;
    }
  }
  for (const [index, browserCode] of browserLocales.entries()) {
    const languageCode = browserCode.split("-")[0].toLowerCase();
    const matchedLocale = locales.find((l) => l.language?.split("-")[0].toLowerCase() === languageCode);
    if (matchedLocale) {
      matchedLocales.push({ code: matchedLocale.code, score: 0.999 - index / browserLocales.length });
      break;
    }
  }
  return matchedLocales;
}
function compareBrowserLocale(a, b) {
  if (a.score === b.score) {
    return b.code.length - a.code.length;
  }
  return b.score - a.score;
}
function findBrowserLocale(locales, browserLocales) {
  const matchedLocales = matchBrowserLocale(
    locales.map((l) => ({ code: l.code, language: l.language || l.code })),
    browserLocales
  );
  return matchedLocales.sort(compareBrowserLocale).at(0)?.code ?? "";
}

function isLocaleOnHost(locale, host) {
  const normalizeDomain = (domain = "") => domain.replace(/https?:\/\//, "");
  return !!locale && (normalizeDomain(locale.domain) === host || toArray(locale.domains).some((x) => normalizeDomain(x) === host));
}
function matchDomainLocale(locales, host, pathLocale) {
  const matches = locales.filter((locale) => isLocaleOnHost(locale, host));
  if (matches.length <= 1) {
    return matches[0]?.code;
  }
  return (
    // match by current path locale
    matches.find((l) => l.code === pathLocale)?.code || matches.find((l) => l.defaultForDomains?.includes(host) ?? l.domainDefault)?.code
  );
}
function withRuntimeDomain(locale, domainLocales) {
  if (typeof locale === "string") {
    return locale;
  }
  const properties = locale;
  const domain = domainLocales[properties.code]?.domain;
  return domain && domain !== properties.domain ? { ...properties, domain } : locale;
}

const getCookieLocale = (event, cookieName) => (getCookie(event, cookieName)) || void 0;
const getRouteLocale = (event, route) => getLocaleFromRoute(route);
const getHeaderLocale = (event) => findBrowserLocale(normalizedLocales, parseAcceptLanguage(getRequestHeader(event, "accept-language") || ""));
const getHostLocale = (event, path, domainLocales) => {
  const host = getRequestURL(event, { xForwardedHost: true }).host;
  const locales = normalizedLocales.map((l) => withRuntimeDomain(l, domainLocales));
  return matchDomainLocale(locales, host, getLocaleFromRoutePath(path));
};
const useDetectors = (event, config, nuxtApp) => {
  if (!event) {
    throw new Error("H3Event is required for server-side locale detection");
  }
  const runtimeI18n = useRuntimeI18n();
  return {
    cookie: () => getCookieLocale(event, config.cookieKey),
    header: () => getHeaderLocale(event) ,
    navigator: () => void 0,
    host: (path) => getHostLocale(event, path, runtimeI18n.domainLocales),
    route: (path) => getRouteLocale(event, path)
  };
};
function createLocaleDetector(config) {
  const { detection} = config;
  const isSupported = config.isSupportedLocale ?? isSupportedLocale;
  function skipDetect(path, pathLocale) {
    {
      return false;
    }
  }
  return function detectLocale(detectors, route, initial) {
    const path = isString(route) ? parsePath(route).pathname : route.path;
    function* detect() {
      const detecting = initial && detection.enabled && !skipDetect(path, detectors.route(path));
      if (detecting) {
        yield detectors.cookie();
        yield detectors.header();
        yield detectors.navigator();
      }
      if (detecting) {
        yield detection.fallbackLocale;
      }
    }
    for (const detected of detect()) {
      if (detected && isSupported(detected)) {
        return detected;
      }
    }
    return "";
  };
}

// Generated by @nuxtjs/i18n
const localizedPaths = [];
const pathToI18nConfig = {};
const i18nPathToPath = {};
const disabledPaths = [];

const matcher = createRouterMatcher([], {});
for (const path of [...localizedPaths, ...Object.keys(i18nPathToPath)]) {
  matcher.addRoute({ path, component: () => "", meta: {} });
}
const disabledI18nMatcher = createRouterMatcher([], {});
for (const path of disabledPaths) {
  disabledI18nMatcher.addRoute({ path, component: () => "", meta: {} });
}
const getI18nPathToI18nPath = (path, locale) => {
  if (!path || !locale) {
    return;
  }
  const plainPath = i18nPathToPath[path] ?? path;
  const i18nConfig = pathToI18nConfig[plainPath];
  if (i18nConfig == null || !(locale in i18nConfig)) {
    return plainPath;
  }
  return i18nConfig[locale] || void 0;
};
function isExistingNuxtRoute(path) {
  if (path === "") {
    return;
  }
  if (path.endsWith("/__nuxt_error")) {
    return;
  }
  const disabledI18nResolvedMatch = disabledI18nMatcher.resolve({ path }, { path: "/", name: "", matched: [], params: {}, meta: {} });
  if (disabledI18nResolvedMatch.matched.length > 0) {
    return;
  }
  const resolvedMatch = matcher.resolve({ path }, { path: "/", name: "", matched: [], params: {}, meta: {} });
  return resolvedMatch.matched.length > 0 ? resolvedMatch : void 0;
}
function matchLocalized(path, locale, defaultLocale) {
  if (path === "") {
    return;
  }
  const parsed = parsePath(path);
  const resolvedMatch = matcher.resolve(
    { path: parsed.pathname || "/" },
    { path: "/", name: "", matched: [], params: {}, meta: {} }
  );
  if (resolvedMatch.matched.length > 0) {
    const alternate = getI18nPathToI18nPath(resolvedMatch.matched[0].path, locale);
    const match = matcher.resolve(
      { params: resolvedMatch.params },
      { path: alternate || "/", name: "", matched: [], params: {}, meta: {} }
    );
    return createTrailingSlashFormatter(false)(withLeadingSlash(joinURL("", match.path)), true);
  }
}

function createRedirectResolver(config) {
  const { detection, rootRedirect, matchLocalized} = config;
  const isSupported = config.isSupportedLocale ?? isSupportedLocale;
  const detectLocale = createLocaleDetector({ detection, isSupportedLocale: isSupported});
  return function resolveRedirectPath(fullPath, path, pathLocale, defaultLocale, detectors) {
    let locale = detectLocale(detectors, fullPath, true) || defaultLocale;
    function getLocalizedMatch(locale2) {
      const res = matchLocalized(path || "/", locale2, defaultLocale);
      if (res && res !== fullPath) {
        return res;
      }
    }
    let resolvedPath = void 0;
    let redirectCode = 302;
    const pathname = parsePath(fullPath).pathname;
    if (rootRedirect && pathname === "/") {
      locale = detection.enabled && locale || defaultLocale;
      resolvedPath = isSupported(detectors.route(rootRedirect.path)) && rootRedirect.path || matchLocalized(rootRedirect.path, locale, defaultLocale);
      redirectCode = rootRedirect.code;
    } else if (config.redirectStatusCode) {
      redirectCode = config.redirectStatusCode;
    }
    switch (detection.redirectOn) {
      case "root":
        if (pathname !== "/") {
          break;
        }
      // fallthrough (root has no prefix)
      case "no prefix":
        if (pathLocale) {
          break;
        }
      // fallthrough to resolve
      case "all":
        resolvedPath ??= getLocalizedMatch(locale);
        break;
    }
    return { path: resolvedPath, code: redirectCode, locale };
  };
}

function createRedirectResponse(event, dest, code) {
  event.node.res.setHeader("location", dest);
  event.node.res.statusCode = sanitizeStatusCode(code, event.node.res.statusCode);
  return {
    headers: event.node.res.getHeaders(),
    statusCode: event.node.res.statusCode,
    body: `<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0; url=${dest.replace(/"/g, "%22")}"></head></html>`
  };
}
const _fl44IpPsqteD9NgAsJ5UTfrPefPYTbVZ0EzmTo87rDA = defineNitroPlugin(async (nitro) => {
  const runtimeI18n = useRuntimeI18n();
  const rootRedirect = resolveRootRedirect(runtimeI18n.rootRedirect);
  runtimeI18n.defaultLocale || "";
  try {
    const cacheStorage = useStorage("cache");
    const cachedKeys = await cacheStorage.getKeys("nitro:handlers:i18n");
    await Promise.all(cachedKeys.map((key) => cacheStorage.removeItem(key)));
  } catch {
  }
  const detection = useI18nDetection();
  const cookieOptions = {
    path: "/",
    domain: detection.cookieDomain || void 0,
    maxAge: 60 * 60 * 24 * 365,
    sameSite: "lax",
    secure: detection.cookieSecure
  };
  const createBaseUrlGetter = () => {
    isFunction(runtimeI18n.baseUrl) ? "" : runtimeI18n.baseUrl || "";
    if (isFunction(runtimeI18n.baseUrl)) {
      console.warn("[nuxt-i18n] Configuring baseUrl as a function is deprecated and will be removed in v11.");
      return () => "";
    }
    return (event, defaultLocale) => {
      return "";
    };
  };
  const resolveRedirectPath = createRedirectResolver({
    detection,
    rootRedirect,
    redirectStatusCode: runtimeI18n.redirectStatusCode,
    matchLocalized});
  const baseUrlGetter = createBaseUrlGetter();
  nitro.hooks.hook("request", async (event) => {
    await initializeI18nContext(event);
  });
  nitro.hooks.hook("render:before", async (context) => {
    const { event } = context;
    const ctx = useI18nContext(event);
    const url = getRequestURL(event);
    const detector = useDetectors(event, detection);
    const localeSegment = detector.route(event.path);
    const pathLocale = isSupportedLocale(localeSegment) && localeSegment || void 0;
    const { pathname } = parsePath(event.path);
    const path = (pathLocale && pathname.slice(pathLocale.length + 1)) ?? pathname;
    if (!url.pathname.includes("/_i18n") && !isExistingNuxtRoute(path)) {
      return;
    }
    const resolved = resolveRedirectPath(event.path, path, pathLocale, ctx.vueI18nOptions.defaultLocale, detector);
    if (resolved.path && resolved.path !== pathname) {
      ctx.detectLocale = resolved.locale;
      detection.useCookie && setCookie(event, detection.cookieKey, resolved.locale, cookieOptions);
      context.response = createRedirectResponse(
        event,
        // the resolved path is base-free (matched against base-free routes), re-add `app.baseURL`
        joinURL(
          baseUrlGetter(event, ctx.vueI18nOptions.defaultLocale),
          useRuntimeConfig(event).app.baseURL,
          resolved.path + url.search
        ),
        resolved.code
      );
      return;
    }
  });
  nitro.hooks.hook("render:html", (htmlContext, { event }) => {
    tryUseI18nContext(event);
  });
});

const rootDir = "/Users/suadasceric/Projects/steelcode-connect/frontend";

const devReducers = {
	VNode: (data) => isVNode(data) ? {
		type: data.type,
		props: data.props
	} : undefined,
	URL: (data) => data instanceof URL ? data.toString() : undefined,
	Symbol: (data) => typeof data === "symbol" ? data.description ?? "" : undefined
};
const asyncContext = getContext("nuxt-dev", {
	asyncContext: true,
	AsyncLocalStorage
});
const _bCrwGMC2hwwloM8LjmAx_baCBGVH4cqNBGTkBiACitI = (nitroApp) => {
	const handler = nitroApp.h3App.handler;
	nitroApp.h3App.handler = (event) => {
		return asyncContext.callAsync({
			logs: [],
			event
		}, () => handler(event));
	};
	onConsoleLog((_log) => {
		const ctx = asyncContext.tryUse();
		if (!ctx) {
			return;
		}
		const rawStack = captureRawStackTrace();
		if (!rawStack || rawStack.includes("runtime/vite-node.mjs")) {
			return;
		}
		const trace = [];
		let filename = "";
		for (const entry of parseRawStackTrace(rawStack)) {
			if (entry.source === globalThis._importMeta_.url) {
				continue;
			}
			if (EXCLUDE_TRACE_RE.test(entry.source)) {
				continue;
			}
			filename ||= entry.source.replace(withTrailingSlash(rootDir), "");
			trace.push({
				...entry,
				source: entry.source.startsWith("file://") ? entry.source.replace("file://", "") : entry.source
			});
		}
		const log = {
			..._log,
			
			filename,
			
			stack: trace
		};
		
		ctx.logs.push(log);
	});
	nitroApp.hooks.hook("afterResponse", () => {
		const ctx = asyncContext.tryUse();
		if (!ctx) {
			return;
		}
		return nitroApp.hooks.callHook("dev:ssr-logs", {
			logs: ctx.logs,
			path: ctx.event.path
		});
	});
	
	nitroApp.hooks.hook("render:html", (htmlContext) => {
		const ctx = asyncContext.tryUse();
		if (!ctx) {
			return;
		}
		try {
			const reducers = Object.assign(Object.create(null), devReducers, ctx.event.context["~payloadReducers"]);
			htmlContext.bodyAppend.unshift(`<script type="application/json" data-nuxt-logs="${appId}">${stringify(ctx.logs, reducers)}<\/script>`);
		} catch (e) {
			const shortError = e instanceof Error && "toString" in e ? ` Received \`${e.toString()}\`.` : "";
			console.warn(`[nuxt] Failed to stringify dev server logs.${shortError} You can define your own reducer/reviver for rich types following the instructions in https://nuxt.com/docs/4.x/api/composables/use-nuxt-app#payload.`);
		}
	});
};
const EXCLUDE_TRACE_RE = /\/node_modules\/(?:.*\/)?(?:nuxt|nuxt-nightly|nuxt-edge|nuxt3|consola|@vue)\/|core\/runtime\/nitro/;
function onConsoleLog(callback) {
	consola$1.addReporter({ log(logObj) {
		callback(logObj);
	} });
	consola$1.wrapConsole();
}

const script = "\"use strict\";(()=>{const o=window,e=document.documentElement,c=[\"dark\",\"light\"],s=getStorageValue(\"localStorage\",\"nuxt-color-mode\")||\"system\";let r=s===\"system\"?f():s;const l=e.getAttribute(\"data-color-mode-forced\");l&&(r=l),i(r),o[\"__NUXT_COLOR_MODE__\"]={preference:s,value:r,getColorScheme:f,addColorScheme:i,removeColorScheme:d};function i(t){const a=\"\"+t+\"\",n=\"\";e.classList?e.classList.add(a):e.className+=\" \"+a,n&&e.setAttribute(\"data-\"+n,t)}function d(t){const a=\"\"+t+\"\",n=\"\";e.classList?e.classList.remove(a):e.className=e.className.replace(new RegExp(a,\"g\"),\"\"),n&&e.removeAttribute(\"data-\"+n)}function u(t){return o.matchMedia(\"(prefers-color-scheme\"+t+\")\")}function f(){if(o.matchMedia&&u(\"\").media!==\"not all\"){for(const t of c)if(u(\":\"+t).matches)return t}return\"light\"}})();function getStorageValue(o,e){switch(o){case\"localStorage\":try{return window.localStorage.getItem(e)}catch{return null}case\"sessionStorage\":try{return window.sessionStorage.getItem(e)}catch{return null}case\"cookie\":try{return getCookie(e)}catch{return null}default:return null}}function getCookie(o){const c=(\"; \"+window.document.cookie).split(\"; \"+o+\"=\");if(c.length===2){const s=c.pop();return s?s.split(\";\").shift():null}}";

const _2ZgYmOCDdt6Ey0NjyNPZyJYHaDsEDjwpdmTH4oNX2E = (function(nitro) {
  nitro.hooks.hook("render:html", (htmlContext) => {
    htmlContext.head.push(`<script>${script}<\/script>`);
  });
});

const plugins = [
  _hISa2eAaFbgIf8ZiB_NCryNg526yte7pO0mNdhgA5sA,
_fl44IpPsqteD9NgAsJ5UTfrPefPYTbVZ0EzmTo87rDA,
_bCrwGMC2hwwloM8LjmAx_baCBGVH4cqNBGTkBiACitI,
_2ZgYmOCDdt6Ey0NjyNPZyJYHaDsEDjwpdmTH4oNX2E,
_wH6JrtIxmaSoA8lCPWFnE9z4lQeXW6H5z3l5aymEQw
];

const assets = {
  "/index.mjs": {
    "type": "text/javascript; charset=utf-8",
    "etag": "\"4848f-nHh2OyID/XIDfFpPHsq5tIcIJzs\"",
    "mtime": "2026-09-29T00:05:24.429Z",
    "size": 296079,
    "path": "index.mjs"
  },
  "/index.mjs.map": {
    "type": "application/json",
    "etag": "\"adfa3-2WylAN9XWf3kcDC7XAVB85NN1k4\"",
    "mtime": "2026-09-29T00:05:24.429Z",
    "size": 712611,
    "path": "index.mjs.map"
  }
};

function readAsset (id) {
  const serverDir = dirname$1(fileURLToPath(globalThis._importMeta_.url));
  return promises.readFile(resolve$1(serverDir, assets[id].path))
}

const publicAssetBases = {"/_nuxt/builds/meta/":{"maxAge":31536000},"/_nuxt/builds/":{"maxAge":1},"/_fonts/":{"maxAge":31536000}};

function isPublicAssetURL(id = '') {
  if (assets[id]) {
    return true
  }
  for (const base in publicAssetBases) {
    if (id.startsWith(base)) { return true }
  }
  return false
}

function getAsset (id) {
  return assets[id]
}

const METHODS = /* @__PURE__ */ new Set(["HEAD", "GET"]);
const EncodingMap = { gzip: ".gz", br: ".br" };
const _MguJ6i = eventHandler((event) => {
  if (event.method && !METHODS.has(event.method)) {
    return;
  }
  let id = decodePath(
    withLeadingSlash(withoutTrailingSlash(parseURL(event.path).pathname))
  );
  let asset;
  const encodingHeader = String(
    getRequestHeader(event, "accept-encoding") || ""
  );
  const encodings = [
    ...encodingHeader.split(",").map((e) => EncodingMap[e.trim()]).filter(Boolean).sort(),
    ""
  ];
  for (const encoding of encodings) {
    for (const _id of [id + encoding, joinURL(id, "index.html" + encoding)]) {
      const _asset = getAsset(_id);
      if (_asset) {
        asset = _asset;
        id = _id;
        break;
      }
    }
  }
  if (!asset) {
    if (isPublicAssetURL(id)) {
      removeResponseHeader(event, "Cache-Control");
      throw createError({ statusCode: 404 });
    }
    return;
  }
  if (asset.encoding !== void 0) {
    appendResponseHeader(event, "Vary", "Accept-Encoding");
  }
  const ifNotMatch = getRequestHeader(event, "if-none-match") === asset.etag;
  if (ifNotMatch) {
    setResponseStatus(event, 304, "Not Modified");
    return "";
  }
  const ifModifiedSinceH = getRequestHeader(event, "if-modified-since");
  const mtimeDate = new Date(asset.mtime);
  if (ifModifiedSinceH && asset.mtime && new Date(ifModifiedSinceH) >= mtimeDate) {
    setResponseStatus(event, 304, "Not Modified");
    return "";
  }
  if (asset.type && !getResponseHeader(event, "Content-Type")) {
    setResponseHeader(event, "Content-Type", asset.type);
  }
  if (asset.etag && !getResponseHeader(event, "ETag")) {
    setResponseHeader(event, "ETag", asset.etag);
  }
  if (asset.mtime && !getResponseHeader(event, "Last-Modified")) {
    setResponseHeader(event, "Last-Modified", mtimeDate.toUTCString());
  }
  if (asset.encoding && !getResponseHeader(event, "Content-Encoding")) {
    setResponseHeader(event, "Content-Encoding", asset.encoding);
  }
  if (asset.size > 0 && !getResponseHeader(event, "Content-Length")) {
    setResponseHeader(event, "Content-Length", asset.size);
  }
  return readAsset(id);
});

const warnOnceSet = /* @__PURE__ */ new Set();
const DEFAULT_ENDPOINT = "https://api.iconify.design";
function getInstallCommand(pkg) {
  const ua = process.env.npm_config_user_agent || "";
  if (ua.startsWith("pnpm")) return `pnpm add -D ${pkg}`;
  if (ua.startsWith("yarn")) return `yarn add -D ${pkg}`;
  if (ua.startsWith("bun")) return `bun add -D ${pkg}`;
  return `npm i -D ${pkg}`;
}
const _ZA1Km1 = defineCachedEventHandler(async (event) => {
  const options = useAppConfig().icon;
  const collectionName = event.context.params?.collection?.replace(/\.json$/, "");
  const collection = collectionName && Object.hasOwn(collections, collectionName) ? await collections[collectionName]?.() : null;
  const apiEndPoint = options.iconifyApiEndpoint || DEFAULT_ENDPOINT;
  const icons = String(parseQuery(parsePath(event.path).search).icons || "").split(",");
  if (!collectionName) return createError({ status: 400, message: "No collection specified" });
  if (!icons.length) return createError({ status: 400, message: "No icons specified" });
  if (!collection && true && !warnOnceSet.has(collectionName) && apiEndPoint === DEFAULT_ENDPOINT) {
    consola$1.warn([
      `[Icon] Collection \`${collectionName}\` is not found locally`,
      `We suggest to install it via \`${getInstallCommand(`@iconify-json/${collectionName}`)}\` to provide the best end-user experience.`
    ].join("\n"));
    warnOnceSet.add(collectionName);
  }
  if (collection) {
    const data = getIcons(
      collection,
      icons
    );
    consola$1.debug(`[Icon] serving ${icons.map((i) => "`" + collectionName + ":" + i + "`").join(",")} from bundled collection`);
    return data;
  }
  if (options.fallbackToApi === true || options.fallbackToApi === "server-only") {
    const apiUrl = new URL(`./${collectionName}.json?icons=${icons.join(",")}`, apiEndPoint);
    consola$1.debug(`[Icon] fetching ${icons.map((i) => "`" + collectionName + ":" + i + "`").join(",")} from iconify api`);
    if (apiUrl.host !== new URL(apiEndPoint).host) {
      return createError({ status: 400, message: "Invalid icon request" });
    }
    try {
      const data = await $fetch(apiUrl.href);
      return data;
    } catch (e) {
      consola$1.error(e);
      if (e.status === 404)
        return createError({ status: 404 });
      else
        return createError({ status: 500, message: "Failed to fetch fallback icon" });
    }
  }
  return createError({ status: 404 });
}, {
  group: "nuxt",
  name: "icon",
  getKey(event) {
    const collection = event.context.params?.collection?.replace(/\.json$/, "") || "unknown";
    const icons = String(parseQuery(parsePath(event.path).search).icons || "").split(",");
    return `${collection}_${icons[0]}_${icons.length}_${hash$1(icons.join(","))}`;
  },
  swr: true,
  maxAge: 60 * 60 * 24 * 7
  // 1 week
});

const storage = prefixStorage(useStorage(), "i18n");
function cachedFunctionI18n(fn, opts) {
  opts = { maxAge: 1, ...opts };
  const pending = {};
  async function get(key, resolver) {
    const isPending = pending[key];
    if (!isPending) {
      pending[key] = Promise.resolve(resolver());
    }
    try {
      return await pending[key];
    } finally {
      delete pending[key];
    }
  }
  return async (...args) => {
    const key = [opts.name, opts.getKey(...args)].join(":").replace(/:\/$/, ":index");
    const maxAge = opts.maxAge ?? 1;
    const isCacheable = !opts.shouldBypassCache(...args) && maxAge >= 0;
    const cache = isCacheable && await storage.getItemRaw(key);
    if (!cache || cache.ttl < Date.now()) {
      pending[key] = Promise.resolve(fn(...args));
      const value = await get(key, () => fn(...args));
      if (isCacheable) {
        await storage.setItemRaw(key, { ttl: Date.now() + maxAge * 1e3, value, mtime: Date.now() });
      }
      return value;
    }
    return cache.value;
  };
}

const _getMessages = async (locale) => {
  return { [locale]: await getLocaleMessagesMerged(locale, localeLoaders[locale]) };
};
cachedFunctionI18n(_getMessages, {
  name: "messages",
  maxAge: -1 ,
  getKey: (locale) => locale,
  shouldBypassCache: (locale) => !isLocaleCacheable(locale)
});
const getMessages = _getMessages ;
const _getMergedMessages = async (locale, fallbackLocales) => {
  const merged = {};
  try {
    if (fallbackLocales.length > 0) {
      const messages = await Promise.all(fallbackLocales.map(getMessages));
      for (const message2 of messages) {
        deepCopy(message2, merged);
      }
    }
    const message = await getMessages(locale);
    deepCopy(message, merged);
    return merged;
  } catch (e) {
    throw new Error("Failed to merge messages: " + e.message, { cause: e });
  }
};
const getMergedMessages = cachedFunctionI18n(_getMergedMessages, {
  name: "merged-single",
  maxAge: -1 ,
  getKey: (locale, fallbackLocales) => `${locale}-[${[...new Set(fallbackLocales)].sort().join("-")}]`,
  shouldBypassCache: (locale, fallbackLocales) => !isLocaleWithFallbacksCacheable(locale, fallbackLocales)
});
const _getAllMergedMessages = async (locales) => {
  const merged = {};
  try {
    const messages = await Promise.all(locales.map(getMessages));
    for (const message of messages) {
      deepCopy(message, merged);
    }
    return merged;
  } catch (e) {
    throw new Error("Failed to merge messages: " + e.message, { cause: e });
  }
};
cachedFunctionI18n(_getAllMergedMessages, {
  name: "merged-all",
  maxAge: -1 ,
  getKey: (locales) => locales.join("-"),
  shouldBypassCache: (locales) => !locales.every((locale) => isLocaleCacheable(locale))
});

const _messagesHandler = defineEventHandler(async (event) => {
  const locale = getRouterParam(event, "locale");
  if (!locale) {
    throw createError({ status: 400, message: "Locale not specified." });
  }
  const ctx = useI18nContext(event);
  if (ctx.localeConfigs && locale in ctx.localeConfigs === false) {
    throw createError({ status: 404, message: `Locale '${locale}' not found.` });
  }
  const messages = await getMergedMessages(locale, ctx.localeConfigs?.[locale]?.fallbacks ?? []);
  deepCopy(messages, ctx.messages);
  return ctx.messages;
});
const _cachedMessageLoader = defineCachedFunction(_messagesHandler, {
  name: "i18n:messages-internal",
  maxAge: -1 ,
  getKey: (event) => [getRouterParam(event, "locale") ?? "null", getRouterParam(event, "hash") ?? "null"].join("-"),
  async shouldBypassCache(event) {
    const locale = getRouterParam(event, "locale");
    if (locale == null) {
      return false;
    }
    const ctx = tryUseI18nContext(event) || await initializeI18nContext(event);
    return !ctx.localeConfigs?.[locale]?.cacheable;
  }
});
defineCachedEventHandler(_cachedMessageLoader, {
  name: "i18n:messages",
  maxAge: -1 ,
  swr: false,
  getKey: (event) => [getRouterParam(event, "locale") ?? "null", getRouterParam(event, "hash") ?? "null"].join("-")
});
const _GKTQm3 = _messagesHandler ;

const VueResolver = (_, value) => {
  return isRef(value) ? toValue(value) : value;
};

const headSymbol = "usehead";
// @__NO_SIDE_EFFECTS__
function vueInstall(head) {
  const plugin = {
    install(app) {
      app.config.globalProperties.$unhead = head;
      app.config.globalProperties.$head = head;
      app.provide(headSymbol, head);
    }
  };
  return plugin.install;
}

// @__NO_SIDE_EFFECTS__
function resolveUnrefHeadInput(input) {
  return walkResolver(input, VueResolver);
}

function filterIslandProps(props) {
  if (!props) {
    return {};
  }
  const out = {};
  for (const key in props) {
    if (!key.startsWith("data-v-")) {
      out[key] = props[key];
    }
  }
  return out;
}
function computeIslandHash(name, filteredProps, context, source) {
  return hash$1([name, filteredProps, context, source]).replace(/[-_]/g, "");
}

const NUXT_PAYLOAD_INLINE = false;

const payloadCache = useStorage("cache:nuxt:payload") ;

// @__NO_SIDE_EFFECTS__
function createHead(options = {}) {
  const head = createHead$1({
    ...options,
    propResolvers: [VueResolver]
  });
  head.install = vueInstall(head);
  return head;
}

const unheadOptions = {
  disableDefaults: true,
};

function encodeEventPath(path) {
	const queryIndex = path.indexOf("?");
	if (queryIndex === -1) {
		return encodePath(path);
	}
	return encodePath(path.slice(0, queryIndex)) + path.slice(queryIndex);
}
function createSSRContext(event) {
	const url = encodeEventPath(event.path);
	const ssrContext = {
		url,
		event,
		runtimeConfig: useRuntimeConfig(event),
		noSSR: event.context.nuxt?.noSSR || (false),
		head: createHead(unheadOptions),
		error: false,
		nuxt: undefined,
		payload: {},
		["~payloadReducers"]: Object.create(null),
		modules: new Set()
	};
	return ssrContext;
}
function setSSRError(ssrContext, error) {
	ssrContext.error = true;
	ssrContext.payload = { error };
	ssrContext.url = error.url;
}

// @ts-expect-error private property consumed by vite-generated url helpers
globalThis.__buildAssetsURL = buildAssetsURL;
// @ts-expect-error private property consumed by vite-generated url helpers
globalThis.__publicAssetsURL = publicAssetsURL;
const APP_ROOT_OPEN_TAG = `<${appRootTag}${propsToString(appRootAttrs)}>`;
const APP_ROOT_CLOSE_TAG = `</${appRootTag}>`;
// @ts-expect-error file will be produced after app build
const getServerEntry = () => Promise.resolve().then(function () { return server; }).then((r) => r.default || r);
// @ts-expect-error file will be produced after app build
const getClientManifest = () => Promise.resolve().then(function () { return client_manifest$1; }).then((r) => r.default || r).then((r) => typeof r === "function" ? r() : r);

const getSSRRenderer = lazyCachedFunction(async () => {
	
	const createSSRApp = await getServerEntry();
	if (!createSSRApp) {
		throw new Error("Server bundle is not available");
	}
	
	const precomputed = undefined ;
	
	const renderer = createRenderer(createSSRApp, {
		precomputed,
		manifest: await getClientManifest() ,
		renderToString: renderToString$1,
		buildAssetsURL
	});
	async function renderToString$1(input, context) {
		const html = await renderToString(input, context);
		
		
		if (process.env.NUXT_VITE_NODE_OPTIONS) {
			renderer.rendererContext.updateManifest(await getClientManifest());
		}
		return APP_ROOT_OPEN_TAG + html + APP_ROOT_CLOSE_TAG;
	}
	return renderer;
});

const getSPARenderer = lazyCachedFunction(async () => {
	const precomputed = undefined ;
	// @ts-expect-error virtual file
	const spaTemplate = await Promise.resolve().then(function () { return _virtual__spaTemplate; }).then((r) => r.template).catch(() => "").then((r) => {
		{
			const APP_SPA_LOADER_OPEN_TAG = `<${appSpaLoaderTag}${propsToString(appSpaLoaderAttrs)}>`;
			const APP_SPA_LOADER_CLOSE_TAG = `</${appSpaLoaderTag}>`;
			const appTemplate = APP_ROOT_OPEN_TAG + APP_ROOT_CLOSE_TAG;
			const loaderTemplate = r ? APP_SPA_LOADER_OPEN_TAG + r + APP_SPA_LOADER_CLOSE_TAG : "";
			return appTemplate + loaderTemplate;
		}
	});
	
	const renderer = createRenderer(() => () => {}, {
		precomputed,
		manifest: await getClientManifest() ,
		renderToString: () => spaTemplate,
		buildAssetsURL
	});
	const result = await renderer.renderToString({});
	const renderToString = (ssrContext) => {
		const config = useRuntimeConfig(ssrContext.event);
		ssrContext.modules ||= new Set();
		ssrContext.payload.serverRendered = false;
		ssrContext.config = {
			public: config.public,
			app: config.app
		};
		return Promise.resolve(result);
	};
	return {
		rendererContext: renderer.rendererContext,
		renderToString
	};
});
function lazyCachedFunction(fn) {
	let res = null;
	return () => {
		if (res === null) {
			res = fn().catch((err) => {
				res = null;
				throw err;
			});
		}
		return res;
	};
}
function getRenderer(ssrContext) {
	return ssrContext.noSSR ? getSPARenderer() : getSSRRenderer();
}
// @ts-expect-error file will be produced after app build
const getSSRStyles = lazyCachedFunction(() => Promise.resolve().then(function () { return styles$1; }).then((r) => r.default || r));

async function renderInlineStyles(usedModules) {
	const styleMap = await getSSRStyles();
	const inlinedStyles = new Set();
	for (const mod of usedModules) {
		if (mod in styleMap && styleMap[mod]) {
			for (const style of await styleMap[mod]()) {
				inlinedStyles.add(style);
			}
		}
	}
	return Array.from(inlinedStyles).map((style) => ({ innerHTML: style }));
}

// @ts-expect-error virtual file
const ROOT_NODE_REGEX = new RegExp(`^<${appRootTag}[^>]*>([\\s\\S]*)<\\/${appRootTag}>$`);

function getServerComponentHTML(body) {
	const match = body.match(ROOT_NODE_REGEX);
	return match?.[1] || body;
}
const SSR_SLOT_TELEPORT_MARKER = /^uid=([^;]*);slot=(.*)$/;
const SSR_CLIENT_TELEPORT_MARKER = /^uid=([^;]*);client=(.*)$/;
const SSR_CLIENT_SLOT_MARKER = /^island-slot=([^;]*);(.*)$/;
function getSlotIslandResponse(ssrContext) {
	if (!ssrContext.islandContext || !Object.keys(ssrContext.islandContext.slots).length) {
		return undefined;
	}
	const response = {};
	for (const [name, slot] of Object.entries(ssrContext.islandContext.slots)) {
		response[name] = {
			...slot,
			fallback: ssrContext.teleports?.[`island-fallback=${name}`]
		};
	}
	return response;
}
function getClientIslandResponse(ssrContext) {
	if (!ssrContext.islandContext || !Object.keys(ssrContext.islandContext.components).length) {
		return undefined;
	}
	const response = {};
	for (const [clientUid, component] of Object.entries(ssrContext.islandContext.components)) {
		
		const html = ssrContext.teleports?.[clientUid]?.replaceAll("<!--teleport start anchor-->", "") || "";
		response[clientUid] = {
			...component,
			html,
			slots: getComponentSlotTeleport(clientUid, ssrContext.teleports ?? {})
		};
	}
	return response;
}
function getComponentSlotTeleport(clientUid, teleports) {
	const entries = Object.entries(teleports);
	const slots = {};
	for (const [key, value] of entries) {
		const match = key.match(SSR_CLIENT_SLOT_MARKER);
		if (match) {
			const [, id, slot] = match;
			if (!slot || clientUid !== id) {
				continue;
			}
			slots[slot] = value;
		}
	}
	return slots;
}
function replaceIslandTeleports(ssrContext, html) {
	const { teleports, islandContext } = ssrContext;
	if (islandContext || !teleports) {
		return html;
	}
	for (const key in teleports) {
		const matchClientComp = key.match(SSR_CLIENT_TELEPORT_MARKER);
		if (matchClientComp) {
			const [, uid, clientId] = matchClientComp;
			if (!uid || !clientId) {
				continue;
			}
			html = html.replace(new RegExp(` data-island-uid="${uid}" data-island-component="${clientId}"[^>]*>`), (full) => {
				return full + teleports[key];
			});
			continue;
		}
		const matchSlot = key.match(SSR_SLOT_TELEPORT_MARKER);
		if (matchSlot) {
			const [, uid, slot] = matchSlot;
			if (!uid || !slot) {
				continue;
			}
			html = html.replace(new RegExp(` data-island-uid="${uid}" data-island-slot="${slot}"[^>]*>`), (full) => {
				return full + teleports[key];
			});
		}
	}
	return html;
}

const ISLAND_SUFFIX_RE = /\.json(?:\?.*)?$/;
const handler$1 = defineEventHandler(async (event) => {
	const nitroApp = useNitroApp();
	setResponseHeaders(event, {
		"content-type": "application/json;charset=utf-8",
		"x-powered-by": "Nuxt"
	});
	const islandContext = await getIslandContext(event);
	const ssrContext = {
		...createSSRContext(event),
		islandContext,
		noSSR: false,
		url: islandContext.url
	};
	
	const renderer = await getSSRRenderer();
	const renderResult = await renderer.renderToString(ssrContext).catch(async (err) => {
		if (ssrContext["~renderResponse"] && err?.message === "skipping render") {
			return {};
		}
		await ssrContext.nuxt?.hooks.callHook("app:error", err);
		throw err;
	});
	
	
	await ssrContext.nuxt?.hooks.callHook("app:rendered", {
		ssrContext,
		renderResult
	});
	if (ssrContext["~renderResponse"]) {
		const response = ssrContext["~renderResponse"];
		if (response.statusCode && response.statusCode >= 400) {
			throw createError({
				statusCode: response.statusCode,
				statusMessage: response.statusMessage
			});
		}
		return returnIslandResponse(event, response);
	}
	
	if (ssrContext.payload?.error) {
		throw ssrContext.payload.error;
	}
	const inlinedStyles = await renderInlineStyles(ssrContext.modules ?? []);
	if (inlinedStyles.length) {
		ssrContext.head.push({ style: inlinedStyles });
	}
	{
		const { styles } = getRequestDependencies(ssrContext, renderer.rendererContext);
		const link = [];
		for (const resource of Object.values(styles)) {
			
			if ("inline" in getQuery(resource.file)) {
				continue;
			}
			
			
			if (resource.file.includes("scoped") && !resource.file.includes("pages/")) {
				link.push({
					rel: "stylesheet",
					href: renderer.rendererContext.buildAssetsURL(resource.file),
					crossorigin: ""
				});
			}
		}
		if (link.length) {
			ssrContext.head.push({ link }, { mode: "server" });
		}
	}
	const islandHead = {};
	for (const entry of ssrContext.head.entries.values()) {
		
		for (const [key, value] of Object.entries(resolveUnrefHeadInput(entry.input))) {
			const currentValue = islandHead[key];
			if (Array.isArray(currentValue)) {
				currentValue.push(...value);
			} else {
				islandHead[key] = value;
			}
		}
	}
	const islandResponse = {
		id: islandContext.id,
		head: islandHead,
		html: getServerComponentHTML(renderResult.html),
		components: getClientIslandResponse(ssrContext),
		slots: getSlotIslandResponse(ssrContext)
	};
	await nitroApp.hooks.callHook("render:island", islandResponse, {
		event,
		islandContext
	});
	return islandResponse;
});
function returnIslandResponse(event, response) {
	for (const header in response.headers || {}) {
		setResponseHeader(event, header, response.headers[header]);
	}
	if (response.statusCode) {
		setResponseStatus(event, response.statusCode, response.statusMessage);
	}
	return response.body;
}
const ISLAND_PATH_PREFIX = "/__nuxt_island/";
const VALID_COMPONENT_NAME_RE = /^[a-z][\w.-]*$/i;
async function getIslandContext(event) {
	let url = event.path || "";
	url.replace(/\?.*$/, "");
	if (!url.startsWith(ISLAND_PATH_PREFIX)) {
		throw createError({
			statusCode: 400,
			statusMessage: "Invalid island request path"
		});
	}
	const componentParts = url.substring(ISLAND_PATH_PREFIX.length).replace(ISLAND_SUFFIX_RE, "").split("_");
	const hashId = componentParts.length > 1 ? componentParts.pop() : undefined;
	const componentName = componentParts.join("_");
	if (!componentName || !VALID_COMPONENT_NAME_RE.test(componentName)) {
		throw createError({
			statusCode: 400,
			statusMessage: "Invalid island component name"
		});
	}
	const rawContext = event.method === "GET" ? getQuery$1(event) : await readBody(event);
	const rawProps = destr$1(rawContext?.props) || {};
	const filteredProps = filterIslandProps(rawProps);
	
	
	const clientContext = {};
	if (rawContext && typeof rawContext === "object") {
		for (const key in rawContext) {
			if (key !== "props") {
				clientContext[key] = rawContext[key];
			}
		}
	}
	
	
	const expectedHash = computeIslandHash(componentName, filteredProps, clientContext, undefined);
	if (!hashId || hashId !== expectedHash) {
		throw createError({
			statusCode: 400,
			statusMessage: "Invalid island request hash"
		});
	}
	return {
		url: typeof rawContext?.url === "string" ? rawContext.url : "/",
		id: hashId,
		name: componentName,
		props: rawProps,
		slots: {},
		components: {}
	};
}

const _lazy_7g8CYp = () => Promise.resolve().then(function () { return customers$1; });
const _lazy_5A0lxA = () => Promise.resolve().then(function () { return mails$1; });
const _lazy_ShVFBO = () => Promise.resolve().then(function () { return members$1; });
const _lazy_zhAYYl = () => Promise.resolve().then(function () { return notifications$1; });
const _lazy_0znNo6 = () => Promise.resolve().then(function () { return ____path_$1; });
const _lazy_JhxBf8 = () => Promise.resolve().then(function () { return logout_post$1; });
const _lazy_Jb35k_ = () => Promise.resolve().then(function () { return renderer; });

const handlers = [
  { route: '', handler: _MguJ6i, lazy: false, middleware: true, method: undefined },
  { route: '/api/customers', handler: _lazy_7g8CYp, lazy: true, middleware: false, method: undefined },
  { route: '/api/mails', handler: _lazy_5A0lxA, lazy: true, middleware: false, method: undefined },
  { route: '/api/members', handler: _lazy_ShVFBO, lazy: true, middleware: false, method: undefined },
  { route: '/api/notifications', handler: _lazy_zhAYYl, lazy: true, middleware: false, method: undefined },
  { route: '/api/**:path', handler: _lazy_0znNo6, lazy: true, middleware: false, method: undefined },
  { route: '/api/v1/auth/logout', handler: _lazy_JhxBf8, lazy: true, middleware: false, method: "post" },
  { route: '/__nuxt_error', handler: _lazy_Jb35k_, lazy: true, middleware: false, method: undefined },
  { route: '/api/_nuxt_icon/:collection', handler: _ZA1Km1, lazy: false, middleware: false, method: undefined },
  { route: '/_i18n/:hash/:locale/messages.json', handler: _GKTQm3, lazy: false, middleware: false, method: undefined },
  { route: '/__nuxt_island/**', handler: handler$1, lazy: false, middleware: false, method: undefined },
  { route: '/_fonts/**', handler: _lazy_Jb35k_, lazy: true, middleware: false, method: undefined },
  { route: '/**', handler: _lazy_Jb35k_, lazy: true, middleware: false, method: undefined }
];

function createNitroApp() {
  const config = useRuntimeConfig();
  const hooks = createHooks();
  const captureError = (error, context = {}) => {
    const promise = hooks.callHookParallel("error", error, context).catch((error_) => {
      console.error("Error while capturing another error", error_);
    });
    if (context.event && isEvent(context.event)) {
      const errors = context.event.context.nitro?.errors;
      if (errors) {
        errors.push({ error, context });
      }
      if (context.event.waitUntil) {
        context.event.waitUntil(promise);
      }
    }
  };
  const h3App = createApp({
    debug: destr(true),
    onError: (error, event) => {
      captureError(error, { event, tags: ["request"] });
      return errorHandler(error, event);
    },
    onRequest: async (event) => {
      event.context.nitro = event.context.nitro || { errors: [] };
      const fetchContext = event.node.req?.__unenv__;
      if (fetchContext?._platform) {
        event.context = {
          _platform: fetchContext?._platform,
          // #3335
          ...fetchContext._platform,
          ...event.context
        };
      }
      if (!event.context.waitUntil && fetchContext?.waitUntil) {
        event.context.waitUntil = fetchContext.waitUntil;
      }
      event.fetch = (req, init) => fetchWithEvent(event, req, init, { fetch: localFetch });
      event.$fetch = (req, init) => fetchWithEvent(event, req, init, {
        fetch: $fetch
      });
      event.waitUntil = (promise) => {
        if (!event.context.nitro._waitUntilPromises) {
          event.context.nitro._waitUntilPromises = [];
        }
        event.context.nitro._waitUntilPromises.push(promise);
        if (event.context.waitUntil) {
          event.context.waitUntil(promise);
        }
      };
      event.captureError = (error, context) => {
        captureError(error, { event, ...context });
      };
      await nitroApp$1.hooks.callHook("request", event).catch((error) => {
        captureError(error, { event, tags: ["request"] });
      });
    },
    onBeforeResponse: async (event, response) => {
      await nitroApp$1.hooks.callHook("beforeResponse", event, response).catch((error) => {
        captureError(error, { event, tags: ["request", "response"] });
      });
    },
    onAfterResponse: async (event, response) => {
      await nitroApp$1.hooks.callHook("afterResponse", event, response).catch((error) => {
        captureError(error, { event, tags: ["request", "response"] });
      });
    }
  });
  const router = createRouter$1({
    preemptive: true
  });
  const nodeHandler = toNodeListener(h3App);
  const localCall = (aRequest) => callNodeRequestHandler(
    nodeHandler,
    aRequest
  );
  const localFetch = (input, init) => {
    if (!input.toString().startsWith("/")) {
      return globalThis.fetch(input, init);
    }
    return fetchNodeRequestHandler(
      nodeHandler,
      input,
      init
    ).then((response) => normalizeFetchResponse(response));
  };
  const $fetch = createFetch({
    fetch: localFetch,
    Headers: Headers$1,
    defaults: { baseURL: config.app.baseURL }
  });
  globalThis.$fetch = $fetch;
  h3App.use(createRouteRulesHandler({ localFetch }));
  for (const h of handlers) {
    let handler = h.lazy ? lazyEventHandler(h.handler) : h.handler;
    if (h.middleware || !h.route) {
      const middlewareBase = (config.app.baseURL + (h.route || "/")).replace(
        /\/+/g,
        "/"
      );
      h3App.use(middlewareBase, handler);
    } else {
      const routeRules = getRouteRulesForPath(
        h.route.replace(/:\w+|\*\*/g, "_")
      );
      if (routeRules.cache) {
        handler = cachedEventHandler(handler, {
          group: "nitro/routes",
          ...routeRules.cache
        });
      }
      router.use(h.route, handler, h.method);
    }
  }
  h3App.use(config.app.baseURL, router.handler);
  const app = {
    hooks,
    h3App,
    router,
    localCall,
    localFetch,
    captureError
  };
  return app;
}
function runNitroPlugins(nitroApp2) {
  for (const plugin of plugins) {
    try {
      plugin(nitroApp2);
    } catch (error) {
      nitroApp2.captureError(error, { tags: ["plugin"] });
      throw error;
    }
  }
}
const nitroApp$1 = createNitroApp();
function useNitroApp() {
  return nitroApp$1;
}
runNitroPlugins(nitroApp$1);

if (!globalThis.crypto) {
  globalThis.crypto = crypto$1.webcrypto;
}
const { NITRO_NO_UNIX_SOCKET, NITRO_DEV_WORKER_ID } = process.env;
trapUnhandledNodeErrors();
parentPort?.on("message", (msg) => {
  if (msg && msg.event === "shutdown") {
    shutdown();
  }
});
const nitroApp = useNitroApp();
const server$1 = new Server(toNodeListener(nitroApp.h3App));
let listener;
listen().catch(() => listen(
  true
  /* use random port */
)).catch((error) => {
  console.error("Dev worker failed to listen:", error);
  return shutdown();
});
nitroApp.router.get(
  "/_nitro/tasks",
  defineEventHandler(async (event) => {
    const _tasks = await Promise.all(
      Object.entries(tasks).map(async ([name, task]) => {
        const _task = await task.resolve?.();
        return [name, { description: _task?.meta?.description }];
      })
    );
    return {
      tasks: Object.fromEntries(_tasks),
      scheduledTasks
    };
  })
);
nitroApp.router.use(
  "/_nitro/tasks/:name",
  defineEventHandler(async (event) => {
    const name = getRouterParam(event, "name");
    const payload = {
      ...getQuery$1(event),
      ...await readBody(event).then((r) => r?.payload).catch(() => ({}))
    };
    return await runTask(name, { payload });
  })
);
function listen(useRandomPort = Boolean(
  NITRO_NO_UNIX_SOCKET || process.versions.webcontainer || "Bun" in globalThis && process.platform === "win32"
)) {
  return new Promise((resolve, reject) => {
    try {
      listener = server$1.listen(useRandomPort ? 0 : getSocketAddress(), () => {
        const address = server$1.address();
        parentPort?.postMessage({
          event: "listen",
          address: typeof address === "string" ? { socketPath: address } : { host: "localhost", port: address?.port }
        });
        resolve();
      });
    } catch (error) {
      reject(error);
    }
  });
}
function getSocketAddress() {
  const socketName = `nitro-worker-${process.pid}-${threadId}-${NITRO_DEV_WORKER_ID}-${Math.round(Math.random() * 1e4)}.sock`;
  if (process.platform === "win32") {
    return join(String.raw`\\.\pipe`, socketName);
  }
  if (process.platform === "linux") {
    const nodeMajor = Number.parseInt(process.versions.node.split(".")[0], 10);
    if (nodeMajor >= 20) {
      return `\0${socketName}`;
    }
  }
  return join(tmpdir(), socketName);
}
async function shutdown() {
  server$1.closeAllConnections?.();
  await Promise.all([
    new Promise((resolve) => listener?.close(resolve)),
    nitroApp.hooks.callHook("close").catch(console.error)
  ]);
  parentPort?.postMessage({ event: "exit" });
}

const _messages = {
	"appName": "Nuxt",
	"status": 500,
	"statusText": "Internal server error",
	"description": "This page is temporarily unavailable.",
	"refresh": "Refresh this page"
};
const template$1 = (messages) => {
	messages = {
		..._messages,
		...messages
	};
	return "<!DOCTYPE html><html lang=\"en\"><head><title>" + escapeHtml(messages.status) + " - " + escapeHtml(messages.statusText) + " | " + escapeHtml(messages.appName) + "</title><meta charset=\"utf-8\"><meta content=\"width=device-width,initial-scale=1.0,minimum-scale=1.0\" name=\"viewport\"><script>!function(){const e=document.createElement(\"link\").relList;if(!(e&&e.supports&&e.supports(\"modulepreload\"))){for(const e of document.querySelectorAll('link[rel=\"modulepreload\"]'))r(e);new MutationObserver(e=>{for(const o of e)if(\"childList\"===o.type)for(const e of o.addedNodes)\"LINK\"===e.tagName&&\"modulepreload\"===e.rel&&r(e)}).observe(document,{childList:!0,subtree:!0})}function r(e){if(e.ep)return;e.ep=!0;const r=function(e){const r={};return e.integrity&&(r.integrity=e.integrity),e.referrerPolicy&&(r.referrerPolicy=e.referrerPolicy),\"use-credentials\"===e.crossOrigin?r.credentials=\"include\":\"anonymous\"===e.crossOrigin?r.credentials=\"omit\":r.credentials=\"same-origin\",r}(e);fetch(e.href,r)}}();<\/script><style>*,:after,:before{box-sizing:border-box;border-width:0;border-style:solid;border-color:var(--un-default-border-color,#e5e7eb)}:after,:before{--un-content:\"\"}html{line-height:1.5;-webkit-text-size-adjust:100%;-moz-tab-size:4;tab-size:4;font-family:ui-sans-serif,system-ui,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;font-feature-settings:normal;font-variation-settings:normal;-webkit-tap-highlight-color:transparent}body{margin:0;line-height:inherit}h1,h2{font-size:inherit;font-weight:inherit}h1,h2,p{margin:0}*,:after,:before{--un-rotate:0;--un-rotate-x:0;--un-rotate-y:0;--un-rotate-z:0;--un-scale-x:1;--un-scale-y:1;--un-scale-z:1;--un-skew-x:0;--un-skew-y:0;--un-translate-x:0;--un-translate-y:0;--un-translate-z:0;--un-pan-x: ;--un-pan-y: ;--un-pinch-zoom: ;--un-scroll-snap-strictness:proximity;--un-ordinal: ;--un-slashed-zero: ;--un-numeric-figure: ;--un-numeric-spacing: ;--un-numeric-fraction: ;--un-border-spacing-x:0;--un-border-spacing-y:0;--un-ring-offset-shadow:0 0 transparent;--un-ring-shadow:0 0 transparent;--un-shadow-inset: ;--un-shadow:0 0 transparent;--un-ring-inset: ;--un-ring-offset-width:0px;--un-ring-offset-color:#fff;--un-ring-width:0px;--un-ring-color:rgba(147,197,253,.5);--un-blur: ;--un-brightness: ;--un-contrast: ;--un-drop-shadow: ;--un-grayscale: ;--un-hue-rotate: ;--un-invert: ;--un-saturate: ;--un-sepia: ;--un-backdrop-blur: ;--un-backdrop-brightness: ;--un-backdrop-contrast: ;--un-backdrop-grayscale: ;--un-backdrop-hue-rotate: ;--un-backdrop-invert: ;--un-backdrop-opacity: ;--un-backdrop-saturate: ;--un-backdrop-sepia: }.grid{display:grid}.mb-2{margin-bottom:.5rem}.mb-4{margin-bottom:1rem}.max-w-520px{max-width:520px}.min-h-screen{min-height:100vh}.place-content-center{place-content:center}.overflow-hidden{overflow:hidden}.bg-white{--un-bg-opacity:1;background-color:rgb(255 255 255/var(--un-bg-opacity))}.px-2{padding-left:.5rem;padding-right:.5rem}.text-center{text-align:center}.text-\\[80px\\]{font-size:80px}.text-2xl{font-size:1.5rem;line-height:2rem}.text-\\[\\#020420\\]{--un-text-opacity:1;color:rgb(2 4 32/var(--un-text-opacity))}.text-\\[\\#64748B\\]{--un-text-opacity:1;color:rgb(100 116 139/var(--un-text-opacity))}.font-semibold{font-weight:600}.leading-none{line-height:1}.tracking-wide{letter-spacing:.025em}.font-sans{font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji}.tabular-nums{--un-numeric-spacing:tabular-nums;font-variant-numeric:var(--un-ordinal) var(--un-slashed-zero) var(--un-numeric-figure) var(--un-numeric-spacing) var(--un-numeric-fraction)}.antialiased{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}@media(prefers-color-scheme:dark){.dark\\:bg-\\[\\#020420\\]{--un-bg-opacity:1;background-color:rgb(2 4 32/var(--un-bg-opacity))}.dark\\:text-white{--un-text-opacity:1;color:rgb(255 255 255/var(--un-text-opacity))}}@media(min-width:640px){.sm\\:text-\\[110px\\]{font-size:110px}.sm\\:text-3xl{font-size:1.875rem;line-height:2.25rem}}</style></head><body class=\"antialiased bg-white dark:bg-[#020420] dark:text-white font-sans grid min-h-screen overflow-hidden place-content-center text-[#020420] tracking-wide\"><div class=\"max-w-520px text-center\"><h1 class=\"font-semibold leading-none mb-4 sm:text-[110px] tabular-nums text-[80px]\">" + escapeHtml(messages.status) + "</h1><h2 class=\"font-semibold mb-2 sm:text-3xl text-2xl\">" + escapeHtml(messages.statusText) + "</h2><p class=\"mb-4 px-2 text-[#64748B] text-md\">" + escapeHtml(messages.description) + "</p></div></body></html>";
};

const error500 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  template: template$1
}, Symbol.toStringTag, { value: 'Module' }));

const server = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: viteNodeEntry_mjs
}, Symbol.toStringTag, { value: 'Module' }));

const client_manifest = () => viteNodeFetch.getManifest();

const client_manifest$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: client_manifest
}, Symbol.toStringTag, { value: 'Module' }));

const template = "";

const _virtual__spaTemplate = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  template: template
}, Symbol.toStringTag, { value: 'Module' }));

const styles = {};

const styles$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: styles
}, Symbol.toStringTag, { value: 'Module' }));

const customers = [{
  id: 1,
  name: "Alex Smith",
  email: "alex.smith@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=1"
  },
  status: "subscribed",
  location: "New York, USA"
}, {
  id: 2,
  name: "Jordan Brown",
  email: "jordan.brown@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=2"
  },
  status: "unsubscribed",
  location: "London, UK"
}, {
  id: 3,
  name: "Taylor Green",
  email: "taylor.green@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=3"
  },
  status: "bounced",
  location: "Paris, France"
}, {
  id: 4,
  name: "Morgan White",
  email: "morgan.white@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=4"
  },
  status: "subscribed",
  location: "Berlin, Germany"
}, {
  id: 5,
  name: "Casey Gray",
  email: "casey.gray@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=5"
  },
  status: "subscribed",
  location: "Tokyo, Japan"
}, {
  id: 6,
  name: "Jamie Johnson",
  email: "jamie.johnson@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=6"
  },
  status: "subscribed",
  location: "Sydney, Australia"
}, {
  id: 7,
  name: "Riley Davis",
  email: "riley.davis@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=7"
  },
  status: "subscribed",
  location: "New York, USA"
}, {
  id: 8,
  name: "Kelly Wilson",
  email: "kelly.wilson@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=8"
  },
  status: "subscribed",
  location: "London, UK"
}, {
  id: 9,
  name: "Drew Moore",
  email: "drew.moore@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=9"
  },
  status: "bounced",
  location: "Paris, France"
}, {
  id: 10,
  name: "Jordan Taylor",
  email: "jordan.taylor@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=10"
  },
  status: "subscribed",
  location: "Berlin, Germany"
}, {
  id: 11,
  name: "Morgan Anderson",
  email: "morgan.anderson@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=11"
  },
  status: "subscribed",
  location: "Tokyo, Japan"
}, {
  id: 12,
  name: "Casey Thomas",
  email: "casey.thomas@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=12"
  },
  status: "unsubscribed",
  location: "Sydney, Australia"
}, {
  id: 13,
  name: "Jamie Jackson",
  email: "jamie.jackson@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=13"
  },
  status: "unsubscribed",
  location: "New York, USA"
}, {
  id: 14,
  name: "Riley White",
  email: "riley.white@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=14"
  },
  status: "unsubscribed",
  location: "London, UK"
}, {
  id: 15,
  name: "Kelly Harris",
  email: "kelly.harris@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=15"
  },
  status: "subscribed",
  location: "Paris, France"
}, {
  id: 16,
  name: "Drew Martin",
  email: "drew.martin@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=16"
  },
  status: "subscribed",
  location: "Berlin, Germany"
}, {
  id: 17,
  name: "Alex Thompson",
  email: "alex.thompson@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=17"
  },
  status: "unsubscribed",
  location: "Tokyo, Japan"
}, {
  id: 18,
  name: "Jordan Garcia",
  email: "jordan.garcia@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=18"
  },
  status: "subscribed",
  location: "Sydney, Australia"
}, {
  id: 19,
  name: "Taylor Rodriguez",
  email: "taylor.rodriguez@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=19"
  },
  status: "bounced",
  location: "New York, USA"
}, {
  id: 20,
  name: "Morgan Lopez",
  email: "morgan.lopez@example.com",
  avatar: {
    src: "https://i.pravatar.cc/128?u=20"
  },
  status: "subscribed",
  location: "London, UK"
}];
const customers_default = eventHandler(async () => {
  return customers;
});

const customers$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: customers_default
}, Symbol.toStringTag, { value: 'Module' }));

const mails = [{
  id: 1,
  from: {
    name: "Alex Smith",
    email: "alex.smith@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=1"
    }
  },
  subject: "Meeting Schedule: Q1 Marketing Strategy Review",
  body: `Dear Team,

I hope this email finds you well. Just a quick reminder about our Q1 Marketing Strategy meeting scheduled for tomorrow at 10 AM EST in Conference Room A.

Agenda:
- Q4 Performance Review
- New Campaign Proposals
- Budget Allocation for Q2
- Team Resource Planning

Please come prepared with your department updates. I've attached the preliminary deck for your review.

Best regards,
Alex Smith
Senior Marketing Director
Tel: (555) 123-4567`,
  date: (/* @__PURE__ */ new Date()).toISOString()
}, {
  id: 2,
  unread: true,
  from: {
    name: "Jordan Brown",
    email: "jordan.brown@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=2"
    }
  },
  subject: "RE: Project Phoenix - Sprint 3 Update",
  body: `Hi team,

Quick update on Sprint 3 deliverables:

\u2705 User authentication module completed
\u{1F3D7}\uFE0F Payment integration at 80%
\u23F3 API documentation pending review

Key metrics:
- Code coverage: 94%
- Sprint velocity: 45 points
- Bug resolution rate: 98%

Please review the attached report for detailed analysis. Let's discuss any blockers in tomorrow's stand-up.

Regards,
Jordan

--
Jordan Brown
Lead Developer | Tech Solutions
Mobile: +1 (555) 234-5678`,
  date: sub(/* @__PURE__ */ new Date(), { minutes: 7 }).toISOString()
}, {
  id: 3,
  unread: true,
  from: {
    name: "Taylor Green",
    email: "taylor.green@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=3"
    }
  },
  subject: "Lunch Plans",
  body: `Hi there!

I was wondering if you'd like to grab lunch this Friday? There's this amazing new Mexican restaurant downtown called "La Casa" that I've been wanting to try. They're known for their authentic tacos and house-made guacamole.

Would 12:30 PM work for you? It would be great to catch up and discuss the upcoming team building event while we're there.

Let me know what you think!

Best,
Taylor`,
  date: sub(/* @__PURE__ */ new Date(), { hours: 3 }).toISOString()
}, {
  id: 4,
  from: {
    name: "Morgan White",
    email: "morgan.white@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=4"
    }
  },
  subject: "New Proposal: Project Horizon",
  body: `Hi team,

I've just uploaded the comprehensive proposal for Project Horizon to our shared drive. The document includes:

\u2022 Detailed project objectives and success metrics
\u2022 Resource allocation and team structure
\u2022 Timeline with key milestones
\u2022 Budget breakdown
\u2022 Risk assessment and mitigation strategies

I'm particularly excited about our innovative approach to the user engagement component, which could set a new standard for our industry.

Could you please review and provide feedback by EOD Friday? I'd like to present this to the steering committee next week.

Thanks in advance,

Morgan White
Senior Project Manager
Tel: (555) 234-5678`,
  date: sub(/* @__PURE__ */ new Date(), { days: 1 }).toISOString()
}, {
  id: 5,
  from: {
    name: "Casey Gray",
    email: "casey.gray@example.com"
  },
  subject: "Updated: San Francisco Conference Trip Itinerary",
  body: `Dear [Name],

Please find your confirmed travel itinerary below:

FLIGHT DETAILS:
Outbound: AA 1234
Date: March 15, 2024
DEP: JFK 09:30 AM
ARR: SFO 12:45 PM

HOTEL:
Marriott San Francisco
Check-in: March 15
Check-out: March 18
Confirmation #: MR123456

SCHEDULE:
March 15 - Evening: Welcome Reception (6 PM)
March 16 - Conference Day 1 (9 AM - 5 PM)
March 17 - Conference Day 2 (9 AM - 4 PM)

Please let me know if you need any modifications.

Best regards,
Casey Gray
Travel Coordinator
Office: (555) 345-6789`,
  date: sub(/* @__PURE__ */ new Date(), { days: 1 }).toISOString()
}, {
  id: 6,
  from: {
    name: "Jamie Johnson",
    email: "jamie.johnson@example.com"
  },
  subject: "Q1 2024 Financial Performance Review",
  body: `Dear Leadership Team,

Please find attached our Q1 2024 financial analysis report. Key highlights:

PERFORMANCE METRICS:
\u2022 Revenue: $12.4M (+15% YoY)
\u2022 Operating Expenses: $8.2M (-3% vs. budget)
\u2022 Net Profit Margin: 18.5% (+2.5% vs. Q4 2023)

AREAS OF OPTIMIZATION:
1. Cloud infrastructure costs (+22% over budget)
2. Marketing spend efficiency (-8% ROI vs. target)
3. Office operational costs (+5% vs. forecast)

I've scheduled a detailed review for Thursday at 2 PM EST. Calendar invite to follow.

Best regards,
Jamie Johnson
Chief Financial Officer
Ext: 4567`,
  date: sub(/* @__PURE__ */ new Date(), { days: 2 }).toISOString()
}, {
  id: 7,
  from: {
    name: "Riley Davis",
    email: "riley.davis@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=7"
    }
  },
  subject: "[Mandatory] New DevOps Tools Training Session",
  body: `Hello Development Team,

This is a reminder about next week's mandatory training session on our updated DevOps toolkit.

\u{1F4C5} Date: Tuesday, March 19
\u23F0 Time: 10:00 AM - 12:30 PM EST
\u{1F4CD} Location: Virtual (Zoom link below)

We'll be covering:
\u2022 GitLab CI/CD pipeline improvements
\u2022 Docker container optimization
\u2022 Kubernetes cluster management
\u2022 New monitoring tools integration

Prerequisites:
1. Install Docker Desktop 4.25
2. Update VS Code to latest version
3. Complete pre-training survey (link attached)

Zoom Link: https://zoom.us/j/123456789
Password: DevOps2024

--
Riley Davis
DevOps Lead
Technical Operations
M: (555) 777-8888`,
  date: sub(/* @__PURE__ */ new Date(), { days: 2 }).toISOString()
}, {
  id: 8,
  unread: true,
  from: {
    name: "Kelly Wilson",
    email: "kelly.wilson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=8"
    }
  },
  subject: "\u{1F389} Happy Birthday!",
  body: `Dear [Name],

On behalf of the entire team, wishing you a fantastic birthday! \u{1F382}

We've organized a small celebration in the break room at 3 PM today. Cake and refreshments will be served!

Your dedication and positive energy make our workplace better every day. Here's to another great year ahead!

Best wishes,
Kelly & The HR Team

P.S. Don't forget to check your email for a special birthday surprise from the company! \u{1F381}

--
Kelly Wilson
HR Director
Human Resources Department
Tel: (555) 999-0000`,
  date: sub(/* @__PURE__ */ new Date(), { days: 2 }).toISOString()
}, {
  id: 9,
  from: {
    name: "Drew Moore",
    email: "drew.moore@example.com"
  },
  subject: "Website Redesign Feedback Request - Phase 2",
  body: `Hi there,

We're entering Phase 2 of our website redesign project and would value your input on the latest iterations.

New Features Implementation:
1. Dynamic product catalog
2. Enhanced search functionality
3. Personalized user dashboard
4. Mobile-responsive navigation

Review Links:
\u2022 Staging Environment: https://staging.example.com
\u2022 Design Specs: [Figma Link]
\u2022 User Flow Documentation: [Confluence Link]

Please provide feedback by EOD Friday. Key areas to focus on:
- User experience
- Navigation flow
- Content hierarchy
- Mobile responsiveness

Your insights will be crucial for our final implementation decisions.

Thanks in advance,
Drew Moore
UX Design Lead
Product Design Team`,
  date: sub(/* @__PURE__ */ new Date(), { days: 5 }).toISOString()
}, {
  id: 10,
  from: {
    name: "Jordan Taylor",
    email: "jordan.taylor@example.com"
  },
  subject: "Corporate Wellness Program - Membership Renewal",
  body: `Dear Valued Member,

Your corporate wellness program membership is due for renewal on April 1st, 2024.

NEW AMENITIES:
\u2728 Expanded yoga studio
\u{1F3CB}\uFE0F State-of-the-art cardio equipment
\u{1F9D8} Meditation room
\u{1F465} Additional group fitness classes

RENEWAL BENEFITS:
\u2022 15% early bird discount
\u2022 3 complimentary personal training sessions
\u2022 Free wellness assessment
\u2022 Access to new mobile app

To schedule a tour or discuss renewal options, please book a time here: [Booking Link]

Stay healthy!

Best regards,
Jordan Taylor
Corporate Wellness Coordinator
Downtown Fitness Center
Tel: (555) 123-7890`,
  date: sub(/* @__PURE__ */ new Date(), { days: 5 }).toISOString()
}, {
  id: 11,
  unread: true,
  from: {
    name: "Morgan Anderson",
    email: "morgan.anderson@example.com"
  },
  subject: "Important: Updates to Your Corporate Insurance Policy",
  body: `Dear [Employee Name],

This email contains important information about changes to your corporate insurance coverage effective April 1, 2024.

KEY UPDATES:
1. Health Insurance
   \u2022 Reduced co-pay for specialist visits ($35 \u2192 $25)
   \u2022 Extended telehealth coverage
   \u2022 New mental health benefits

2. Dental Coverage
   \u2022 Increased annual maximum ($1,500 \u2192 $2,000)
   \u2022 Added orthodontic coverage for dependents

3. Vision Benefits
   \u2022 Enhanced frame allowance
   \u2022 New LASIK discount program

Please review the attached documentation carefully and complete the acknowledgment form by March 25th.

Questions? Join our virtual info session:
\u{1F4C5} March 20th, 2024
\u23F0 11:00 AM EST
\u{1F517} [Teams Link]

Regards,
Morgan Anderson
Benefits Coordinator
HR Department`,
  date: sub(/* @__PURE__ */ new Date(), { days: 12 }).toISOString()
}, {
  id: 12,
  from: {
    name: "Casey Thomas",
    email: "casey.thomas@example.com"
  },
  subject: '\u{1F4DA} March Book Club Meeting: "The Great Gatsby"',
  body: `Hello Book Lovers!

I hope you're enjoying F. Scott Fitzgerald's masterpiece! Our next meeting details:

\u{1F4C5} Thursday, March 21st
\u23F0 5:30 PM - 7:00 PM
\u{1F4CD} Main Conference Room (or Zoom)

Discussion Topics:
1. Symbolism of the green light
2. The American Dream theme
3. Character development
4. Social commentary

Please bring your suggestions for April's book selection!

Refreshments will be provided \u{1F36A}

RSVP by replying to this email.

Happy reading!
Casey

--
Casey Thomas
Book Club Coordinator
Internal Culture Committee`,
  date: sub(/* @__PURE__ */ new Date(), { months: 1 }).toISOString()
}, {
  id: 13,
  from: {
    name: "Jamie Jackson",
    email: "jamie.jackson@example.com"
  },
  subject: "\u{1F373} Company Cookbook Project - Recipe Submission Reminder",
  body: `Dear Colleagues,

Final call for our company cookbook project submissions!

Guidelines for Recipe Submission:
1. Include ingredients list with measurements
2. Step-by-step instructions
3. Cooking time and servings
4. Photo of the finished dish (optional)
5. Any cultural or personal significance

Submission Deadline: March 22nd, 2024

We already have some amazing entries:
\u2022 Sarah's Famous Chili
\u2022 Mike's Mediterranean Pasta
\u2022 Lisa's Vegan Brownies
\u2022 Tom's Family Paella

All proceeds from cookbook sales will support our local food bank.

Submit here: [Form Link]

Cooking together,
Jamie Jackson
Community Engagement Committee
Ext. 5432`,
  date: sub(/* @__PURE__ */ new Date(), { months: 1 }).toISOString()
}, {
  id: 14,
  from: {
    name: "Riley White",
    email: "riley.white@example.com"
  },
  subject: "\u{1F9D8}\u200D\u2640\uFE0F Updated Corporate Wellness Schedule - Spring 2024",
  body: `Dear Wellness Program Participants,

Our Spring 2024 wellness schedule is now available!

NEW CLASSES:
Monday:
\u2022 7:30 AM - Morning Flow Yoga
\u2022 12:15 PM - HIIT Express
\u2022 5:30 PM - Meditation Basics

Wednesday:
\u2022 8:00 AM - Power Vinyasa
\u2022 12:00 PM - Desk Stretching
\u2022 4:30 PM - Mindfulness Workshop

Friday:
\u2022 7:45 AM - Gentle Yoga
\u2022 12:30 PM - Stress Management
\u2022 4:45 PM - Weekend Wind-Down

All classes available in-person and via Zoom.
Download our app to reserve your spot!

Namaste,
Riley White
Corporate Wellness Instructor
Wellness & Benefits Team`,
  date: sub(/* @__PURE__ */ new Date(), { months: 1 }).toISOString()
}, {
  id: 15,
  from: {
    name: "Kelly Harris",
    email: "kelly.harris@example.com"
  },
  subject: '\u{1F4DA} Book Launch Event: "Digital Transformation in the Modern Age"',
  body: `Dear [Name],

You're cordially invited to the launch of my new book, "Digital Transformation in the Modern Age: A Leadership Guide"

EVENT DETAILS:
\u{1F4C5} Date: April 15th, 2024
\u23F0 Time: 6:00 PM - 8:30 PM EST
\u{1F4CD} Grand Hotel Downtown
   123 Business Ave.

AGENDA:
6:00 PM - Welcome Reception
6:30 PM - Keynote Presentation
7:15 PM - Q&A Session
7:45 PM - Book Signing
8:00 PM - Networking

Light refreshments will be served.
Each attendee will receive a signed copy of the book.

RSVP by April 1st: [Event Link]

Looking forward to sharing this milestone with you!

Best regards,
Kelly Harris
Digital Strategy Consultant
Author, "Digital Transformation in the Modern Age"`,
  date: sub(/* @__PURE__ */ new Date(), { months: 1 }).toISOString()
}, {
  id: 16,
  from: {
    name: "Drew Martin",
    email: "drew.martin@example.com"
  },
  subject: "\u{1F680} TechCon 2024: Early Bird Registration Now Open",
  body: `Dear Tech Enthusiasts,

Registration is now open for TechCon 2024: "Innovation at Scale"

CONFERENCE HIGHLIGHTS:
\u{1F4C5} May 15-17, 2024
\u{1F4CD} Tech Convention Center

KEYNOTE SPEAKERS:
\u2022 Sarah Johnson - CEO, Future Tech Inc.
\u2022 Dr. Michael Chang - AI Research Director
\u2022 Lisa Rodriguez - Cybersecurity Expert

TRACKS:
1. AI/ML Innovation
2. Cloud Architecture
3. DevSecOps
4. Digital Transformation
5. Emerging Technologies

EARLY BIRD PRICING (ends April 1):
Full Conference Pass: $899 (reg. $1,199)
Team Discount (5+): 15% off

Register here: [Registration Link]

Best regards,
Drew Martin
Conference Director
TechCon 2024`,
  date: sub(/* @__PURE__ */ new Date(), { months: 1, days: 4 }).toISOString()
}, {
  id: 17,
  from: {
    name: "Alex Thompson",
    email: "alex.thompson@example.com"
  },
  subject: "\u{1F3A8} Modern Perspectives: Contemporary Art Exhibition",
  body: `Hi there,

Hope you're well! I wanted to personally invite you to an extraordinary art exhibition this weekend.

"Modern Perspectives: Breaking Boundaries"
\u{1F4C5} Saturday & Sunday
\u23F0 10 AM - 6 PM
\u{1F4CD} Metropolitan Art Gallery

FEATURED ARTISTS:
\u2022 Maria Chen - Mixed Media
\u2022 James Wright - Digital Art
\u2022 Sofia Patel - Installation
\u2022 Robert Kim - Photography

SPECIAL EVENTS:
\u2022 Artist Talk: Saturday, 2 PM
\u2022 Workshop: Sunday, 11 AM
\u2022 Wine Reception: Saturday, 5 PM

Would love to meet you there! Let me know if you'd like to go together.

Best,
Alex Thompson
Curator
Metropolitan Art Gallery
Tel: (555) 234-5678`,
  date: sub(/* @__PURE__ */ new Date(), { months: 1, days: 15 }).toISOString()
}, {
  id: 18,
  from: {
    name: "Jordan Garcia",
    email: "jordan.garcia@example.com"
  },
  subject: '\u{1F91D} Industry Networking Event: "Connect & Innovate 2024"',
  body: `Dear Professional Network,

You're invited to our premier networking event!

EVENT DETAILS:
\u{1F4C5} March 28th, 2024
\u23F0 6:00 PM - 9:00 PM
\u{1F4CD} Innovation Hub
   456 Enterprise Street

SPEAKERS:
\u2022 Mark Thompson - "Future of Work"
\u2022 Dr. Sarah Chen - "Innovation Trends"
\u2022 Robert Mills - "Digital Leadership"

SCHEDULE:
6:00 - Registration & Welcome
6:30 - Keynote Presentations
7:30 - Networking Session
8:30 - Panel Discussion

Complimentary hors d'oeuvres and beverages will be served.

RSVP Required: [Registration Link]

Best regards,
Jordan Garcia
Event Coordinator
Professional Networking Association`,
  date: sub(/* @__PURE__ */ new Date(), { months: 1, days: 18 }).toISOString()
}, {
  id: 19,
  from: {
    name: "Taylor Rodriguez",
    email: "taylor.rodriguez@example.com"
  },
  subject: "\u{1F31F} Community Service Day - Volunteer Opportunities",
  body: `Dear Colleagues,

Join us for our annual Community Service Day!

EVENT DETAILS:
\u{1F4C5} Saturday, April 6th, 2024
\u23F0 9:00 AM - 3:00 PM
\u{1F4CD} Multiple Locations

VOLUNTEER OPPORTUNITIES:
1. City Park Cleanup
   \u2022 Garden maintenance
   \u2022 Trail restoration
   \u2022 Playground repair

2. Food Bank
   \u2022 Sorting donations
   \u2022 Packing meals
   \u2022 Distribution

3. Animal Shelter
   \u2022 Dog walking
   \u2022 Facility cleaning
   \u2022 Social media support

All volunteers receive:
\u2022 Company volunteer t-shirt
\u2022 Lunch and refreshments
\u2022 Certificate of participation
\u2022 8 hours community service credit

Sign up here: [Volunteer Portal]

Making a difference together,
Taylor Rodriguez
Community Outreach Coordinator
Corporate Social Responsibility Team`,
  date: sub(/* @__PURE__ */ new Date(), { months: 1, days: 25 }).toISOString()
}, {
  id: 20,
  from: {
    name: "Morgan Lopez",
    email: "morgan.lopez@example.com"
  },
  subject: "\u{1F697} Vehicle Maintenance Reminder: 30,000 Mile Service",
  body: `Dear Valued Customer,

Your vehicle is due for its 30,000-mile maintenance service.

RECOMMENDED SERVICES:
\u2022 Oil and filter change
\u2022 Tire rotation and alignment
\u2022 Brake system inspection
\u2022 Multi-point safety inspection
\u2022 Fluid level check and top-off
\u2022 Battery performance test

SERVICE CENTER DETAILS:
\u{1F4CD} Downtown Auto Care
   789 Service Road

\u260E\uFE0F (555) 987-6543

Available Appointments:
\u2022 Monday-Friday: 7:30 AM - 6:00 PM
\u2022 Saturday: 8:00 AM - 2:00 PM

Schedule online: [Booking Link]
or call our service desk directly.

Drive safely,
Morgan Lopez
Service Coordinator
Downtown Auto Care
Emergency: (555) 987-6544`,
  date: sub(/* @__PURE__ */ new Date(), { months: 2 }).toISOString()
}];
const mails_default = eventHandler(async () => {
  return mails;
});

const mails$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: mails_default
}, Symbol.toStringTag, { value: 'Module' }));

const members = [{
  name: "Anthony Fu",
  username: "antfu",
  role: "member",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/antfu" }
}, {
  name: "Baptiste Leproux",
  username: "larbish",
  role: "member",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/larbish" }
}, {
  name: "Benjamin Canac",
  username: "benjamincanac",
  role: "owner",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/benjamincanac" }
}, {
  name: "C\xE9line Dumerc",
  username: "celinedumerc",
  role: "member",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/celinedumerc" }
}, {
  name: "Daniel Roe",
  username: "danielroe",
  role: "member",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/danielroe" }
}, {
  name: "Farnabaz",
  username: "farnabaz",
  role: "member",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/farnabaz" }
}, {
  name: "Ferdinand Coumau",
  username: "FerdinandCoumau",
  role: "member",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/FerdinandCoumau" }
}, {
  name: "Hugo Richard",
  username: "hugorcd",
  role: "owner",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/hugorcd" }
}, {
  name: "Pooya Parsa",
  username: "pi0",
  role: "member",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/pi0" }
}, {
  name: "Sarah Moriceau",
  username: "SarahM19",
  role: "member",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/SarahM19" }
}, {
  name: "S\xE9bastien Chopin",
  username: "Atinux",
  role: "owner",
  avatar: { src: "https://ipx.nuxt.com/f_auto,s_192x192/gh_avatar/atinux" }
}];
const members_default = eventHandler(async () => {
  return members;
});

const members$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: members_default
}, Symbol.toStringTag, { value: 'Module' }));

const notifications = [{
  id: 1,
  unread: true,
  sender: {
    name: "Jordan Brown",
    email: "jordan.brown@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=2"
    }
  },
  body: "sent you a message",
  date: sub(/* @__PURE__ */ new Date(), { minutes: 7 }).toISOString()
}, {
  id: 2,
  sender: {
    name: "Lindsay Walton"
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { hours: 1 }).toISOString()
}, {
  id: 3,
  unread: true,
  sender: {
    name: "Taylor Green",
    email: "taylor.green@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=3"
    }
  },
  body: "sent you a message",
  date: sub(/* @__PURE__ */ new Date(), { hours: 3 }).toISOString()
}, {
  id: 4,
  sender: {
    name: "Courtney Henry",
    avatar: {
      src: "https://i.pravatar.cc/128?u=4"
    }
  },
  body: "added you to a project",
  date: sub(/* @__PURE__ */ new Date(), { hours: 3 }).toISOString()
}, {
  id: 5,
  sender: {
    name: "Tom Cook",
    avatar: {
      src: "https://i.pravatar.cc/128?u=5"
    }
  },
  body: "abandonned cart",
  date: sub(/* @__PURE__ */ new Date(), { hours: 7 }).toISOString()
}, {
  id: 6,
  sender: {
    name: "Casey Thomas",
    avatar: {
      src: "https://i.pravatar.cc/128?u=6"
    }
  },
  body: "purchased your product",
  date: sub(/* @__PURE__ */ new Date(), { days: 1, hours: 3 }).toISOString()
}, {
  id: 7,
  unread: true,
  sender: {
    name: "Kelly Wilson",
    email: "kelly.wilson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=8"
    }
  },
  body: "sent you a message",
  date: sub(/* @__PURE__ */ new Date(), { days: 2 }).toISOString()
}, {
  id: 8,
  sender: {
    name: "Jamie Johnson",
    email: "jamie.johnson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=9"
    }
  },
  body: "requested a refund",
  date: sub(/* @__PURE__ */ new Date(), { days: 5, hours: 4 }).toISOString()
}, {
  id: 9,
  unread: true,
  sender: {
    name: "Morgan Anderson",
    email: "morgan.anderson@example.com"
  },
  body: "sent you a message",
  date: sub(/* @__PURE__ */ new Date(), { days: 6 }).toISOString()
}, {
  id: 10,
  sender: {
    name: "Drew Moore"
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 6 }).toISOString()
}, {
  id: 11,
  sender: {
    name: "Riley Davis"
  },
  body: "abandonned cart",
  date: sub(/* @__PURE__ */ new Date(), { days: 7 }).toISOString()
}, {
  id: 12,
  sender: {
    name: "Jordan Taylor"
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 9 }).toISOString()
}, {
  id: 13,
  sender: {
    name: "Kelly Wilson",
    email: "kelly.wilson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=8"
    }
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 10 }).toISOString()
}, {
  id: 14,
  sender: {
    name: "Jamie Johnson",
    email: "jamie.johnson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=9"
    }
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 11 }).toISOString()
}, {
  id: 15,
  sender: {
    name: "Morgan Anderson"
  },
  body: "purchased your product",
  date: sub(/* @__PURE__ */ new Date(), { days: 12 }).toISOString()
}, {
  id: 16,
  sender: {
    name: "Drew Moore",
    avatar: {
      src: "https://i.pravatar.cc/128?u=16"
    }
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 13 }).toISOString()
}, {
  id: 17,
  sender: {
    name: "Riley Davis"
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 14 }).toISOString()
}, {
  id: 18,
  sender: {
    name: "Jordan Taylor"
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 15 }).toISOString()
}, {
  id: 19,
  sender: {
    name: "Kelly Wilson",
    email: "kelly.wilson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=8"
    }
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 16 }).toISOString()
}, {
  id: 20,
  sender: {
    name: "Jamie Johnson",
    email: "jamie.johnson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=9"
    }
  },
  body: "purchased your product",
  date: sub(/* @__PURE__ */ new Date(), { days: 17 }).toISOString()
}, {
  id: 21,
  sender: {
    name: "Morgan Anderson"
  },
  body: "abandonned cart",
  date: sub(/* @__PURE__ */ new Date(), { days: 17 }).toISOString()
}, {
  id: 22,
  sender: {
    name: "Drew Moore"
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 18 }).toISOString()
}, {
  id: 23,
  sender: {
    name: "Riley Davis"
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 19 }).toISOString()
}, {
  id: 24,
  sender: {
    name: "Jordan Taylor",
    avatar: {
      src: "https://i.pravatar.cc/128?u=24"
    }
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 20 }).toISOString()
}, {
  id: 25,
  sender: {
    name: "Kelly Wilson",
    email: "kelly.wilson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=8"
    }
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 20 }).toISOString()
}, {
  id: 26,
  sender: {
    name: "Jamie Johnson",
    email: "jamie.johnson@example.com",
    avatar: {
      src: "https://i.pravatar.cc/128?u=9"
    }
  },
  body: "abandonned cart",
  date: sub(/* @__PURE__ */ new Date(), { days: 21 }).toISOString()
}, {
  id: 27,
  sender: {
    name: "Morgan Anderson"
  },
  body: "subscribed to your email list",
  date: sub(/* @__PURE__ */ new Date(), { days: 22 }).toISOString()
}];
const notifications_default = eventHandler(async () => {
  return notifications;
});

const notifications$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: notifications_default
}, Symbol.toStringTag, { value: 'Module' }));

const ____path_ = defineEventHandler((event) => {
  const path = getRouterParam(event, "path") || "";
  const { search } = getRequestURL(event);
  return proxyRequest(event, `http://127.0.0.1:8000/api/${path}${search}`);
});

const ____path_$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: ____path_
}, Symbol.toStringTag, { value: 'Module' }));

const logout_post = defineEventHandler(async (event) => {
  await $fetch("http://127.0.0.1:8000/api/v1/auth/logout", {
    method: "POST",
    headers: {
      cookie: getRequestHeader(event, "cookie") || ""
    }
  });
  deleteCookie(event, "steelcode_session", {
    path: "/",
    httpOnly: true,
    sameSite: "lax"
  });
  return null;
});

const logout_post$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: logout_post
}, Symbol.toStringTag, { value: 'Module' }));

function renderPayloadResponse(ssrContext) {
	return {
		body: encodeForwardSlashes(stringify(splitPayload(ssrContext).payload, ssrContext["~payloadReducers"])) ,
		statusCode: getResponseStatus(ssrContext.event),
		statusMessage: getResponseStatusText(ssrContext.event),
		headers: {
			"content-type": "application/json;charset=utf-8" ,
			"x-powered-by": "Nuxt"
		}
	};
}
function renderPayloadJsonScript(opts) {
	const contents = opts.data ? encodeForwardSlashes(stringify(opts.data, opts.ssrContext["~payloadReducers"])) : "";
	const payload = {
		"type": "application/json",
		"innerHTML": contents,
		"data-nuxt-data": appId,
		"data-ssr": !(opts.ssrContext.noSSR)
	};
	{
		payload.id = "__NUXT_DATA__";
	}
	if (opts.src) {
		payload["data-src"] = opts.src;
	}
	const config = uneval(opts.ssrContext.config);
	return [payload, { innerHTML: `window.__NUXT__={};window.__NUXT__.config=${config}` }];
}

function encodeForwardSlashes(str) {
	return str.replaceAll("/", "\\u002F");
}
function splitPayload(ssrContext) {
	const { data, prerenderedAt, ...initial } = ssrContext.payload;
	return {
		initial: {
			...initial,
			prerenderedAt
		},
		payload: {
			data,
			prerenderedAt
		}
	};
}

const renderSSRHeadOptions = {"omitLineBreaks":true};

// @ts-expect-error private property consumed by vite-generated url helpers
globalThis.__buildAssetsURL = buildAssetsURL;
// @ts-expect-error private property consumed by vite-generated url helpers
globalThis.__publicAssetsURL = publicAssetsURL;
const HAS_APP_TELEPORTS = !!(appTeleportAttrs.id);
const APP_TELEPORT_OPEN_TAG = HAS_APP_TELEPORTS ? `<${appTeleportTag}${propsToString(appTeleportAttrs)}>` : "";
const APP_TELEPORT_CLOSE_TAG = HAS_APP_TELEPORTS ? `</${appTeleportTag}>` : "";
const PAYLOAD_URL_RE = /^[^?]*\/_payload.json(?:\?.*)?$/ ;
const PAYLOAD_FILENAME = "_payload.json" ;
const handler = defineRenderHandler((event) => {
	
	const ssrError = event.path.startsWith("/__nuxt_error") ? getQuery$1(event) : null;
	if (ssrError && !("__unenv__" in event.node.req)) {
		throw createError({
			status: 404,
			statusText: "Page Not Found: /__nuxt_error",
			message: "Page Not Found: /__nuxt_error"
		});
	}
	return renderRoute(event, ssrError);
});
async function renderRoute(event, ssrError) {
	const nitroApp = useNitroApp();
	
	const ssrContext = createSSRContext(event);
	
	const headEntryOptions = { mode: "server" };
	ssrContext.head.push(appHead, headEntryOptions);
	if (ssrError) {
		
		const status = ssrError.status || ssrError.statusCode;
		if (status) {
			
			ssrError.status = ssrError.statusCode = Number.parseInt(status);
		}
		if (typeof ssrError.data === "string") {
			try {
				ssrError.data = destr(ssrError.data);
			} catch {}
		}
		setSSRError(ssrContext, ssrError);
	}
	
	const routeOptions = getRouteRules(event);
	if (routeOptions.ssr === false) {
		ssrContext.noSSR = true;
	}
	
	const _PAYLOAD_EXTRACTION = !ssrContext.noSSR && ((routeOptions.isr || routeOptions.cache));
	
	
	
	const _PAYLOAD_INLINE = !_PAYLOAD_EXTRACTION || NUXT_PAYLOAD_INLINE;
	const isRenderingPayload = (_PAYLOAD_EXTRACTION || routeOptions.prerender) && PAYLOAD_URL_RE.test(ssrContext.url);
	if (isRenderingPayload) {
		const url = ssrContext.url.substring(0, ssrContext.url.lastIndexOf("/")) || "/";
		ssrContext.url = url;
		event._path = event.node.req.url = url;
		if (payloadCache && await payloadCache.hasItem(url + ".json")) {
			return payloadCache.getItem(url + ".json");
		}
	}
	const payloadURL = _PAYLOAD_EXTRACTION ? joinURL(ssrContext.runtimeConfig.app.cdnURL || ssrContext.runtimeConfig.app.baseURL, ssrContext.url.replace(/\?.*$/, ""), PAYLOAD_FILENAME) + "?" + ssrContext.runtimeConfig.app.buildId : undefined;
	
	const renderer = await getRenderer(ssrContext);
	const _rendered = await renderer.renderToString(ssrContext).catch(async (error) => {
		
		
		if ((ssrContext["~renderResponse"] || ssrContext._renderResponse) && error.message === "skipping render") {
			return {};
		}
		
		const _err = !ssrError && ssrContext.payload?.error || error;
		await ssrContext.nuxt?.hooks.callHook("app:error", _err);
		throw _err;
	});
	
	
	const inlinedStyles = [];
	await ssrContext.nuxt?.hooks.callHook("app:rendered", {
		ssrContext,
		renderResult: _rendered
	});
	if (ssrContext["~renderResponse"] || ssrContext._renderResponse) {
		
		return ssrContext["~renderResponse"] || ssrContext._renderResponse;
	}
	
	if (ssrContext.payload?.error && !ssrError) {
		throw ssrContext.payload.error;
	}
	
	if (isRenderingPayload) {
		const response = renderPayloadResponse(ssrContext);
		if (payloadCache) {
			await payloadCache.setItem(ssrContext.url + ".json", response);
		}
		return response;
	}
	if (_PAYLOAD_EXTRACTION) {
		
		
		if (payloadCache) {
			await payloadCache.setItem((ssrContext.url === "/" ? "/" : ssrContext.url.replace(/\/$/, "")) + ".json", renderPayloadResponse(ssrContext));
		}
	}
	const NO_SCRIPTS = routeOptions.noScripts;
	
	const { styles, scripts } = getRequestDependencies(ssrContext, renderer.rendererContext);
	
	
	if (_PAYLOAD_EXTRACTION && !_PAYLOAD_INLINE && !NO_SCRIPTS) {
		ssrContext.head.push({ link: [{
			rel: "preload",
			as: "fetch",
			crossorigin: "anonymous",
			href: payloadURL
		} ] }, headEntryOptions);
	}
	
	if (inlinedStyles.length) {
		ssrContext.head.push({ style: inlinedStyles });
	}
	const link = [];
	for (const resource of Object.values(styles)) {
		
		if ("inline" in getQuery(resource.file)) {
			continue;
		}
		
		
		
		link.push({
			rel: "stylesheet",
			href: renderer.rendererContext.buildAssetsURL(resource.file),
			crossorigin: ""
		});
	}
	if (link.length) {
		ssrContext.head.push({ link }, headEntryOptions);
	}
	if (!NO_SCRIPTS) {
		
		
		
		if (ssrContext["~lazyHydratedModules"]) {
			for (const id of ssrContext["~lazyHydratedModules"]) {
				ssrContext.modules?.delete(id);
			}
		}
		ssrContext.head.push({ link: getPreloadLinks(ssrContext, renderer.rendererContext) }, headEntryOptions);
		ssrContext.head.push({ link: getPrefetchLinks(ssrContext, renderer.rendererContext) }, headEntryOptions);
		
		ssrContext.head.push({ script: _PAYLOAD_INLINE ? renderPayloadJsonScript({
			ssrContext,
			data: ssrContext.payload
		})  : renderPayloadJsonScript({
			ssrContext,
			data: splitPayload(ssrContext).initial,
			src: payloadURL
		})  }, {
			...headEntryOptions,
			
			tagPosition: "bodyClose",
			tagPriority: "high"
		});
	}
	
	if (!routeOptions.noScripts) {
		const tagPosition = "head";
		ssrContext.head.push({ script: Object.values(scripts).map((resource) => ({
			type: resource.module ? "module" : null,
			src: renderer.rendererContext.buildAssetsURL(resource.file),
			defer: resource.module ? null : true,
			
			
			tagPosition,
			crossorigin: ""
		})) }, headEntryOptions);
	}
	const { headTags, bodyTags, bodyTagsOpen, htmlAttrs, bodyAttrs } = await renderSSRHead(ssrContext.head, renderSSRHeadOptions);
	
	const htmlContext = {
		htmlAttrs: htmlAttrs ? [htmlAttrs] : [],
		head: normalizeChunks([headTags]),
		bodyAttrs: bodyAttrs ? [bodyAttrs] : [],
		bodyPrepend: normalizeChunks([bodyTagsOpen, ssrContext.teleports?.body]),
		body: [replaceIslandTeleports(ssrContext, _rendered.html) , APP_TELEPORT_OPEN_TAG + (HAS_APP_TELEPORTS ? joinTags([ssrContext.teleports?.[`#${appTeleportAttrs.id}`]]) : "") + APP_TELEPORT_CLOSE_TAG],
		bodyAppend: [bodyTags]
	};
	
	await nitroApp.hooks.callHook("render:html", htmlContext, { event });
	
	return {
		body: renderHTMLDocument(htmlContext),
		statusCode: getResponseStatus(event),
		statusMessage: getResponseStatusText(event),
		headers: {
			"content-type": "text/html;charset=utf-8",
			"x-powered-by": "Nuxt"
		}
	};
}
function normalizeChunks(chunks) {
	const result = [];
	for (const _chunk of chunks) {
		const chunk = _chunk?.trim();
		if (chunk) {
			result.push(chunk);
		}
	}
	return result;
}
function joinTags(tags) {
	return tags.join("");
}
function joinAttrs(chunks) {
	if (chunks.length === 0) {
		return "";
	}
	return " " + chunks.join(" ");
}
function renderHTMLDocument(html) {
	return "<!DOCTYPE html>" + `<html${joinAttrs(html.htmlAttrs)}>` + `<head>${joinTags(html.head)}</head>` + `<body${joinAttrs(html.bodyAttrs)}>${joinTags(html.bodyPrepend)}${joinTags(html.body)}${joinTags(html.bodyAppend)}</body>` + "</html>";
}

const renderer = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: handler
}, Symbol.toStringTag, { value: 'Module' }));
//# sourceMappingURL=index.mjs.map
