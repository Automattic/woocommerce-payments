/**
 * External dependencies
 */
import React from 'react';

import { useGetPaymentMethodStatuses } from 'wcpay/data';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import CapabilityRequestList, {
	CapabilityStatus,
} from './capability-request-map';
import methodsConfiguration from '../../payment-methods-map';
import InlineNotice from 'components/inline-notice';
import { select } from '@wordpress/data';
import { STORE_NAME } from 'wcpay/data/constants';
import { Notice } from '@wordpress/components';

const CapabilityNotice = ( {
	id,
	country,
	states,
}: {
	id: string;
	country?: string;
	states: Record< string, CapabilityStatus >;
} ) => {
	const paymentMethodStatuses = useGetPaymentMethodStatuses() as Record<
		string,
		Record< string, string >
	>;
	const validStatuses: Array< string > = Object.entries(
		upeCapabilityStatuses
	).map( ( [ value ] ) => {
		return value;
	} );
	const settings = select( STORE_NAME ).getSettings() as Record<
		string,
		any
	>;

	const getStatusAndRequirements = ( itemId: string ) => {
		const stripeKey = methodsConfiguration[ itemId ].stripe_key;
		const stripeStatusContainer = paymentMethodStatuses[ stripeKey ] ?? [];
		if ( ! stripeStatusContainer ) {
			return {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [],
			};
		}
		return {
			status: stripeStatusContainer.status,
			requirements: stripeStatusContainer.requirements,
		};
	};

	const requestCapability = () => {
		// TO do
	};

	// Display the notice if the capability has status.
	if ( validStatuses.includes( getStatusAndRequirements( id ).status ) ) {
		return null;
	}

	// Skip the notice if the country doesn't match.
	if (
		typeof country !== 'undefined' &&
		settings.account_country !== country
	) {
		return null;
	}

	const noticeData = states[ getStatusAndRequirements( id ).status ] ?? null;

	// If the status data doesnt exist, hide the notice.
	if ( ! noticeData ) return null;

	const moreDetails = () => {
		if ( typeof noticeData.actionUrl !== 'undefined' ) {
			window.location.href = noticeData.actionUrl;
		}
	};

	let actions;
	if ( noticeData.actions === 'request' || noticeData.actions === 'link' ) {
		actions = [
			{
				label: noticeData.actionsLabel,
				onClick:
					noticeData.actions === 'request'
						? requestCapability
						: moreDetails,
			},
		];
	}

	return (
		<InlineNotice
			status={ noticeData.status }
			isDismissible={ true }
			//onRemove={ () => setIsDismissed( true ) }
			actions={ actions as readonly Notice.Action[] }
			className="woopayments-request-jcb"
		>
			{ noticeData.content }
		</InlineNotice>
	);
};

const CapabilityRequestNotice = (): JSX.Element => {
	return (
		<>
			{ CapabilityRequestList.map( ( request ) => (
				<CapabilityNotice
					id={ request.id }
					country={ request.country }
					states={ request.states }
					key={ request.id }
				/>
			) ) }
		</>
	);
};

export default CapabilityRequestNotice;
