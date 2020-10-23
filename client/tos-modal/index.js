/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import interpolateComponents from 'interpolate-components';
import { Button, Modal } from '@wordpress/components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { getPaymentMethodsUrl } from 'utils';
import './style.scss';

const TosLink = ( props ) => (
	<Link
		{ ...props }
		href="https://wordpress.com/tos"
		target="_blank"
		type="external"
	/>
);

const TosModalUI = ( { onAccept, onDecline, isBusy } ) => {
	const title = __(
		'WooCommerce Payments: Terms of Service',
		'woocommerce-payments'
	);
	const message = interpolateComponents( {
		mixedString: __(
			'To continue using WooCommerce Payments, please review and agree to our {{link}}Terms of Service{{/link}}.' +
				' By clicking “Accept” you agree to the Terms of Service.',
			'woocommerce-payments'
		),
		components: { link: <TosLink /> },
	} );

	return (
		<Modal
			title={ title }
			isDismissible={ false }
			shouldCloseOnClickOutside={ false }
			shouldCloseOnEsc={ false }
			onRequestClose={ onDecline }
			className="woocommerce-payments__tos-modal"
		>
			<div className="woocommerce-payments__tos-wrapper">
				<div className="woocommerce-payments__tos-modal-message">
					{ message }
				</div>
				<div className="woocommerce-payments__tos-footer">
					<Button isTertiary onClick={ onDecline } isBusy={ isBusy }>
						{ __( 'Decline', 'woocommerce-payments' ) }
					</Button>

					<Button isPrimary onClick={ onAccept } isBusy={ isBusy }>
						{ __( 'Accept', 'woocommerce-payments' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

const DisableModalUI = ( { onDisable, onCancel, isBusy } ) => {
	const title = __( 'Disable WooCommerce Payments', 'woocommerce-payments' );
	const message = interpolateComponents( {
		mixedString: __(
			'By declining our {{link}}Terms of Service{{/link}},' +
				' you’ll no longer be able to capture credit card payments using WooCommerce Payments.' +
				' Your previous transaction and deposit data will still be available.',
			'woocommerce-payments'
		),
		components: { link: <TosLink /> },
	} );

	return (
		<Modal
			title={ title }
			isDismissible={ false }
			shouldCloseOnClickOutside={ false }
			shouldCloseOnEsc={ false }
			onRequestClose={ onDisable }
			className="woocommerce-payments__tos-modal"
		>
			<div className="woocommerce-payments__tos-wrapper">
				<div className="woocommerce-payments__tos-modal-message">
					{ message }
				</div>
				<div className="woocommerce-payments__tos-footer">
					<Button isTertiary onClick={ onCancel } isBusy={ isBusy }>
						{ __( 'Back', 'woocommerce-payments' ) }
					</Button>

					<Button isPrimary onClick={ onDisable } isBusy={ isBusy }>
						{ __( 'Disable', 'woocommerce-payments' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

const makeTosRequest = ( { accept } ) =>
	apiFetch( {
		path: '/wc/v3/payments/tos',
		method: 'POST',
		data: { accept },
	} );

const TosModal = () => {
	const [ isTosModalOpen, setIsTosModalOpen ] = useState( true );
	const [ isDisableModalOpen, setIsDisableModalOpen ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );

	const closeTosModal = () => setIsTosModalOpen( false );
	const closeDisableModal = () => setIsDisableModalOpen( false );

	const declineTos = () => {
		closeTosModal();
		setIsDisableModalOpen( true );
	};

	const acceptTos = async () => {
		try {
			setIsBusy( true );
			await makeTosRequest( { accept: true } );
			closeTosModal();
		} catch ( err ) {
			// Note: errors handling will be added in https://github.com/Automattic/woocommerce-payments/pull/993
			// eslint-disable-next-line no-console
			console.error( err );
		} finally {
			setIsBusy( false );
		}
	};
	const disablePlugin = async () => {
		try {
			setIsBusy( true );
			await makeTosRequest( { accept: false } );
			closeDisableModal();
			window.location = getPaymentMethodsUrl();
		} catch ( err ) {
			// Note: errors handling will be added in https://github.com/Automattic/woocommerce-payments/pull/993
			// eslint-disable-next-line no-console
			console.error( err );
		} finally {
			setIsBusy( false );
		}
	};

	const cancelPluginDisable = () => {
		closeDisableModal();
		setIsTosModalOpen( true );
	};

	if ( isDisableModalOpen ) {
		return (
			<DisableModalUI
				onDisable={ disablePlugin }
				onCancel={ cancelPluginDisable }
				isBusy={ isBusy }
			/>
		);
	}

	if ( isTosModalOpen ) {
		return (
			<TosModalUI
				onAccept={ acceptTos }
				onDecline={ declineTos }
				isBusy={ isBusy }
			/>
		);
	}

	return null;
};

export default TosModal;
