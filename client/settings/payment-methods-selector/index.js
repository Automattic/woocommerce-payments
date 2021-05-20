/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { useState, useCallback, useEffect } from '@wordpress/element';
import { HorizontalRule } from '@wordpress/primitives';

/**
 * Internal dependencies
 */

import { useEnabledPaymentMethodIds } from 'data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';
import './style.scss';

const availablePaymentMethods = [
	'woocommerce_payments_giropay',
	'woocommerce_payments_sofort',
	'woocommerce_payments_sepa',
];
const PaymentMethodsSelector = ( { className } ) => {
	const {
		enabledPaymentMethodIds: enabledMethodIds,
		updateEnabledPaymentMethodIds: updateEnabledMethodIds,
	} = useEnabledPaymentMethodIds();

	const [
		isPaymentMethodsSelectorModalOpen,
		setIsPaymentMethodsSelectorModalOpen,
	] = useState( false );

	const [ paymentMethods, setPaymentMethods ] = useState( {} );

	useEffect( () => {
		setPaymentMethods(
			availablePaymentMethods
				.filter(
					( methodId ) => ! enabledMethodIds.includes( methodId )
				)
				.reduce( ( acc, value ) => {
					acc[ value ] = false;
					return acc;
				}, {} )
		);
	}, [ enabledMethodIds ] );

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
	return (
		<>
			{ isPaymentMethodsSelectorModalOpen && (
				<Modal
					title={ __(
						'Add payment methods',
						'woocommerce-payments'
					) }
					onRequestClose={ handleAddSelectedCancelClick }
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
									fees="missing fees"
									name={ key }
								/>
							)
						) }
					</PaymentMethodCheckboxes>
					<HorizontalRule className="woocommerce-payments__payment-method-selector__separator" />
					<div className="woocommerce-payments__payment-method-selector__footer">
						<Button
							isSecondary
							onClick={ handleAddSelectedCancelClick }
						>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button isPrimary onClick={ handleAddSelectedClick }>
							{ __( 'Add selected', 'woocommerce-payments' ) }
						</Button>
					</div>
				</Modal>
			) }
			<Button
				isSecondary
				className={ className }
				onClick={ handlePaymentMethodAddButtonClick }
			>
				{ __( 'Add payment method', 'woocommerce-payments' ) }
			</Button>
		</>
	);
};

export default PaymentMethodsSelector;
