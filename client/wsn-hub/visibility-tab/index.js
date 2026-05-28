/**
 * Visibility tab stub.
 *
 * Real content (mode-row stack with All / By taxonomy / Specific products picker)
 * lands in RSM-2480.
 *
 * @format
 */

import { __ } from '@wordpress/i18n';

const VisibilityTab = () => (
	<div className="wcpay-wsn-hub__tab-stub" role="status">
		<p>
			{ __(
				'Visibility content lands in RSM-2480.',
				'woocommerce-payments'
			) }
		</p>
	</div>
);

export default VisibilityTab;
