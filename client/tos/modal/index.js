/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import interpolateComponents from '@automattic/interpolate-components';
import { Button, Notice, Modal } from '@wordpress/components';
import { Link } from '@woocommerce/components';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { getPaymentMethodsUrl } from 'utils';
import {
	makeTosAcceptanceRequest,
	maybeTrackStripeConnected,
} from '../request.js';
import './style.scss';

const TosLink = ( props ) => (
	<Link
		{ ...props }
		href="https://wordpress.com/tos"
		target="_blank"
		rel="noopener noreferrer"
		type="external"
	/>
);

const TosModalUI = ( { onAccept, onDecline, isBusy, hasError } ) => {
	const title = sprintf(
		/* translators: %s: WooPayments */
		__( '%s: Terms of Service', 'woocommerce-payments' ),
		'WooPayments'
	);
	const message = interpolateComponents( {
		mixedString: sprintf(
			/* translators: %s: WooPayments */
			__(
				'To continue using %s, please review and agree to our {{link}}Terms of Service{{/link}}.' +
					' By clicking “Accept” you agree to the Terms of Service.',
				'woocommerce-payments'
			),
			'WooPayments'
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
			{ hasError && (
				<Notice
					status="error"
					isDismissible={ false }
					className="woocommerce-payments__tos-error"
				>
					{ __(
						'Something went wrong. Please try accepting the Terms of Service again!',
						'woocommerce-payments'
					) }
				</Notice>
			) }
			<div className="woocommerce-payments__tos-wrapper">
				<div className="woocommerce-payments__tos-modal-message">
					{ message }
				</div>
				<div className="woocommerce-payments__tos-footer">
					<Button
						isSecondary
						onClick={ onDecline }
						disabled={ isBusy }
					>
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

const DisableModalUI = ( { onDisable, onCancel, isBusy, hasError } ) => {
	const title = sprintf(
		/* translators: %s: WooPayments */
		__( 'Disable %s', 'woocommerce-payments' ),
		'WooPayments'
	);
	const message = interpolateComponents( {
		mixedString: sprintf(
			/* translators: %s: WooPayments */
			__(
				'By declining our {{link}}Terms of Service{{/link}},' +
					' you’ll no longer be able to capture credit card payments using %s.' +
					' Your previous transaction and payout data will still be available.',
				'woocommerce-payments'
			),
			'WooPayments'
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
			{ hasError && (
				<Notice
					status="error"
					isDismissible={ false }
					className="woocommerce-payments__tos-error"
				>
					{ __(
						'Something went wrong. Please try again!',
						'woocommerce-payments'
					) }
				</Notice>
			) }

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

const TosModal = () => {
	const [ isTosModalOpen, setIsTosModalOpen ] = useState( true );
	const [ isDisableModalOpen, setIsDisableModalOpen ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ hasAcceptanceError, setAcceptanceError ] = useState( false );
	const [ hasDeclineError, setDeclineError ] = useState( false );

	const closeTosModal = () => setIsTosModalOpen( false );
	const closeDisableModal = () => setIsDisableModalOpen( false );

	const declineTos = () => {
		closeTosModal();
		setIsDisableModalOpen( true );
	};

	const acceptTos = async () => {
		try {
			setAcceptanceError( false );
			setIsBusy( true );
			await makeTosAcceptanceRequest( { accept: true } );
			maybeTrackStripeConnected();
			closeTosModal();
		} catch ( err ) {
			setAcceptanceError( true );
		} finally {
			setIsBusy( false );
		}
	};
	const disablePlugin = async () => {
		try {
			setDeclineError( false );
			setIsBusy( true );
			await makeTosAcceptanceRequest( { accept: false } );
			closeDisableModal();
			window.location = addQueryArgs( getPaymentMethodsUrl(), {
				'tos-disabled': 1,
			} );
		} catch ( err ) {
			setDeclineError( true );
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
				hasError={ hasDeclineError }
			/>
		);
	}

	if ( isTosModalOpen ) {
		return (
			<TosModalUI
				onAccept={ acceptTos }
				onDecline={ declineTos }
				isBusy={ isBusy }
				hasError={ hasAcceptanceError }
			/>
		);
	}

	return null;
};

export default TosModal;
