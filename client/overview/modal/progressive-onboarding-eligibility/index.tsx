/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Button, Modal } from '@wordpress/components';
import { Icon, store, widget, tool } from '@wordpress/icons';
import { useDispatch } from '@wordpress/data';

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

	const markAsDismissed = async () => {
		setModalDismissed( true );

		// Update the option to mark the modal as dismissed.
		await updateOptions( {
			wcpay_onboarding_eligibility_modal_dismissed: true,
		} );
	};

	const handleSetup = () => {
		trackEligibilityModalClosed( 'setup_deposits' );

		// Note: we don't need to update the option here because it will be handled upon redirect to the connect URL.
		window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
			collect_payout_requirements: true,
		} );
	};

	const handlePaymentsOnly = () => {
		trackEligibilityModalClosed( 'enable_payments_only' );
		markAsDismissed();
		setModalVisible( false );
	};

	const handleDismiss = () => {
		trackEligibilityModalClosed( 'dismiss' );
		markAsDismissed();
		setModalVisible( false );
	};

	// Workaround to remove Modal header from the modal until `hideHeader` prop can be used.
	useEffect( () => {
		document
			.querySelector(
				'.wcpay-progressive-onboarding-eligibility-modal .components-modal__header-heading-container'
			)
			?.remove();
	}, [] );

	if ( ! modalVisible || modalDismissed ) return null;

	return (
		<Modal
			title={ '' }
			className="wcpay-progressive-onboarding-eligibility-modal"
			onRequestClose={ handleDismiss }
		>
			<ConfettiAnimation />
			<h1 className="wcpay-progressive-onboarding-eligibility-modal__heading">
				{ __( 'You’re ready to sell.', 'woocommerce-payments' ) }
			</h1>
			<h2 className="wcpay-progressive-onboarding-eligibility-modal__subheading">
				{ __(
					'Start selling now and fast track the setup process, or continue the process to set up deposits with WooPayments.',
					'woocommerce-payments'
				) }
			</h2>
			<div className="wcpay-progressive-onboarding-eligibility-modal__benefits">
				<div>
					<Icon icon={ store } size={ 32 } />
					<h3 className="wcpay-progressive-onboarding-eligibility-modal__benefits__subtitle">
						{ __(
							'Start selling instantly',
							'woocommerce-payments'
						) }
					</h3>
					{ sprintf(
						/* translators: %s: WooPayments */
						__(
							'%s enables you to start processing credit card payments right away.',
							'woocommerce-payments'
						),
						'WooPayments'
					) }
				</div>
				<div>
					<Icon icon={ widget } size={ 32 } />
					<h3 className="wcpay-progressive-onboarding-eligibility-modal__benefits__subtitle">
						{ __( 'Quick and easy setup', 'woocommerce-payments' ) }
					</h3>
					{ __(
						'The setup process is super simple and ensures your store is ready to accept card payments.',
						'woocommerce-payments'
					) }
				</div>
				<div>
					<Icon icon={ tool } size={ 32 } />
					<h3 className="wcpay-progressive-onboarding-eligibility-modal__benefits__subtitle">
						{ __( 'Flexible process', 'woocommerce-payments' ) }
					</h3>
					{ __(
						'You have a $5,000 balance limit or 30 days from your first transaction to verify and set up deposits in your account.',
						'woocommerce-payments'
					) }
				</div>
			</div>
			<div className="wcpay-progressive-onboarding-eligibility-modal__footer">
				<Button variant="secondary" onClick={ handleSetup }>
					{ __( 'Start receiving deposits', 'woocommerce-payments' ) }
				</Button>
				<Button variant="primary" onClick={ handlePaymentsOnly }>
					{ __( 'Start selling', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default ProgressiveOnboardingEligibilityModal;
