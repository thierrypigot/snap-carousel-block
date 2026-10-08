/**
 * Snap carousel: front-end behaviour.
 *
 * CSS scroll-snap does the scrolling. This module adds the arrows, keyboard
 * shortcuts, edge states (fades, disabled arrows) and position announcements.
 * Vanilla JS, zero dependency, RTL-aware.
 */

const EDGE_TOLERANCE = 2;
const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

const scrollBehavior = () => ( reducedMotion.matches ? 'auto' : 'smooth' );

/**
 * Replaces `%1$d`-style placeholders.
 *
 * @param {string}    template Translated string.
 * @param {...number} values   Values in placeholder order.
 * @return {string} Formatted string.
 */
function format( template, ...values ) {
	return template.replace(
		/%(\d)\$d/g,
		( match, index ) => values[ index - 1 ]
	);
}

function toggleAttribute( element, name, force ) {
	if ( force ) {
		element.setAttribute( name, '' );
	} else {
		element.removeAttribute( name );
	}
}

function initCarousel( root ) {
	const track = root.querySelector( '.snap-carousel__track' );
	if ( ! track ) {
		return;
	}

	const nav = root.querySelector( '.snap-carousel__nav' );
	const prev = root.querySelector( '.snap-carousel__prev' );
	const next = root.querySelector( '.snap-carousel__next' );
	const live = root.querySelector( '.snap-carousel__live' );

	let l10n = {};
	try {
		l10n = JSON.parse( root.dataset.snapL10n || '{}' );
	} catch {
		l10n = {};
	}

	let announcePending = false;

	const isRtl = () => getComputedStyle( track ).direction === 'rtl';
	const slides = () =>
		Array.from( track.children ).filter( ( child ) =>
			child.classList.contains( 'snap-carousel__slide' )
		);

	function position() {
		return {
			// RTL browsers report negative offsets: normalise to 0 = start.
			current: Math.abs( track.scrollLeft ),
			max: track.scrollWidth - track.clientWidth,
		};
	}

	function update() {
		const { current, max } = position();
		const scrollable = max > EDGE_TOLERANCE;
		const atStart = current <= EDGE_TOLERANCE;
		const atEnd = current >= max - EDGE_TOLERANCE;

		root.classList.toggle( 'is-scrollable', scrollable );
		// Focusable only when there is something to scroll.
		if ( scrollable ) {
			track.setAttribute( 'tabindex', '0' );
		} else {
			track.removeAttribute( 'tabindex' );
		}
		toggleAttribute( track, 'data-at-start', atStart );
		toggleAttribute( track, 'data-at-end', atEnd );

		// aria-disabled keeps the focus on the button at the edges.
		if ( prev ) {
			prev.setAttribute(
				'aria-disabled',
				String( ! scrollable || atStart )
			);
		}
		if ( next ) {
			next.setAttribute(
				'aria-disabled',
				String( ! scrollable || atEnd )
			);
		}
	}

	function announce() {
		if ( ! live ) {
			return;
		}
		const all = slides();
		const box = track.getBoundingClientRect();
		const visible = [];
		all.forEach( ( slide, index ) => {
			const rect = slide.getBoundingClientRect();
			if (
				rect.left >= box.left - EDGE_TOLERANCE &&
				rect.right <= box.right + EDGE_TOLERANCE
			) {
				visible.push( index + 1 );
			}
		} );
		if ( ! visible.length ) {
			return;
		}
		const first = visible[ 0 ];
		const last = visible[ visible.length - 1 ];
		live.textContent =
			first === last
				? format(
						l10n.itemOf || 'Slide %1$d of %2$d',
						first,
						all.length
					)
				: format(
						l10n.itemsOf || 'Slides %1$d to %2$d of %3$d',
						first,
						last,
						all.length
					);
	}

	function step() {
		const first = slides()[ 0 ];
		if ( ! first ) {
			return track.clientWidth;
		}
		const gap = parseFloat( getComputedStyle( track ).columnGap ) || 0;
		return first.getBoundingClientRect().width + gap;
	}

	/**
	 * Scrolls by one slide.
	 *
	 * @param {number} direction 1 for next, -1 for previous (reading order).
	 */
	function go( direction ) {
		track.scrollBy( {
			left: direction * step() * ( isRtl() ? -1 : 1 ),
			behavior: scrollBehavior(),
		} );
	}

	function onArrow( event, direction ) {
		if ( event.currentTarget.getAttribute( 'aria-disabled' ) === 'true' ) {
			return;
		}
		announcePending = true;
		go( direction );
	}

	function onScrollEnd() {
		update();
		if ( announcePending ) {
			announcePending = false;
			announce();
		}
	}

	prev?.addEventListener( 'click', ( event ) => onArrow( event, -1 ) );
	next?.addEventListener( 'click', ( event ) => onArrow( event, 1 ) );

	let frame = 0;
	track.addEventListener(
		'scroll',
		() => {
			cancelAnimationFrame( frame );
			frame = requestAnimationFrame( update );
		},
		{ passive: true }
	);

	if ( 'onscrollend' in window ) {
		track.addEventListener( 'scrollend', onScrollEnd );
	} else {
		let timer = 0;
		track.addEventListener(
			'scroll',
			() => {
				clearTimeout( timer );
				timer = setTimeout( onScrollEnd, 150 );
			},
			{ passive: true }
		);
	}

	// A link focused in the peek, under the fade, is brought fully into view.
	track.addEventListener( 'focusin', ( event ) => {
		const slide = event.target.closest( '.snap-carousel__slide' );
		if ( ! slide || event.target === track ) {
			return;
		}
		const box = track.getBoundingClientRect();
		const rect = slide.getBoundingClientRect();
		if (
			rect.left < box.left - EDGE_TOLERANCE ||
			rect.right > box.right + EDGE_TOLERANCE
		) {
			slide.scrollIntoView( {
				block: 'nearest',
				inline: 'nearest',
				behavior: scrollBehavior(),
			} );
		}
	} );

	track.addEventListener( 'keydown', ( event ) => {
		// Only when the track itself has the focus: links inside keep their keys.
		if ( event.target !== track ) {
			return;
		}
		const rtl = isRtl();
		switch ( event.key ) {
			case 'ArrowRight':
				event.preventDefault();
				go( rtl ? -1 : 1 );
				break;
			case 'ArrowLeft':
				event.preventDefault();
				go( rtl ? 1 : -1 );
				break;
			case 'Home':
				event.preventDefault();
				track.scrollTo( { left: 0, behavior: scrollBehavior() } );
				break;
			case 'End':
				event.preventDefault();
				track.scrollTo( {
					left: rtl ? -track.scrollWidth : track.scrollWidth,
					behavior: scrollBehavior(),
				} );
				break;
		}
	} );

	if ( 'ResizeObserver' in window ) {
		new ResizeObserver( update ).observe( track );
	} else {
		window.addEventListener( 'resize', update );
	}

	if ( nav ) {
		nav.hidden = false;
	}
	update();
}

document
	.querySelectorAll( '.wp-block-wearewp-snap-carousel[data-snap-carousel]' )
	.forEach( initCarousel );
