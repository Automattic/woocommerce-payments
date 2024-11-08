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
								'Deposits are now Payouts!',
								'woocommerce-payments'
							),
							descriptions: {
								desktop: createInterpolateElement(
									__(
										"Heads up! We've given Deposits a new industry approved alias: Payouts! Don't worry, it's still the same reliable function, just with a little more style. <link>Learn More.</link>",
										'woocommerce'
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
