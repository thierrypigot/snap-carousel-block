/**
 * Snap carousel block: registration.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import metadata from './block.json';
import Edit from './edit';
import transforms from './transforms';
import './style.scss';
import './editor.scss';

const icon = (
	<svg
		viewBox="0 0 24 24"
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
		focusable="false"
	>
		<path
			d="M3 6h12v12H3zM17 6h4v12h-4z"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
		/>
	</svg>
);

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	// Inner blocks only: the carousel markup is built by render.php.
	save: () => <InnerBlocks.Content />,
	transforms,
} );
