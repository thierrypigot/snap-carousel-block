<?php
/**
 * Settings helpers: visible slides, gaps, block gap value, mode.
 *
 * @package WearewpSnapCarouselBlock
 */

test( 'réglages : valeurs par défaut quand rien n’est réglé', function () {
	assert_same(
		array(
			'desktop' => 3.3,
			'tablet'  => 2.2,
			'mobile'  => 1.15,
		),
		Wearewp_Snapcarousel_Block::get_per_view( array() )
	);
} );

test( 'réglages : bornes 1 à 6, arrondi au centième, valeur non numérique ignorée', function () {
	$values = Wearewp_Snapcarousel_Block::get_per_view(
		array(
			'perView' => array(
				'desktop' => 9,
				'tablet'  => '0.2',
				'mobile'  => 'abc',
			),
		)
	);
	assert_same( 6.0, $values['desktop'] );
	assert_same( 1.0, $values['tablet'] );
	assert_same( 1.15, $values['mobile'] );
	assert_same( 2.35, Wearewp_Snapcarousel_Block::get_per_view( array( 'perView' => array( 'desktop' => 2.3456 ) ) )['desktop'] );
} );

test( 'réglages : espacements visibles (3 → 2, 3,3 → 3, 1,15 → 1)', function () {
	assert_same( 2.0, (float) Wearewp_Snapcarousel_Block::get_gaps( 3.0 ) );
	assert_same( 3.0, (float) Wearewp_Snapcarousel_Block::get_gaps( 3.3 ) );
	assert_same( 1.0, (float) Wearewp_Snapcarousel_Block::get_gaps( 1.15 ) );
	assert_same( 0.0, (float) Wearewp_Snapcarousel_Block::get_gaps( 1.0 ) );
} );

test( 'réglages : préréglage d’espacement résolu en variable CSS', function () {
	$gap = static fn( $value ) => call_private( 'get_gap', array( 'style' => array( 'spacing' => array( 'blockGap' => $value ) ) ) );
	assert_same( 'var(--wp--preset--spacing--md)', $gap( 'var:preset|spacing|md' ) );
	assert_same( 'var(--wp--preset--spacing--x-large)', $gap( 'var:preset|spacing|xLarge' ) );
	assert_same( 'var(--wp--preset--spacing--2-xl)', $gap( 'var:preset|spacing|2xl' ) );
	assert_same( '1.5rem', $gap( '1.5rem' ) );
	assert_same( 'calc(1rem + 2px)', $gap( 'calc(1rem + 2px)' ) );
} );

test( 'réglages : valeur d’espacement dangereuse rejetée', function () {
	$gap = static fn( $value ) => call_private( 'get_gap', array( 'style' => array( 'spacing' => array( 'blockGap' => $value ) ) ) );
	assert_same( '', $gap( '1rem;background:url(x)' ) );
	assert_same( '', $gap( '1rem" onmouseover="x' ) );
	assert_same( '', $gap( array( 'top' => '1rem' ) ) );
	assert_same( '', call_private( 'get_gap', array() ) );
} );

test( 'réglages : mode selon les enfants directs', function () {
	$mode = static fn( ...$names ) => Wearewp_Snapcarousel_Block::get_mode( new WP_Block( 'wearewp/snap-carousel', array_map( static fn( $n ) => new WP_Block( $n ), $names ) ) );
	assert_same( 'query', $mode( 'core/query' ) );
	assert_same( 'gallery', $mode( 'core/gallery' ) );
	assert_same( 'blocks', $mode( 'core/cover' ) );
	assert_same( 'blocks', $mode( 'core/query', 'core/cover' ) );
	assert_same( 'blocks', $mode() );
} );

test( 'réglages : taille du voile en part de diapositive, moyen par défaut', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 5 );
	assert_contains( '--snap-fade:0.3;', Wearewp_Snapcarousel_Block::render( array(), $content, $block ) );
	assert_contains( '--snap-fade:0.5;', Wearewp_Snapcarousel_Block::render( array( 'fadeSize' => 'large' ), $content, $block ) );
	assert_contains( '--snap-fade:0.3;', Wearewp_Snapcarousel_Block::render( array( 'fadeSize' => 'huge' ), $content, $block ) );
} );
