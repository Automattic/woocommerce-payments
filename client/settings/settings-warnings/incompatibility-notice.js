/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';

const IncompatibilityNotice = ( { message, learnMoreLinkHref } ) => (
	<InlineNotice status="warning" icon={ true } isDismissible={ false }>
		{ message }{ ' ' }
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
	</InlineNotice>
);

export const WooPayIncompatibilityNotice = () => (
	<IncompatibilityNotice
		message={ __(
			'One or more of your extensions are incompatible with WooPay.',
			'woocommerce-payments'
		) }
		learnMoreLinkHref="https://woocommerce.com/document/woopay-merchant-documentation/#compatibility"
	/>
);
