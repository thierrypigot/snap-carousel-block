/**
 * Snap carousel: block transforms.
 *
 * A Group becomes a carousel (its children become slides). A Query Loop or
 * a Gallery is placed inside a carousel. The carousel ungroups back.
 */
import { createBlock } from '@wordpress/blocks';

import { BLOCK_NAME, SLIDE_NAME, isContentBlockType } from './utils';

/**
 * Wraps a non-content block into a slide, so it stays editable in a locked pattern.
 *
 * @param {Object} block Block object.
 * @return {Object} Slide-ready block.
 */
function asSlide( block ) {
	return isContentBlockType( block.name )
		? block
		: createBlock( SLIDE_NAME, {}, [ block ] );
}

/**
 * Carousel attributes taken from the source block: alignment, anchor, margin.
 *
 * @param {Object} attributes Source attributes.
 * @return {Object} Carousel attributes.
 */
function carouselAttributes( attributes ) {
	const result = {};
	if ( attributes.align === 'wide' || attributes.align === 'full' ) {
		result.align = attributes.align;
	}
	if ( attributes.anchor ) {
		result.anchor = attributes.anchor;
	}
	const margin = attributes.style?.spacing?.margin;
	if ( margin ) {
		result.style = { spacing: { margin } };
	}
	return result;
}

/**
 * Source attributes left on the inner block: what moved to the carousel is removed.
 *
 * @param {Object} attributes Source attributes.
 * @return {Object} Inner block attributes.
 */
function innerAttributes( attributes ) {
	const { align, anchor, ...rest } = attributes;
	if ( rest.style?.spacing?.margin ) {
		const { margin, ...spacing } = rest.style.spacing;
		rest.style = { ...rest.style, spacing };
	}
	return rest;
}

/**
 * Transform placing a Query Loop or a Gallery inside a carousel.
 *
 * @param {string} name Block name.
 * @return {Object} Transform.
 */
function wrapInside( name ) {
	return {
		type: 'block',
		blocks: [ name ],
		transform: ( attributes, innerBlocks ) =>
			createBlock( BLOCK_NAME, carouselAttributes( attributes ), [
				createBlock( name, innerAttributes( attributes ), innerBlocks ),
			] ),
	};
}

const transforms = {
	from: [
		{
			type: 'block',
			blocks: [ 'core/group' ],
			transform: ( attributes, innerBlocks ) => {
				const result = carouselAttributes( attributes );
				if ( attributes.className ) {
					result.className = attributes.className;
				}
				return createBlock(
					BLOCK_NAME,
					result,
					innerBlocks.map( asSlide )
				);
			},
		},
		wrapInside( 'core/query' ),
		wrapInside( 'core/gallery' ),
	],
	ungroup: ( attributes, innerBlocks ) =>
		innerBlocks.flatMap( ( block ) =>
			block.name === SLIDE_NAME ? block.innerBlocks : block
		),
};

export default transforms;
