/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
//import { TableCard } from '@woocommerce/components';
import { Table } from '@woocommerce/components';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import AvailableCurrenciesModal from './modal';

const {
	enabledCurrencies,
	availableCurrencies,
	defaultCurrency,
	// eslint-disable-next-line no-undef
} = wcpayMultiCurrencySettings;
console.log( enabledCurrencies );
console.log( availableCurrencies );
console.log( defaultCurrency );

const defaultText = __( 'Default currency', 'woocommerce-payments' );

const isDefaultCurrency = ( code ) => {
	return code === defaultCurrency.code;
};

const getEditUrl = ( key ) => {
	return `admin.php?page=wc-settings&tab=wcpay_multi_currency&section=${ key.toLowerCase() }`;
};

const getActionLinks = ( key ) => {
	const edit = (
		<a className={ 'test' } href={ getEditUrl( key ) }>
			{ __( 'Edit', 'woocommerce-payments' ) }
		</a>
	);
	const remove = isDefaultCurrency( key ) ? (
		''
	) : (
		<a className={ 'test' } href={ '#remove' }>
			{ __( 'Remove', 'woocommerce-payments' ) }
		</a>
	);
	return (
		<div className={ 'actions' }>
			{ edit } { remove }
		</div>
	);
};

const getFlag = ( key ) => {
	return `[${ key } flagImg] `;
};

const getRow = ( key ) => {
	const currency = enabledCurrencies[ key ];
	const code = isDefaultCurrency( key )
		? `${ currency.code } - ${ defaultText }`
		: currency.code;
	return (
		<>
			<div className={ 'test' }>
				{ getFlag( key ) }
				<a className={ 'test' } href={ getEditUrl( key ) }>
					{ currency.name }
				</a>
				&nbsp;<span>({ code })</span>
			</div>
			{ getActionLinks( key ) }
		</>
	);
};

const getRows = () => {
	const enabledKeys = Object.keys( enabledCurrencies );
	const rows = [];

	for ( let i = 0; i < enabledKeys.length; i++ ) {
		const row = [
			{
				display: getRow( enabledKeys[ i ] ),
				value: i + 1,
			},
		];
		rows.push( row );
	}

	return rows;
};

const headers = [
	{
		label: __( 'Currency name', 'woocommerce-payments' ),
		isLeftAligned: true,
	},
];

const CurrencyTable = () => {
	const [ isModalOpen, setModalOpen ] = useState( false );

	const onClose = () => {
		setModalOpen( false );
	};
	const onSubmit = () => {
		setModalOpen( false );
		//submit();
	};

	const rows = getRows();
	rows.push( [
		{
			display: (
				<Button isSecondary onClick={ () => setModalOpen( true ) }>
					{ __( 'Add currencies ', 'woocommerce-payments' ) }
				</Button>
			),
			value: 'last',
		},
	] );

	return (
		<>
			<Table
				rows={ rows }
				headers={ headers }
				query={ '' }
				// rowHeader={}
				// caption={ 'caption line' }
				onSort={ null }
			/>
			{ isModalOpen && (
				<AvailableCurrenciesModal
					availableCurrencies={ availableCurrencies }
					enabledCurrencies={ enabledCurrencies }
					defaultCurrency={ defaultCurrency.code }
					onSubmit={ onSubmit }
					onClose={ onClose }
				/>
			) }
		</>
	);
};

export default CurrencyTable;
