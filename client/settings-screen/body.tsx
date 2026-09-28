/**
 * External dependencies
 */
import React from 'react';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

type Form = { fields: { id: string }[] } & Record< string, unknown >;

/** The props WooCommerce passes to a screen's body. Experimental, so only the parts used here are typed. */
export type BodyProps = {
	definition: { form: Form };
	view?: string;
	onChangeView: ( view?: string ) => void;
	renderForm: ( form: Form ) => React.ReactNode;
};

/** Each tab shows these cards from the View Config layout. */
export const getTabs = () => [
	{
		name: 'general',
		title: __( 'General', 'woocommerce-payments' ),
		cards: [ 'general', 'fraud-protection', 'account-notifications' ],
	},
	{
		name: 'payment-methods',
		title: __( 'Payment methods', 'woocommerce-payments' ),
		cards: [ 'payment-methods', 'express-checkouts' ],
	},
	{
		name: 'transactions',
		title: __( 'Transactions', 'woocommerce-payments' ),
		cards: [ 'transactions', 'bank-statement', 'customer-support' ],
	},
	{
		name: 'payouts',
		title: __( 'Payouts', 'woocommerce-payments' ),
		cards: [ 'payouts' ],
	},
	{
		name: 'advanced',
		title: __( 'Advanced settings', 'woocommerce-payments' ),
		cards: [ 'advanced' ],
	},
];

/** Gets the form for one tab, keeping only its cards. */
export const getTabForm = ( form: Form, cards: string[] ): Form => ( {
	...form,
	fields: form.fields.filter( ( card ) => cards.includes( card.id ) ),
} );

/** Shows the settings in tabs, with the selected tab in the route's `view` parameter. */
const SettingsScreenBody = ( {
	definition,
	view,
	onChangeView,
	renderForm,
}: BodyProps ) => {
	const tabs = getTabs();
	const current = tabs.find( ( tab ) => tab.name === view ) ?? tabs[ 0 ];

	return (
		<TabPanel
			className="wcpay-settings-screen__tabs"
			tabs={ tabs }
			initialTabName={ current.name }
			// TabPanel also calls this on mount, which shouldn't add the default tab to the URL.
			onSelect={ ( name ) =>
				name !== current.name && onChangeView( name )
			}
		>
			{ ( tab ) => {
				const cards =
					tabs.find( ( item ) => item.name === tab.name )?.cards ??
					[];
				return renderForm( getTabForm( definition.form, cards ) );
			} }
		</TabPanel>
	);
};

export default SettingsScreenBody;
