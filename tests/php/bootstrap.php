<?php
/**
 * Test bootstrap: real WordPress HTML API, stubs for the rest.
 *
 * No PHPUnit, no database: the render logic only needs WP_HTML_Tag_Processor
 * from WordPress core (WP_CORE_DIR, default: the WordPress install around the
 * plugin) and a handful of escaping and i18n functions, stubbed here.
 *
 * @package WearewpSnapCarouselBlock
 */

define( 'ABSPATH', __DIR__ . '/' );

$wearewp_core = getenv( 'WP_CORE_DIR' ) ? getenv( 'WP_CORE_DIR' ) : dirname( __DIR__, 5 );
$wearewp_html = rtrim( $wearewp_core, '/\\' ) . '/wp-includes/html-api';
if ( ! is_dir( $wearewp_html ) ) {
	fwrite( STDERR, "WordPress introuvable : définir WP_CORE_DIR (dossier qui contient wp-includes).\n" );
	exit( 2 );
}

require_once $wearewp_core . '/wp-includes/class-wp-token-map.php';
// UTF-8 helpers used by the HTML API since WordPress 6.9.
if ( is_file( $wearewp_core . '/wp-includes/utf8.php' ) ) {
	require_once $wearewp_core . '/wp-includes/utf8.php';
}
foreach ( array( 'html5-named-character-references', 'class-wp-html-attribute-token', 'class-wp-html-span', 'class-wp-html-text-replacement', 'class-wp-html-decoder', 'class-wp-html-tag-processor' ) as $wearewp_file ) {
	require_once "{$wearewp_html}/{$wearewp_file}.php";
}

/*
 * Stubs of the WordPress functions used by the plugin and the HTML API.
 */

// Registered filters, for the hook tests.
$GLOBALS['wearewp_filters'] = array();

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['wearewp_filters'][ $hook ][] = array( $callback, $priority, $args );
	return true;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function _x( $text, $context, $domain = 'default' ) {
	return $text;
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr_x( $text, $context, $domain = 'default' ) {
	return esc_attr( $text );
}

function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function wp_unique_id( $prefix = '' ) {
	static $id = 0;
	return $prefix . ( ++$id );
}

function wp_kses_uri_attributes() {
	return array( 'action', 'cite', 'href', 'src', 'srcset', 'xmlns' );
}

function esc_url( $url ) {
	return $url;
}

function _doing_it_wrong( $function, $message, $version ) {
	throw new RuntimeException( "{$function}: {$message}" );
}

/**
 * Wrapper attributes as core builds them, minus block supports.
 *
 * @param array $extra `class` and `style`.
 * @return string Attributes.
 */
function get_block_wrapper_attributes( $extra = array() ) {
	return sprintf(
		'class="%s" style="%s"',
		esc_attr( trim( 'wp-block-wearewp-snap-carousel ' . ( $extra['class'] ?? '' ) ) ),
		esc_attr( $extra['style'] ?? '' )
	);
}

/**
 * Minimal WP_Block: the plugin only reads `name` and `inner_blocks`.
 */
class WP_Block {
	public $name;
	public $inner_blocks;

	public function __construct( $name, array $inner_blocks = array() ) {
		$this->name         = $name;
		$this->inner_blocks = $inner_blocks;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-wearewp-snapcarousel-block.php';
