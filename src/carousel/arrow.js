/**
 * Chevron of the navigation buttons, same path as the PHP render.
 *
 * @param {Object} props
 * @param {string} props.direction `prev` or `next`.
 */
export default function Arrow( { direction } ) {
	return (
		<svg
			viewBox="0 0 20 20"
			width="20"
			height="20"
			aria-hidden="true"
			focusable="false"
		>
			<path
				d={
					direction === 'prev'
						? 'M12.5 4 6.5 10l6 6'
						: 'M7.5 4l6 6-6 6'
				}
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
}
