/** @format */
/**
 * External dependencies
 */
import { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState, useCallback, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';
import ConfirmationModal from '../../components/confirmation-modal';
import WCPaySettingsContext from '../wcpay-settings-context';
import { formatAccountFeesDescription } from '../../utils/account-fees';

const PaymentMethodsSelector = ( { className } ) => {
	const { accountFees } = useContext( WCPaySettingsContext );

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const [
		isPaymentMethodsSelectorModalOpen,
		setIsPaymentMethodsSelectorModalOpen,
	] = useState( false );

	const [ paymentMethods, setPaymentMethods ] = useState( {} );

	useEffect( () => {
		setPaymentMethods(
			availablePaymentMethodIds
				.filter(
					( methodId ) => ! enabledMethodIds.includes( methodId )
				)
				.reduce( ( acc, value ) => {
					acc[ value ] = false;
					return acc;
				}, {} )
		);
	}, [ availablePaymentMethodIds, enabledMethodIds ] );

	const addSelectedPaymentMethods = ( itemIds ) => {
		updateEnabledMethodIds( [
			...new Set( [ ...enabledMethodIds, ...itemIds ] ),
		] );
	};

	const handleChange = ( paymentMethod, enabled ) => {
		setPaymentMethods( ( oldPaymentMethods ) => ( {
			...oldPaymentMethods,
			[ paymentMethod ]: enabled,
		} ) );
	};

	const handlePaymentMethodAddButtonClick = useCallback( () => {
		setIsPaymentMethodsSelectorModalOpen( true );
	}, [ setIsPaymentMethodsSelectorModalOpen ] );

	const handleAddSelectedCancelClick = useCallback( () => {
		setIsPaymentMethodsSelectorModalOpen( false );
	}, [ setIsPaymentMethodsSelectorModalOpen ] );

	const handleAddSelectedClick = () => {
		setIsPaymentMethodsSelectorModalOpen( false );
		const selectedPaymentMethods = Object.entries( paymentMethods )
			.filter( ( [ , enabled ] ) => enabled )
			.map( ( [ method ] ) => method );
		addSelectedPaymentMethods( selectedPaymentMethods );
	};

	const getMethodFee = ( methodId ) => {
		const methodFees = accountFees[ methodId ];

		if ( ! methodFees ) {
			return __( 'missing fees', 'woocommerce-payments' );
		}

		/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
		const format = __( '%1$f%% + %2$s', 'woocommerce-payments' );

		return formatAccountFeesDescription( methodFees, format, '' );
	};

	return (
		<>
			{ isPaymentMethodsSelectorModalOpen && (
				<ConfirmationModal
					title={ __(
						'Add payment methods',
						'woocommerce-payments'
					) }
					onRequestClose={ handleAddSelectedCancelClick }
					actions={
						<>
							<Button
								isSecondary
								onClick={ handleAddSelectedCancelClick }
							>
								{ __( 'Cancel', 'woocommerce-payments' ) }
							</Button>
							<Button
								isPrimary
								onClick={ handleAddSelectedClick }
								disabled={
									0 ===
									Object.entries( paymentMethods ).filter(
										( [ , enabled ] ) => enabled
									).length
								}
							>
								{ __( 'Add selected', 'woocommerce-payments' ) }
							</Button>
						</>
					}
				>
					<p>
						{ __(
							"Increase your store's conversion by offering your customers preferred and convenient payment methods.",
							'woocommerce-payments'
						) }
					</p>
					<PaymentMethodCheckboxes>
						{ Object.entries( paymentMethods ).map(
							( [ key, enabled ] ) => (
								<PaymentMethodCheckbox
									key={ key }
									checked={ enabled }
									onChange={ handleChange }
									fees={ getMethodFee( key ) }
									name={ key }
								/>
							)
						) }
					</PaymentMethodCheckboxes>
				</ConfirmationModal>
			) }
			<Button
				isSecondary
				className={ className }
				onClick={ handlePaymentMethodAddButtonClick }
				disabled={
					enabledMethodIds.length === availablePaymentMethodIds.length
				}
			>
				{ __( 'Add payment method', 'woocommerce-payments' ) }
			</Button>
		</>
	);
};

export default PaymentMethodsSelector;
