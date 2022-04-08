/**
 * External dependencies
 */
import React from 'react';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import './style.scss';

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
		<div className="onboarding__requirement-group">
			<Gridicon icon={ icon } size={ 18 } />
			<div className="headline">{ headline }</div>
			{ subline && <div className="subline">{ subline }</div> }
		</div>
	);
};

export default RequirementGroup;
