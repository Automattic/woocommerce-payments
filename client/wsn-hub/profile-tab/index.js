/**
 * Profile tab stub.
 *
 * Real content (branding card with logo-override, hero banner, contact email,
 * refund page picker, free-shipping derivation) lands in RSM-2481.
 *
 * @format
 */

import { __ } from '@wordpress/i18n';

const ProfileTab = () => (
	<div className="wcpay-wsn-hub__tab-stub" role="status">
		<p>
			{ __(
				'Profile content lands in RSM-2481.',
				'woocommerce-payments'
			) }
		</p>
	</div>
);

export default ProfileTab;
