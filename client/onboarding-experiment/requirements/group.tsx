/**
 * External dependencies
 */
import React from 'react';
import type Gridicon from 'gridicons/dist/';

/**
 * Internal dependencies
 */
import './style.scss';

interface RequirementGroupProps {
	icon: typeof Gridicon;
	headline: string;
	subline?: string;
}

const RequirementGroup = ( {
	icon,
	headline,
	subline,
}: RequirementGroupProps ): JSX.Element => {
	return (
		<div className="onboarding__requirement-group">
			{ icon( { size: 18 } ) }
			<div className="headline">{ headline }</div>
			{ subline && <div className="subline">{ subline }</div> }
		</div>
	);
};

export default RequirementGroup;
