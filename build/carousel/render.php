<?php
/**
 * Snap carousel block: server-side render.
 *
 * @package WearewpSnapCarouselBlock
 * @since   2.0.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped by Wearewp_Snapcarousel_Block::render().
echo Wearewp_Snapcarousel_Block::render( $attributes, $content, $block );
