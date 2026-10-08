<?php
/**
 * Plugin Name:       Snap Carousel Block
 * Description:       Accessible horizontal carousel block: every block placed inside becomes a slide, Query Loop and Gallery included. CSS scroll-snap, keyboard navigation, ARIA, zero dependency.
 * Version:           1.0.0
 * Author:            WeAre[WP]
 * Author URI:        https://www.wearewp.pro
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       snap-carousel-block
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      8.0
 *
 * @package WearewpSnapCarouselBlock
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WEAREWP_SNAPCAROUSELBLOCK_VERSION', '1.0.0' );

require_once __DIR__ . '/includes/class-wearewp-snapcarousel-block.php';

Wearewp_Snapcarousel_Block::init();

/**
 * Loads the translations bundled in /languages.
 *
 * @since 1.0.0
 */
function wearewp_snapcarouselblock_load_textdomain() {
	load_plugin_textdomain( 'snap-carousel-block', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wearewp_snapcarouselblock_load_textdomain', 0 );

/**
 * Registers the carousel and slide blocks from their build metadata.
 *
 * @since 1.0.0
 */
function wearewp_snapcarouselblock_register_blocks() {
	register_block_type( __DIR__ . '/build/carousel' );
	register_block_type( __DIR__ . '/build/slide' );

	wp_set_script_translations( 'wearewp-snap-carousel-editor-script', 'snap-carousel-block', __DIR__ . '/languages' );
}
add_action( 'init', 'wearewp_snapcarouselblock_register_blocks' );
