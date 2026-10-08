/**
 * ESLint: the default @wordpress/scripts config, plus two plugin settings.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,

	// Packages provided by WordPress at runtime (script dependencies built by
	// wp-scripts), not installed with npm: resolved and allowed as core modules.
	{
		settings: {
			'import/core-modules': [
				'@wordpress/block-editor',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/data',
				'@wordpress/element',
				'@wordpress/i18n',
			],
		},
	},

	// Command-line tools: run by Node, report on the console.
	{
		files: [ 'tools/**/*.mjs' ],
		languageOptions: {
			globals: { process: 'readonly' },
		},
		rules: {
			'no-console': 'off',
		},
	},

	// Front-end module: runs in the browser.
	{
		files: [ 'src/**/view.js' ],
		languageOptions: {
			globals: {
				cancelAnimationFrame: 'readonly',
				document: 'readonly',
				getComputedStyle: 'readonly',
				requestAnimationFrame: 'readonly',
				ResizeObserver: 'readonly',
				window: 'readonly',
			},
		},
	},
];
