/**
 * External dependencies
 */
import React from 'react';

import CapabilityRequestList from './capability-request-map';
import CapabilityNotice from './capability-request-notice';

const CapabilityRequestNotice = (): JSX.Element => {
	return (
		<>
			{ CapabilityRequestList.map( ( request ) => (
				<CapabilityNotice
					id={ request.id }
					label={ request.label }
					country={ request.country }
					states={ request.states }
					key={ request.id }
				/>
			) ) }
		</>
	);
};

export default CapabilityRequestNotice;
