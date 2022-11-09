/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import Gridicon from 'gridicons';
import interpolateComponents from 'interpolate-components';
import { useCallback, useState } from '@wordpress/element';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import CurrencyDeleteIllustration from 'wcpay/components/currency-delete-illustration';
import PaymentMethodIcon from 'wcpay/settings/payment-method-icon';

// TODO: Delete button and modal should be separated.
// TODO: This removes the item, but the list does not refresh.
const DeleteButton = ( { code, label, symbol, onClick, className } ) => {
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	const currencyDependentPaymentMethods =
		window.multiCurrencyPaymentMethodsMap;

	const isModalNeededToConfirm =
		currencyDependentPaymentMethods &&
		currencyDependentPaymentMethods[ code ] &&
		0 < Object.keys( currencyDependentPaymentMethods[ code ] ).length;

	const dependentPaymentMethods = isModalNeededToConfirm
		? Object.keys( currencyDependentPaymentMethods[ code ] )
		: [];

	const handleDeleteIconClick = useCallback( () => {
		if ( isModalNeededToConfirm ) {
			setIsConfirmationModalOpen( true );
		} else {
			onClick( code );
		}
	}, [ setIsConfirmationModalOpen, isModalNeededToConfirm, onClick, code ] );

	const handleDeleteConfirmationClick = useCallback( () => {
		setIsConfirmationModalOpen( false );
		onClick( code );
	}, [ onClick, setIsConfirmationModalOpen, code ] );

	const handleDeleteCancelClick = useCallback( () => {
		setIsConfirmationModalOpen( false );
	}, [ setIsConfirmationModalOpen ] );

	return (
		<>
			{ isConfirmationModalOpen && (
				<ConfirmationModal
					title={ interpolateComponents( {
						mixedString: sprintf(
							__(
								/* translators: %1: Name of the currency being removed */
								'{{infoIcon /}} Remove %1$s',
								'woocommerce-payments'
							),
							label
						),
						components: {
							infoIcon: (
								<Gridicon
									icon="info-outline"
									className="currency-delete-illustration__currency-info-icon"
								/>
							),
						},
					} ) }
					onRequestClose={ handleDeleteCancelClick }
					className="enabled-currency-delete-modal"
					actions={
						<>
							<Button
								onClick={ handleDeleteConfirmationClick }
								isPrimary
								isDestructive
							>
								{ __( 'Remove', 'woocommerce-payments' ) }
							</Button>
							<Button
								onClick={ handleDeleteCancelClick }
								isSecondary
							>
								{ __( 'Cancel', 'woocommerce-payments' ) }
							</Button>
						</>
					}
				>
					<CurrencyDeleteIllustration symbol={ symbol } />
					<p>
						{ interpolateComponents( {
							mixedString: sprintf(
								__(
									'Are you sure you want to remove {{strong}}%s (%s){{/strong}}? ' +
										'Your customers will no longer be able to pay in this currency and ' +
										'use payment methods listed below.',
									'woocommerce-payments'
								),
								label,
								code === symbol
									? code
									: [ code, symbol ].join( ' ' )
							),
							components: {
								strong: <strong />,
							},
						} ) }
					</p>
					<ul>
						{ dependentPaymentMethods.map( ( paymentMethod ) => (
							<li key={ 'pm-icon-wrapper-' + paymentMethod }>
								<PaymentMethodIcon
									key={ 'pm-icon-' + paymentMethod }
									name={ paymentMethod }
									showName={ true }
								/>
							</li>
						) ) }
					</ul>
					<p>
						{ sprintf(
							__(
								'You can add %s (%s) again at any time in Multi-Currency settings.',
								'woocommerce-payments'
							),
							label,
							code === symbol
								? code
								: [ code, symbol ].join( ' ' )
						) }
					</p>
				</ConfirmationModal>
			) }
			<Button
				isLink
				aria-label={ sprintf(
					__(
						/* translators: %1: Name of the currency being removed */
						'Remove %1$s as an enabled currency',
						'woocommerce-payments'
					),
					label
				) }
				className={ className }
				onClick={ handleDeleteIconClick }
			>
				<Icon icon="trash" />
			</Button>
		</>
	);
};

export default DeleteButton;
