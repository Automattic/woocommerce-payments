/** @format */
/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardDivider,
	CardHeader,
	DropdownMenu,
	Notice,
} from '@wordpress/components';
import { moreVertical, trash } from '@wordpress/icons';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'data';

import useIsUpeEnabled from '../settings/wcpay-upe-toggle/hook.js';
import WcPayUpeContext from '../settings/wcpay-upe-toggle/context';
import PaymentMethodsList from 'components/payment-methods-list';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import PaymentMethodsSelector from 'settings/payment-methods-selector';
import CreditCardIcon from '../gateway-icons/credit-card';
import GiropayIcon from '../gateway-icons/giropay';
import SofortIcon from '../gateway-icons/sofort';
import SepaIcon from '../gateway-icons/sepa';

const methodsConfiguration = {
	card: {
		id: 'card',
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		Icon: CreditCardIcon,
	},
	giropay: {
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
		Icon: GiropayIcon,
	},
	sofort: {
		id: 'sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, and Netherlands.',
			'woocommerce-payments'
		),
		Icon: SofortIcon,
	},
	sepa_debit: {
		id: 'sepa_debit',
		label: __( 'Direct debit payment', 'woocommerce-payments' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
		Icon: SepaIcon,
	},
};

// @todo - remove once #2174 is merged and use real banner instead.
function UpeSetupBanner() {
	const [ , setIsUpeEnabled ] = useIsUpeEnabled();
	return (
		<>
			<p>UPE IS DISABLED!!!</p>
			<Button
				label="Enable UPE"
				isPrimary
				onClick={ () => setIsUpeEnabled( true ) }
			>
				{ __( 'Enable UPE', 'woocommerce-payments' ) }
			</Button>
		</>
	);
}

function PaymentMethodsDropdownMenu() {
	const [ , setIsUpeEnabled ] = useIsUpeEnabled();
	return (
		<DropdownMenu
			icon={ moreVertical }
			label={ __( 'Add Feedback or Disable', 'woocommerce-payments' ) }
			controls={ [
				{
					title: __( 'Provide Feedback', 'woocommerce-payments' ),
					icon: 'megaphone',
					onClick: () => console.log( 'Provide Feedback' ),
				},
				{
					title: 'Disable',
					icon: trash,
					onClick: () => setIsUpeEnabled( false ),
				},
			] }
		/>
	);
}

const UpeDisableError = () => {
	return (
		<Notice status="error" isDismissible={ true }>
			{ __(
				'Error disabling payment methods. Please try again.',
				'woocommerce-payments'
			) }
		</Notice>
	);
};

const PaymentMethods = () => {
	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();
	const enabledMethods = availablePaymentMethodIds
		.filter( ( method ) => enabledMethodIds.includes( method ) )
		.map( ( methodId ) => methodsConfiguration[ methodId ] );

	const disabledMethods = availablePaymentMethodIds
		.filter( ( methodId ) => ! enabledMethodIds.includes( methodId ) )
		.map( ( methodId ) => methodsConfiguration[ methodId ] );

	const handleDeleteClick = ( itemId ) => {
		updateEnabledMethodIds(
			enabledMethodIds.filter( ( id ) => id !== itemId )
		);
	};

	const { isUpeEnabled, status } = useContext( WcPayUpeContext );

	return (
		<>
			{ 'error' === status && <UpeDisableError /> }
			<Card
				className={ classNames( 'payment-methods', {
					'is-loading': 'pending' === status,
				} ) }
			>
				{ isUpeEnabled && (
					<CardHeader
						size={ null }
						className="payment-methods-header"
					>
						<PaymentMethodsDropdownMenu />
					</CardHeader>
				) }
				<CardBody>
					{ isUpeEnabled ? (
						<PaymentMethodsList className="payment-methods__enabled-methods">
							{ enabledMethods.map(
								( { id, label, description, Icon } ) => (
									<PaymentMethod
										key={ id }
										Icon={ Icon }
										onDeleteClick={
											1 < enabledMethods.length
												? handleDeleteClick
												: undefined
										}
										id={ id }
										label={ label }
										description={ description }
									/>
								)
							) }
						</PaymentMethodsList>
					) : (
						<UpeSetupBanner />
					) }
				</CardBody>
				{ isUpeEnabled && 1 < availablePaymentMethodIds.length ? (
					<>
						<CardDivider />
						<CardBody className="payment-methods__available-methods-container">
							<PaymentMethodsSelector className="payment-methods__add-payment-method" />
							<ul className="payment-methods__available-methods">
								{ disabledMethods.map(
									( { id, label, Icon } ) => (
										<li
											key={ id }
											className={ classNames(
												'payment-methods__available-method',
												{
													'has-icon-border':
														'card' !== id,
												}
											) }
											aria-label={ label }
										>
											<Icon height="24" width="38" />
										</li>
									)
								) }
							</ul>
						</CardBody>
					</>
				) : null }
			</Card>
		</>
	);
};

export default PaymentMethods;
