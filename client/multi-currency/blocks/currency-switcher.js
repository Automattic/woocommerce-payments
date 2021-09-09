/**
 * External dependencies
 */
// import { sprintf, __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import React from 'react';
// import ReactDOM from 'react-dom';

registerBlockType( 'woocommerce-payments/multi-currency-switcher', {
	apiVersion: 2,
	title: 'Currency Switcher',
	icon: 'smiley',
	category: 'widgets',
	attributes: {
		symbol: { type: 'boolean' },
		flag: { type: 'boolean' },
	},
	edit( props ) {
		function updateSymbol( event ) {
			props.setAttributes( { symbol: event.target.value } );
		}
		function updateFlag( event ) {
			props.setAttributes( { flag: event.target.value } );
		}
		return (
			<div>
				<p>
					<label htmlFor="title">Title</label>
					<input id="title" className="widefat" type="text" value />
				</p>
				<p>
					<input
						id="symbol"
						className="checkbox"
						type="checkbox"
						value={ props.attributes.symbol }
						onChange={ updateSymbol }
						checked={ props.attributes.symbol ? 'checked' : '' }
					/>
					<label htmlFor="symbol">Display currency symbols</label>
				</p>
				<p>
					<input
						id="flag"
						className="checkbox"
						type="checkbox"
						value={ props.attributes.flag }
						onChange={ updateFlag }
						checked={ props.attributes.flag ? 'checked' : '' }
					/>
					<label htmlFor="flag">
						Display flags on supported devices
					</label>
				</p>
			</div>
		);
	},
	save( props ) {
		return createElement(
			'h3',
			{ style: { border: '3px solid red' } },
			'The value of symbol is ' +
				props.attributes.symbol +
				' and the value of flag is ' +
				props.attributes.flag
		);
	},
} );
