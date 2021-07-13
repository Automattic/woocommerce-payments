/**
 * External dependencies
 */
import React, { useCallback, useContext, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useSettings,
} from '../../data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';

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

	const { saveSettings, isSaving } = useSettings();

	// I am using internal state in this component
	// and committing the changes on `initialEnabledPaymentMethodIds` only when the "continue" button is clicked.
	// Otherwise a user could navigate to another page via soft-routing and the settings would be in un-saved state,
	// possibly causing errors.
	const [
		paymentMethodsState,
		handlePaymentMethodChange,
	] = usePaymentMethodsCheckboxState(
		initialEnabledPaymentMethodIds
			.filter( ( methodId ) => 'card' === methodId )
			.reduce(
				( map, paymentMethod ) => ( {
					...map,
					[ paymentMethod ]: true,
				} ),
				{}
			)
	);

	const { setCompleted } = useContext( WizardTaskContext );

	const handleContinueClick = useCallback( () => {
		// creating a separate callback, so that the main thread isn't blocked on click of the button
		const callback = async () => {
			const checkedPaymentMethods = Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => enabled && method )
				.filter( Boolean );

			if ( 1 > checkedPaymentMethods.length ) {
				alert(
					__(
						'Please select at least one method',
						'woocommerce-payments'
					)
				);
				return;
			}

			updateEnabledPaymentMethodIds( [
				'card',
				...checkedPaymentMethods,
			] );

			const isSuccess = await saveSettings();
			if ( ! isSuccess ) {
				// restoring the state, in case of soft route
				updateEnabledPaymentMethodIds(
					initialEnabledPaymentMethodIds.filter(
						( methodId ) => 'card' === methodId
					)
				);
				return;
			}

			setCompleted( true, 'setup-complete' );
		};

		callback();
	}, [
		updateEnabledPaymentMethodIds,
		paymentMethodsState,
		saveSettings,
		setCompleted,
		initialEnabledPaymentMethodIds,
	] );

	return (
		<WizardTaskItem
			className="add-payment-methods-task"
			title={ __(
				'Set up additional payment methods',
				'woocommerce-payments'
			) }
			index={ 2 }
		>
			<CollapsibleBody>
				<Card className="add-payment-methods-task__payment-selector-wrapper">
					<CardBody>
						<p className="wcpay-wizard-task__description-element is-muted-color">
							{ interpolateComponents( {
								mixedString: __(
									'For best results, we recommend adding all available payment methods. ' +
										"We'll only show your customer the most relevant payment methods " +
										'based on their location. {{learnMoreLink /}}.',
									'woocommerce-payments'
								),
								components: {
									// TODO
									learnMoreLink: (
										<a href="admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments">
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</a>
									),
								},
							} ) }
						</p>
						{ /* eslint-disable-next-line max-len */ }
						<p className="add-payment-methods-task__payment-selector-title wcpay-wizard-task__description-element">
							{ __(
								'Payments accepted at checkout',
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
				<Button
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __( 'Add payment methods', 'woocommerce-payments' ) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddPaymentMethodsTask;
