/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useSettings } from '../../data';

const General = () => {
	const { settings } = useSettings();

	return (
		<Card className="general-settings">
			<CardBody>
				<CheckboxControl
					checked={ settings.enabled }
					label={ __(
						'Enable WooCommerce Payments',
						'woocommerce-payments'
					) }
				/>
			</CardBody>
		</Card>
	);
};

export default General;
