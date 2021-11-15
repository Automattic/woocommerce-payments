/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import './styles.scss';
import { useGetAvailablePaymentMethodIds } from '../data';
import PaymentMethodIcon from '../settings/payment-method-icon';
import PaymentDeleteIllustration from '../components/payment-delete-illustration';
import WooCardIcon from '../gateway-icons/woo-card';
import ConfirmationModal from '../components/confirmation-modal';

const DisableConfirmationModal = ( { onClose, onConfirm } ) => {
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	return (
		<ConfirmationModal
			title={ __(
				'Disable WooCommerce Payments',
				'woocommerce-payments'
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
			<PaymentDeleteIllustration Icon={ WooCardIcon } />
			<p>
				{ interpolateComponents( {
					mixedString: __(
						'WooCommerce Payments is currently powering multiple popular payment methods on your store. ' +
							'Without it, they will no longer be available to your customers ' +
							'which may {{strong}}influence conversions and sales on your store.{{/strong}}',
						'woocommerce-payments'
					),
					components: {
						strong: <strong />,
					},
				} ) }
			</p>
			<p>
				{ interpolateComponents( {
					mixedString: __(
						'You can enable WooCommerce Payments again at any time in {{settingsLink}}settings{{/settingsLink}}.',
						'woocommerce-payments'
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
					{ __(
						'Payment methods that need WooCommerce Payments:',
						'woocommerce-payments'
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
					mixedString: __(
						'{{strong}}Need help?{{/strong}} ' +
							'Learn more about {{wooCommercePaymentsLink}}WooCommerce Payments{{/wooCommercePaymentsLink}} or ' +
							'{{contactSupportLink}}contact WooCommerce Support{{/contactSupportLink}}.',
						'woocommerce-payments'
					),
					components: {
						strong: <strong />,
						wooCommercePaymentsLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a href="https://woocommerce.com/document/payments/" />
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
