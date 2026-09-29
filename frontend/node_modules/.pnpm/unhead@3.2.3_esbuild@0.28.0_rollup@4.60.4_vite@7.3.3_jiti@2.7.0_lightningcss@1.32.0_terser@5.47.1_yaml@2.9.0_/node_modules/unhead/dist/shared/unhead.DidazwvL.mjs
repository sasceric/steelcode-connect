import { a as callHook } from './unhead.Bm4Y6XQI.mjs';

function createNoopedRecordingProxy(instance = {}) {
  const stack = [];
  let stackIdx = -1;
  const handler = (reuseStack = false) => ({
    get(_, prop, receiver) {
      if (!reuseStack) {
        const v = Reflect.get(_, prop, receiver);
        if (typeof v !== "undefined") {
          return v;
        }
        stackIdx++;
        stack[stackIdx] = [];
      }
      stack[stackIdx].push({ type: "get", key: prop });
      return new Proxy(() => {
      }, handler(true));
    },
    apply(_, __, args) {
      stack[stackIdx].push({ type: "apply", key: "", args });
      return void 0;
    }
  });
  return {
    proxy: new Proxy(instance || {}, handler()),
    stack
  };
}
function createForwardingProxy(target) {
  const handler = {
    get(_, prop, receiver) {
      const v = Reflect.get(_, prop, receiver);
      if (typeof v === "object") {
        return new Proxy(v, handler);
      }
      return v;
    },
    apply(_, __, args) {
      Reflect.apply(_, __, args);
      return void 0;
    }
  };
  return new Proxy(target, handler);
}
function replayProxyRecordings(target, stack) {
  stack.forEach((recordings) => {
    let context = target;
    let prevContext = target;
    recordings.forEach(({ type, key, args }) => {
      if (type === "get") {
        prevContext = context;
        context = context[key];
      } else if (type === "apply") {
        context = context.call(prevContext, ...args);
      }
    });
  });
}

function createScriptScope(script) {
  const controller = new AbortController();
  const disposers = /* @__PURE__ */ new Set();
  let disposed = false;
  const track = (dispose2) => {
    const trackedDispose = () => {
      if (disposers.delete(trackedDispose))
        dispose2();
    };
    if (disposed)
      dispose2();
    else
      disposers.add(trackedDispose);
    return trackedDispose;
  };
  const dispose = () => {
    if (disposed)
      return;
    disposed = true;
    controller.abort();
    let firstError;
    for (const off of [...disposers].reverse()) {
      try {
        off();
      } catch (error) {
        firstError ||= error;
      }
    }
    if (firstError)
      queueMicrotask(() => {
        throw firstError;
      });
  };
  const onScriptAbort = () => {
    controller.abort(script.signal.reason);
    queueMicrotask(() => queueMicrotask(dispose));
  };
  if (script.signal.aborted) {
    onScriptAbort();
  } else {
    script.signal.addEventListener("abort", onScriptAbort, { once: true });
    track(() => script.signal.removeEventListener("abort", onScriptAbort));
  }
  return Object.assign(Object.create(script), {
    script,
    signal: controller.signal,
    dispose,
    setupTriggerHandler(trigger) {
      if (disposed)
        return () => {
        };
      try {
        return track(script._setupTriggerHandler(trigger, false));
      } catch (error) {
        dispose();
        throw error;
      }
    },
    onLoaded(fn, options) {
      return disposed ? () => {
      } : track(script.onLoaded(fn, options));
    },
    onError(fn, options) {
      return disposed ? () => {
      } : track(script.onError(fn, options));
    }
  });
}

function createScriptWaitFor(signal) {
  return ((setup) => new Promise((outerResolve, outerReject) => {
    let settled = false;
    let resolving = false;
    let resolution;
    let cleanup;
    let onAbort;
    const finish = (settle, value) => {
      if (settled)
        return;
      settled = true;
      signal.removeEventListener("abort", onAbort);
      const currentCleanup = cleanup;
      cleanup = void 0;
      try {
        currentCleanup?.();
      } catch (error) {
        outerReject(error);
        return;
      }
      settle(value);
    };
    const reject = (reason) => queueMicrotask(() => finish(outerReject, reason));
    const resolve = (value) => {
      resolution = value;
      if (!settled && !resolving) {
        resolving = true;
        Promise.resolve(value).then(
          (resolved) => finish(outerResolve, resolved),
          reject
        );
      }
      return value;
    };
    onAbort = () => {
      const error = new Error("Script lifecycle aborted");
      error.name = "AbortError";
      reject(typeof signal.reason === "undefined" ? error : signal.reason);
    };
    if (signal.aborted) {
      onAbort();
      return;
    }
    signal.addEventListener("abort", onAbort, { once: true });
    try {
      const result = setup(resolve, reject);
      cleanup = result !== resolution && typeof result === "function" ? result : void 0;
    } catch (error) {
      reject(error);
    }
  }));
}

function useScript(head, _input, _options) {
  return _useScript(head, _input, _options, !!_options?.scope);
}
function _useScript(head, _input, _options, scoped) {
  const input = typeof _input === "string" ? { src: _input } : { ..._input };
  const {
    beforeInit,
    eventContext: _eventContext,
    resolve: resolveApi,
    scope: _scope,
    trigger,
    use,
    warmupStrategy: _warmupStrategy,
    ...entryOptions
  } = _options || {};
  const id = input.key || input.src || (typeof input.innerHTML === "string" ? input.innerHTML : "");
  const scripts = head._scripts || (head._scripts = /* @__PURE__ */ Object.create(null));
  const prevScript = Object.hasOwn(scripts, id) ? scripts[id] : void 0;
  if (prevScript) {
    const result2 = scoped ? createScriptScope(prevScript) : prevScript;
    if (scoped)
      result2.setupTriggerHandler(trigger);
    else
      prevScript._setupTriggerHandler(trigger, false);
    return result2;
  }
  const lifecycleController = new AbortController();
  const useContext = {
    signal: lifecycleController.signal,
    waitFor: createScriptWaitFor(lifecycleController.signal)
  };
  const resolveUse = () => resolveApi ? resolveApi(useContext) : use?.();
  beforeInit?.();
  let initialUseResult;
  let initialUseError;
  let initialUseFailed = false;
  try {
    initialUseResult = !head.ssr && (resolveApi || use) ? resolveUse() : void 0;
  } catch (error) {
    initialUseFailed = true;
    initialUseError = error;
  }
  const initialUseIsAsync = !!initialUseResult && typeof initialUseResult.then === "function";
  const initialInstance = initialUseIsAsync ? null : initialUseResult || null;
  const initialUseOutcome = initialUseFailed ? Promise.resolve([false, initialUseError]) : initialUseIsAsync ? Promise.resolve(initialUseResult).then(
    (api) => [true, api],
    (error) => [false, error]
  ) : void 0;
  const _events = [];
  let loadError;
  const syncStatus = (s) => {
    script.status = s;
    _events.push({ type: s, timestamp: Date.now() });
    callHook(head, "script:updated", hookCtx);
  };
  const failReadiness = (reason) => {
    loadError = reason instanceof Error ? reason : new Error(String(reason));
    lifecycleController.abort(loadError);
    syncStatus("error");
  };
  let onload = typeof input.onload === "function" ? input.onload.bind(_eventContext) : null;
  let onerror = typeof input.onerror === "function" ? input.onerror.bind(_eventContext) : null;
  const releaseEventHandlers = () => {
    onload = null;
    onerror = null;
  };
  input.onload = (e) => {
    if (lifecycleController.signal.aborted)
      return;
    try {
      syncStatus("loaded");
      onload?.(e);
    } finally {
      releaseEventHandlers();
    }
  };
  input.onerror = (e) => {
    if (lifecycleController.signal.aborted)
      return;
    try {
      lifecycleController.abort();
      syncStatus("error");
      onerror?.(e);
    } finally {
      releaseEventHandlers();
    }
  };
  const _cbs = { loaded: [], error: [] };
  const _uniqueCbs = /* @__PURE__ */ new Set();
  const callCbs = (cbs, value) => cbs?.forEach((cb) => {
    try {
      void Promise.resolve(cb(value)).catch((error) => console.error(error));
    } catch (error) {
      console.error(error);
    }
  });
  const _registerCb = (key, cb, options) => {
    if (head.ssr) {
      return () => {
      };
    }
    let uniqueKey;
    if (options?.key) {
      uniqueKey = `${key}:${options.key}`;
      if (_uniqueCbs.has(uniqueKey)) {
        return () => {
        };
      }
      _uniqueCbs.add(uniqueKey);
    }
    if (_cbs[key]) {
      _cbs[key].push(cb);
      return () => {
        const idx = _cbs[key]?.indexOf(cb) ?? -1;
        if (idx !== -1)
          _cbs[key]?.splice(idx, 1);
        if (uniqueKey)
          _uniqueCbs.delete(uniqueKey);
      };
    }
    if (key === "loaded" && script.status === "loaded")
      cb(script.instance);
    else if (key === "error" && script.status === "error")
      cb(loadError);
    return () => {
      if (uniqueKey)
        _uniqueCbs.delete(uniqueKey);
    };
  };
  const loadPromise = new Promise((resolve) => {
    if (head.ssr)
      return;
    const emit = (api) => queueMicrotask(() => {
      if (lifecycleController.signal.aborted || script.status === "removed")
        resolve(false);
      else
        resolve(api);
    });
    let resolvingApi = false;
    const unhook = head.hooks?.hook("script:updated", ({ script: updatedScript }) => {
      if (updatedScript !== script)
        return;
      const status = updatedScript.status;
      if (status === "loaded" || status === "error" || status === "removed") {
        if (status === "loaded") {
          if (resolvingApi)
            return;
          resolvingApi = true;
          if (!resolveApi && !use) {
            emit({});
            unhook?.();
            return;
          }
          const useOutcome = initialUseOutcome || (() => {
            try {
              return Promise.resolve(resolveUse()).then(
                (api) => [true, api],
                (error) => [false, error]
              );
            } catch (error) {
              return Promise.resolve([false, error]);
            }
          })();
          void useOutcome.then((outcome) => {
            if (lifecycleController.signal.aborted || updatedScript.status === "removed")
              return;
            if (!outcome[0]) {
              failReadiness(outcome[1]);
            } else if (outcome[1]) {
              emit(outcome[1]);
              unhook?.();
            } else {
              failReadiness(new Error("use() resolved without a script API"));
            }
          });
        } else {
          resolve(false);
          unhook?.();
        }
      }
    });
  });
  const script = {
    _loadPromise: loadPromise,
    _events,
    _warmupStrategy: void 0,
    instance: initialInstance,
    proxy: null,
    id,
    signal: lifecycleController.signal,
    src: input.src,
    input,
    status: "awaitingLoad",
    remove() {
      const hadEntry = !!script.entry;
      lifecycleController.abort();
      releaseEventHandlers();
      script._triggerAbortControllers?.forEach((ac) => ac.abort());
      script._triggerAbortControllers?.clear();
      script._triggerPromises = [];
      script._warmupEl?.dispose();
      script._warmupEl = void 0;
      if (script.entry) {
        script.entry.dispose();
        script.entry = void 0;
      }
      if (scripts[id] === script)
        delete scripts[id];
      if (script.status !== "removed")
        syncStatus("removed");
      return hadEntry;
    },
    warmup(rel) {
      const { src } = input;
      const isCrossOrigin = !src.startsWith("/") || src.startsWith("//");
      const isPreconnect = rel === "preconnect" || rel === "dns-prefetch";
      let href = src;
      if (!rel || isPreconnect && !isCrossOrigin) {
        return;
      }
      if (isPreconnect) {
        const $url = new URL(src);
        href = `${$url.protocol}//${$url.host}`;
      }
      const link = {
        href,
        rel,
        crossorigin: typeof input.crossorigin !== "undefined" ? input.crossorigin : isCrossOrigin ? "anonymous" : void 0,
        referrerpolicy: typeof input.referrerpolicy !== "undefined" ? input.referrerpolicy : isCrossOrigin ? "no-referrer" : void 0,
        fetchpriority: typeof input.fetchpriority !== "undefined" ? input.fetchpriority : "low",
        integrity: input.integrity,
        as: rel === "preload" ? "script" : void 0
      };
      script._warmupEl = head.push({ link: [link] }, { head, tagPriority: "high" });
      return script._warmupEl;
    },
    load(cb) {
      if (script.status === "removed")
        return loadPromise;
      script._triggerAbortControllers?.forEach((ac) => ac.abort());
      script._triggerAbortControllers?.clear();
      script._triggerPromises = [];
      if (!script.entry) {
        syncStatus("loading");
        const defaults = {
          defer: true,
          fetchpriority: "low"
        };
        if (input.src && (input.src.startsWith("http") || input.src.startsWith("//"))) {
          defaults.crossorigin = "anonymous";
          defaults.referrerpolicy = "no-referrer";
        }
        script.entry = head.push({
          script: [{ ...defaults, ...input }]
        }, entryOptions);
      }
      if (cb)
        _registerCb("loaded", cb);
      return loadPromise;
    },
    onLoaded(cb, options) {
      return _registerCb("loaded", cb, options);
    },
    onError(cb, options) {
      return _registerCb("error", cb, options);
    },
    setupTriggerHandler(trigger2) {
      return script._setupTriggerHandler(trigger2);
    },
    _setupTriggerHandler(trigger2, removeOnError = true) {
      const noop = () => {
      };
      if (script.status !== "awaitingLoad") {
        return noop;
      }
      if ((typeof trigger2 === "undefined" || trigger2 === "client") && !head.ssr || trigger2 === "server") {
        script.load();
        return noop;
      } else if (trigger2 instanceof Promise) {
        if (head.ssr) {
          return noop;
        }
        const abortController = new AbortController();
        script._triggerAbortControllers = script._triggerAbortControllers || /* @__PURE__ */ new Set();
        script._triggerAbortControllers.add(abortController);
        const abortPromise = new Promise((resolve) => {
          abortController.signal.addEventListener("abort", () => {
            script._triggerAbortControllers?.delete(abortController);
            resolve();
          });
        });
        script._triggerAbortController = abortController;
        script._triggerPromises = script._triggerPromises || [];
        const triggerPromise = Promise.race([
          trigger2.then((v) => typeof v === "undefined" || v ? script.load : void 0),
          abortPromise
        ]).catch((error) => {
        }).then((res) => {
          res?.();
        }).finally(() => {
          script._triggerAbortControllers?.delete(abortController);
          const idx = script._triggerPromises?.indexOf(triggerPromise) ?? -1;
          if (idx !== -1)
            script._triggerPromises?.splice(idx, 1);
        });
        script._triggerPromises.push(triggerPromise);
        return () => abortController.abort();
      } else if (typeof trigger2 === "function") {
        if (head.ssr) {
          return noop;
        }
        const abortController = new AbortController();
        script._triggerAbortControllers = script._triggerAbortControllers || /* @__PURE__ */ new Set();
        script._triggerAbortControllers.add(abortController);
        script._triggerAbortController = abortController;
        let cleanup;
        abortController.signal.addEventListener("abort", () => {
          script._triggerAbortControllers?.delete(abortController);
          if (typeof cleanup === "function")
            cleanup();
          cleanup = void 0;
        }, { once: true });
        try {
          cleanup = trigger2(script.load);
          if (abortController.signal.aborted) {
            if (typeof cleanup === "function")
              cleanup();
            cleanup = void 0;
          }
        } catch (error) {
          abortController.abort();
          if (removeOnError)
            script.remove();
          throw error;
        }
        return () => abortController.abort();
      }
      return noop;
    },
    _cbs
  };
  loadPromise.then((api) => {
    if (api !== false) {
      script.instance = api;
      const cbs = _cbs.loaded;
      _cbs.loaded = null;
      _cbs.error = null;
      callCbs(cbs, api);
    } else {
      const cbs = script.status === "error" ? _cbs.error : null;
      _cbs.loaded = null;
      _cbs.error = null;
      callCbs(cbs, loadError);
    }
  });
  const hookCtx = { script };
  const result = scoped ? createScriptScope(script) : script;
  try {
    result.setupTriggerHandler(trigger);
  } catch (error) {
    script.remove();
    throw error;
  }
  if (resolveApi || use) {
    const { proxy, stack } = createNoopedRecordingProxy(head.ssr ? {} : initialInstance || {});
    script.proxy = proxy;
    script.onLoaded((instance) => {
      replayProxyRecordings(instance, stack);
      script.proxy = createForwardingProxy(instance);
    });
  }
  const warmupStrategy = _warmupStrategy || (typeof trigger === "undefined" || trigger === "client" ? "preload" : false);
  if (warmupStrategy) {
    script._warmupStrategy = warmupStrategy;
    script.warmup(warmupStrategy);
  }
  scripts[id] = script;
  return result;
}

export { useScript as u };
