/**
 * External dependencies
 */
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import './style.scss';
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import { useState } from '@wordpress/element';

const LearnMoreLink = ( props ) => (
	<Link
		{ ...props }
		href="https://docs.woocommerce.com/document/payments/countries/"
		target="_blank"
		rel="noopener noreferrer"
		type="external"
	/>
);

const OnboardingLocationCheckModal = () => {
	// Declare state attributes
	const [ isModalOpen, setModalOpen ] = useState( true );
	const [ isProcessingContinue, setProcessingContinue ] = useState( false );
	if ( ! isModalOpen ) {
		return null;
	}

	// Declare hooks to handle button clicks
	const onContinue = () => {
		setProcessingContinue( true );
	};
	const onCancel = () => {
		setModalOpen( false );
	};

	const title = __(
		'WooCommerce Payments: location check',
		'woocommerce-payments'
	);

	const message = interpolateComponents( {
		mixedString: __(
			'… {{link}}Learn more{{/link}}',
			'woocommerce-payments'
		),
		components: { link: <LearnMoreLink /> },
	} );

	return (
		<Modal
			title={ title }
			isDismissible={ true }
			shouldCloseOnClickOutside={ true }
			shouldCloseOnEsc={ true }
			onRequestClose={ onCancel }
			className="woocommerce-payments__onboarding_location_check-modal"
		>
			<div className="woocommerce-payments__onboarding_location_check-wrapper">
				<div className="woocommerce-payments__onboarding_location_check-modal-message">
					{ message }
				</div>
				<div className="woocommerce-payments__onboarding_location_check-footer">
					<Button
						isSecondary
						onClick={ onContinue }
						isBusy={ isProcessingContinue }
					>
						{ __( 'Continue', 'woocommerce-payments' ) }
					</Button>

					<Button
						isPrimary
						onClick={ onCancel }
						disabled={ isProcessingContinue }
					>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default OnboardingLocationCheckModal;
