/** @format */

/**
 * External dependencies
 */
import React, { useContext, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardDivider,
	CardHeader,
	DropdownMenu,
	ExternalLink,
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import classNames from 'classnames';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
} from 'wcpay/data';

import useIsUpeEnabled from '../settings/wcpay-upe-toggle/hook.js';
import WcPayUpeContext from '../settings/wcpay-upe-toggle/context';

// Survey modal imports.
import WcPaySurveyContextProvider from '../settings/survey-modal/provider';
import SurveyModal from '../settings/survey-modal';
import DisableUPEModal from '../settings/disable-upe-modal';
import PaymentMethodsList from 'components/payment-methods-list';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import PaymentMethodsSelector from 'settings/payment-methods-selector';
import WCPaySettingsContext from '../settings/wcpay-settings-context';
import Pill from '../components/pill';
import methodsConfiguration from '../payment-methods-map';
import CardBody from '../settings/card-body';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';

const PaymentMethodsDropdownMenu = ( { setOpenModal } ) => {
	return (
		<DropdownMenu
			icon={ moreVertical }
			label={ __( 'Add feedback or disable', 'woocommerce-payments' ) }
			controls={ [
				{
					title: __( 'Provide feedback', 'woocommerce-payments' ),
					onClick: () => setOpenModal( 'survey' ),
				},
				{
					title: 'Disable',
					onClick: () => setOpenModal( 'disable' ),
				},
			] }
		/>
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
					<ExternalLink href="https://woocommerce.com/document/payments/additional-payment-methods/">
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

	const paymentMethodStatuses = useGetPaymentMethodStatuses();

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
		featureFlags: { upeSettingsPreview: isUpeSettingsPreviewEnabled },
	} = useContext( WCPaySettingsContext );

	const { isUpeEnabled, status } = useContext( WcPayUpeContext );
	const [ openModalIdentifier, setOpenModalIdentifier ] = useState( '' );

	return (
		<>
			{ 'disable' === openModalIdentifier ? (
				<DisableUPEModal
					setOpenModal={ setOpenModalIdentifier }
					triggerAfterDisable={ () =>
						setOpenModalIdentifier( 'survey' )
					}
				/>
			) : null }
			{ 'survey' === openModalIdentifier ? (
				<WcPaySurveyContextProvider>
					<SurveyModal
						setOpenModal={ setOpenModalIdentifier }
						surveyKey="wcpay-upe-disable-early-access"
						surveyQuestion="why-disable"
					/>
				</WcPaySurveyContextProvider>
			) : null }

			<Card
				className={ classNames( 'payment-methods', {
					'is-loading': 'pending' === status,
				} ) }
			>
				{ isUpeEnabled && (
					<CardHeader className="payment-methods__header">
						<h4 className="payment-methods__heading">
							<span>
								{ __(
									'Payment methods',
									'woocommerce-payments'
								) }
							</span>{ ' ' }
							<Pill>
								{ __( 'Early access', 'woocommerce-payments' ) }
							</Pill>
						</h4>
						<PaymentMethodsDropdownMenu
							setOpenModal={ setOpenModalIdentifier }
						/>
					</CardHeader>
				) }

				<CardBody size={ null }>
					<PaymentMethodsList className="payment-methods__enabled-methods">
						{ enabledMethods.map(
							( { id, label, description, Icon } ) => (
								<PaymentMethod
									key={ id }
									Icon={ Icon }
									status={
										paymentMethodStatuses[
											methodsConfiguration[ id ]
												.stripe_key
										].status ??
										upeCapabilityStatuses.UNREQUESTED
									}
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
				{ isUpeSettingsPreviewEnabled && ! isUpeEnabled && (
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
