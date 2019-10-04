/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import './style.scss';
import { HelloWorld } from 'hello-world';
import TransactionsPage from 'transactions';
import TransactionDetailsPage from 'transaction-details';
import ConnectAccountPage from 'connect-account-page';
import 'payments-api/payments-data-store';

const DepositsPage = () => <HelloWorld>Hello from the deposits page</HelloWorld>;
const DisputesPage = () => <HelloWorld>Hello from the disputes page</HelloWorld>;

addFilter( 'woocommerce_admin_pages_list', 'woocommerce-payments', pages => {
	const { menuID, rootLink } = get_menu_settings();

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
        container: DepositsPage,
        path: '/payments/deposits',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            __( 'Deposits', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: TransactionsPage,
        path: '/payments/transactions',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            __( 'Transactions', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: TransactionDetailsPage,
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
    return pages;
} );

/**
 * Get menu settings based on the top level link being connect or deposits
 *
 * @returns { { menuID, rootLink } }  Object containing menuID and rootLink
 */
function get_menu_settings() {
	const connectPage = document.querySelector( '#toplevel_page_wc-admin-path--payments-connect' );
	const topLevelPage = connectPage ? 'connect' : 'deposits';

	return {
		menuID: `toplevel_page_wc-admin-path--payments-${ topLevelPage }`,
		rootLink: [ `/payments/${ topLevelPage }`, __( 'Payments', 'woocommerce-payments' ) ],
	};
}
