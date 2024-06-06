/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import InlineNotice from '../inline-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { getAdminUrl } from 'wcpay/utils';
import { useDispatch } from '@wordpress/data';
import { debug } from 'console';

interface DismissedDuplicateNotice {
    [key: string]: string[];
}

interface DuplicateNoticeProps {
	paymentMethod: string;
	gatewaysEnablingPaymentMethod: string[];
	dismissedDuplicateNotices: DismissedDuplicateNotice[];
	setDismissedDuplicateNotices: (notices: (string | { [key: string]: string[] })[]) => null,
}

function DuplicateNotice( {
	paymentMethod,
	gatewaysEnablingPaymentMethod,
	dismissedDuplicateNotices,
	setDismissedDuplicateNotices,
}: DuplicateNoticeProps ): JSX.Element | null {
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const handleDismiss = useCallback(() => {
		// Check if the payment method already exists in dismissedDuplicateNotices
		const existingIndex = dismissedDuplicateNotices.findIndex(notice => Object.keys(notice)[0] === paymentMethod);
	
		if (existingIndex !== -1) {
			// If it exists, update the existing entry
			const updatedNotices = [...dismissedDuplicateNotices];
			updatedNotices[existingIndex][paymentMethod] = [
				...new Set([
					...updatedNotices[existingIndex][paymentMethod],
					...gatewaysEnablingPaymentMethod
				])
			];
			setDismissedDuplicateNotices(updatedNotices);
			updateOptions({
				wcpay_duplicate_payment_method_notices_dismissed: updatedNotices,
			});
			wcpaySettings.dismissedDuplicateNotices = updatedNotices;
		} else {
			// If it doesn't exist, add a new entry
			const updatedNotices = [
				...dismissedDuplicateNotices,
				{ [paymentMethod]: gatewaysEnablingPaymentMethod }
			];
			setDismissedDuplicateNotices(updatedNotices);
			updateOptions({
				wcpay_duplicate_payment_method_notices_dismissed: updatedNotices,
			});
			wcpaySettings.dismissedDuplicateNotices = updatedNotices;
		}
	}, [
		paymentMethod,
		dismissedDuplicateNotices,
		setDismissedDuplicateNotices,
		updateOptions,
	]);
	

	if (dismissedDuplicateNotices.some(obj => Object.keys(obj).includes(paymentMethod))) {
		const duplicateNotice = dismissedDuplicateNotices.find(obj => Object.keys(obj).includes(paymentMethod));
		
		const isEqual = duplicateNotice && duplicateNotice[paymentMethod] && 
			duplicateNotice[paymentMethod].length === gatewaysEnablingPaymentMethod.length &&
			duplicateNotice[paymentMethod].every((value, index) => value === gatewaysEnablingPaymentMethod[index]);

		if ( isEqual ) {
			return null;
		}
	}


	return (
		<InlineNotice
			status="warning"
			icon={ true }
			isDismissible={ true }
			onRemove={ handleDismiss }
		>
			{ interpolateComponents( {
				mixedString: __(
					'This payment method is enabled by other extensions. {{reviewExtensions}}Review extensions{{/reviewExtensions}} to improve the shopper experience.',
					'woocommerce-payments'
				),
				components: {
					reviewExtensions: (
						<a
							href={ getAdminUrl( {
								page: 'wc-settings',
								tab: 'checkout',
							} ) }
						>
							Review extensions
						</a>
					),
				},
			} ) }
		</InlineNotice>
	);
}

export default DuplicateNotice;
