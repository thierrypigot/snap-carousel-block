/**
 * Carousel slide block: wraps any blocks into one slide.
 *
 * Declared as content (`contentRole`), so slides can be added, duplicated
 * and removed inside a `contentOnly` pattern.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

import metadata from './block.json';
import './style.scss';

const icon = (
	<svg
		viewBox="0 0 24 24"
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
		focusable="false"
	>
		<path
			d="M6 5h12v14H6z"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
		/>
	</svg>
);

function Edit() {
	return <div { ...useInnerBlocksProps( useBlockProps() ) } />;
}

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save: () => <div { ...useInnerBlocksProps.save( useBlockProps.save() ) } />,
} );
