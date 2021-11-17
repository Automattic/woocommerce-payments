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
import { Button, Card, CardBody, ExternalLink } from '@wordpress/components';
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
	useGetPaymentMethodStatuses,
	useSettings,
} from '../../data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';
import { LoadableBlock } from '../../components/loadable';
import LoadableSettingsSection from '../../settings/loadable-settings-section';
import CurrencyInformationForMethods from '../../components/currency-information-for-methods';
import { upeCapabilityStatuses, upeMethods } from '../constants';
import paymentMethodsMap from '../../payment-methods-map';
import ConfirmPaymentMethodActivationModal from 'wcpay/payment-methods/activation-modal';

const usePaymentMethodsCheckboxState = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const [ paymentMethodsState, setPaymentMethodsState ] = useState( {} );

	useEffect( () => {
		setPaymentMethodsState(
			// by default, all the checkboxes should be "checked"
			availablePaymentMethods
				.filter( ( method ) => upeMethods.includes( method ) )
				.reduce(
					( map, paymentMethod ) => ( {
						...map,
						[ paymentMethod ]: true,
					} ),
					{}
				)
		);
	}, [ availablePaymentMethods, setPaymentMethodsState ] );

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

const ContinueButton = ( { paymentMethodsState } ) => {
	const { setCompleted } = useContext( WizardTaskContext );
	const [
		initialEnabledPaymentMethodIds,
		updateEnabledPaymentMethodIds,
	] = useEnabledPaymentMethodIds();

	const { saveSettings, isSaving } = useSettings();

	const checkedPaymentMethods = useMemo(
		() =>
			Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => enabled && method )
				.filter( Boolean ),
		[ paymentMethodsState ]
	);

	const unCheckedPaymentMethods = Object.entries( paymentMethodsState )
		.map( ( [ method, enabled ] ) => ! enabled && method )
		.filter( Boolean );

	const handleContinueClick = useCallback( () => {
		// creating a separate callback, so that the main thread isn't blocked on click of the button
		const callback = async () => {
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

			setCompleted(
				{
					initialMethods: initialEnabledPaymentMethodIds,
				},
				'setup-complete'
			);
		};

		callback();
	}, [
		unCheckedPaymentMethods,
		checkedPaymentMethods,
		updateEnabledPaymentMethodIds,
		saveSettings,
		setCompleted,
		initialEnabledPaymentMethodIds,
	] );

	return (
		<Button
			isBusy={ isSaving }
			disabled={ isSaving || 1 > checkedPaymentMethods.length }
			onClick={ handleContinueClick }
			isPrimary
		>
			{ __( 'Add payment methods', 'woocommerce-payments' ) }
		</Button>
	);
};

const AddPaymentMethodsTask = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const paymentMethodStatuses = useGetPaymentMethodStatuses();
	const { isActive } = useContext( WizardTaskContext );

	// I am using internal state in this component
	// and committing the changes on `initialEnabledPaymentMethodIds` only when the "continue" button is clicked.
	// Otherwise a user could navigate to another page via soft-routing and the settings would be in un-saved state,
	// possibly causing errors.
	const [
		paymentMethodsState,
		handlePaymentMethodChange,
	] = usePaymentMethodsCheckboxState();

	const selectedMethods = useMemo(
		() =>
			Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => enabled && method )
				.filter( Boolean ),
		[ paymentMethodsState ]
	);

	const [ activationModalParams, handleActivationModalOpen ] = useState(
		null
	);

	const completeActivation = ( itemId ) => {
		paymentMethodsState[ itemId ] = true;
		handlePaymentMethodChange( paymentMethodsState );
		handleActivationModalOpen( null );
	};

	const getStatusAndRequirements = ( itemId ) => {
		const stripeKey = paymentMethodsMap[ itemId ].stripe_key;
		const stripeStatusContainer = paymentMethodStatuses[ stripeKey ] ?? [];
		if ( ! stripeStatusContainer ) {
			return {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [],
			};
		}
		return {
			status: stripeStatusContainer.status,
			requirements: stripeStatusContainer.requirements,
		};
	};

	const handleCheckClick = ( itemId, status ) => {
		if ( status ) {
			const statusAndRequirements = getStatusAndRequirements( itemId );
			if (
				'unrequested' === statusAndRequirements.status &&
				0 < statusAndRequirements.requirements.length
			) {
				handleActivationModalOpen( {
					id: itemId,
					requirements: statusAndRequirements.requirements,
				} );
			} else {
				completeActivation( itemId );
			}
		} else {
			paymentMethodsState[ itemId ] = false;
			handlePaymentMethodChange( paymentMethodsState );
		}
	};

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
								'based on their location. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-payments'
						),
						components: {
							learnMoreLink: (
								// eslint-disable-next-line max-len
								<ExternalLink href="https://woocommerce.com/document/payments/additional-payment-methods/#available-methods" />
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
									{ upeMethods.map(
										( key ) =>
											availablePaymentMethods.includes(
												key
											) && (
												<PaymentMethodCheckbox
													key={ key }
													checked={
														paymentMethodsState[
															key
														] &&
														upeCapabilityStatuses.INACTIVE !==
															getStatusAndRequirements(
																key
															).status
													}
													status={
														getStatusAndRequirements(
															key
														).status
													}
													onChange={ (
														name,
														status
													) => {
														handleCheckClick(
															name,
															status
														);
													} }
													name={ key }
												/>
											)
									) }
								</PaymentMethodCheckboxes>
							</LoadableSettingsSection>
						</LoadableBlock>
						{ activationModalParams && (
							<ConfirmPaymentMethodActivationModal
								onClose={ () => {
									handleActivationModalOpen( null );
								} }
								onConfirmClose={ () => {
									completeActivation(
										activationModalParams.id
									);
								} }
								requirements={
									activationModalParams.requirements
								}
								paymentMethod={ activationModalParams.id }
							/>
						) }
					</CardBody>
				</Card>
				<CurrencyInformationForMethods
					selectedMethods={ selectedMethods }
				/>
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
