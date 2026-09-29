import { R as RecordingEntry } from './shared/unhead.-ZJHMNfo.mjs';
export { h as AsVoidFunctions, m as EventHandlerOptions, w as ScriptInstance, x as ScriptScope, F as UseFunctionType, G as UseScriptContext, I as UseScriptContextOptions, J as UseScriptInput, K as UseScriptOptions, L as UseScriptResolvedInput, M as UseScriptResolver, N as UseScriptReturn, O as UseScriptScopeReturn, Q as UseScriptStatus, d as UseScriptTrigger, T as UseScriptWaitFor, V as UseScriptWaitForResolve, W as UseScriptWaitForSetup, X as WarmupStrategy } from './shared/unhead.-ZJHMNfo.mjs';
export { u as useScript } from './shared/unhead.DCiqWw76.mjs';
import 'hookable';
import './shared/unhead.DZtuWlyq.mjs';

declare function createSpyProxy<T extends Record<string, any> | any[]>(target: T, onApply: (stack: RecordingEntry[][]) => void): T;

export { RecordingEntry, createSpyProxy };
