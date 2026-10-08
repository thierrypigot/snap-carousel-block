/**
 * Editor helpers: same rules as the PHP render.
 */
import { getBlockType } from '@wordpress/blocks';

import {
	getGapValue,
	getGaps,
	getPerView,
	getStaticTiers,
	getStyleVars,
	isContentBlockType,
} from '../../src/carousel/utils';

jest.mock( '@wordpress/blocks', () => ( { getBlockType: jest.fn() } ), {
	virtual: true,
} );

describe( 'getPerView', () => {
	it( 'returns the defaults when nothing is set', () => {
		expect( getPerView() ).toEqual( {
			desktop: 3.3,
			tablet: 2.2,
			mobile: 1.15,
		} );
	} );

	it( 'clamps between 1 and 6 and rounds to the hundredth', () => {
		expect(
			getPerView( { desktop: 9, tablet: '0.2', mobile: 2.3456 } )
		).toEqual( { desktop: 6, tablet: 1, mobile: 2.35 } );
	} );

	it( 'falls back to the default of a tier with a non-numeric value', () => {
		expect( getPerView( { desktop: 'abc' } ).desktop ).toBe( 3.3 );
	} );
} );

describe( 'getGaps', () => {
	it.each( [
		[ 3, 2 ],
		[ 3.3, 3 ],
		[ 1.15, 1 ],
		[ 1, 0 ],
	] )( '%s visible slides show %s gaps', ( perView, gaps ) => {
		expect( getGaps( perView ) ).toBe( gaps );
	} );
} );

describe( 'getStaticTiers', () => {
	const perView = { desktop: 3.3, tablet: 2.2, mobile: 1.15 };

	it( 'lists the tiers where every slide fits', () => {
		expect( getStaticTiers( perView, 3 ) ).toEqual( [ 'desktop' ] );
		expect( getStaticTiers( perView, 2 ) ).toEqual( [
			'desktop',
			'tablet',
		] );
		expect( getStaticTiers( perView, 1 ) ).toEqual( [
			'desktop',
			'tablet',
			'mobile',
		] );
		expect( getStaticTiers( perView, 7 ) ).toEqual( [] );
	} );

	it( 'returns nothing when the count is unknown (Query Loop)', () => {
		expect( getStaticTiers( perView, null ) ).toEqual( [] );
	} );
} );

describe( 'getGapValue', () => {
	it.each( [
		[ 'var:preset|spacing|md', 'var(--wp--preset--spacing--md)' ],
		[ 'var:preset|spacing|xLarge', 'var(--wp--preset--spacing--x-large)' ],
		[ 'var:preset|spacing|2xl', 'var(--wp--preset--spacing--2-xl)' ],
		[ '1.5rem', '1.5rem' ],
	] )( 'resolves %s like the PHP render', ( raw, css ) => {
		expect( getGapValue( raw ) ).toBe( css );
	} );

	it( 'ignores an empty or axial value', () => {
		expect( getGapValue( '' ) ).toBeUndefined();
		expect( getGapValue( { top: '1rem' } ) ).toBeUndefined();
	} );
} );

describe( 'getStyleVars', () => {
	it( 'exposes the tiers, the count, the fade and the gap', () => {
		expect(
			getStyleVars(
				{
					perView: { desktop: 3, tablet: 2.2, mobile: 1.15 },
					fadeSize: 'large',
					style: { spacing: { blockGap: 'var:preset|spacing|md' } },
				},
				5
			)
		).toEqual( {
			'--snap-pv-d': 3,
			'--snap-gaps-d': 2,
			'--snap-pv-t': 2.2,
			'--snap-gaps-t': 2,
			'--snap-pv-m': 1.15,
			'--snap-gaps-m': 1,
			'--snap-count': 5,
			'--snap-fade': 0.5,
			'--snap-gap': 'var(--wp--preset--spacing--md)',
		} );
	} );

	it( 'uses the medium fade and leaves the count out when unknown', () => {
		const style = getStyleVars( { fadeSize: 'huge' }, null );
		expect( style[ '--snap-fade' ] ).toBe( 0.3 );
		expect( style ).not.toHaveProperty( '--snap-count' );
		expect( style ).not.toHaveProperty( '--snap-gap' );
	} );
} );

describe( 'isContentBlockType', () => {
	const types = {
		'core/cover': { attributes: { url: { role: 'content' } } },
		'core/group': { attributes: { tagName: {} } },
		'wearewp/snap-carousel-slide': { supports: { contentRole: true } },
		'legacy/block': {
			attributes: { text: { __experimentalRole: 'content' } },
		},
	};

	beforeEach( () => {
		getBlockType.mockImplementation( ( name ) => types[ name ] );
	} );

	it.each( [
		[ 'core/cover', true ],
		[ 'wearewp/snap-carousel-slide', true ],
		[ 'legacy/block', true ],
		[ 'core/group', false ],
		[ 'unknown/block', false ],
	] )( '%s is a content block: %s', ( name, expected ) => {
		expect( isContentBlockType( name ) ).toBe( expected );
	} );
} );
