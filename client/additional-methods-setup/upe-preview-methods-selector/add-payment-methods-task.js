/**
 * External dependencies
 */
import React, {
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
} from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, Notice } from '@wordpress/components';
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
	useCurrencies,
	useEnabledCurrencies,
} from '../../data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';
import { LoadableBlock } from '../../components/loadable';
import LoadableSettingsSection from '../../settings/loadable-settings-section';
import './add-payment-methods-task.scss';

const usePaymentMethodsCheckboxState = () => {
	const [ paymentMethodsState, setPaymentMethodsState ] = useState( {} );

	const handleChange = useCallback(
		( paymentMethodName, enabled ) => {
			setPaymentMethodsState( ( oldValues ) => ( {
				...oldValues,
				[ paymentMethodName ]: enabled,
			} ) );
		},
		[ setPaymentMethodsState ]
	);

	return [ paymentMethodsState, handleChange, setPaymentMethodsState ];
};

const CurrencyInformation = ( { selectedMethods } ) => {
	const { isLoading: isLoadingCurrencyInformation } = useCurrencies();
	const { enabledCurrencies } = useEnabledCurrencies();

	if ( isLoadingCurrencyInformation ) {
		return null;
	}

	// if EUR is already enabled, no need to display the info message
	const enabledCurrenciesIds = Object.values( enabledCurrencies ).map(
		( currency ) => currency.id
	);
	if ( enabledCurrenciesIds.includes( 'eur' ) ) {
		return null;
	}

	const enabledMethodsRequiringEuros = selectedMethods.filter( ( method ) =>
		[ 'giropay', 'sepa_debit', 'sofort' ].includes( method )
	);

	if ( 0 === enabledMethodsRequiringEuros.length ) {
		return null;
	}

	return (
		<Notice isDismissible={ false } className="currency-notice">
			{ interpolateComponents( {
				mixedString: __(
					"The selected methods require an additional currency, so {{strong}}we'll add Euro (€) to your store{{/strong}}. " +
						'You can view & manage currencies later in settings.',
					'woocommerce-payments'
				),
				components: {
					strong: <strong />,
				},
			} ) }
		</Notice>
	);
};

const ContinueButton = ( { paymentMethodsState } ) => {
	const { setCompleted } = useContext( WizardTaskContext );
	const [
		initialEnabledPaymentMethodIds,
		updateEnabledPaymentMethodIds,
	] = useEnabledPaymentMethodIds();

	// TODO: add test to ensure `useSettings` is not called when task is not active
	const { saveSettings, isSaving } = useSettings();

	const handleContinueClick = useCallback( () => {
		// creating a separate callback, so that the main thread isn't blocked on click of the button
		const callback = async () => {
			const checkedPaymentMethods = Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => enabled && method )
				.filter( Boolean );
			const unCheckedPaymentMethods = Object.entries(
				paymentMethodsState
			)
				.map( ( [ method, enabled ] ) => ! enabled && method )
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
				// adding the newly selected payment methods and removing them from the `initialEnabledPaymentMethodIds` if unchecked
				...new Set(
					[
						...initialEnabledPaymentMethodIds,
						...checkedPaymentMethods,
					].filter(
						( method ) =>
							! unCheckedPaymentMethods.includes( method )
					)
				),
			] );

			const isSuccess = await saveSettings();
			if ( ! isSuccess ) {
				// restoring the state, in case of soft route
				updateEnabledPaymentMethodIds( initialEnabledPaymentMethodIds );
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
		<Button
			isBusy={ isSaving }
			disabled={ isSaving }
			onClick={ handleContinueClick }
			isPrimary
		>
			{ __( 'Add payment methods', 'woocommerce-payments' ) }
		</Button>
	);
};

const AddPaymentMethodsTask = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const { isActive } = useContext( WizardTaskContext );

	// I am using internal state in this component
	// and committing the changes on `initialEnabledPaymentMethodIds` only when the "continue" button is clicked.
	// Otherwise a user could navigate to another page via soft-routing and the settings would be in un-saved state,
	// possibly causing errors.
	const [
		paymentMethodsState,
		handlePaymentMethodChange,
		setPaymentMethodsState,
	] = usePaymentMethodsCheckboxState();
	const selectedMethods = useMemo(
		() =>
			Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => enabled && method )
				.filter( Boolean ),
		[ paymentMethodsState ]
	);

	useEffect( () => {
		setPaymentMethodsState(
			// by default, all the checkboxes should be "checked"
			availablePaymentMethods
				.filter( ( method ) =>
					[ 'giropay', 'sofort', 'sepa_debit' ].includes( method )
				)
				.reduce(
					( map, paymentMethod ) => ( {
						...map,
						[ paymentMethod ]: true,
					} ),
					{}
				)
		);
	}, [ availablePaymentMethods, setPaymentMethodsState ] );

	return (
		<WizardTaskItem
			className="add-payment-methods-task"
			title={ __(
				'Boost your sales with payment methods',
				'woocommerce-payments'
			) }
			index={ 2 }
		>
			<CollapsibleBody>
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
								<a href="?TODO">
									{ __(
										'Learn more',
										'woocommerce-payments'
									) }
								</a>
							),
						},
					} ) }
				</p>
				<Card className="add-payment-methods-task__payment-selector-wrapper">
					<CardBody>
						{ /* eslint-disable-next-line max-len */ }
						<p className="add-payment-methods-task__payment-selector-title wcpay-wizard-task__description-element">
							{ __(
								'Payments accepted at checkout',
								'woocommerce-payments'
							) }
						</p>
						<LoadableBlock numLines={ 10 } isLoading={ ! isActive }>
							<LoadableSettingsSection numLines={ 10 }>
								<PaymentMethodCheckboxes>
									{ availablePaymentMethods.includes(
										'giropay'
									) && (
										<PaymentMethodCheckbox
											checked={
												paymentMethodsState.giropay
											}
											onChange={
												handlePaymentMethodChange
											}
											fees="missing fees"
											name="giropay"
										/>
									) }
									{ availablePaymentMethods.includes(
										'sofort'
									) && (
										<PaymentMethodCheckbox
											checked={
												paymentMethodsState.sofort
											}
											onChange={
												handlePaymentMethodChange
											}
											fees="missing fees"
											name="sofort"
										/>
									) }
									{ availablePaymentMethods.includes(
										'sepa_debit'
									) && (
										<PaymentMethodCheckbox
											checked={
												paymentMethodsState.sepa_debit
											}
											onChange={
												handlePaymentMethodChange
											}
											fees="missing fees"
											name="sepa_debit"
										/>
									) }
								</PaymentMethodCheckboxes>
							</LoadableSettingsSection>
						</LoadableBlock>
					</CardBody>
				</Card>
				<CurrencyInformation selectedMethods={ selectedMethods } />
				<LoadableBlock numLines={ 10 } isLoading={ ! isActive }>
					<ContinueButton
						paymentMethodsState={ paymentMethodsState }
					/>
				</LoadableBlock>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddPaymentMethodsTask;
