/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom';
import { __ } from '@wordpress/i18n';
import { Button, ExternalLink } from '@wordpress/components';
import { recordEvent } from 'tracks';

/**
 * Internal dependencies
 */
import './style.scss';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import AfterpayBanner from 'assets/images/bnpl_announcement_afterpay.png?asset';
import ClearpayBanner from 'assets/images/bnpl_announcement_clearpay.png?asset';
import { getAdminUrl } from 'wcpay/utils';

const BannerIcon =
	window.wcpayBnplAnnouncement?.accountCountry === 'GB'
		? ClearpayBanner
		: AfterpayBanner;

const Dialog = () => {
	useEffect( () => {
		recordEvent( 'wcpay_bnpl_april15_feature_announcement_view' );
	}, [] );

	const [ isHidden, setIsHidden ] = useState( false );

	if ( isHidden ) return null;

	return (
		<ConfirmationModal
			aria={ { labelledby: 'wcpay-bnpl-announcement' } }
			className="wcpay-bnpl-announcement"
			onRequestClose={ () => setIsHidden( true ) }
			actions={
				<>
					<ExternalLink
						onClick={ () => {
							recordEvent(
								'wcpay_bnpl_april15_feature_announcement_learn_click'
							);
							setIsHidden( true );
						} }
						href="https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/"
					>
						{ __( 'Learn more', 'woocommerce-payments' ) }
					</ExternalLink>
					<Button
						variant="primary"
						href={ `${ getAdminUrl( {
							page: 'wc-settings',
							tab: 'checkout',
							section: 'woocommerce_payments',
						} ) }#payment-methods` }
						onClick={ () => {
							recordEvent(
								'wcpay_bnpl_april15_feature_announcement_enable_click'
							);
							setIsHidden( true );
						} }
					>
						{ __( 'Get started', 'woocommerce-payments' ) }
					</Button>
				</>
			}
		>
			<div className="wcpay-bnpl-announcement__payment-icons">
				<img
					src={ BannerIcon }
					alt={ __(
						'Buy now, pay later is here',
						'woocommerce-payments'
					) }
				></img>
			</div>
			<h1 id="wcpay-bnpl-announcement">
				{ __( 'Buy now, pay later is here', 'woocommerce-payments' ) }
			</h1>
			<p>
				{ __(
					// eslint-disable-next-line max-len
					'Boost conversions and give your shoppers additional buying power, with buy now, pay later — now available in your WooPayments dashboard.*',
					'woocommerce-payments'
				) }
			</p>
			<p className="wcpay-bnpl-announcement__fine-print">
				{ __(
					'*Subject to country availability',
					'woocommerce-payments'
				) }
			</p>
		</ConfirmationModal>
	);
};

const container = document.getElementById( 'wpwrap' );
if ( container ) {
	const dialogWrapper = document.createElement( 'div' );
	container.appendChild( dialogWrapper );

	ReactDOM.createRoot( dialogWrapper ).render( <Dialog /> );
}
