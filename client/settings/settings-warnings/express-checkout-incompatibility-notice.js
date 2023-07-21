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

const ExpressCheckoutIncompatibilityNotice = () => (
	<Notice
		status="warning"
		isDismissible={ false }
		className="express-checkout__notice express-checkout__apple-google-incompatibility-warning"
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
			{ interpolateComponents( {
				mixedString: __(
					/* eslint-disable-next-line max-len */
					'Apple Pay and Google Pay are not available in all countries and may not work for you. ' +
						/* eslint-disable-next-line max-len */
						"Please check {{appleLink}}Apple's{{/appleLink}} or {{googleLink}}Google's{{/googleLink}} website for more details.",
					'woocommerce-payments'
				),
				components: {
					googleLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							target="_blank"
							rel="noreferrer"
							href="https://support.google.com/googlepay/answer/12429287"
						/>
					),
					appleLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							target="_blank"
							rel="noreferrer"
							/* eslint-disable-next-line max-len */
							href="https://support.apple.com/en-us/HT207957"
						/>
					),
				},
			} ) }
		</span>
	</Notice>
);

export default ExpressCheckoutIncompatibilityNotice;
