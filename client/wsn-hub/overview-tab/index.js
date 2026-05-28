/**
 * Overview tab stub.
 *
 * Real content (pre-enable hero, stat cards, recent-orders table, footer
 * disable affordance) lands in RSM-2493.
 *
 * @format
 */

import { __ } from '@wordpress/i18n';

const OverviewTab = () => (
	<div className="wcpay-wsn-hub__tab-stub" role="status">
		<p>
			{ __(
				'Overview content lands in RSM-2493.',
				'woocommerce-payments'
			) }
		</p>
	</div>
);

export default OverviewTab;
