import { C as ClientUnhead } from './shared/unhead.D0MR3Pv6.mjs';
import { C as CreateClientHeadOptions, b as HeadRenderer, U as Unhead } from './shared/unhead.-ZJHMNfo.mjs';
import { R as ResolvableHead } from './shared/unhead.DZtuWlyq.mjs';
import { R as RenderDomHeadOptions } from './shared/unhead.DsjxmmhV.mjs';
import 'hookable';

declare function createHead<T = ResolvableHead>(options?: CreateClientHeadOptions): ClientUnhead<T>;

declare function createDomRenderer(options?: RenderDomHeadOptions): HeadRenderer<boolean>;
/** @deprecated Use `head.render()` instead */
declare function renderDOMHead<T extends Unhead<any>>(head: T, options?: RenderDomHeadOptions): boolean;

declare function createDebouncedFn(callee: () => void, delayer: (fn: () => void) => void): () => void;

export { ClientUnhead, CreateClientHeadOptions, Unhead, createDebouncedFn, createDomRenderer, createHead, renderDOMHead };
