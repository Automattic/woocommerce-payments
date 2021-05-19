/**
 * External dependencies
 */
import React, { useCallback, useContext, useState } from 'react';
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
import './add-payment-methods-task.scss';

const useGetCountryName = () => {
	const baseLocation = useSelect(
		( select ) =>
			select( 'wc/admin/settings' ).getSetting(
				'wc_admin',
				'baseLocation'
			),
		[]
	);

	const countries = useSelect(
		( select ) =>
			select( 'wc/admin/settings' ).getSetting( 'wc_admin', 'countries' ),
		[]
	);

	return countries[ baseLocation.country ];
};

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

	return [ paymentMethodsState, handleChange ];
};

const AddPaymentMethodsTask = () => {
	const { setCompleted } = useContext( WizardTaskContext );

	const handleContinueClick = useCallback( () => {
		setCompleted( true, 'setup-complete' );
	}, [ setCompleted ] );

	const countryName = useGetCountryName();

	const [
		paymentMethodsState,
		handlePaymentMethodChange,
	] = usePaymentMethodsCheckboxState();

	const [ isWalletsChecked, setWalletsChecked ] = useState( false );

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
							<a href="admin.php?page=wc-settings">
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
							<PaymentMethodCheckbox
								checked={
									paymentMethodsState.woocommerce_payments_giropay
								}
								onChange={ handlePaymentMethodChange }
								fees="missing fees"
								name="woocommerce_payments_giropay"
							/>
							<PaymentMethodCheckbox
								checked={
									paymentMethodsState.woocommerce_payments_sofort
								}
								onChange={ handlePaymentMethodChange }
								fees="missing fees"
								name="woocommerce_payments_sofort"
							/>
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
							<PaymentMethodCheckbox
								checked={
									paymentMethodsState.woocommerce_payments_sepa
								}
								onChange={ handlePaymentMethodChange }
								fees="missing fees"
								name="woocommerce_payments_sepa"
							/>
							<PaymentMethodCheckbox
								checked={ paymentMethodsState[ 'apple-pay' ] }
								onChange={ handlePaymentMethodChange }
								fees="missing fees"
								name="apple-pay"
							/>
							<PaymentMethodCheckbox
								checked={ paymentMethodsState[ 'google-pay' ] }
								onChange={ handlePaymentMethodChange }
								fees="missing fees"
								name="google-pay"
							/>
						</PaymentMethodCheckboxes>
					</CardBody>
				</Card>
				<div className="wcpay-wizard-task__description-element">
					<CheckboxControl
						checked={ isWalletsChecked }
						onChange={ setWalletsChecked }
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
				<Button onClick={ handleContinueClick } isPrimary>
					{ __( 'Continue', 'woocommerce-payments' ) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddPaymentMethodsTask;
