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
import { Link, List } from '@woocommerce/components';
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

const OnboardingLocationCheckModal = ( {
	countries,
	onDeclined,
	onConfirmed,
} ) => {
	// Declare state attributes
	const [ isModalOpen, setModalOpen ] = useState( true );
	const [ isProcessingContinue, setProcessingContinue ] = useState( false );
	if ( ! isModalOpen ) {
		return null;
	}

	// Declare hooks to handle button clicks
	const handleConfirmedRequest = () => {
		setProcessingContinue( true );
		onConfirmed();
	};
	const handleDeclinedRequest = () => {
		setModalOpen( false );
		onDeclined();
	};

	const title = __( 'WooCommerce Payments', 'woocommerce-payments' );

	const message = interpolateComponents( {
		mixedString: __(
			"It appears you're attempting to set up WooCommerce Payments from an unsupported country. " +
				'In order to complete the set up of WooCommerce Payments, your store is required to have a business ' +
				'entity in one of the following countries: {{list /}} ' +
				'{{link}}Learn more{{/link}} about setting up business entities in foreign countries.',
			'woocommerce-payments'
		),
		components: {
			link: <LearnMoreLink />,
			list: <List items={ countries } />,
		},
	} );

	return (
		<Modal
			title={ title }
			isDismissible={ true }
			shouldCloseOnClickOutside={ true }
			shouldCloseOnEsc={ true }
			onRequestClose={ handleDeclinedRequest }
			className="woocommerce-payments__onboarding_location_check-modal"
		>
			<div className="woocommerce-payments__onboarding_location_check-wrapper">
				<div className="woocommerce-payments__onboarding_location_check-modal-message">
					{ message }
				</div>
				<div className="woocommerce-payments__onboarding_location_check-footer">
					<Button
						isSecondary
						onClick={ handleConfirmedRequest }
						isBusy={ isProcessingContinue }
					>
						{ __( 'Continue', 'woocommerce-payments' ) }
					</Button>

					<Button
						isPrimary
						onClick={ handleDeclinedRequest }
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
