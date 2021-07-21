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
	ExternalLink,
} from '@wordpress/components';
import { moreVertical, trash } from '@wordpress/icons';
import classNames from 'classnames';
import { addQueryArgs } from '@wordpress/url';

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
import WCPaySettingsContext from '../settings/wcpay-settings-context';
import Pill from '../components/pill';
import methodsConfiguration from '../payment-methods-map';

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

const UpeSetupBanner = () => {
	const [ , setIsUpeEnabled ] = useIsUpeEnabled();

	const handleEnableUpeClick = () => {
		setIsUpeEnabled( true ).then( () => {
			window.location.href = addQueryArgs( 'admin.php', {
				page: 'wc-admin',
				task: 'woocommerce-payments--additional-payment-methods',
			} );
		} );
	};

	return (
		<>
			<CardDivider />
			<CardBody className="payment-methods__express-checkouts">
				<Pill>{ __( 'Early access', 'woocommerce-payments' ) }</Pill>
				<h3>
					{ __(
						'Enable the new WooCommerce Payments checkout experience',
						'woocommerce-payments'
					) }
				</h3>
				<p>
					{ __(
						/* eslint-disable-next-line max-len */
						'Get early access to additional payment methods and an improved checkout experience, coming soon to WooCommerce Payments.',
						'woocommerce-payments'
					) }
				</p>

				<div className="payment-methods__express-checkouts-actions">
					<span className="payment-methods__express-checkouts-get-started">
						<Button isPrimary onClick={ handleEnableUpeClick }>
							{ __(
								'Enable in your store',
								'woocommerce-payments'
							) }
						</Button>
					</span>
					<ExternalLink href="https://docs.woocommerce.com/document/payments/">
						{ __( 'Learn more', 'woocommerce-payments' ) }
					</ExternalLink>
				</div>
			</CardBody>
		</>
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

	const {
		featureFlags: {
			upeSettingsPreview: isUpeSettingsPreviewEnabled,
			upe: isUpeFeatureEnabled,
		},
	} = useContext( WCPaySettingsContext );

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
					<CardHeader className="payment-methods__header">
						<h4 className="payment-methods__heading">
							Payment methods{ ' ' }
							<Pill>
								{ __( 'Early access', 'woocommerce-payments' ) }
							</Pill>
						</h4>
						<PaymentMethodsDropdownMenu />
					</CardHeader>
				) }

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
				{ isUpeSettingsPreviewEnabled && ! isUpeFeatureEnabled && (
					<UpeSetupBanner />
				) }

				{ isUpeEnabled && 1 < availablePaymentMethodIds.length ? (
					<>
						<CardDivider />
						<CardBody className="payment-methods__available-methods-container">
							<PaymentMethodsSelector />
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
