import { R as RecordingEntry } from './shared/unhead.DYgm6Ycn.js';
export { h as AsVoidFunctions, m as EventHandlerOptions, w as ScriptInstance, x as ScriptScope, F as UseFunctionType, G as UseScriptContext, I as UseScriptContextOptions, J as UseScriptInput, K as UseScriptOptions, L as UseScriptResolvedInput, M as UseScriptResolver, N as UseScriptReturn, O as UseScriptScopeReturn, Q as UseScriptStatus, d as UseScriptTrigger, T as UseScriptWaitFor, V as UseScriptWaitForResolve, W as UseScriptWaitForSetup, X as WarmupStrategy } from './shared/unhead.DYgm6Ycn.js';
export { u as useScript } from './shared/unhead.BmwsgX2y.js';
import 'hookable';
import './shared/unhead.DZtuWlyq.js';

declare function createSpyProxy<T extends Record<string, any> | any[]>(target: T, onApply: (stack: RecordingEntry[][]) => void): T;

export { RecordingEntry, createSpyProxy };
