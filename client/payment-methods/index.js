/** @format */
/* eslint-disable max-len */

/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardDivider,
	ExternalLink,
	Button,
} from '@wordpress/components';
import classNames from 'classnames';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'data';
import PaymentMethodsList from 'components/payment-methods-list';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import PaymentMethodsSelector from 'settings/payment-methods-selector';
import CreditCardIcon from '../gateway-icons/credit-card';
import GiropayIcon from '../gateway-icons/giropay';
import SofortIcon from '../gateway-icons/sofort';
import SepaIcon from '../gateway-icons/sepa';
import WCPaySettingsContext from '../settings/wcpay-settings-context';
import { NAMESPACE } from '../data/constants';

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

	const handleEnableUpeClick = () => {
		return apiFetch( {
			path: `${ NAMESPACE }/upe_flag_toggle`,
			method: 'POST',
			// eslint-disable-next-line camelcase
			data: { is_upe_enabled: true },
		} )
			.then( () => {
				console.log( 'success' );
				window.location.href = addQueryArgs( 'admin.php', {
					page: 'wc-admin',
					task: 'woocommerce-payments--additional-payment-methods',
				} );
			} )
			.catch( () => {
				// error handling
			} );
	};

	const {
		featureFlags: {
			upeSettingsPreview: isUPESettingsPreviewEnabled,
			upe: isUPEEnabled,
		},
	} = useContext( WCPaySettingsContext );

	return (
		<>
			<Card className="payment-methods">
				<CardBody size={ null }>
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
				</CardBody>

				{ isUPESettingsPreviewEnabled && ! isUPEEnabled && (
					<>
						<CardDivider />
						<CardBody className="payment-methods__express-checkouts">
							<span className="payment-methods__pill">
								{ __( 'Early access', 'woocommerce-payments' ) }
							</span>
							<h3>
								{ __(
									'Enable the new WooCommerce Payments checkout experience',
									'woocommerce-payments'
								) }
							</h3>
							<p>
								{ __(
									'Get early access to additional payment methods and an improved checkout experience, coming soon to WooCommerce Payments.',
									'woocommerce-payments'
								) }
							</p>

							<div className="payment-methods__express-checkouts-actions">
								<span className="payment-methods__express-checkouts-get-started">
									<Button
										isPrimary
										onClick={ handleEnableUpeClick }
									>
										{ __(
											'Enable in your store',
											'woocommerce-payments'
										) }
									</Button>
								</span>
								<ExternalLink href="https://docs.woocommerce.com/document/payments/">
									{ __(
										'Learn more',
										'woocommerce-payments'
									) }
								</ExternalLink>
							</div>
						</CardBody>
					</>
				) }

				{ 1 < availablePaymentMethodIds.length ? (
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
