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
import {
	AffirmIcon,
	AfterpayIcon,
	ClearpayIcon,
	KlarnaIcon,
} from 'wcpay/payment-methods-icons';

const AfterpayClearpayIcon =
	window.wcpayBnplAnnouncement?.accountStatus?.country === 'GB'
		? ClearpayIcon
		: AfterpayIcon;

const Dialog = () => {
	useEffect( () => {
		recordEvent( 'wcpay_bnpl_april15_feature_announcement_view' );
	}, [] );

	const [ isHidden, setIsHidden ] = useState( false );

	if ( isHidden ) return null;

	return (
		<ConfirmationModal
			className="wcpay-bnpl-announcement"
			title={ __( 'Buy now, pay later is here', 'woocommerce-payments' ) }
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
						href="https://woo.com/document/woopayments/payment-methods/buy-now-pay-later/"
					>
						{ __( 'Learn more', 'woocommerce-payments' ) }
					</ExternalLink>
					<Button
						variant="primary"
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
				<KlarnaIcon />
				<AfterpayClearpayIcon />
				<AffirmIcon />
			</div>
			<p>
				{ __(
					'Boost conversions and give your shoppers additional buying power,',
					'woocommerce-payments'
				) }
				<br></br>
				{ __( 'with buy now, pay later — ', 'woocommerce-payments' ) }
				<br></br>
				{ __(
					'now available in your WooPayments dashboard *.',
					'woocommerce-payments'
				) }
			</p>
			<p className="wcpay-bnpl-announcement__fine-print">
				{ __(
					'* Subject to country availability',
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
