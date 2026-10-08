=== Snap Carousel Block ===
Contributors: wearewp
Tags: carousel, slider, a11y, block, scroll-snap
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accessible carousel block: any block placed inside becomes a slide. WCAG 2.2 AA, CSS scroll-snap, keyboard navigation, zero dependency.

== Description ==

Insert the **Snap carousel** block and place anything inside: every inner block becomes a slide. A single Query Loop or Gallery placed inside scrolls its posts or images.

CSS scroll-snap does the scrolling. A small JavaScript module (under 3 KB) adds the arrows, keyboard shortcuts, edge states and position announcements. Without JavaScript, the carousel still scrolls by touch, trackpad and keyboard.

= Features =

* Visible slides set per tier (desktop, tablet, mobile), decimals allowed for the peek effect: 3.3 shows 3 slides and 30 % of the next one
* Tiers follow the width of the carousel itself (container queries), not the screen: a carousel sharing a row keeps the right density. Tiers: under 480 px, 480 to 767 px, 768 px and more
* When every slide fits, the carousel becomes a plain grid: the same pattern works with 3 or 7 items
* Fade at the end (and optionally the start) while slides remain, never wider than the peek
* Arrows at the top, at the bottom or on both sides
* Transforms: Group to carousel (children become slides), Query Loop or Gallery into a carousel, and back

= Locked patterns (contentOnly) =

The carousel and the **Carousel slide** block are content blocks: inside a `contentOnly` pattern, slides can still be duplicated, moved and removed, from WordPress 7.0. Slides must be content blocks themselves (Cover, Image, Heading, Paragraph…). Wrap a slide made of groups or columns into a **Carousel slide** block. Restrict the allowed slides of a pattern with the `allowedBlocks` attribute, for example `["core/cover"]`.

= Accessibility (WCAG 2.2 AA, RGAA 4.1) =

* Region named by the "Carousel name" setting, `aria-roledescription="carousel"`
* Each slide labelled "2 of 7" (`role="group"`, list items keep their list semantics)
* Native buttons, `aria-controls`, `aria-disabled` at the edges so the focus stays on the button
* Arrow keys, Home and End when the track has the focus
* Position announced in a polite live region after an arrow action only
* No autoplay, `prefers-reduced-motion` respected
* In grid mode, no carousel semantics at all

= Theme customization =

Arrows and focus use CSS custom properties: `--snap-arrow-color`, `--snap-arrow-bg`, `--snap-arrow-size`, `--snap-focus-color`, `--snap-nav-gap`. The gap between slides is the block spacing setting.

= Limits =

* A direct child rendering several root elements (synced pattern of several blocks, Custom HTML with several elements) is not one slide: wrap it in a Carousel slide block
* Nested carousels are not supported

== Changelog ==

= 1.0.0 =
* Initial release

== Development ==

`npm run build` compiles `src/` into `build/`. `npm test` runs both suites:

* `npm run test:php`: server render (slide detection and decoration in the three modes, static grid, arrows order, escaping, settings), with the real WordPress HTML API of the surrounding install (or `WP_CORE_DIR`), without PHPUnit nor database
* `npm run test:js`: editor helpers and block transforms, with Jest

Release: add the `= x.y.z =` entry at the top of the Changelog, then `npm run release -- patch` (or `minor`, `major`, `x.y.z`; `--dry-run` to simulate). It bumps the version everywhere (header, constant, Stable tag, package.json, block.json), builds, runs the tests, commits and tags `vx.y.z`. `git push origin main --follow-tags` then triggers the GitHub workflow, which checks the versions, runs the tests and publishes the release with the install zip `snap-carousel-block-x.y.z.zip`.
