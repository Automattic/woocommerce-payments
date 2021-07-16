/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState, useCallback } from '@wordpress/element';

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

const AddPaymentMethodsModal = ( { onClose } ) => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();

	const [
		enabledPaymentMethods,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const notEnabledPaymentMethods = availablePaymentMethods.filter(
		( methodId ) => ! enabledPaymentMethods.includes( methodId )
	);
	const [ selectedPaymentMethods, setSelectedPaymentMethods ] = useState(
		[]
	);

	const handleCheckboxClick = ( paymentMethod, isSelected ) => {
		if ( isSelected ) {
			setSelectedPaymentMethods( ( oldPaymentMethods ) => [
				...oldPaymentMethods,
				paymentMethod,
			] );
			return;
		}

		setSelectedPaymentMethods( ( oldPaymentMethods ) => {
			return [
				...oldPaymentMethods.filter(
					( item ) => item !== paymentMethod
				),
			];
		} );
	};

	const handleConfirmationClick = () => {
		updateEnabledMethodIds( [
			...new Set( [
				...enabledPaymentMethods,
				...selectedPaymentMethods,
			] ),
		] );
		onClose();
	};
	return (
		<ConfirmationModal
			title={ __( 'Add payment methods', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
			actions={
				<>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
					<Button
						isPrimary
						onClick={ handleConfirmationClick }
						disabled={ 0 === selectedPaymentMethods.length }
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
				{ notEnabledPaymentMethods.map( ( method ) => (
					<PaymentMethodCheckbox
						key={ method }
						checked={ selectedPaymentMethods.includes( method ) }
						onChange={ handleCheckboxClick }
						fees="missing fees"
						name={ method }
					/>
				) ) }
			</PaymentMethodCheckboxes>
		</ConfirmationModal>
	);
};

const PaymentMethodsSelector = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const [ enabledPaymentMethods ] = useEnabledPaymentMethodIds();

	const [ isModalOpen, setIsModalOpen ] = useState( false );

	const handleModalOpen = useCallback( () => {
		setIsModalOpen( true );
	}, [ setIsModalOpen ] );

	const handleModalClose = useCallback( () => {
		setIsModalOpen( false );
	}, [ setIsModalOpen ] );

	return (
		<>
			{ isModalOpen && (
				<AddPaymentMethodsModal onClose={ handleModalClose } />
			) }
			<Button
				isSecondary
				onClick={ handleModalOpen }
				disabled={
					enabledPaymentMethods.length ===
					availablePaymentMethods.length
				}
			>
				{ __( 'Add payment method', 'woocommerce-payments' ) }
			</Button>
		</>
	);
};

export default PaymentMethodsSelector;
