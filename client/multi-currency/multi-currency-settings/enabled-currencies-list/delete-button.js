/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useCallback, useState } from '@wordpress/element';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import CurrencyDeleteIllustration from 'wcpay/components/currency-delete-illustration';
import PaymentMethodIcon from 'wcpay/settings/payment-method-icon';
import paymentMethodsMap from 'wcpay/payment-methods-map';

const DeleteButton = ( { code, label, symbol, onClick, className } ) => {
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	const currencyDependentPaymentMethods =
		window.multiCurrencyPaymentMethodsMap;

	const isModalNeededToConfirm =
		currencyDependentPaymentMethods &&
		currencyDependentPaymentMethods[ code ] &&
		Object.keys( currencyDependentPaymentMethods[ code ] ).length > 0;

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
					title={ sprintf(
						__(
							/* translators: %1: Name of the currency being removed */
							'Remove %1$s',
							'woocommerce-payments'
						),
						label
					) }
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
							<li key={ paymentMethod }>
								<PaymentMethodIcon
									Icon={
										paymentMethodsMap[ paymentMethod ].icon
									}
									label={
										paymentMethodsMap[ paymentMethod ].label
									}
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
