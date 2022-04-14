/* eslint-disable max-len */
/**
 * Internal dependencies
 */
import React from 'react';

const LockIcon = ( props ) => (
	<svg
		width={ 16 }
		height={ 16 }
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
		{ ...props }
	>
		<mask
			id="lock"
			style={ {
				maskType: 'alpha',
			} }
			maskUnits="userSpaceOnUse"
			x={ 2 }
			y={ 1 }
			width={ 12 }
			height={ 14 }
		>
			<path
				fillRule="evenodd"
				clipRule="evenodd"
				d="M12 5.333h-.667v-.666A3.337 3.337 0 0 0 8 1.333a3.337 3.337 0 0 0-3.333 3.334v.666H4c-.737 0-1.333.597-1.333 1.334v6.666c0 .737.596 1.334 1.333 1.334h8c.737 0 1.333-.597 1.333-1.334V6.667c0-.737-.596-1.334-1.333-1.334Zm-6-.666c0-1.103.897-2 2-2s2 .897 2 2v.666H6v-.666ZM8.667 12v-1.518A1.33 1.33 0 0 0 8 8c-.737 0-1.333.597-1.333 1.333 0 .493.27.918.666 1.149V12h1.334Z"
				fill="#fff"
			/>
		</mask>
		<g mask="url(#lock)">
			<path fill="currentColor" d="M0 0h16v16H0z" />
		</g>
	</svg>
);

export default LockIcon;
