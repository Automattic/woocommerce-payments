/** @format **/

/**
 * External dependencies
 */
import { Button, Modal, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import Currency from '@woocommerce/currency';
import apiFetch from '@wordpress/api-fetch';

const currency = new Currency();

const InstantDepositModal = ( {
	amount,
	fee,
	net,
	onClose,
	onSubmit,
	inProgress,
	hasError,
} ) => {
	// TODO: add proper url for instant payout doc
	const learnMoreHref = '';
	const description = createInterpolateElement(
		/* translators: <a> - instant payout doc URL */
		__(
			'Need cash in a hurry? Instant deposits are available within 30 minutes for a nominal 1% service fee. <a>Learn more</a>',
			'woocommerce-payments'
		),
		// eslint-disable-next-line jsx-a11y/anchor-has-content
		{ a: <a href={ learnMoreHref } /> }
	);

	return (
		<Modal
			title={ __( 'Instant deposit', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
			className="wcpay-instant-deposits-modal"
		>
			{ hasError && <InstantDepositSubmitNotice error={ hasError } /> }

			<p>{ description }</p>
			<ul>
				<li className="wcpay-instant-deposits-modal__balance">
					{ __(
						'Balance available for instant deposit: ',
						'woocommerce-payments'
					) }
					<span>{ currency.formatCurrency( amount / 100 ) }</span>
				</li>
				<li className="wcpay-instant-deposits-modal__fee">
					{ __( '1% service fee: ', 'woocommerce-payments' ) }
					<span>-{ currency.formatCurrency( fee / 100 ) }</span>
				</li>
				<li className="wcpay-instant-deposits-modal__net">
					{ __( 'Net deposit amount: ', 'woocommerce-payments' ) }
					<span>{ currency.formatCurrency( net / 100 ) }</span>
				</li>
			</ul>
			<Button isPrimary onClick={ onSubmit } isBusy={ inProgress }>
				{ sprintf(
					/* translators: %s: Monetary amount to deposit */
					__( 'Deposit %s now', 'woocommerce-payments' ),
					currency.formatCurrency( net / 100 )
				) }
			</Button>
			<Button isDefault onClick={ onClose }>
				{ __( 'Cancel', 'woocommerce-payments' ) }
			</Button>
		</Modal>
	);
};

const InstantDepositSubmitNotice = ( { error } ) => {
	let message = '';
	// TODO: supply proper error notices.
	switch ( error.code ) {
		default:
			message = __(
				'There was an error, please try again.',
				'woocommerce-payments'
			);
	}

	return (
		<Notice status="error" isDismissible={ false }>
			<p>{ message }</p>
		</Notice>
	);
};

const InstantDepositButton = ( {
	balance: { amount, fee, net, transaction_ids: transactionIds },
} ) => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const [ inProgress, setInProgress ] = useState( false );
	const [ hasError, setHasError ] = useState( false );

	// TODO: Use wp.data
	const submit = async () => {
		try {
			setInProgress( true );
			setHasError( false );
			await apiFetch( {
				path: '/wc/v3/payments/deposits',
				method: 'POST',
				data: {
					type: 'instant',
					transactionIds,
				},
			} );
			// TODO: Success notice? Full-reload the page so the new deposit appears?
		} catch ( err ) {
			setHasError( err );
		} finally {
			setInProgress( false );
		}
	};

	return (
		<>
			<Button isDefault onClick={ () => setModalOpen( true ) }>
				{ __( 'Instant deposit', 'woocommerce-payments' ) }
			</Button>
			{ isModalOpen && (
				<InstantDepositModal
					amount={ amount }
					fee={ fee }
					net={ net }
					inProgress={ inProgress }
					onSubmit={ submit }
					onClose={ () => {
						setModalOpen( false );
						setHasError( false );
					} }
					hasError={ hasError }
				/>
			) }
		</>
	);
};

export default InstantDepositButton;
