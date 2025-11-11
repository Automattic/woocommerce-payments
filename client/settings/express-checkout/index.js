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

const ExpressCheckout = () => {
	const {
		featureFlags: { amazonPay: isAmazonPayEligible },
	} = useContext( WCPaySettingsContext );

	return (
		<Card className="express-checkouts">
			<CardBody size={ 0 }>
				<ul className="express-checkouts-list">
					<WooPayExpressCheckoutItem />
					<AppleGooglePayExpressCheckoutItem />
					<LinkExpressCheckoutItem />
					{ isAmazonPayEligible && <AmazonPayExpressCheckoutItem /> }
				</ul>
			</CardBody>
		</Card>
	);
};

export default ExpressCheckout;
