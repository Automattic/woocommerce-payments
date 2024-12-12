/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import BannerNotice from '../banner-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';
import { recordEvent } from 'wcpay/tracks';
import { ClickTooltip } from 'wcpay/components/tooltip';
import ErrorBoundary from 'wcpay/components/error-boundary';
import SetupLivePaymentsModal from './modal';
import './style.scss';

interface Props {
	from: string;
	source: string;
}

const SandboxModeSwitchToLiveNotice: React.FC< Props > = ( {
	from,
	source,
} ) => {
	const [ livePaymentsModalVisible, setLivePaymentsModalVisible ] = useState(
		false
	);

	const handleCtaClick = () => {
		recordEvent( 'wcpay_setup_live_payments_modal_open', {
			from,
			source,
		} );

		setLivePaymentsModalVisible( true );
	};

	return (
		<>
			<BannerNotice
				status="warning"
				className="sandbox-mode-notice"
				isDismissible={ false }
			>
				{ interpolateComponents( {
					mixedString: sprintf(
						/* translators: %1$s: WooPayments */
						__(
							// eslint-disable-next-line max-len
							"{{div}}{{strong}}You're using a test account.{{/strong}} To accept payments from shoppers, {{switchToLiveLink}}activate your %1$s account.{{/switchToLiveLink}}{{/div}}{{learnMoreIcon/}}",
							'woocommerce-payments'
						),
						'WooPayments'
					),
					components: {
						div: <div />,
						strong: <strong />,
						learnMoreIcon: (
							<ClickTooltip
								buttonIcon={ <HelpOutlineIcon /> }
								buttonLabel={ __(
									'Learn more about sandbox mode',
									'woocommerce-payments'
								) }
								maxWidth={ '250px' }
								content={
									<>
										{ interpolateComponents( {
											mixedString: sprintf(
												/* translators: %1$s: WooPayments */
												__(
													// eslint-disable-next-line max-len
													'Sandbox mode gives you access to all %1$s features while checkout transactions are simulated. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
													'woocommerce-payments'
												),
												'WooPayments'
											),
											components: {
												learnMoreLink: (
													// eslint-disable-next-line jsx-a11y/anchor-has-content
													<Link
														href={
															// eslint-disable-next-line max-len
															'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/sandbox-mode/'
														}
														target="_blank"
														rel="noreferrer"
														type="external"
														onClick={ () =>
															recordEvent(
																'wcpay_overview_sandbox_mode_learn_more_clicked'
															)
														}
													/>
												),
											},
										} ) }
									</>
								}
							/>
						),
						switchToLiveLink: (
							<Button variant="link" onClick={ handleCtaClick } />
						),
					},
				} ) }
			</BannerNotice>
			{ livePaymentsModalVisible && (
				<ErrorBoundary>
					<SetupLivePaymentsModal
						from={ from }
						source={ source }
						onClose={ () => setLivePaymentsModalVisible( false ) }
					/>
				</ErrorBoundary>
			) }
		</>
	);
};

export default SandboxModeSwitchToLiveNotice;
