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
import './style.scss';

interface NotificationSettingsProps {
	setNotificationEmailValid?: ( valid: boolean ) => void;
}

export const NotificationSettingsDescription: React.FC = () => (
	<>
		<h2>{ __( 'Account notifications', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Receive important notifications about your WooPayments account.',
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/woopayments/settings-guide/#account-notifications">
			{ __( 'Learn more', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const NotificationSettings: React.FC< NotificationSettingsProps > = ( {
	setNotificationEmailValid,
} ) => {
	return (
		<Card className="notification-settings">
			<CardBody className="wcpay-card-body">
				<NotificationsEmailInput
					onValidationChange={ setNotificationEmailValid }
				/>
			</CardBody>
		</Card>
	);
};

export default NotificationSettings;
