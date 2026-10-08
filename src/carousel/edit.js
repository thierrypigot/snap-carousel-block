/**
 * Snap carousel: editor component.
 */
import { __ } from '@wordpress/i18n';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

import Arrow from './arrow';
import { SLIDE_NAME, getPerView, getStaticTiers, getStyleVars } from './utils';

const SLIDE_TEMPLATE = [ 1, 2, 3 ].map( () => [
	SLIDE_NAME,
	{},
	[
		[
			'core/paragraph',
			{ placeholder: __( 'Slide content…', 'snap-carousel-block' ) },
		],
	],
] );

const TIER_LABELS = {
	desktop: __( 'Desktop', 'snap-carousel-block' ),
	tablet: __( 'Tablet', 'snap-carousel-block' ),
	mobile: __( 'Mobile', 'snap-carousel-block' ),
};

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		perView,
		fadeStart,
		fadeEnd,
		fadeSize,
		showArrows,
		arrowsPosition,
		label,
		allowedBlocks,
	} = attributes;
	const trackRef = useRef();

	const { mode, count, selectedSlideId } = useSelect(
		( select ) => {
			const {
				getBlockOrder,
				getBlockName,
				getBlockParents,
				getSelectedBlockClientId,
			} = select( blockEditorStore );
			const order = getBlockOrder( clientId );
			const firstName =
				order.length === 1 ? getBlockName( order[ 0 ] ) : null;

			let currentMode = 'blocks';
			let slides = order.length;
			if ( firstName === 'core/query' ) {
				// Posts are only known on the front end.
				currentMode = 'query';
				slides = null;
			} else if ( firstName === 'core/gallery' ) {
				currentMode = 'gallery';
				slides = getBlockOrder( order[ 0 ] ).length;
			}

			// Direct child holding the selection, to bring it into view.
			let slideId = null;
			const selected = getSelectedBlockClientId();
			if ( selected && currentMode === 'blocks' ) {
				const path = [ ...getBlockParents( selected ), selected ];
				const index = path.indexOf( clientId );
				if ( index !== -1 && index < path.length - 1 ) {
					slideId = path[ index + 1 ];
				}
			}

			return {
				mode: currentMode,
				count: slides,
				selectedSlideId: slideId,
			};
		},
		[ clientId ]
	);

	useEffect( () => {
		if ( ! selectedSlideId || ! trackRef.current ) {
			return;
		}
		trackRef.current.ownerDocument
			.getElementById( `block-${ selectedSlideId }` )
			?.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
	}, [ selectedSlideId ] );

	const values = getPerView( perView );
	const staticTiers = getStaticTiers( values, count );

	const blockProps = useBlockProps( {
		className: [
			`is-mode-${ mode }`,
			...staticTiers.map( ( tier ) => `is-static-${ tier }` ),
			fadeStart && 'has-fade-start',
			fadeEnd && 'has-fade-end',
			showArrows && 'has-arrows',
			showArrows && `has-arrows-${ arrowsPosition }`,
			'is-scrollable',
		]
			.filter( Boolean )
			.join( ' ' ),
		style: getStyleVars( attributes, count ),
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{
			ref: trackRef,
			// Query Loop and Gallery scroll their own list: the wrapper steps aside.
			className:
				mode === 'blocks'
					? 'snap-carousel__track'
					: 'snap-carousel__inner',
			'data-at-start': mode === 'blocks' ? '' : undefined,
		},
		{
			orientation: 'horizontal',
			allowedBlocks,
			template: SLIDE_TEMPLATE,
			renderAppender: InnerBlocks.ButtonBlockAppender,
		}
	);

	const scroll = ( direction ) => {
		const root = trackRef.current;
		const track =
			mode === 'blocks'
				? root
				: root?.querySelector(
						'.wp-block-post-template, .wp-block-gallery'
					);
		if ( ! track ) {
			return;
		}
		const rtl =
			track.ownerDocument.defaultView.getComputedStyle( track )
				.direction === 'rtl';
		track.scrollBy( {
			left: direction * track.clientWidth * 0.8 * ( rtl ? -1 : 1 ),
			behavior: 'smooth',
		} );
	};

	// Arrows shown below the slides come after them in the focus order too.
	const nav = showArrows && (
		<div className="snap-carousel__nav">
			<button
				type="button"
				className="snap-carousel__prev"
				onClick={ () => scroll( -1 ) }
				aria-label={ __( 'Previous slides', 'snap-carousel-block' ) }
			>
				<Arrow direction="prev" />
			</button>
			<button
				type="button"
				className="snap-carousel__next"
				onClick={ () => scroll( 1 ) }
				aria-label={ __( 'Next slides', 'snap-carousel-block' ) }
			>
				<Arrow direction="next" />
			</button>
		</div>
	);

	const setTier = ( tier, value ) =>
		setAttributes( {
			perView: { ...values, [ tier ]: value ?? values[ tier ] },
		} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Display', 'snap-carousel-block' ) }>
					{ Object.keys( TIER_LABELS ).map( ( tier ) => (
						<RangeControl
							key={ tier }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ TIER_LABELS[ tier ] }
							value={ values[ tier ] }
							onChange={ ( value ) => setTier( tier, value ) }
							min={ 1 }
							max={ 6 }
							step={ 0.05 }
						/>
					) ) }
					<p className="components-base-control__help">
						{ __(
							'Visible slides. A decimal value lets the next slide peek out. When every slide fits, the carousel becomes a plain grid.',
							'snap-carousel-block'
						) }
					</p>
				</PanelBody>
				<PanelBody
					title={ __( 'Fade', 'snap-carousel-block' ) }
					initialOpen={ false }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Fade the end while slides remain',
							'snap-carousel-block'
						) }
						checked={ fadeEnd }
						onChange={ ( value ) =>
							setAttributes( { fadeEnd: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Fade the start once scrolled',
							'snap-carousel-block'
						) }
						checked={ fadeStart }
						onChange={ ( value ) =>
							setAttributes( { fadeStart: value } )
						}
					/>
					{ ( fadeEnd || fadeStart ) && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Fade width', 'snap-carousel-block' ) }
							value={ fadeSize }
							options={ [
								{
									value: 'small',
									label: __( 'Small', 'snap-carousel-block' ),
								},
								{
									value: 'medium',
									label: __(
										'Medium',
										'snap-carousel-block'
									),
								},
								{
									value: 'large',
									label: __( 'Large', 'snap-carousel-block' ),
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { fadeSize: value } )
							}
						/>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Navigation', 'snap-carousel-block' ) }
					initialOpen={ false }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show arrows', 'snap-carousel-block' ) }
						help={ __(
							'Without arrows, a thin scrollbar stays visible.',
							'snap-carousel-block'
						) }
						checked={ showArrows }
						onChange={ ( value ) =>
							setAttributes( { showArrows: value } )
						}
					/>
					{ showArrows && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Arrows position',
								'snap-carousel-block'
							) }
							value={ arrowsPosition }
							options={ [
								{
									value: 'top-end',
									label: __(
										'Top, end',
										'snap-carousel-block'
									),
								},
								{
									value: 'bottom-end',
									label: __(
										'Bottom, end',
										'snap-carousel-block'
									),
								},
								{
									value: 'sides',
									label: __(
										'On both sides',
										'snap-carousel-block'
									),
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { arrowsPosition: value } )
							}
						/>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Accessibility', 'snap-carousel-block' ) }
					initialOpen={ false }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Carousel name', 'snap-carousel-block' ) }
						help={ __(
							'Describes the carousel for screen readers, for example: Our causes.',
							'snap-carousel-block'
						) }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				{ arrowsPosition !== 'bottom-end' && nav }
				<div { ...innerBlocksProps } />
				{ arrowsPosition === 'bottom-end' && nav }
			</section>
		</>
	);
}
