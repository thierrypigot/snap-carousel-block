<?php
/**
 * Slide resolution and decoration (process_slides).
 *
 * @package WearewpSnapCarouselBlock
 */

const WEAREWP_APPLY = array(
	'interactive' => true,
	'id'          => 'snap-carousel-t',
	'total'       => 0,
);

function wearewp_apply( $total, $interactive = true ) {
	return array_merge( WEAREWP_APPLY, compact( 'total', 'interactive' ) );
}

/*
 * Mode blocks.
 */

test( 'blocks : compte les enfants marqués, pas leurs descendants', function () {
	$html = '<div data-wearewp-snap-slide class="a"><p>1</p></div><figure data-wearewp-snap-slide><img src="x.jpg" alt=""></figure><div data-wearewp-snap-slide><div><p>3</p></div></div>';
	list( , $count ) = call_private( 'process_slides', $html, 'blocks' );
	assert_same( 3, $count );
} );

test( 'blocks : retire le marqueur et décore chaque diapositive', function () {
	$html = '<div data-wearewp-snap-slide class="a">1</div><div data-wearewp-snap-slide>2</div>';
	list( $out ) = call_private( 'process_slides', $html, 'blocks', wearewp_apply( 2 ) );
	assert_not_contains( 'data-wearewp-snap-slide', $out );
	assert_contains( 'class="a snap-carousel__slide"', $out );
	assert_contains( 'role="group"', $out );
	assert_contains( 'aria-label="1 of 2"', $out );
	assert_contains( 'aria-label="2 of 2"', $out );
	assert_contains( 'aria-roledescription="slide"', $out );
} );

test( 'blocks : grille statique, classe sans attribut ARIA', function () {
	$html = '<div data-wearewp-snap-slide>1</div>';
	list( $out ) = call_private( 'process_slides', $html, 'blocks', wearewp_apply( 1, false ) );
	assert_contains( 'class="snap-carousel__slide"', $out );
	assert_not_contains( 'role=', $out );
	assert_not_contains( 'aria-', $out );
} );

test( 'blocks : sans enfant marqué, aucune diapositive', function () {
	list( , $count ) = call_private( 'process_slides', '<p>Texte</p>', 'blocks' );
	assert_same( 0, $count );
} );

/*
 * Mode query.
 */

$wearewp_query = '<div class="wp-block-query"><ul class="wp-block-post-template">'
	. '<li class="wp-block-post post-1"><h2>Un</h2><ul><li class="wp-block-post">liste imbriquée</li></ul></li>'
	. '<li class="wp-block-post post-2"><h2>Deux</h2></li>'
	. '<li class="wp-block-post post-3"><h2>Trois</h2></li>'
	. '</ul><nav class="wp-block-query-pagination"><ul><li class="wp-block-post">hors piste</li></ul></nav></div>';

test( 'query : compte les li de premier niveau du post-template seulement', function () use ( $wearewp_query ) {
	list( , $count ) = call_private( 'process_slides', $wearewp_query, 'query' );
	assert_same( 3, $count );
} );

test( 'query : la piste est le ul, les li gardent leur rôle de liste', function () use ( $wearewp_query ) {
	list( $out ) = call_private( 'process_slides', $wearewp_query, 'query', wearewp_apply( 3 ) );
	preg_match( '/<ul [^>]*wp-block-post-template[^>]*>/', $out, $track );
	foreach ( array( 'snap-carousel__track', 'id="snap-carousel-t"', 'tabindex="0"', 'data-at-start' ) as $part ) {
		assert_contains( $part, $track[0] );
	}
	assert_contains( 'aria-label="1 of 3"', $out );
	assert_contains( 'aria-label="3 of 3"', $out );
	assert_not_contains( 'role="group"', $out, 'Un li ne doit pas perdre son rôle listitem.' );
	assert_same( 3, substr_count( $out, 'snap-carousel__slide' ) );
} );

test( 'query : sans post-template (aucun résultat), aucune diapositive', function () {
	list( , $count ) = call_private( 'process_slides', '<div class="wp-block-query"><p>Aucun résultat.</p></div>', 'query' );
	assert_same( 0, $count );
} );

/*
 * Mode gallery : piste et diapositives sont toutes deux des FIGURE.
 */

$wearewp_gallery = '<figure class="wp-block-gallery has-nested-images">'
	. '<figure class="wp-block-image"><img src="1.jpg" alt=""></figure>'
	. '<figure class="wp-block-image"><img src="2.jpg" alt=""><figcaption>Légende</figcaption></figure>'
	. '<figure class="wp-block-image"><img src="3.jpg" alt=""></figure>'
	. '<figcaption class="blocks-gallery-caption">Légende de galerie</figcaption></figure>';

test( 'gallery : compte les images (régression : 0 en 2.0.0-dev)', function () use ( $wearewp_gallery ) {
	list( , $count ) = call_private( 'process_slides', $wearewp_gallery, 'gallery' );
	assert_same( 3, $count );
} );

test( 'gallery : la piste est la galerie, les images sont des groupes', function () use ( $wearewp_gallery ) {
	list( $out ) = call_private( 'process_slides', $wearewp_gallery, 'gallery', wearewp_apply( 3 ) );
	assert_contains( 'class="wp-block-gallery has-nested-images snap-carousel__track"', $out );
	assert_same( 3, substr_count( $out, 'role="group"' ) );
	assert_contains( 'aria-label="2 of 3"', $out );
	assert_not_contains( 'blocks-gallery-caption snap-carousel__slide', $out );
} );
