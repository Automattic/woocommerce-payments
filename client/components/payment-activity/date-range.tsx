/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */

import './style.scss';

interface Props {
	start: string;
	end: string;
}

const DateRange: React.FC< Props > = ( { start, end } ) => {
	return (
		<>
			{ start }-{ end }
		</>
	);
};

export default DateRange;
