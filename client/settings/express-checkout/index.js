/** @format **/

/**
 * External dependencies
 */
import { Card } from '@wordpress/components';
import { useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import './style.scss';
import WooPayExpressCheckoutItem from './woopay-item';
import AppleGooglePayExpressCheckoutItem from './apple-google-pay-item';
import LinkExpressCheckoutItem from './link-item';
import AmazonPayExpressCheckoutItem from './amazon-pay-item';
import WCPaySettingsContext from '../wcpay-settings-context';
import { useGetAvailablePaymentMethodIds } from 'wcpay/data/settings';

export const ExpressCheckoutControl = () => {
	const {
		featureFlags: { amazonPay: isAmazonPayEligible },
	} = useContext( WCPaySettingsContext );
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	return (
		<div className="express-checkouts">
			<ul className="express-checkouts-list">
				<WooPayExpressCheckoutItem />
				<AppleGooglePayExpressCheckoutItem />
				<LinkExpressCheckoutItem />
				{ isAmazonPayEligible &&
					availablePaymentMethodIds.includes( 'amazon_pay' ) && (
						<AmazonPayExpressCheckoutItem />
					) }
			</ul>
		</div>
	);
};

const ExpressCheckout = () => (
	<Card className="express-checkouts">
		<CardBody size={ 0 }>
			<ExpressCheckoutControl />
		</CardBody>
	</Card>
);

export default ExpressCheckout;
