/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import strings from './strings';
import { sanitizeHTML } from 'utils/sanitize';

const Incentive: React.FC< NonNullable<
	typeof wcpaySettings.connectIncentive
> > = ( incentive ) => {
	return (
		<div className="connect-account-page__incentive">
			<div className="connect-account-page__incentive-pill">
				{ strings.incentive.limitedTimeOffer }
			</div>
			<h2
				// eslint-disable-next-line react/no-danger
				dangerouslySetInnerHTML={ sanitizeHTML(
					incentive.description + '*'
				) }
			/>
			<p>{ strings.incentive.details }</p>
			<p>{ strings.incentive.termsAndConditions( incentive.tc_url ) }</p>
		</div>
	);
};

export default Incentive;
