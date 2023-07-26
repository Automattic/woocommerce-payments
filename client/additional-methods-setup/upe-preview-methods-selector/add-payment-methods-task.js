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
import {
	Button,
	Card,
	CardBody,
	ExternalLink,
	CardDivider,
} from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

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
	// For UPE, the card payment method is required and always active.
	const [ paymentMethodsState, setPaymentMethodsState ] = useState( {
		card: true,
	} );

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
			{ __( 'Continue', 'woocommerce-payments' ) }
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
	] = usePaymentMethodsCheckboxState( availablePaymentMethods );

	useEffect( () => {
		availablePaymentMethods
			.filter( ( method ) => upeMethods.includes( method ) )
			.forEach( ( method ) => {
				handlePaymentMethodChange( method, false );
			} );
	}, [ availablePaymentMethods, handlePaymentMethodChange ] );

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

	const completeActivation = ( method ) => {
		handlePaymentMethodChange( method, true );
		handleActivationModalOpen( null );
	};

	const getStatusAndRequirements = ( method ) => {
		const stripeKey = paymentMethodsMap[ method ].stripe_key;
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

	const handleCheckClick = ( method, status ) => {
		if ( status ) {
			const statusAndRequirements = getStatusAndRequirements( method );
			if (
				'unrequested' === statusAndRequirements.status &&
				0 < statusAndRequirements.requirements.length
			) {
				handleActivationModalOpen( {
					id: method,
					requirements: statusAndRequirements.requirements,
				} );
			} else {
				completeActivation( method );
			}
		} else {
			handlePaymentMethodChange( method, false );
		}
	};

	const prepareUpePaymentMethods = ( upeMethodIds ) => {
		return upeMethodIds.map(
			( key ) =>
				availablePaymentMethods.includes( key ) && (
					<PaymentMethodCheckbox
						key={ key }
						checked={
							paymentMethodsState[ key ] &&
							upeCapabilityStatuses.INACTIVE !==
								getStatusAndRequirements( key ).status
						}
						status={ getStatusAndRequirements( key ).status }
						onChange={ ( name, status ) => {
							handleCheckClick( name, status );
						} }
						name={ key }
					/>
				)
		);
	};

	const availableBuyNowPayLaterUpeMethods = upeMethods.filter(
		( id ) =>
			paymentMethodsMap[ id ].allows_pay_later &&
			availablePaymentMethods.includes( id )
	);

	return (
		<WizardTaskItem
			className="add-payment-methods-task"
			title={ __(
				'Enable additional payment methods',
				'woocommerce-payments'
			) }
			index={ 1 }
		>
			<CollapsibleBody>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ interpolateComponents( {
						mixedString: __(
							'Increase your store’s conversion by offering your customers' +
								' preferred and convenient payment methods on checkout.',
							'woocommerce-payments'
						),
						components: {
							learnMoreLink: (
								// eslint-disable-next-line max-len
								<ExternalLink href="https://woocommerce.com/document/woocommerce-payments/payment-methods/additional-payment-methods/" />
							),
						},
					} ) }
				</p>
				<Card
					className="add-payment-methods-task__payment-selector-wrapper"
					size="small"
				>
					<CardBody>
						{ /* eslint-disable-next-line max-len */ }
						<p className="add-payment-methods-task__payment-selector-title wcpay-wizard-task__description-element">
							{ __(
								'Popular in your country',
								'woocommerce-payments'
							) }
						</p>
						<LoadableBlock numLines={ 10 } isLoading={ ! isActive }>
							<LoadableSettingsSection numLines={ 10 }>
								<PaymentMethodCheckboxes>
									<PaymentMethodCheckbox
										key="card"
										checked={ paymentMethodsState.card }
										// The card payment method is required when UPE is active and it can't be deactivated.
										required={ true }
										locked={ true }
										status={
											getStatusAndRequirements( 'card' )
												.status
										}
										name="card"
									/>
									{ prepareUpePaymentMethods(
										upeMethods.filter(
											( id ) =>
												! paymentMethodsMap[ id ]
													.allows_pay_later
										)
									) }
								</PaymentMethodCheckboxes>
							</LoadableSettingsSection>
						</LoadableBlock>
					</CardBody>
					{ wcpaySettings.isBnplAffirmAfterpayEnabled &&
						0 < availableBuyNowPayLaterUpeMethods.length && (
							<>
								<CardDivider />
								<CardBody>
									<p className="add-payment-methods-task__payment-selector-title wcpay-wizard-task__description-element">
										{ __(
											'Buy Now, Pay Later',
											'woocommerce-payments'
										) }
									</p>
									<LoadableBlock
										numLines={ 10 }
										isLoading={ ! isActive }
									>
										<LoadableSettingsSection
											numLines={ 10 }
										>
											<PaymentMethodCheckboxes>
												{ prepareUpePaymentMethods(
													availableBuyNowPayLaterUpeMethods
												) }
											</PaymentMethodCheckboxes>
										</LoadableSettingsSection>
									</LoadableBlock>
								</CardBody>
							</>
						) }
					{ activationModalParams && (
						<ConfirmPaymentMethodActivationModal
							onClose={ () => {
								handleActivationModalOpen( null );
							} }
							onConfirmClose={ () => {
								completeActivation( activationModalParams.id );
							} }
							requirements={ activationModalParams.requirements }
							paymentMethod={ activationModalParams.id }
						/>
					) }
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
