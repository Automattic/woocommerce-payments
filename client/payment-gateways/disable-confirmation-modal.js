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
import { useGetAvailablePaymentMethodIds } from '../data';
import PaymentMethodIcon from '../settings/payment-method-icon';
import PaymentDeleteIllustration from '../components/payment-delete-illustration';
import WooCardIcon from 'assets/images/cards/woo-card.svg?asset';
import ConfirmationModal from '../components/confirmation-modal';

const DisableConfirmationModal = ( { onClose, onConfirm } ) => {
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

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
					<Button onClick={ onConfirm } isPrimary isDestructive>
						Disable
					</Button>
					<Button onClick={ onClose } isSecondary>
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
							'%s is currently powering multiple popular payment methods on your store. ' +
								'Without it, they will no longer be available to your customers ' +
								'which may {{strong}}influence conversions and sales on your store.{{/strong}}',
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
				{ interpolateComponents( {
					mixedString: sprintf(
						/* translators: %s: WooPayments */
						__(
							'You can enable %s again at any time in {{settingsLink}}settings{{/settingsLink}}.',
							'woocommerce-payments'
						),
						'WooPayments'
					),
					components: {
						settingsLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a href="admin.php?page=wc-settings&tab=checkout&section" />
						),
					},
				} ) }
			</p>
			<p>
				<strong>
					{ sprintf(
						/* translators: %s: WooPayments */
						__(
							'Payment methods that need %s:',
							'woocommerce-payments'
						),
						'WooPayments'
					) }
				</strong>
			</p>
			<ul className="disable-confirmation-modal__payment-methods-list">
				{ availablePaymentMethodIds.map( ( methodId ) => (
					<li key={ methodId }>
						<PaymentMethodIcon name={ methodId } showName />
					</li>
				) ) }
			</ul>
			<p>
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
