import { C as ClientUnhead } from './shared/unhead.CqhsKMPw.js';
import { C as CreateClientHeadOptions, b as HeadRenderer, U as Unhead } from './shared/unhead.DYgm6Ycn.js';
import { R as ResolvableHead } from './shared/unhead.DZtuWlyq.js';
import { R as RenderDomHeadOptions } from './shared/unhead.2CpBSPvV.js';
import 'hookable';

declare function createHead<T = ResolvableHead>(options?: CreateClientHeadOptions): ClientUnhead<T>;

declare function createDomRenderer(options?: RenderDomHeadOptions): HeadRenderer<boolean>;
/** @deprecated Use `head.render()` instead */
declare function renderDOMHead<T extends Unhead<any>>(head: T, options?: RenderDomHeadOptions): boolean;

declare function createDebouncedFn(callee: () => void, delayer: (fn: () => void) => void): () => void;

export { ClientUnhead, CreateClientHeadOptions, Unhead, createDebouncedFn, createDomRenderer, createHead, renderDOMHead };
