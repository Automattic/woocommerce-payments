/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

import { useGetPaymentMethodStatuses } from 'wcpay/data';
import { useState } from '@wordpress/element';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import methodsConfiguration from '../../payment-methods-map';
import InlineNotice from 'components/inline-notice';
import { select, useDispatch } from '@wordpress/data';
import { NAMESPACE, STORE_NAME } from 'wcpay/data/constants';
import apiFetch from '@wordpress/api-fetch';
import DismissConfirmationModal from './capability-request-dismiss-modal';
import { CapabilityNoticeProps } from './types';
import { Action } from 'wcpay/types/notices';

const CapabilityNotice = ( {
	id,
	label,
	country,
	states,
}: CapabilityNoticeProps ): JSX.Element | null => {
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const { createNotice } = useDispatch( 'core/notices' );
	const { capabilityRequestNotices } = wcpaySettings;
	const [ isDismissed, setIsDismissed ] = useState(
		capabilityRequestNotices[ id ] ?? false
	);
	const [ isDismissModalOpen, setDismissModalOpen ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( false );

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

	// Retrieve the capability status
	const stripeKey = methodsConfiguration[ id ].stripe_key ?? null;
	const stripeStatusContainer = paymentMethodStatuses[ stripeKey ] ?? [];
	const status = ! stripeStatusContainer
		? upeCapabilityStatuses.UNREQUESTED
		: stripeStatusContainer.status;

	// Display the notice if the capability has status.
	if ( validStatuses.includes( status ) ) {
		return null;
	}

	// Skip the notice if the country doesn't match.
	if (
		typeof country !== 'undefined' &&
		settings.account_country !== country
	) {
		return null;
	}

	// If the status data doesnt exist, hide the notice.
	const noticeData = states[ status ] ?? null;
	if ( ! noticeData ) return null;

	const requestCapability = async () => {
		setIsLoading( true );

		try {
			await apiFetch< string >( {
				path: `${ NAMESPACE }/settings/request-capability`,
				data: {
					id: id,
				},
				method: 'POST',
			} );

			setIsDismissed( true );

			createNotice(
				'success',
				__(
					'Capability requested successfully!',
					'woocommerce-payments'
				)
			);

			setIsLoading( false );
		} catch ( exception ) {
			createNotice(
				'error',
				__( 'Error requesting the capability!', 'woocommerce-payments' )
			);

			setIsLoading( false );
		}
	};

	const moreDetails = () => {
		if ( typeof noticeData.actionUrl !== 'undefined' ) {
			window.location.href = noticeData.actionUrl;
		}
	};

	const closeModal = () => {
		setDismissModalOpen( false );
	};

	const dismissNotice = () => {
		updateOptions( {
			wcpay_capability_request_dismissed_notices: {
				...capabilityRequestNotices,
				[ id ]: true,
			},
		} );
		wcpaySettings.capabilityRequestNotices = {
			...capabilityRequestNotices,
			[ id ]: true,
		};

		setIsDismissed( true );
	};

	const dismissModal = () => {
		if ( status === 'unrequested' || status === 'pending_verification' ) {
			setDismissModalOpen( true );
		} else {
			dismissNotice();
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
				isBusy: isLoading,
				disabled: isLoading,
			},
		];
	}

	if ( isDismissed ) {
		return null;
	}

	return (
		<>
			<InlineNotice
				status={ noticeData.status }
				isDismissible={ true }
				onRemove={ dismissModal }
				actions={ actions as readonly Action[] }
				className="woopayments-request-jcb"
			>
				{ noticeData.content }
			</InlineNotice>

			{ isDismissModalOpen && (
				<DismissConfirmationModal
					onClose={ closeModal }
					onSubmit={ dismissNotice }
					label={ label }
				/>
			) }
		</>
	);
};

export default CapabilityNotice;
