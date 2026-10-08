/**
 * Block transforms: Group to carousel, Query Loop and Gallery into a carousel,
 * ungroup back.
 */
import transforms from '../../src/carousel/transforms';

jest.mock(
	'@wordpress/blocks',
	() => ( {
		createBlock: ( name, attributes = {}, innerBlocks = [] ) => ( {
			name,
			attributes,
			innerBlocks,
		} ),
		getBlockType: ( name ) =>
			( {
				'core/cover': { attributes: { url: { role: 'content' } } },
				'core/image': { attributes: { url: { role: 'content' } } },
				'core/group': { attributes: {} },
			} )[ name ],
	} ),
	{ virtual: true }
);

const block = ( name, attributes = {}, innerBlocks = [] ) => ( {
	name,
	attributes,
	innerBlocks,
} );

const from = ( name ) =>
	transforms.from.find( ( transform ) => transform.blocks.includes( name ) );

describe( 'Group to carousel', () => {
	const result = from( 'core/group' ).transform(
		{
			align: 'wide',
			anchor: 'combats',
			className: 'is-style-foo',
			backgroundColor: 'primary',
			style: {
				spacing: { margin: { top: '2rem' }, padding: { top: '1rem' } },
			},
		},
		[ block( 'core/cover' ), block( 'core/group' ) ]
	);

	it( 'keeps alignment, anchor, class and margin', () => {
		expect( result.name ).toBe( 'wearewp/snap-carousel' );
		expect( result.attributes ).toEqual( {
			align: 'wide',
			anchor: 'combats',
			className: 'is-style-foo',
			style: { spacing: { margin: { top: '2rem' } } },
		} );
	} );

	it( 'keeps content blocks as slides and wraps the others', () => {
		expect( result.innerBlocks[ 0 ].name ).toBe( 'core/cover' );
		expect( result.innerBlocks[ 1 ].name ).toBe(
			'wearewp/snap-carousel-slide'
		);
		expect( result.innerBlocks[ 1 ].innerBlocks[ 0 ].name ).toBe(
			'core/group'
		);
	} );

	it( 'ignores a content alignment', () => {
		expect(
			from( 'core/group' ).transform( { align: 'center' }, [] ).attributes
		).toEqual( {} );
	} );
} );

describe.each( [ 'core/query', 'core/gallery' ] )(
	'%s into a carousel',
	( name ) => {
		const result = from( name ).transform(
			{
				align: 'full',
				anchor: 'actus',
				queryId: 3,
				style: {
					spacing: {
						margin: { top: '2rem' },
						padding: { top: '1rem' },
					},
				},
			},
			[ block( 'core/post-template' ) ]
		);
		const inner = result.innerBlocks[ 0 ];

		it( 'moves alignment, anchor and margin to the carousel', () => {
			expect( result.attributes ).toEqual( {
				align: 'full',
				anchor: 'actus',
				style: { spacing: { margin: { top: '2rem' } } },
			} );
		} );

		it( 'leaves the rest on the inner block, without duplicating the anchor', () => {
			expect( inner.name ).toBe( name );
			expect( inner.attributes ).toEqual( {
				queryId: 3,
				style: { spacing: { padding: { top: '1rem' } } },
			} );
			expect( inner.innerBlocks ).toHaveLength( 1 );
		} );
	}
);

describe( 'ungroup', () => {
	it( 'unwraps the slide blocks and keeps the direct slides', () => {
		const result = transforms.ungroup( {}, [
			block( 'core/cover' ),
			block( 'wearewp/snap-carousel-slide', {}, [
				block( 'core/group' ),
				block( 'core/paragraph' ),
			] ),
		] );
		expect( result.map( ( item ) => item.name ) ).toEqual( [
			'core/cover',
			'core/group',
			'core/paragraph',
		] );
	} );
} );
