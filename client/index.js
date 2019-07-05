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

const DepositsPage = () => <HelloWorld>Hello from the deposits page</HelloWorld>;
const DisputesPage = () => <HelloWorld>Hello from the disputes page</HelloWorld>;

addFilter( 'woocommerce_admin_pages_list', 'woocommerce-payments', pages => {
    const menuID = 'toplevel_page_wc-admin-path--payments-deposits';
    const rootLink = [ '/payments/deposits', __( 'Payments', 'woocommerce-payments' ) ];
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
