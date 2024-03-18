/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { Notice } from '@wordpress/components';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';
import './style.scss';

const IncompatibilityNotice = ( { message, learnMoreLinkHref } ) => (
	<Notice
		status="warning"
		isDismissible={ false }
		className="express-checkout__notice express-checkout__incompatibility-warning"
	>
		<span>
			<NoticeOutlineIcon
				style={ {
					color: '#F0B849',
					fill: 'currentColor',
					marginBottom: '-5px',
					marginRight: '10px',
				} }
				size={ 20 }
			/>
		</span>
		<span>
			{ message }
			<br />
			{ interpolateComponents( {
				mixedString: __(
					'{{learnMoreLink}}Learn More{{/learnMoreLink}}',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							target="_blank"
							rel="noreferrer"
							href={ learnMoreLinkHref }
						/>
					),
				},
			} ) }
		</span>
	</Notice>
);

export const WooPayIncompatibilityNotice = () => (
	<IncompatibilityNotice
		message={ __(
			'One or more of your extensions are incompatible with WooPay.',
			'woocommerce-payments'
		) }
		learnMoreLinkHref="https://woo.com/document/woopay-merchant-documentation/#compatibility"
	/>
);

export const ExpressCheckoutIncompatibilityNotice = () => (
	<IncompatibilityNotice
		message={ __(
			'One or more of your extensions alters checkout fields. This might cause issues with this payment method.',
			'woocommerce-payments'
		) }
		// eslint-disable-next-line max-len
		learnMoreLinkHref="https://woo.com/document/woopayments/payment-methods/apple-pay-and-google-pay-compatibility/#faq-extra-fields-on-checkout"
	/>
);
