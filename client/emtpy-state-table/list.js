/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';

export const EmptyStateTableHeaders = [
	{
		text: 'Date',
		classNames: 'is-sorted is-left-aligned',
	},
	{
		text: 'Type',
	},
	{
		text: 'Amount',
	},
	{
		text: 'Bank account',
	},
];

export const EmptyStateList = ( props ) => {
	const [ isSubmitted, setSubmitted ] = useState( false );

	const handleSetup = () => {
		setSubmitted( true );
		wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_CLICKED, {
			// eslint-disable-next-line camelcase
			wpcom_connection: wcpaySettings.isJetpackConnected ? 'Yes' : 'No',
		} );
	};

	return (
		<div className="empty-state-deposits-list">
			<div>
				<img src={ props.listBanner } alt="" />
			</div>
			<p className="intro-copy">
				{ __(
					"Collect payments, track cash flow, and manage recurring revenue directly from your store's \
dashboard -- all without setup costs or monthly fees."
				) }
			</p>
			<p className="terms-of-service">
				{ createInterpolateElement(
					__(
						'By clicking "Finish," you agree to the <a>Terms of Service</a>',
						'woocommerce-payments'
					),
					{
						a: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a
								href="https://wordpress.com/tos/"
								target="_blank"
								rel="noreferrer"
							/>
						),
					}
				) }
			</p>
			<div>
				<Button
					isPrimary
					isBusy={ isSubmitted }
					disabled={ isSubmitted }
					onClick={ handleSetup }
					href={ wcpaySettings.connectUrl }
				>
					{ __( 'Finish setup', 'woocommerce-payments' ) }
				</Button>
			</div>
		</div>
	);
};
