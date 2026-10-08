/**
 * Helpers shared by the editor: mirror the PHP rendering rules.
 */
import { getBlockType } from '@wordpress/blocks';

export const BLOCK_NAME = 'wearewp/snap-carousel';
export const SLIDE_NAME = 'wearewp/snap-carousel-slide';

export const TIERS = [ 'desktop', 'tablet', 'mobile' ];
const TIER_SUFFIX = { desktop: 'd', tablet: 't', mobile: 'm' };
const DEFAULT_PER_VIEW = { desktop: 3.3, tablet: 2.2, mobile: 1.15 };
// Fade width as a share of a slide: medium covers a 0.3 peek.
export const FADE_SIZES = { small: 0.15, medium: 0.3, large: 0.5 };

/**
 * Visible slides per tier, clamped between 1 and 6.
 *
 * @param {Object} perView Raw attribute.
 * @return {Object} Visible slides keyed by tier.
 */
export function getPerView( perView = {} ) {
	const result = {};
	TIERS.forEach( ( tier ) => {
		const value = parseFloat( perView[ tier ] );
		const safe = Number.isFinite( value )
			? value
			: DEFAULT_PER_VIEW[ tier ];
		result[ tier ] =
			Math.round( Math.min( 6, Math.max( 1, safe ) ) * 100 ) / 100;
	} );
	return result;
}

/**
 * Gaps inside the visible width: 3 slides show 2 gaps, 3.3 slides show 3.
 *
 * @param {number} perView Visible slides.
 * @return {number} Gaps.
 */
export function getGaps( perView ) {
	return Number.isInteger( perView ) ? perView - 1 : Math.floor( perView );
}

/**
 * Tiers where every slide fits: the carousel becomes a plain grid there.
 *
 * @param {Object}      perView Visible slides per tier.
 * @param {number|null} count   Number of slides, null when unknown.
 * @return {string[]} Static tiers.
 */
export function getStaticTiers( perView, count ) {
	if ( ! count ) {
		return [];
	}
	return TIERS.filter( ( tier ) => count <= Math.floor( perView[ tier ] ) );
}

/**
 * Block gap as a CSS value, preset references resolved.
 *
 * @param {string|undefined} gap Raw `style.spacing.blockGap`.
 * @return {string|undefined} CSS value.
 */
export function getGapValue( gap ) {
	if ( typeof gap !== 'string' || ! gap ) {
		return undefined;
	}
	if ( gap.startsWith( 'var:preset|spacing|' ) ) {
		return `var(--wp--preset--spacing--${ toKebabCase(
			gap.slice( gap.lastIndexOf( '|' ) + 1 )
		) })`;
	}
	return gap;
}

/**
 * Preset slug as WordPress writes it in CSS variables: `xLarge` gives
 * `x-large`, `2xl` gives `2-xl` (same cuts as `_wp_to_kebab_case()`).
 *
 * @param {string} slug Preset slug.
 * @return {string} Kebab-case slug.
 */
function toKebabCase( slug ) {
	return slug
		.replace( /([a-z])([A-Z])/g, '$1-$2' )
		.replace( /([a-zA-Z])(\d)/g, '$1-$2' )
		.replace( /(\d)([a-zA-Z])/g, '$1-$2' )
		.replace( /[\s_]+/g, '-' )
		.toLowerCase();
}

/**
 * Inline custom properties of the wrapper.
 *
 * @param {Object}      attributes Block attributes.
 * @param {number|null} count      Number of slides.
 * @return {Object} Style object.
 */
export function getStyleVars( attributes, count ) {
	const perView = getPerView( attributes.perView );
	const style = {};
	TIERS.forEach( ( tier ) => {
		style[ `--snap-pv-${ TIER_SUFFIX[ tier ] }` ] = perView[ tier ];
		style[ `--snap-gaps-${ TIER_SUFFIX[ tier ] }` ] = getGaps(
			perView[ tier ]
		);
	} );
	if ( count ) {
		style[ '--snap-count' ] = count;
	}
	style[ '--snap-fade' ] =
		FADE_SIZES[ attributes.fadeSize ] || FADE_SIZES.medium;
	const gap = getGapValue( attributes.style?.spacing?.blockGap );
	if ( gap ) {
		style[ '--snap-gap' ] = gap;
	}
	return style;
}

/**
 * Whether a block type counts as content in a `contentOnly` pattern.
 *
 * Mirrors the block editor rule: only content blocks can be inserted,
 * duplicated or removed inside a locked pattern.
 *
 * @param {string} name Block name.
 * @return {boolean} True for a content block.
 */
export function isContentBlockType( name ) {
	const type = getBlockType( name );
	if ( ! type ) {
		return false;
	}
	if ( type.supports?.contentRole ) {
		return true;
	}
	return Object.values( type.attributes || {} ).some(
		( attribute ) =>
			attribute?.role === 'content' ||
			attribute?.__experimentalRole === 'content'
	);
}
