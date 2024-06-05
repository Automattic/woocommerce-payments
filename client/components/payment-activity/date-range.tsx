/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */

import './style.scss';

interface Props {
	start: moment.Moment | undefined;
	end: moment.Moment | undefined;
}

const DateRange: React.FC< Props > = ( { start, end } ) => {
	// Today - show only today's date with no end date
	if ( start?.isSame( end, 'day' ) ) {
		return <>{ start.format( 'MMMM D, YYYY' ) }</>;
	}

	// Different year - show year for both start and end
	if ( ! start?.isSame( end, 'year' ) ) {
		return (
			<>
				{ start?.format( 'MMMM D, YYYY' ) } -{ ' ' }
				{ end?.format( 'MMMM D, YYYY' ) }
			</>
		);
	}

	// Same year - show year only once
	return (
		<>
			{ start?.format( 'MMMM D' ) } - { end?.format( 'MMMM D, YYYY' ) }
		</>
	);
};

export default DateRange;
