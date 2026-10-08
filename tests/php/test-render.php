<?php
/**
 * Full server render (render).
 *
 * @package WearewpSnapCarouselBlock
 */

/**
 * Carousel in blocks mode whose children already carry the marker.
 *
 * @param int $count Slides.
 * @return array Content and block.
 */
function wearewp_blocks_carousel( $count ) {
	$children = array();
	$content  = '';
	for ( $i = 1; $i <= $count; $i++ ) {
		$children[] = new WP_Block( 'core/cover' );
		$content   .= "<div data-wearewp-snap-slide class=\"wp-block-cover\"><a href=\"#{$i}\">Lien {$i}</a></div>";
	}
	return array( $content, new WP_Block( 'wearewp/snap-carousel', $children ) );
}

test( 'render : 7 diapositives, région carrousel complète', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 7 );
	$out = Wearewp_Snapcarousel_Block::render( array( 'label' => 'Nos combats' ), $content, $block );
	assert_contains( '<section class="wp-block-wearewp-snap-carousel is-mode-blocks has-fade-end has-arrows has-arrows-top-end"', $out );
	assert_contains( 'aria-roledescription="carousel" aria-label="Nos combats"', $out );
	assert_contains( '--snap-count:7;', $out );
	assert_contains( '--snap-pv-d:3.3;--snap-gaps-d:3;', $out );
	assert_contains( 'class="snap-carousel__track" id="', $out );
	assert_contains( 'aria-label="7 of 7"', $out );
	assert_contains( '<div class="snap-carousel__nav" hidden>', $out );
	assert_contains( 'aria-live="polite"', $out );
	assert_not_contains( 'is-static-', $out );
} );

test( 'render : 3 diapositives, grille en bureau seulement', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 3 );
	$out = Wearewp_Snapcarousel_Block::render( array(), $content, $block );
	assert_contains( 'is-static-desktop', $out );
	assert_not_contains( 'is-static-tablet', $out );
	assert_contains( '<section ', $out, 'Toujours un carrousel en tablette et en mobile.' );
} );

test( 'render : 1 diapositive, grille partout, aucune sémantique de carrousel', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 1 );
	$out = Wearewp_Snapcarousel_Block::render( array( 'label' => 'X' ), $content, $block );
	assert_same( 0, strpos( $out, '<div class="wp-block-wearewp-snap-carousel' ) );
	foreach ( array( '<section', 'aria-', 'role=', 'tabindex', 'snap-carousel__nav', 'aria-live' ) as $absent ) {
		assert_not_contains( $absent, $out );
	}
} );

test( 'render : flèches en bas après la piste dans le DOM (ordre du focus)', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 5 );
	$out = Wearewp_Snapcarousel_Block::render( array( 'arrowsPosition' => 'bottom-end' ), $content, $block );
	assert_true( strpos( $out, 'snap-carousel__track' ) < strpos( $out, 'snap-carousel__nav' ) );
} );

test( 'render : flèches en haut avant la piste', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 5 );
	$out = Wearewp_Snapcarousel_Block::render( array(), $content, $block );
	assert_true( strpos( $out, 'snap-carousel__nav' ) < strpos( $out, 'snap-carousel__track' ) );
} );

test( 'render : sans flèches, ni bouton ni classe has-arrows', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 5 );
	$out = Wearewp_Snapcarousel_Block::render( array( 'showArrows' => false ), $content, $block );
	assert_not_contains( 'snap-carousel__nav', $out );
	assert_not_contains( 'has-arrows', $out );
} );

test( 'render : position de flèches inconnue ramenée à top-end', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 5 );
	$out = Wearewp_Snapcarousel_Block::render( array( 'arrowsPosition' => '"><script>' ), $content, $block );
	assert_contains( 'has-arrows-top-end', $out );
	assert_not_contains( '<script>', $out );
} );

test( 'render : le nom accessible est échappé', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 5 );
	$out = Wearewp_Snapcarousel_Block::render( array( 'label' => '"><img src=x onerror=alert(1)>' ), $content, $block );
	assert_contains( 'aria-label="&quot;&gt;&lt;img src=x onerror=alert(1)&gt;"', $out );
	assert_not_contains( '<img src=x', $out );
} );

test( 'render : nom vide remplacé par « Carousel »', function () {
	list( $content, $block ) = wearewp_blocks_carousel( 5 );
	$out = Wearewp_Snapcarousel_Block::render( array( 'label' => '   ' ), $content, $block );
	assert_contains( 'aria-label="Carousel"', $out );
} );

test( 'render : carrousel vide, rien ; contenu sans diapositive, enveloppe simple', function () {
	$empty = new WP_Block( 'wearewp/snap-carousel' );
	assert_same( '', Wearewp_Snapcarousel_Block::render( array(), '  ', $empty ) );
	$query = new WP_Block( 'wearewp/snap-carousel', array( new WP_Block( 'core/query' ) ) );
	$out   = Wearewp_Snapcarousel_Block::render( array(), '<p>Aucun résultat.</p>', $query );
	assert_contains( '<p>Aucun résultat.</p>', $out );
	assert_not_contains( '<section', $out );
} );

test( 'render : mode query, pas de piste supplémentaire autour de la boucle', function () {
	$block = new WP_Block( 'wearewp/snap-carousel', array( new WP_Block( 'core/query' ) ) );
	$html  = '<div class="wp-block-query"><ul class="wp-block-post-template"><li class="wp-block-post">1</li><li class="wp-block-post">2</li><li class="wp-block-post">3</li><li class="wp-block-post">4</li></ul></div>';
	$out   = Wearewp_Snapcarousel_Block::render( array(), $html, $block );
	assert_contains( 'is-mode-query', $out );
	assert_same( 1, substr_count( $out, 'snap-carousel__track' ) );
	assert_contains( 'aria-controls="' . preg_replace( '/.*<ul[^>]* id="([^"]+)".*/s', '$1', $out ) . '"', $out );
} );
