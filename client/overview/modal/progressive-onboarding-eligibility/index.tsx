/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Button, Modal } from '@wordpress/components';
import { Icon, store, currencyDollar } from '@wordpress/icons';
import { useDispatch } from '@wordpress/data';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { trackEligibilityModalClosed } from 'onboarding/tracking';
import ConfettiAnimation from 'components/confetti-animation';
import './style.scss';

const ProgressiveOnboardingEligibilityModal: React.FC = () => {
	const [ modalVisible, setModalVisible ] = useState( true );
	const [ modalDismissed, setModalDismissed ] = useState(
		wcpaySettings.progressiveOnboarding?.isEligibilityModalDismissed
	);

	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const urlParams = new URLSearchParams( window.location.search );
	const urlSource =
		urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) || 'unknown';

	const markAsDismissed = async () => {
		setModalDismissed( true );

		// Update the option to mark the modal as dismissed.
		await updateOptions( {
			wcpay_onboarding_eligibility_modal_dismissed: true,
		} );
	};

	const handleSetup = () => {
		trackEligibilityModalClosed( 'setup_deposits', urlSource );

		// Note: we don't need to update the option here because it will be handled upon redirect to the connect URL.
		window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
			collect_payout_requirements: true,
			source: urlSource,
			from: 'WCPAY_OVERVIEW',
		} );
	};

	const handlePaymentsOnly = () => {
		trackEligibilityModalClosed( 'enable_payments_only', urlSource );
		markAsDismissed();
		setModalVisible( false );
	};

	const handleDismiss = () => {
		trackEligibilityModalClosed( 'dismiss', urlSource );
		markAsDismissed();
		setModalVisible( false );
	};

	if ( ! modalVisible || modalDismissed ) return null;

	return (
		<Modal
			title={ __(
				"You're ready to accept payments!",
				'woocommerce-payments'
			) }
			className="wcpay-progressive-onboarding-eligibility-modal"
			onRequestClose={ handleDismiss }
		>
			<ConfettiAnimation />
			<h2 className="wcpay-progressive-onboarding-eligibility-modal__subheading">
				{ interpolateComponents( {
					mixedString: sprintf(
						__(
							'Great news — your %s account has been activated. You can now start accepting payments on your store, subject to {{restrictionsLink}}certain restrictions{{/restrictionsLink}}.',
							'woocommerce-payments'
						),
						'WooPayments'
					),
					components: {
						restrictionsLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a
								rel="external noopener noreferrer"
								target="_blank"
								href="https://woocommerce.com/document/woopayments/startup-guide/gradual-signup/"
							/>
						),
					},
				} ) }
			</h2>
			<div className="wcpay-progressive-onboarding-eligibility-modal__benefits">
				<div>
					<Icon icon={ store } width={ 24 } height={ 24 } />
					<div>
						<h3 className="wcpay-progressive-onboarding-eligibility-modal__benefits__subtitle">
							{ __(
								'Start selling instantly',
								'woocommerce-payments'
							) }
						</h3>
						{ __(
							'You have 30 days from your first transaction or until you reach $5,000 in sales to verify your information and set up payouts.',
							'woocommerce-payments'
						) }
					</div>
				</div>
				<div>
					<Icon icon={ currencyDollar } width={ 24 } height={ 24 } />
					<div>
						<h3 className="wcpay-progressive-onboarding-eligibility-modal__benefits__subtitle">
							{ __(
								'Start receiving payouts',
								'woocommerce-payments'
							) }
						</h3>
						{ __(
							'Provide some additional details about your business so you can continue accepting payments and begin receiving payouts without restrictions.',
							'woocommerce-payments'
						) }
					</div>
				</div>
			</div>
			<div className="wcpay-progressive-onboarding-eligibility-modal__footer">
				<Button variant="secondary" onClick={ handleSetup }>
					{ __( 'Set up payouts', 'woocommerce-payments' ) }
				</Button>
				<Button variant="primary" onClick={ handlePaymentsOnly }>
					{ __( 'Start selling', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default ProgressiveOnboardingEligibilityModal;
