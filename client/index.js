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
import DisputesPage from 'disputes';
import DisputeEvidencePage from 'disputes/evidence';
import PaymentDetailsPage from 'payment-details';
import ConnectAccountPage from 'connect-account-page';
import { withTestNotice, topics } from './util';

const DepositsPage = () => <HelloWorld>Hello from the deposits page</HelloWorld>;

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
		container: withTestNotice( DepositsPage, topics.deposits ),
        path: '/payments/deposits',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            __( 'Deposits', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: withTestNotice( TransactionsPage, topics.transactions ),
        path: '/payments/transactions',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            __( 'Transactions', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: withTestNotice( PaymentDetailsPage, topics.paymentDetails ),
        path: '/payments/transactions/details',
        wpOpenMenu: menuID,
        breadcrumbs: [
            rootLink,
            [ '/payments/transactions', __( 'Transactions', 'woocommerce-payments' ) ],
            __( 'Payment Details', 'woocommerce-payments' ),
        ],
    } );
    pages.push( {
        container: withTestNotice( DisputesPage, topics.disputes ),
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
