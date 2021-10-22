/** @format */
/**
 * External dependencies
 */
import React, { useContext, useState, useCallback } from 'react';
import { sprintf, __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	useCurrencies,
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
} from 'wcpay/data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';
import ConfirmationModal from '../../components/confirmation-modal';
import PaymentMethodsMap from 'wcpay/payment-methods-map';
import RequirementsMap from 'wcpay/requirements-map';
import CurrencyInformationForMethods from '../../components/currency-information-for-methods';
import WCPaySettingsContext from '../wcpay-settings-context';
import PaymentConfirmIllustration from 'wcpay/components/payment-confirm-illustration';
import interpolateComponents from 'interpolate-components';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import './style.scss';

const ConfirmPaymentMethodActivationModal = ( {
	paymentMethod,
	requirements,
	onClose,
	onConfirmClose,
} ) => {
	const handleConfirmationClick = () => {
		onConfirmClose();
	};
	return (
		<ConfirmationModal
			title={ sprintf(
				__( 'One more step to enable %s', 'woocommerce_payments' ),
				PaymentMethodsMap[ paymentMethod ].label
			) }
			shouldCloseOnClickOutside={ false }
			onRequestClose={ onClose }
			className={ 'wcpay-payment-method-confirmation-modal' }
			actions={
				<>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
					<Button isPrimary onClick={ handleConfirmationClick }>
						{ __( 'Continue', 'woocommerce-payments' ) }
					</Button>
				</>
			}
		>
			<PaymentConfirmIllustration
				Icon={ PaymentMethodsMap[ paymentMethod ].Icon }
				hasBorder={ 'card' !== PaymentMethodsMap[ paymentMethod ].id }
			/>
			<p>
				{ sprintf(
					__(
						'You need to provide more information to enable %s on your checkout:',
						'woocommerce-payments'
					),
					PaymentMethodsMap[ paymentMethod ].label
				) }
			</p>
			<ul className={ 'payment-method-requirements-list' }>
				{ requirements.map( ( requirement, index ) => (
					<li key={ 'requirement' + index }>
						{ RequirementsMap[ requirement ] ?? requirement }
					</li>
				) ) }
			</ul>
			<p>
				{ interpolateComponents( {
					mixedString: __(
						'If you choose to continue, our payment partner Stripe will send an e-mail ' +
							'to {{merchantEmail /}} to collect the required information',
						'woocommerce-payments'
					),
					components: {
						merchantEmail: (
							<b>{ wcSettings.currentUserData.email ?? '' }</b>
						),
					},
				} ) }
			</p>
		</ConfirmationModal>
	);
};

const AddPaymentMethodsModal = ( { onClose } ) => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const paymentMethodStatuses = useGetPaymentMethodStatuses();

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

	const [ modalPaymentMethod, setModalPaymentMethod ] = useState( null );

	const handleModalOpen = useCallback(
		( paymentMethod, requirements ) => {
			setModalPaymentMethod( { paymentMethod, requirements } );
		},
		[ setModalPaymentMethod ]
	);

	const handleModalClose = useCallback( () => {
		setModalPaymentMethod( null );
	}, [ setModalPaymentMethod ] );

	const handleModalConfirmClose = useCallback( () => {
		setSelectedPaymentMethods( ( oldPaymentMethods ) => [
			...oldPaymentMethods,
			modalPaymentMethod,
		] );
		setModalPaymentMethod( null );
	}, [
		setModalPaymentMethod,
		setSelectedPaymentMethods,
		modalPaymentMethod,
	] );

	const handleCheckboxClick = ( paymentMethod, isSelected ) => {
		if ( isSelected ) {
			const stripeKey = PaymentMethodsMap[ paymentMethod ].stripe_key;
			const stripeStatus = paymentMethodStatuses[ stripeKey ] ?? [];
			if (
				stripeStatus &&
				'unrequested' === stripeStatus.status &&
				0 < stripeStatus.requirements.length
			) {
				handleModalOpen( paymentMethod, stripeStatus.requirements );
			} else {
				setSelectedPaymentMethods( ( oldPaymentMethods ) => [
					...oldPaymentMethods,
					paymentMethod,
				] );
				return;
			}
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
			// using this because when the tooltips inside the modal are clicked, they cause the modal to close
			shouldCloseOnClickOutside={ false }
			onRequestClose={ onClose }
			className={ 'wcpay-payment-methods-add-modal' }
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
						name={ method }
						status={
							paymentMethodStatuses[
								PaymentMethodsMap[ method ].stripe_key
							] ?? upeCapabilityStatuses.UNREQUESTED
						}
					/>
				) ) }
			</PaymentMethodCheckboxes>
			<CurrencyInformationForMethods
				selectedMethods={ selectedPaymentMethods }
			/>
			{ modalPaymentMethod && (
				<ConfirmPaymentMethodActivationModal
					paymentMethod={ modalPaymentMethod.paymentMethod }
					requirements={ modalPaymentMethod.requirements }
					onClose={ handleModalClose }
					onConfirmClose={ handleModalConfirmClose }
				/>
			) }
		</ConfirmationModal>
	);
};

const LoadCurrencyData = () => {
	// ensures that the currency data is present before the modal is opened
	useCurrencies();

	return null;
};

const PaymentMethodsSelector = () => {
	const {
		featureFlags: { multiCurrency },
	} = useContext( WCPaySettingsContext );

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
			{ multiCurrency && <LoadCurrencyData /> }
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
