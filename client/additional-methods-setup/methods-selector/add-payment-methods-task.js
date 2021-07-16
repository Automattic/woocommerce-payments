/**
 * External dependencies
 */
import React, { useCallback, useContext, useMemo, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardDivider,
	CheckboxControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useSettings,
	usePaymentRequestEnabledSettings,
} from '../../data';
import './add-payment-methods-task.scss';
import CurrencyInformationForMethods from '../../components/currency-information-for-methods';

const useGetCountryName = () => {
	const storeCountry = useSelect(
		( select ) =>
			select( 'wc/admin/settings' ).getSettings( 'general' )?.general
				?.woocommerce_default_country || '',
		[]
	);

	const [ countryCode ] = storeCountry.split( ':' );

	const countries = useSelect(
		( select ) =>
			select( 'wc/admin/settings' ).getSetting( 'wc_admin', 'countries' ),
		[]
	);

	return countries[ countryCode ];
};

const usePaymentMethodsCheckboxState = ( initialValue ) => {
	const [ paymentMethodsState, setPaymentMethodsState ] = useState(
		initialValue
	);
	const handleChange = useCallback(
		( paymentMethodName, enabled ) => {
			setPaymentMethodsState( ( oldValues ) => ( {
				...oldValues,
				[ paymentMethodName ]: enabled,
			} ) );
		},
		[ setPaymentMethodsState ]
	);

	return [ paymentMethodsState, handleChange ];
};

const AddPaymentMethodsTask = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const [
		initialEnabledPaymentMethodIds,
		updateEnabledPaymentMethodIds,
	] = useEnabledPaymentMethodIds();

	const [
		initialIsPaymentRequestEnabled,
		setIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const { saveSettings, isSaving } = useSettings();

	// I am using internal state in this component
	// and committing the changes on `initialEnabledPaymentMethodIds` only when the "continue" button is clicked.
	// Otherwise a user could navigate to another page via soft-routing and the settings would be in un-saved state,
	// possibly causing errors.
	const [
		paymentMethodsState,
		handlePaymentMethodChange,
	] = usePaymentMethodsCheckboxState(
		initialEnabledPaymentMethodIds.reduce(
			( map, paymentMethod ) => ( { ...map, [ paymentMethod ]: true } ),
			{}
		)
	);

	const [ isPaymentRequestChecked, setPaymentRequestChecked ] = useState(
		initialIsPaymentRequestEnabled
	);

	const { setCompleted } = useContext( WizardTaskContext );

	const checkedPaymentMethods = useMemo(
		() =>
			Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => enabled && method )
				.filter( Boolean ),
		[ paymentMethodsState ]
	);

	const handleContinueClick = useCallback( () => {
		// creating a separate callback, so that the main thread isn't blocked on click of the button
		const callback = async () => {
			if ( 1 > checkedPaymentMethods.length ) {
				alert(
					__(
						'Please select at least one method',
						'woocommerce-payments'
					)
				);
				return;
			}

			setIsPaymentRequestEnabled( isPaymentRequestChecked );
			updateEnabledPaymentMethodIds( checkedPaymentMethods );

			const isSuccess = await saveSettings();
			if ( ! isSuccess ) {
				// restoring the state, in case of soft route
				setIsPaymentRequestEnabled( initialIsPaymentRequestEnabled );
				updateEnabledPaymentMethodIds( initialEnabledPaymentMethodIds );
				return;
			}

			setCompleted( true, 'setup-complete' );
		};

		callback();
	}, [
		checkedPaymentMethods,
		updateEnabledPaymentMethodIds,
		saveSettings,
		setCompleted,
		initialEnabledPaymentMethodIds,
		initialIsPaymentRequestEnabled,
		isPaymentRequestChecked,
		setIsPaymentRequestEnabled,
	] );

	const countryName = useGetCountryName();

	return (
		<WizardTaskItem
			className="add-payment-methods-task"
			title={ __(
				'Set up additional payment methods',
				'woocommerce-payments'
			) }
			index={ 1 }
		>
			<p className="wcpay-wizard-task__description-element is-muted-color">
				{ interpolateComponents( {
					mixedString: __(
						"Increase your store's conversion by offering " +
							'your customers preferred and convenient payment methods on checkout. ' +
							'You can manage them later in {{settingsLink /}}.',
						'woocommerce-payments'
					),
					components: {
						settingsLink: (
							<a href="admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments">
								{ __( 'settings', 'woocommerce-payments' ) }
							</a>
						),
					},
				} ) }
			</p>
			<CollapsibleBody>
				<Card className="add-payment-methods-task__payment-selector-wrapper">
					<CardBody>
						{ /* eslint-disable-next-line max-len */ }
						<p className="add-payment-methods-task__payment-selector-title wcpay-wizard-task__description-element">
							{ sprintf(
								__(
									'Popular with customers in %1$s',
									'woocommerce-payments'
								),
								countryName
							) }
						</p>
						<PaymentMethodCheckboxes>
							{ availablePaymentMethods.includes( 'card' ) && (
								<PaymentMethodCheckbox
									checked={ paymentMethodsState.card }
									onChange={ handlePaymentMethodChange }
									fees="missing fees"
									name="card"
								/>
							) }
						</PaymentMethodCheckboxes>
					</CardBody>
					<CardDivider />
					<CardBody>
						<p className="add-payment-methods-task__payment-selector-title">
							{ __(
								'Additional payment methods',
								'woocommerce-payments'
							) }
						</p>
						<PaymentMethodCheckboxes>
							{ availablePaymentMethods.includes( 'giropay' ) && (
								<PaymentMethodCheckbox
									checked={ paymentMethodsState.giropay }
									onChange={ handlePaymentMethodChange }
									fees="missing fees"
									name="giropay"
								/>
							) }
							{ availablePaymentMethods.includes( 'sofort' ) && (
								<PaymentMethodCheckbox
									checked={ paymentMethodsState.sofort }
									onChange={ handlePaymentMethodChange }
									fees="missing fees"
									name="sofort"
								/>
							) }
							{ availablePaymentMethods.includes(
								'sepa_debit'
							) && (
								<PaymentMethodCheckbox
									checked={ paymentMethodsState.sepa_debit }
									onChange={ handlePaymentMethodChange }
									fees="missing fees"
									name="sepa_debit"
								/>
							) }
						</PaymentMethodCheckboxes>
					</CardBody>
				</Card>
				<CurrencyInformationForMethods
					selectedMethods={ checkedPaymentMethods }
				/>
				<div className="wcpay-wizard-task__description-element">
					<CheckboxControl
						checked={ isPaymentRequestChecked }
						onChange={ setPaymentRequestChecked }
						label={ __(
							'Enable Apple Pay & Google Pay',
							'woocommerce-payments'
						) }
						help={ interpolateComponents( {
							mixedString: __(
								'By enabling this feature, you agree to {{stripeLink /}}, ' +
									"{{appleLink /}}, {{googleLink /}} and {{microsoftLink /}}'s terms of use.",
								'woocommerce-payments'
							),
							components: {
								stripeLink: (
									<a
										target="_blank"
										rel="noreferrer"
										href="https://stripe.com/apple-pay/legal"
									>
										{ __(
											'Stripe',
											'woocommerce-payments'
										) }
									</a>
								),
								appleLink: (
									<a
										target="_blank"
										rel="noreferrer"
										href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/"
									>
										{ __(
											'Apple',
											'woocommerce-payments'
										) }
									</a>
								),
								googleLink: (
									<a
										target="_blank"
										rel="noreferrer"
										href="https://androidpay.developers.google.com/terms/sellertos"
									>
										{ __(
											'Google',
											'woocommerce-payments'
										) }
									</a>
								),
								microsoftLink: (
									<a
										target="_blank"
										rel="noreferrer"
										href="https://www.microsoft.com/en/servicesagreement/"
									>
										{ __(
											'Microsoft',
											'woocommerce-payments'
										) }
									</a>
								),
							},
						} ) }
					/>
				</div>
				<Button
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __( 'Continue', 'woocommerce-payments' ) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddPaymentMethodsTask;
