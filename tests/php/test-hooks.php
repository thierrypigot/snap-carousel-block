<?php
/**
 * Detection of direct children (watch_parent and flag_slide).
 *
 * Simulates the order of WP_Block::render(): render_block_data for each
 * child (with its parent), then render_block once the child is rendered,
 * then render_block for the parent itself.
 *
 * @package WearewpSnapCarouselBlock
 */

/**
 * Resets the stack of carousels being rendered.
 */
function wearewp_reset_stack() {
	$property = new ReflectionProperty( Wearewp_Snapcarousel_Block::class, 'stack' );
	$property->setAccessible( true );
	$property->setValue( null, array() );
}

/**
 * Renders a child as core would, inside its parent.
 *
 * @param WP_Block $parent Parent block.
 * @param WP_Block $child  Child block.
 * @param string   $html   Child output.
 * @return string Filtered output.
 */
function wearewp_render_child( $parent, $child, $html ) {
	Wearewp_Snapcarousel_Block::watch_parent( array(), array(), $parent );
	return Wearewp_Snapcarousel_Block::flag_slide( $html, array(), $child );
}

test( 'hooks : init accroche le marquage en toute dernière priorité', function () {
	$GLOBALS['wearewp_filters'] = array();
	Wearewp_Snapcarousel_Block::init();
	assert_same( PHP_INT_MAX, $GLOBALS['wearewp_filters']['render_block'][0][1] );
	assert_same( 3, $GLOBALS['wearewp_filters']['render_block_data'][0][2] );
} );

test( 'hooks : marque la balise racine de chaque enfant direct', function () {
	wearewp_reset_stack();
	$a        = new WP_Block( 'core/cover' );
	$b        = new WP_Block( 'core/cover' );
	$carousel = new WP_Block( 'wearewp/snap-carousel', array( $a, $b ) );
	$out      = wearewp_render_child( $carousel, $a, '<div class="wp-block-cover"><p>1</p></div>' );
	assert_same( '<div data-wearewp-snap-slide class="wp-block-cover"><p>1</p></div>', $out );
} );

test( 'hooks : ignore les descendants qui ne sont pas des enfants directs', function () {
	wearewp_reset_stack();
	$heading  = new WP_Block( 'core/heading' );
	$cover    = new WP_Block( 'core/cover', array( $heading ) );
	$carousel = new WP_Block( 'wearewp/snap-carousel', array( $cover ) );
	Wearewp_Snapcarousel_Block::watch_parent( array(), array(), $carousel );
	Wearewp_Snapcarousel_Block::watch_parent( array(), array(), $cover );
	$out = Wearewp_Snapcarousel_Block::flag_slide( '<h3>Titre</h3>', array(), $heading );
	assert_same( '<h3>Titre</h3>', $out );
} );

test( 'hooks : ne marque rien en mode query ni gallery', function () {
	wearewp_reset_stack();
	$query    = new WP_Block( 'core/query' );
	$carousel = new WP_Block( 'wearewp/snap-carousel', array( $query ) );
	$out      = wearewp_render_child( $carousel, $query, '<div class="wp-block-query"></div>' );
	assert_not_contains( 'data-wearewp-snap-slide', $out );
} );

test( 'hooks : un enfant au rendu vide reste vide', function () {
	wearewp_reset_stack();
	$a        = new WP_Block( 'core/cover' );
	$carousel = new WP_Block( 'wearewp/snap-carousel', array( $a, new WP_Block( 'core/cover' ) ) );
	assert_same( '  ', wearewp_render_child( $carousel, $a, '  ' ) );
} );

test( 'hooks : carrousels imbriqués, chacun marque ses propres enfants', function () {
	wearewp_reset_stack();
	$inner_child = new WP_Block( 'core/cover' );
	$inner       = new WP_Block( 'wearewp/snap-carousel', array( $inner_child, new WP_Block( 'core/cover' ) ) );
	$outer       = new WP_Block( 'wearewp/snap-carousel', array( $inner, new WP_Block( 'core/cover' ) ) );

	// L'extérieur commence à rendre son premier enfant : le carrousel intérieur.
	Wearewp_Snapcarousel_Block::watch_parent( array(), array(), $outer );
	$child = wearewp_render_child( $inner, $inner_child, '<div>i</div>' );
	assert_contains( 'data-wearewp-snap-slide', $child, 'Enfant du carrousel intérieur.' );

	// Le carrousel intérieur a fini : il est dépilé, puis marqué comme enfant de l'extérieur.
	$inner_out = Wearewp_Snapcarousel_Block::flag_slide( '<section class="inner">…</section>', array(), $inner );
	assert_same( '<section data-wearewp-snap-slide class="inner">…</section>', $inner_out );
} );

test( 'hooks : hors carrousel, aucun effet', function () {
	wearewp_reset_stack();
	$out = Wearewp_Snapcarousel_Block::flag_slide( '<p>x</p>', array(), new WP_Block( 'core/paragraph' ) );
	assert_same( '<p>x</p>', $out );
} );
