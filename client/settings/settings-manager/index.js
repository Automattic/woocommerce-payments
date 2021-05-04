/** @format */
/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useSettings } from '../../data';
import PaymentMethods from '../../payment-methods';
import General from './general';
import SettingsSection from '../settings-section';
import { LoadableBlock } from '../../components/loadable';

const SettingsManager = () => {
	const { saveSettings, isSaving, isLoading } = useSettings();

	return (
		<div className="settings-manager">
			<SettingsSection
				title={ __(
					'Payments accepted on checkout',
					'woocommerce-payments'
				) }
				description={ __(
					'Add and edit payments available to customers at checkout. Drag & drop to reorder.',
					'woocommerce-payments'
				) }
			>
				<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
					<PaymentMethods />
				</LoadableBlock>
			</SettingsSection>

			<SettingsSection
				title={ __( 'General Settings', 'woocommerce-payments' ) }
				description={ __(
					'Change WooCommerce Payments settings and update your store’s configuration to ensure smooth transactions.',
					'woocommerce-payments'
				) }
			>
				<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
					<General />
				</LoadableBlock>
			</SettingsSection>

			<LoadableBlock isLoading={ isLoading } numLines={ 3 }>
				<Button
					isPrimary
					isBusy={ isSaving }
					disabled={ isSaving || isLoading }
					onClick={ saveSettings }
				>
					{ __( 'Save changes', 'woocommerce-payments' ) }
				</Button>
			</LoadableBlock>
		</div>
	);
};

export default SettingsManager;
