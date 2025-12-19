/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import './styles.scss';
import {
	useEnabledPaymentMethodIds,
	usePaymentRequestEnabledSettings,
	useWooPayEnabledSettings,
} from '../data';
import PaymentMethodIcon from '../settings/payment-method-icon';
import PaymentDeleteIllustration from '../components/payment-delete-illustration';
import WooCardIcon from 'assets/images/cards/woo-card.svg?asset';
import ConfirmationModal from '../components/confirmation-modal';
import paymentMethodsMap from 'wcpay/payment-methods-map';
import { WooIconShort } from 'wcpay/payment-methods-icons';

const DisableConfirmationModal = ( { onClose, onConfirm } ) => {
	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();
	const [ isWooPayEnabled ] = useWooPayEnabledSettings();
	const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();
	const isStripeLinkEnabled = Boolean(
		enabledMethodIds.find( ( id ) => id === 'link' )
	);

	return (
		<ConfirmationModal
			title={ sprintf(
				/* translators: %s: WooPayments */
				__( 'Disable %s', 'woocommerce-payments' ),
				'WooPayments'
			) }
			onRequestClose={ onClose }
			actions={
				<>
					<Button
						onClick={ onConfirm }
						variant="primary"
						isDestructive
						__next40pxDefaultSize
					>
						Disable
					</Button>
					<Button
						onClick={ onClose }
						variant="secondary"
						__next40pxDefaultSize
					>
						Cancel
					</Button>
				</>
			}
		>
			<PaymentDeleteIllustration
				icon={ ( props ) => (
					<img src={ WooCardIcon } alt="WooCard" { ...props } />
				) }
			/>
			<p>
				{ interpolateComponents( {
					mixedString: sprintf(
						/* translators: %s: WooPayments */
						__(
							'%s is currently powering multiple popular payment methods on your store.' +
								' Without it, they will no longer be available to your customers, which may influence sales.',
							'woocommerce-payments'
						),
						'WooPayments'
					),
					components: {
						strong: <strong />,
					},
				} ) }
			</p>
			<p>
				{ sprintf(
					/* translators: %s: WooPayments */
					__(
						'Payment methods that need %s:',
						'woocommerce-payments'
					),
					'WooPayments'
				) }
			</p>
			<ul className="disable-confirmation-modal__payment-methods-list">
				{ enabledMethodIds
					.filter( ( methodId ) => methodId !== 'link' )
					.map( ( methodId ) => (
						<li key={ methodId }>
							<PaymentMethodIcon
								Icon={ paymentMethodsMap[ methodId ].icon }
								label={ paymentMethodsMap[ methodId ].label }
							/>
						</li>
					) ) }
				{ isPaymentRequestEnabled && (
					<>
						<li>
							<PaymentMethodIcon
								Icon={ paymentMethodsMap.google_pay.icon }
								label={ paymentMethodsMap.google_pay.label }
							/>
						</li>
						<li>
							<PaymentMethodIcon
								Icon={ paymentMethodsMap.apple_pay.icon }
								label={ paymentMethodsMap.apple_pay.label }
							/>
						</li>
					</>
				) }
				{ isStripeLinkEnabled && (
					<li>
						<PaymentMethodIcon
							Icon={ paymentMethodsMap.link.icon }
							label={ paymentMethodsMap.link.label }
						/>
					</li>
				) }
				{ isWooPayEnabled && (
					<li>
						<PaymentMethodIcon
							Icon={ WooIconShort }
							label={ __( 'WooPay', 'woocommerce-payments' ) }
						/>
					</li>
				) }
			</ul>
			<p className="no-padding">
				{ interpolateComponents( {
					mixedString: sprintf(
						/* translators: %s: WooPayments */
						__(
							'{{strong}}Need help?{{/strong}} ' +
								'Learn more about {{wooCommercePaymentsLink}}%s{{/wooCommercePaymentsLink}} or ' +
								'{{contactSupportLink}}contact WooCommerce Support{{/contactSupportLink}}.',
							'woocommerce-payments'
						),
						'WooPayments'
					),
					components: {
						strong: <strong />,
						wooCommercePaymentsLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a href="https://woocommerce.com/document/woopayments/" />
						),
						contactSupportLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a href="https://woocommerce.com/my-account/create-a-ticket/?select=5278104" />
						),
					},
				} ) }
			</p>
		</ConfirmationModal>
	);
};

export default DisableConfirmationModal;
