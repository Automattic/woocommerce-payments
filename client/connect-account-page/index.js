/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import Page from 'components/page';
import HeroImage from './hero-image';

const ConnectAccountPage = () => {
	return (
		<Page isNarrow className="connect-account">
			<Card className="connect-account__card">
				<HeroImage />
				<h2>
					{ wcpaySettings.strings.setupHeadings.map( ( heading, i ) => ( <span key={ i }>{ heading }<br /></span> ) ) }
				</h2>
				{ ! wcpaySettings.onBoardingDisabled ? (
				<>
				<p dangerouslySetInnerHTML={ { __html: wcpaySettings.strings.setupTerms } } />
				<hr className="full-width" />
				<p>
					<Button isPrimary isLarge href={ wcpaySettings.connectUrl }>{ wcpaySettings.strings.setupGetStarted }</Button>
				</p>
				</>
				) : (
				<p>
					{ __( 'We\'ve temporarily paused new account creation.', 'woocommmerce-payments' ) }
					<br />
					{ __( 'We\'ll notify you when we resume!', 'woocommmerce-payments' ) }
				</p>
				) }
			</Card>
		</Page>
	);
};

export default ConnectAccountPage;
