/**
 * External dependencies
 */
import interpolateComponents from '@automattic/interpolate-components';
import { Button, Modal, ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { List } from '@woocommerce/components';
import { useState } from '@wordpress/element';
import './style.scss';

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

	const title = 'WooPayments';

	const message = interpolateComponents( {
		mixedString: sprintf(
			/* translators: %1$s: WooPayments */
			__(
				"It appears you're attempting to set up %1$s from an unsupported country. " +
					'In order to complete the set up of %1$s, your store is required to have a business ' +
					'entity in one of the following countries: {{list /}} ' +
					'{{link}}Learn more{{/link}} about setting up business entities in foreign countries.',
				'woocommerce-payments'
			),
			'WooPayments'
		),
		components: {
			link: (
				<ExternalLink href="https://woocommerce.com/document/woopayments/compatibility/countries/" />
			),
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
						variant="secondary"
						onClick={ handleConfirmedRequest }
						isBusy={ isProcessingContinue }
						__next40pxDefaultSize
					>
						{ __( 'Continue', 'woocommerce-payments' ) }
					</Button>

					<Button
						variant="primary"
						onClick={ handleDeclinedRequest }
						disabled={ isProcessingContinue }
						__next40pxDefaultSize
					>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default OnboardingLocationCheckModal;
