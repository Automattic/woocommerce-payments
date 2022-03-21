/**
 * External dependencies
 */
import React from 'react';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */

interface RequirementGroupProps {
	icon: string;
	headline: string;
	subline?: string;
}

const RequirementGroup = ( {
	icon,
	headline,
	subline,
}: RequirementGroupProps ): JSX.Element => {
	return (
		<div>
			<p>
				<Gridicon icon={ icon } /> { headline }
			</p>
			{ subline && <pre>{ subline }</pre> }
		</div>
	);
};

export default RequirementGroup;
