<?php
/**
 * Snap carousel block: slide detection and server-side rendering.
 *
 * @package WearewpSnapCarouselBlock
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the `wearewp/snap-carousel` block.
 *
 * Slides are resolved from the inner blocks:
 * - `blocks`  : every direct inner block is a slide (general case);
 * - `query`   : a single Query Loop, its `li.wp-block-post` items are the slides;
 * - `gallery` : a single Gallery, its `figure.wp-block-image` items are the slides.
 *
 * In `blocks` mode, direct children are flagged while they render (filters
 * `render_block_data` and `render_block`), so slide boundaries are known
 * without parsing nested markup.
 *
 * @since 1.0.0
 */
final class Wearewp_Snapcarousel_Block {

	/**
	 * Block name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NAME = 'wearewp/snap-carousel';

	/**
	 * Temporary attribute set on the root tag of each direct child.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MARKER = 'data-wearewp-snap-slide';

	/**
	 * Visible slides per tier when nothing is set.
	 *
	 * @since 1.0.0
	 * @var array<string, float>
	 */
	const DEFAULT_PER_VIEW = array(
		'desktop' => 3.3,
		'tablet'  => 2.2,
		'mobile'  => 1.15,
	);

	/**
	 * CSS custom property suffix per tier.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	const TIER_SUFFIX = array(
		'desktop' => 'd',
		'tablet'  => 't',
		'mobile'  => 'm',
	);

	/**
	 * Fade width per size option, as a share of a slide width.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	const FADE_SIZES = array(
		'small'  => '0.15',
		'medium' => '0.3',
		'large'  => '0.5',
	);

	/**
	 * Carousels currently rendering, innermost last.
	 *
	 * @since 1.0.0
	 * @var WP_Block[]
	 */
	private static $stack = array();

	/**
	 * Hooks the slide detection filters.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_filter( 'render_block_data', array( __CLASS__, 'watch_parent' ), 10, 3 );
		// Last: the flag must land on the outermost markup, after any wrapping filter.
		add_filter( 'render_block', array( __CLASS__, 'flag_slide' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Remembers the carousel whose children are about to render.
	 *
	 * @since 1.0.0
	 *
	 * @param array         $parsed_block Block being rendered.
	 * @param array         $source_block Original parsed block.
	 * @param WP_Block|null $parent_block Parent block instance, if any.
	 * @return array Unchanged parsed block.
	 */
	public static function watch_parent( $parsed_block, $source_block, $parent_block = null ) {
		if ( $parent_block instanceof WP_Block && self::NAME === $parent_block->name && end( self::$stack ) !== $parent_block ) {
			self::$stack[] = $parent_block;
		}
		return $parsed_block;
	}

	/**
	 * Flags the root tag of each direct child of the carousel being rendered.
	 *
	 * @since 1.0.0
	 *
	 * @param string        $block_content Rendered block.
	 * @param array         $parsed_block  Parsed block.
	 * @param WP_Block|null $instance      Block instance.
	 * @return string Block content, flagged when it is a slide.
	 */
	public static function flag_slide( $block_content, $parsed_block, $instance = null ) {
		if ( ! $instance instanceof WP_Block || empty( self::$stack ) ) {
			return $block_content;
		}

		// A carousel has finished rendering: its own children are done.
		if ( end( self::$stack ) === $instance ) {
			array_pop( self::$stack );
		}

		// Query Loop and Gallery modes find their slides inside the single child.
		$carousel = end( self::$stack );
		if ( ! $carousel || 'blocks' !== self::get_mode( $carousel ) || '' === trim( $block_content ) || ! self::is_direct_child( $carousel, $instance ) ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );
		if ( $processor->next_tag() ) {
			$processor->set_attribute( self::MARKER, true );
		}
		return $processor->get_updated_html();
	}

	/**
	 * Server-side render callback.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Rendered inner blocks.
	 * @param WP_Block $block      Block instance.
	 * @return string Carousel markup.
	 */
	public static function render( $attributes, $content, $block ) {
		$mode = self::get_mode( $block );

		list( , $count ) = self::process_slides( $content, $mode );

		if ( 0 === $count ) {
			// Nothing to scroll (empty carousel, Query Loop without results).
			return '' === trim( $content ) ? '' : sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $content );
		}

		$per_view     = self::get_per_view( $attributes );
		$static_tiers = array();
		foreach ( $per_view as $tier => $value ) {
			if ( $count <= floor( $value ) ) {
				$static_tiers[] = $tier;
			}
		}
		$interactive = count( $static_tiers ) < count( $per_view );
		$track_id    = wp_unique_id( 'snap-carousel-' );

		list( $content ) = self::process_slides(
			$content,
			$mode,
			array(
				'interactive' => $interactive,
				'id'          => $track_id,
				'total'       => $count,
			)
		);

		$classes = array( 'is-mode-' . $mode );
		foreach ( $static_tiers as $tier ) {
			$classes[] = 'is-static-' . $tier;
		}
		if ( ! empty( $attributes['fadeStart'] ) ) {
			$classes[] = 'has-fade-start';
		}
		if ( ! isset( $attributes['fadeEnd'] ) || $attributes['fadeEnd'] ) {
			$classes[] = 'has-fade-end';
		}
		$show_arrows = $interactive && ( ! isset( $attributes['showArrows'] ) || $attributes['showArrows'] );
		$position    = 'top-end';
		if ( $show_arrows ) {
			$position  = isset( $attributes['arrowsPosition'] ) && in_array( $attributes['arrowsPosition'], array( 'top-end', 'bottom-end', 'sides' ), true ) ? $attributes['arrowsPosition'] : 'top-end';
			$classes[] = 'has-arrows';
			$classes[] = 'has-arrows-' . $position;
		}

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => implode( ' ', $classes ),
				'style' => self::get_style( $attributes, $per_view, $count ),
			)
		);

		if ( 'blocks' === $mode ) {
			$track_attributes = $interactive
				? sprintf( ' id="%s" tabindex="0" data-at-start', esc_attr( $track_id ) )
				: '';
			$content          = sprintf( '<div class="snap-carousel__track"%1$s>%2$s</div>', $track_attributes, $content );
		}

		if ( ! $interactive ) {
			return sprintf( '<div %1$s>%2$s</div>', $wrapper, $content );
		}

		$label = isset( $attributes['label'] ) && '' !== trim( $attributes['label'] )
			? $attributes['label']
			: __( 'Carousel', 'snap-carousel-block' );

		$l10n = array(
			/* translators: 1: current slide number, 2: total slides. */
			'itemOf'  => __( 'Slide %1$d of %2$d', 'snap-carousel-block' ),
			/* translators: 1: first visible slide, 2: last visible slide, 3: total slides. */
			'itemsOf' => __( 'Slides %1$d to %2$d of %3$d', 'snap-carousel-block' ),
		);

		// Arrows shown below the slides come after them in the focus order too.
		$nav = $show_arrows ? self::get_nav_html( $track_id ) : '';

		return sprintf(
			'<section %1$s aria-roledescription="%2$s" aria-label="%3$s" data-snap-carousel data-snap-l10n="%4$s">%5$s%6$s%7$s<p class="snap-carousel__live snap-carousel__sr" aria-live="polite" aria-atomic="true"></p></section>',
			$wrapper,
			esc_attr_x( 'carousel', 'aria-roledescription of the carousel region', 'snap-carousel-block' ),
			esc_attr( $label ),
			esc_attr( wp_json_encode( $l10n ) ),
			'bottom-end' === $position ? '' : $nav,
			$content,
			'bottom-end' === $position ? $nav : ''
		);
	}

	/**
	 * Tells how slides are resolved from the inner blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Block $block Carousel instance.
	 * @return string `blocks`, `query` or `gallery`.
	 */
	public static function get_mode( $block ) {
		if ( 1 === count( $block->inner_blocks ) ) {
			$name = $block->inner_blocks[0]->name;
			if ( 'core/query' === $name ) {
				return 'query';
			}
			if ( 'core/gallery' === $name ) {
				return 'gallery';
			}
		}
		return 'blocks';
	}

	/**
	 * Visible slides per tier, clamped between 1 and 6.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return array<string, float> Visible slides keyed by tier.
	 */
	public static function get_per_view( $attributes ) {
		$raw    = isset( $attributes['perView'] ) && is_array( $attributes['perView'] ) ? $attributes['perView'] : array();
		$result = array();
		foreach ( self::DEFAULT_PER_VIEW as $tier => $default ) {
			$value           = isset( $raw[ $tier ] ) && is_numeric( $raw[ $tier ] ) ? (float) $raw[ $tier ] : $default;
			$result[ $tier ] = round( min( 6, max( 1, $value ) ), 2 );
		}
		return $result;
	}

	/**
	 * Number of gaps inside the visible width.
	 *
	 * 3 slides show 2 gaps; 3.3 slides show 3 gaps (the peek follows one).
	 *
	 * @since 1.0.0
	 *
	 * @param float $per_view Visible slides.
	 * @return float Gaps.
	 */
	public static function get_gaps( $per_view ) {
		return floor( $per_view ) === $per_view ? $per_view - 1 : floor( $per_view );
	}

	/**
	 * Inline custom properties of the wrapper.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @param array $per_view   Visible slides per tier.
	 * @param int   $count      Number of slides.
	 * @return string Inline style.
	 */
	private static function get_style( $attributes, $per_view, $count ) {
		$declarations = array();
		foreach ( $per_view as $tier => $value ) {
			$suffix         = self::TIER_SUFFIX[ $tier ];
			$declarations[] = sprintf( '--snap-pv-%s:%s', $suffix, $value );
			$declarations[] = sprintf( '--snap-gaps-%s:%s', $suffix, self::get_gaps( $value ) );
		}
		$declarations[] = '--snap-count:' . (int) $count;

		$fade_size      = isset( $attributes['fadeSize'], self::FADE_SIZES[ $attributes['fadeSize'] ] ) ? $attributes['fadeSize'] : 'medium';
		$declarations[] = '--snap-fade:' . self::FADE_SIZES[ $fade_size ];

		$gap = self::get_gap( $attributes );
		if ( $gap ) {
			$declarations[] = '--snap-gap:' . $gap;
		}

		return implode( ';', $declarations ) . ';';
	}

	/**
	 * Block gap as a CSS value, preset references resolved.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string CSS value, empty when unset or unsafe.
	 */
	private static function get_gap( $attributes ) {
		$gap = $attributes['style']['spacing']['blockGap'] ?? '';
		if ( ! is_string( $gap ) || '' === $gap ) {
			return '';
		}
		if ( str_starts_with( $gap, 'var:preset|spacing|' ) ) {
			$gap = 'var(--wp--preset--spacing--' . self::to_kebab_case( substr( $gap, strrpos( $gap, '|' ) + 1 ) ) . ')';
		}
		// Lengths, var() and calc() only: the value lands in a style attribute.
		return preg_match( '/^[a-z0-9.%()\-+*\/\s,]+$/i', $gap ) ? $gap : '';
	}

	/**
	 * Preset slug as WordPress writes it in CSS variables.
	 *
	 * Uses the core helper when available (private API), same cuts otherwise:
	 * `xLarge` gives `x-large`, `2xl` gives `2-xl`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Preset slug.
	 * @return string Kebab-case slug.
	 */
	private static function to_kebab_case( $slug ) {
		if ( function_exists( '_wp_to_kebab_case' ) ) {
			return _wp_to_kebab_case( $slug );
		}
		$slug = preg_replace( array( '/([a-z])([A-Z])/', '/([a-zA-Z])(\d)/', '/(\d)([a-zA-Z])/', '/[\s_]+/' ), array( '$1-$2', '$1-$2', '$1-$2', '-' ), $slug );
		return strtolower( $slug );
	}

	/**
	 * Previous and next buttons, hidden until the script takes over.
	 *
	 * @since 1.0.0
	 *
	 * @param string $track_id ID of the scrolling element.
	 * @return string Navigation markup.
	 */
	private static function get_nav_html( $track_id ) {
		$button = '<button type="button" class="snap-carousel__%1$s" aria-controls="%2$s"%3$s><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><path d="%4$s" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="snap-carousel__sr">%5$s</span></button>';

		return '<div class="snap-carousel__nav" hidden>'
			. sprintf( $button, 'prev', esc_attr( $track_id ), ' aria-disabled="true"', 'M12.5 4 6.5 10l6 6', esc_html__( 'Previous slides', 'snap-carousel-block' ) )
			. sprintf( $button, 'next', esc_attr( $track_id ), '', 'M7.5 4l6 6-6 6', esc_html__( 'Next slides', 'snap-carousel-block' ) )
			. '</div>';
	}

	/**
	 * Counts the slides, and decorates them when `$apply` is given.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $html  Rendered inner blocks.
	 * @param string     $mode  `blocks`, `query` or `gallery`.
	 * @param array|null $apply Null to count only, or `interactive`, `id` and `total`.
	 * @return array Updated HTML and slide count.
	 */
	private static function process_slides( $html, $mode, $apply = null ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$count     = 0;

		if ( 'blocks' === $mode ) {
			while ( $processor->next_tag() ) {
				if ( null === $processor->get_attribute( self::MARKER ) ) {
					continue;
				}
				++$count;
				if ( $apply ) {
					$processor->remove_attribute( self::MARKER );
					self::decorate_slide( $processor, $count, $apply );
				}
			}
			return array( $apply ? $processor->get_updated_html() : $html, $count );
		}

		// Query Loop and Gallery: the track is an element of the inner block.
		$track_tag   = 'query' === $mode ? 'UL' : 'FIGURE';
		$track_class = 'query' === $mode ? 'wp-block-post-template' : 'wp-block-gallery';
		$slide_tag   = 'query' === $mode ? 'LI' : 'FIGURE';
		$slide_class = 'query' === $mode ? 'wp-block-post' : 'wp-block-image';
		$depth       = 0;

		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $processor->get_tag();

			if ( 0 === $depth ) {
				if ( ! $processor->is_tag_closer() && $track_tag === $tag && $processor->has_class( $track_class ) ) {
					$depth = 1;
					if ( $apply ) {
						$processor->add_class( 'snap-carousel__track' );
						if ( $apply['interactive'] ) {
							$processor->set_attribute( 'id', $apply['id'] );
							$processor->set_attribute( 'tabindex', '0' );
							$processor->set_attribute( 'data-at-start', true );
						}
					}
				}
				continue;
			}

			// Before the depth update: in a Gallery, slides and track are both FIGURE.
			if ( 1 === $depth && ! $processor->is_tag_closer() && $slide_tag === $tag && $processor->has_class( $slide_class ) ) {
				++$count;
				if ( $apply ) {
					self::decorate_slide( $processor, $count, $apply, 'LI' !== $tag );
				}
			}

			if ( $track_tag === $tag ) {
				$depth += $processor->is_tag_closer() ? -1 : 1;
				if ( 0 === $depth ) {
					break;
				}
			}
		}

		return array( $apply ? $processor->get_updated_html() : $html, $count );
	}

	/**
	 * Adds the slide class and, for a live carousel, its ARIA attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_HTML_Tag_Processor $processor Processor positioned on the slide.
	 * @param int                   $index     1-based slide index.
	 * @param array                 $apply     `interactive` and `total`.
	 * @param bool                  $set_role  False for a list item, which keeps its implicit role.
	 */
	private static function decorate_slide( $processor, $index, $apply, $set_role = true ) {
		$processor->add_class( 'snap-carousel__slide' );
		if ( ! $apply['interactive'] ) {
			return;
		}
		if ( $set_role ) {
			$processor->set_attribute( 'role', 'group' );
		}
		$processor->set_attribute( 'aria-roledescription', _x( 'slide', 'aria-roledescription of a carousel slide', 'snap-carousel-block' ) );
		/* translators: 1: slide number, 2: total slides. */
		$processor->set_attribute( 'aria-label', sprintf( __( '%1$d of %2$d', 'snap-carousel-block' ), $index, $apply['total'] ) );
	}

	/**
	 * Whether a block is a direct inner block of a carousel.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Block $carousel Carousel instance.
	 * @param WP_Block $instance Block instance.
	 * @return bool
	 */
	private static function is_direct_child( $carousel, $instance ) {
		foreach ( $carousel->inner_blocks as $child ) {
			if ( $child === $instance ) {
				return true;
			}
		}
		return false;
	}
}
