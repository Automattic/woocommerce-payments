/** @format **/

/**
 * External dependencies
 */
import { WPCard as Card } from 'hack-week-2024-components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import './style.scss';
import WooPayExpressCheckoutItem from './woopay-item';
import AppleGooglePayExpressCheckoutItem from './apple-google-pay-item';
import LinkExpressCheckoutItem from './link-item';

const ExpressCheckout = () => {
	return (
		<Card className="express-checkouts">
			<CardBody>
				<ul className="express-checkouts-list">
					<WooPayExpressCheckoutItem />
					<AppleGooglePayExpressCheckoutItem />
					<LinkExpressCheckoutItem />
				</ul>
			</CardBody>
		</Card>
	);
};

export default ExpressCheckout;
