/**
 * External dependencies
 */
import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './index.scss';

const PreviewModal = ( {
	isPreviewModalOpen,
	setPreviewModalOpen,
	isStorefrontSwitcherEnabledValue,
	isAutomaticSwitchEnabledValue,
	clientCurrency,
} ) => {
	const handlePreviewModalCloseClick = () => {
		setPreviewModalOpen( false );
	};

	return (
		isPreviewModalOpen && (
			<Modal
				title={ __( 'Preview', 'woocommerce-payments' ) }
				isDismissible={ true }
				className="multi-currency-store-settings-preview-modal"
				shouldCloseOnClickOutside={ false }
				onRequestClose={ handlePreviewModalCloseClick }
			>
				<iframe
					title={ __( 'Preview', 'woocommerce-payments' ) }
					className={ 'multi-currency-store-settings-preview-iframe' }
					src={
						'/?is_mc_onboarding_simulation=1&enable_storefront_switcher=' +
						isStorefrontSwitcherEnabledValue +
						'&enable_auto_currency=' +
						isAutomaticSwitchEnabledValue +
						'&client_currency=' +
						clientCurrency
					}
				></iframe>
			</Modal>
		)
	);
};

export default PreviewModal;
