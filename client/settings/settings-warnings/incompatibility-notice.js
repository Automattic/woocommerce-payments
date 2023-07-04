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

const WooPayIncompatibilityNotice = () => (
	<Notice
		status="warning"
		isDismissible={ false }
		className="express-checkout__notice"
	>
		<span>
			<NoticeOutlineIcon
				style={ {
					color: '#F0B849',
					fill: 'currentColor',
					marginBottom: '-5px',
					marginRight: '16px',
				} }
				size={ 20 }
			/>
			{ interpolateComponents( {
				mixedString: __(
					/* eslint-disable-next-line max-len */
					'One or more of your extensions are incompatible with WooPay.' +
						' ' +
						'{{learnMoreLink}}Learn More{{/learnMoreLink}}',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							target="_blank"
							rel="noreferrer"
							href="https://woocommerce.com/document/woopay-merchant-documentation/#compatibility"
						/>
					),
				},
			} ) }
		</span>
	</Notice>
);

export default WooPayIncompatibilityNotice;
