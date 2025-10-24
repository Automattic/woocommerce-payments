/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, ExternalLink } from '@wordpress/components';
import React from 'react';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import NotificationsEmailInput from './notifications-email-input';

export const NotificationSettingsDescription: React.FC = () => (
	<>
		<h2>{ __( 'Notifications', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Configure how you receive important alerts about your WooPayments account.',
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/woopayments/">
			{ __( 'Learn more', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const NotificationSettings: React.FC = () => {
	return (
		<Card className="notification-settings">
			<CardBody className="wcpay-card-body">
				<NotificationsEmailInput />
			</CardBody>
		</Card>
	);
};

export default NotificationSettings;
