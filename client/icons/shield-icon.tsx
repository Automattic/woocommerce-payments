/**
 * External dependencies
 */
import React, { HTMLAttributes } from 'react';

const ShieldIcon: React.FC< HTMLAttributes< SVGElement > > = ( props ) => {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			width="18"
			height="18"
			viewBox="0 0 18 18"
			fill="none"
			{ ...props }
		>
			<path
				fillRule="evenodd"
				clipRule="evenodd"
				d="M9 0.175781L15.75 3.24396V7.81781C15.75 11.7168 13.2458 15.4084 9.7147 16.573C9.25069 16.726 8.74931 16.726 8.2853 16.573C4.75416 15.4084 2.25 11.7168 2.25 7.81781V3.24396L9 0.175781ZM3.75 4.20983V7.81781C3.75 11.1307 5.89514 14.2052 8.75512 15.1485C8.914 15.2009 9.086 15.2009 9.24488 15.1485C12.1049 14.2052 14.25 11.1307 14.25 7.81781V4.20983L9 1.82347L3.75 4.20983Z"
				fill="white"
			/>
		</svg>
	);
};

export default ShieldIcon;
