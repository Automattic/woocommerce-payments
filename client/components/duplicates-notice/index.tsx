/**
 * External dependencies
 */
import React from 'react';
import InlineNotice from '../inline-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { ExternalLink, Notice } from '@wordpress/components';
import { getAdminUrl } from 'wcpay/utils';
import { useDispatch } from '@wordpress/data';

interface DuplicatesNoticeProps {
	id: string;
	dismissedNotices: string[];
	setDismissedNotices: ( notices: string[] ) => void;
}

function DuplicatesNotice( props: DuplicatesNoticeProps ): JSX.Element {
	const { id, dismissedNotices, setDismissedNotices } = props;

	const useDuplicatesDetectionDismissedNoticeState = () => {
		const { updateOptions } = useDispatch( 'wc/admin/options' );

		const setNextDismissedPaymentMethodDuplicateNotice = () => {
			const updatedDismissedNotices = [ ...dismissedNotices, id ];
			setDismissedNotices( updatedDismissedNotices );
			wcpaySettings.dismissedPaymentMethodNotices = updatedDismissedNotices;
			updateOptions( {
				wcpay_duplicate_payment_methods_notice_dismissed: updatedDismissedNotices,
			} );
		};

		return {
			handleDismissPaymentMethodDuplicateNotice: setNextDismissedPaymentMethodDuplicateNotice,
		};
	};

	const {
		handleDismissPaymentMethodDuplicateNotice,
	} = useDuplicatesDetectionDismissedNoticeState();

	return ! dismissedNotices.includes( id ) ? (
		<InlineNotice
			status="warning"
			icon={ true }
			isDismissible={ true }
			onRemove={ handleDismissPaymentMethodDuplicateNotice }
		>
			{ interpolateComponents( {
				mixedString: __(
					'This payment method is enabled by other extensions. Consider reviewing payments settings to improve the shopper experience.{{newline}}{{/newline}}{{reviewExtensions}}Review extensions{{/reviewExtensions}}.',
					'woocommerce-payments'
				),
				components: {
					newline: <br />,
					reviewExtensions: (
						<ExternalLink
							href={ getAdminUrl( {
								page: 'wc-settings',
								tab: 'checkout',
							} ) }
						/>
					),
				},
			} ) }
		</InlineNotice>
	) : (
		<></>
	);
}

export default DuplicatesNotice;
