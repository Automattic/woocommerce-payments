/** @format */
/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { LoadableBlock } from '../../components/loadable';
import { useSettings } from '../../data';
import PaymentMethods from '../../payment-methods';
import SettingsSection from '../settings-section';
import DigitalWallets from '../digital-wallets';
import GeneralSettings from '../general-settings';
import TestModeSettings from '../test-mode-settings';

const SettingsManager = ( { accountStatus = {} } ) => {
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
				title={ __(
					'Digital wallets & express payment methods',
					'woocommerce-payments'
				) }
				description={ __(
					// eslint-disable-next-line max-len
					'Let customers use express payment methods and digital wallets like Apple Pay and Google Pay for fast & easy checkouts.',
					'woocommerce-payments'
				) }
			>
				<DigitalWallets />
			</SettingsSection>
			<SettingsSection
				title={ __( 'Settings', 'woocommerce-payments' ) }
				description={ __(
					"Change WooCommerce Payments settings and update your store's configuration to ensure smooth transactions.",
					'woocommerce-payments'
				) }
			>
				<GeneralSettings accountLink={ accountStatus.accountLink } />
			</SettingsSection>
			<SettingsSection>
				<TestModeSettings />
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
