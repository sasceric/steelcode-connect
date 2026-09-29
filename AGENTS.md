# SteelCode Connect agent rules

## Vue and Nuxt formatting

- Keep Vue templates readable and consistently multiline. Do not compress nested
  elements, component props, or event handlers into a single line.
- Never use a multi-statement inline event handler such as `@click`,
  `@update:model-value`, or `@submit`. Event handlers may call one method only;
  move all state updates and side effects into a clearly named script function.
- Put each significant component, prop group, and nested template block on its
  own line, following the existing Nuxt UI component style.
- Prefer reusable components over duplicated template markup.

## Date and time inputs

- Do not use the native `date`, `time`, or `datetime-local` input as the primary
  date selection UI.
- Reuse the product detail calendar popover pattern: `UPopover`, `UCalendar`, and
  a separate `UInput type="time"` when time is needed.

## PHP formatting

- Keep PHP files fully formatted and readable. Never compress imports, methods,
  conditionals, or class declarations onto one line.
- Use one `use` statement per line. Expand grouped imports where doing so makes
  the import list easier to scan.
- Use a blank line after `<?php`, after the namespace, and between logical
  sections of a class.
- Put class and method opening braces on their own line. Use braces for every
  conditional and loop body, including single-statement bodies.
- Expand long method signatures, arrays, chained calls, and return payloads over
  multiple lines. Keep controller actions and private helpers easy to inspect.
- Use consistent spacing around operators and after commas; do not combine
  unrelated assignments or statements with semicolons on a single line.
