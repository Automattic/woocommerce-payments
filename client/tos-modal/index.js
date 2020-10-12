/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import interpolateComponents from 'interpolate-components';
import { Button, Modal } from '@wordpress/components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import './style.scss';

const TosModal = () => {
	// TODO: detect initial state
	const [ isOpen, setIsOpen ] = useState( true );
	const [ isDisableModalOpen, setIsDisableModalOpen ] = useState( false );

	const closeModal = () => setIsOpen( false );
	const closeDisableModal = () => setIsDisableModalOpen( false );

	const declineTos = () => {
		closeModal();
		setIsDisableModalOpen( true );
	};

	// TODO: Add handlers
	const acceptTos = closeModal;
	const disablePlugin = closeDisableModal;

	const cancelPluginDisable = () => {
		closeDisableModal();
		setIsOpen( true );
	};

	const title = __(
		'WooCommerce Payments: Terms of Service',
		'woocommerce-payments'
	);
	const trackingMessage = interpolateComponents( {
		mixedString: __(
			'To continue using WooCommerce Payments, please review and agree to our {{link}}Terms of Service{{/link}}.' +
				' By clicking “Accept” you agree to the Terms of Service.',
			'woocommerce-payments'
		),
		components: {
			link: (
				<Link
					href="https://wordpress.com/tos"
					target="_blank"
					type="external"
				/>
			),
		},
	} );

	if ( isDisableModalOpen ) {
		const disableModalTitle = __(
			'Disable WooCommerce Payments',
			'woocommerce-payments'
		);
		const disableMessage = interpolateComponents( {
			mixedString: __(
				'By declining our {{link}}Terms of Service{{/link}},' +
					' you’ll no longer be able to capture credit card payments using WooCommerce Payments.' +
					' Your previous transaction and deposit data will still be available.',
				'woocommerce-payments'
			),
			components: {
				link: (
					<Link
						href="https://wordpress.com/tos"
						target="_blank"
						type="external"
					/>
				),
			},
		} );

		return (
			<Modal
				title={ disableModalTitle }
				isDismissible={ false }
				shouldCloseOnClickOutside={ false }
				shouldCloseOnEsc={ false }
				onRequestClose={ disablePlugin }
				className="woocommerce-payments__tos-modal"
			>
				<div className="woocommerce-payments__tos-wrapper">
					<div className="woocommerce-payments__tos-modal-message">
						{ disableMessage }
					</div>
					<div className="woocommerce-payments__tos-footer">
						<Button isTertiary onClick={ cancelPluginDisable }>
							{ __( 'Back', 'woocommerce-payments' ) }
						</Button>

						<Button isPrimary onClick={ disablePlugin }>
							{ __( 'Disable', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			</Modal>
		);
	}

	if ( isOpen ) {
		return (
			<Modal
				title={ title }
				isDismissible={ false }
				shouldCloseOnClickOutside={ false }
				shouldCloseOnEsc={ false }
				onRequestClose={ closeModal }
				className="woocommerce-payments__tos-modal"
			>
				<div className="woocommerce-payments__tos-wrapper">
					<div className="woocommerce-payments__tos-modal-message">
						{ trackingMessage }
					</div>
					<div className="woocommerce-payments__tos-footer">
						<Button isTertiary onClick={ declineTos }>
							{ __( 'Decline', 'woocommerce-payments' ) }
						</Button>

						<Button isPrimary onClick={ acceptTos }>
							{ __( 'Accept', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			</Modal>
		);
	}

	return null;
};

export default TosModal;
