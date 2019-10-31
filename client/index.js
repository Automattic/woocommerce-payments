/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import { HelloWorld } from 'hello-world';
import TransactionsPage from 'transactions';
import DisputesPage from 'disputes';
import DisputeEvidencePage from 'disputes/evidence';
import PaymentDetailsPage from 'payment-details';
import ConnectAccountPage from 'connect-account-page';

const DepositsPage = () => <HelloWorld>Hello from the deposits page</HelloWorld>;

/**
 * Adds a test notice to a component if test mode is enabled.
 *
 * @param {Function} Component The component to be rendered.
 * @returns {Function} The component with a notice added, if applicable.
 */
const withTestModeNotice = ( Component ) => {
	const addNotice = () => (
		<div>
			<Notice status="warning" isDismissible={ false }>
				<b>Test Mode Active:</b> All transactions are simulated. Customers can't make real purchases through WooCommerce Payments.
			</Notice>
			<br />
		</div>
	);

	return ( ...props ) => (
		<div>
			{ '1' === wcpaySettings.test_mode ? addNotice() : null }
			{ Component( ...props ) }
		</div>
	);
};

addFilter( 'woocommerce_admin_pages_list', 'woocommerce-payments', pages => {
	const { menuID, rootLink } = getMenuSettings();

    pages.push( {
        container: ConnectAccountPage,
        path: '/payments/connect',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            __( 'Connect', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: withTestModeNotice( DepositsPage ),
        path: '/payments/deposits',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            __( 'Deposits', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: withTestModeNotice( TransactionsPage ),
        path: '/payments/transactions',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            __( 'Transactions', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: PaymentDetailsPage,
        path: '/payments/transactions/details',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            [ '/payments/transactions', __( 'Transactions', 'woocommerce-payments' ) ],
            __( 'Payment Details', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: DisputesPage,
        path: '/payments/disputes',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            __( 'Disputes', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: DisputeEvidencePage,
        path: '/payments/disputes/evidence',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            [ '/payments/disputes', __( 'Disputes', 'woocommerce-payments' ) ],
            __( 'Evidence', 'woocommerce-payments' ),
        ],
    } );
    return pages;
} );

/**
 * Get menu settings based on the top level link being connect or deposits
 *
 * @returns { { menuID, rootLink } }  Object containing menuID and rootLink
 */
function getMenuSettings() {
	const connectPage = document.querySelector( '#toplevel_page_wc-admin-path--payments-connect' );
	const topLevelPage = connectPage ? 'connect' : 'deposits';

	return {
		menuID: `toplevel_page_wc-admin-path--payments-${ topLevelPage }`,
		rootLink: [ `/payments/${ topLevelPage }`, __( 'Payments', 'woocommerce-payments' ) ],
	};
}
