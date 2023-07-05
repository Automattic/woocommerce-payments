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

const WooPayIncompatibilityNotice = () => (
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
			{ __(
				'One or more of your extensions are incompatible with WooPay.'
			) }
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
							href="https://woocommerce.com/document/woopay-merchant-documentation/#compatibility"
						/>
					),
				},
			} ) }
		</span>
	</Notice>
);

export default WooPayIncompatibilityNotice;
