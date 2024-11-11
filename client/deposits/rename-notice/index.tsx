/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React, { useState, useEffect } from 'react';
import { TourKit } from '@woocommerce/components';
import { useDispatch } from '@wordpress/data';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';

export const PayoutsRenameNotice = () => {
	const isPayoutsRenameNoticeDismissed =
		wcpaySettings.isPayoutsRenameNoticeDismissed;
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ showTour, setShowTour ] = useState( false );

	const onClose = () => {
		updateOptions( {
			wcpay_payouts_rename_notice_dismissed: true,
		} );
		setShowTour( false );
		wcpaySettings.isPayoutsRenameNoticeDismissed = true;
	};

	useEffect( () => {
		if ( ! isPayoutsRenameNoticeDismissed ) {
			setShowTour( true );
		}
	}, [ isPayoutsRenameNoticeDismissed ] );

	if ( ! showTour ) return null;

	return (
		<TourKit
			config={ {
				placement: 'bottom',
				options: {
					effects: {
						overlay: false,
						autoScroll: {
							behavior: 'smooth',
						},
					},
					classNames:
						'wc-admin-payments-overview-payouts-rename-tour',
				},
				steps: [
					{
						referenceElements: {
							desktop:
								'#toplevel_page_wc-admin-path--payments-overview ul.wp-submenu li a[href*="payouts"]',
						},
						meta: {
							name: 'deposits-now-payouts',
							heading: __(
								'Deposits are now known as Payouts!',
								'woocommerce-payments'
							),
							descriptions: {
								desktop: createInterpolateElement(
									__(
										"Same reliable system for quick access to your earnings — now with a clearer name. To enhance your experience, 'Deposits' will now be called 'Payouts'. <link>Learn More.</link>",
										'woocommerce-payments'
									),
									{
										link: (
											// eslint-disable-next-line jsx-a11y/anchor-has-content
											<a
												href="https://woocommerce.com/document/woopayments/payouts/deposits-and-payouts/"
												target="_blank"
												rel="noreferrer"
											/>
										),
									}
								),
							},
						},
					},
				],
				closeHandler: onClose,
			} }
		></TourKit>
	);
};
