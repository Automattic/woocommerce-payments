/**
 * External dependencies
 */
import * as React from 'react';
import moment from 'moment';

/**
 * Internal dependencies
 */

import './style.scss';

interface Props {
	start: moment.Moment | undefined;
	end: moment.Moment | undefined;
}

const DateRange: React.FC< Props > = ( { start, end } ) => {
	return (
		<>
			{ start }-{ end }
		</>
	);
};

export default DateRange;
