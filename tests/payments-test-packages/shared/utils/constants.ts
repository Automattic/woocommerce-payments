/**
 * External dependencies
 */
import path from 'path';
import { fileURLToPath } from 'url';

// ES module equivalent of __dirname
// eslint-disable-next-line @typescript-eslint/naming-convention
const __filename = fileURLToPath( import.meta.url );
// eslint-disable-next-line @typescript-eslint/naming-convention
const __dirname = path.dirname( __filename );

/**
 * WooPayments Admin Paths
 */
export const wcpaySettingsPath =
	'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments';

export const wcpayTransactionsPath =
	'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Ftransactions';

export const wcpayDepositsPath =
	'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fdeposits';

export const wcpayDisputesPath =
	'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes';

export const wcpayOverviewPath =
	'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview';

export const wcpayConnectPath =
	'/wp-admin/admin.php?page=wc-admin&path=/payments/connect';

/**
 * Multi-Currency Paths
 */
export const multiCurrencySettingsPath =
	'/wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency';

export const multiCurrencyOnboardingPath =
	'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fmulti-currency-setup';

/**
 * WooCommerce Admin Paths
 */
export const wcSettingsPath = '/wp-admin/admin.php?page=wc-settings';

export const wcOrdersPath = '/wp-admin/admin.php?page=wc-orders';

export const wcSubscriptionsPath =
	'/wp-admin/admin.php?page=wc-orders--shop_subscription';

export const wcOrderAnalyticsPath =
	'/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Forders';

/**
 * WordPress Admin Paths
 */
export const wpWidgetsPath = '/wp-admin/widgets.php';

export const wpThemesPath = '/wp-admin/themes.php';

export const wpOptionsPath = '/wp-admin/options.php';

export const actionSchedulerPath = '/wp-admin/tools.php?page=action-scheduler';

/**
 * Frontend Paths
 */
export const checkoutPath = '/checkout';

export const cartPath = '/cart';

export const shopPath = '/shop';

export const myAccountPath = '/my-account';

/**
 * Test Configuration Flags
 */
export const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

export const shouldRunActionSchedulerTests =
	process.env.SKIP_WC_ACTION_SCHEDULER_TESTS !== '1';

export const shouldRunWCBlocksTests = process.env.SKIP_WC_BLOCKS_TESTS !== '1';

export const wooCoreVersion = process.env.E2E_WC_VERSION;

export const isAtomicSite = process.env.NODE_ENV === 'atomic';

/**
 * Performance Report Configuration
 */
export const performanceReportDir = path.join( __dirname, '../../reports/' );

export const performanceReportFilename = path.join(
	performanceReportDir,
	'checkout-performance.txt'
);

export const performanceNumberOfTrials = 3;
