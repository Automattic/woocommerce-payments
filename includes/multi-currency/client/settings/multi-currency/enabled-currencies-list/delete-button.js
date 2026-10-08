/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useCallback, useState } from '@wordpress/element';
import {
	ConfirmationModal,
	PaymentMethodIcon,
} from 'multi-currency/interface/components';
import CurrencyDeleteIllustration from 'multi-currency/components/currency-delete-illustration';
import { paymentMethodsMap } from 'multi-currency/interface/assets';
import { useEnabledCurrencies } from 'multi-currency/data';
import getCurrencyRemovalImpact from './get-currency-removal-impact';

const DeleteButton = ( { code, label, symbol, onClick, className } ) => {
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] =
		useState( false );

	const { enabledCurrencies } = useEnabledCurrencies();
	const enabledCodes = enabledCurrencies
		? Object.keys( enabledCurrencies )
		: [];

	const { unavailable, limited } = getCurrencyRemovalImpact(
		window.multiCurrencyPaymentMethodsMap,
		code,
		enabledCodes
	);

	const isModalNeededToConfirm = unavailable.length + limited.length > 0;

	const renderPaymentMethods = ( methods ) => (
		<ul>
			{ methods.map( ( paymentMethod ) => (
				<li key={ paymentMethod }>
					<PaymentMethodIcon
						Icon={ paymentMethodsMap[ paymentMethod ].icon }
						label={ paymentMethodsMap[ paymentMethod ].label }
					/>
				</li>
			) ) }
		</ul>
	);

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
										'Your customers will no longer be able to pay in this currency.',
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
					{ unavailable.length > 0 && (
						<>
							<p>
								{ __(
									'These payment methods will no longer be available at checkout, ' +
										'since none of your remaining currencies support them:',
									'woocommerce-payments'
								) }
							</p>
							{ renderPaymentMethods( unavailable ) }
						</>
					) }
					{ limited.length > 0 && (
						<>
							<p>
								{ sprintf(
									/* translators: %s: Name of the currency being removed */
									__(
										'These payment methods will stay available in your other currencies, but not for payments in %s:',
										'woocommerce-payments'
									),
									label
								) }
							</p>
							{ renderPaymentMethods( limited ) }
						</>
					) }
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
				__next40pxDefaultSize
			>
				<Icon icon="trash" />
			</Button>
		</>
	);
};

export default DeleteButton;
