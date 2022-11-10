/* eslint-disable max-len */
/**
 * Internal dependencies
 */
import React from 'react';

const PhoneIcon = ( props ) => (
	<svg
		width={ 16 }
		height={ 21 }
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
		{ ...props }
	>
		<mask
			id="phone-icon"
			style={ { maskType: 'alpha' } }
			maskUnits="userSpaceOnUse"
			x={ 4 }
			y={ 6 }
			width={ 8 }
			height={ 14 }
		>
			<path
				fillRule="evenodd"
				clipRule="evenodd"
				d="M10.667 6.333H5.333C4.597 6.333 4 6.931 4 7.667v10.666c0 .736.597 1.334 1.333 1.334h5.334c.736 0 1.333-.598 1.333-1.334V7.667c0-.736-.597-1.334-1.333-1.334ZM8.667 19H7.333v-.667h1.334V19Zm-3.334-1.333h5.334V8.333H5.333v9.334Z"
				fill="#fff"
			/>
		</mask>
		<g mask="url(#phone-icon)">
			<path fill="currentColor" d="M0 5h16v16H0z" />
		</g>
	</svg>
);

export default PhoneIcon;
