! ( function ( e ) {
	var t = {};
	function n( r ) {
		if ( t[ r ] ) return t[ r ].exports;
		var i = ( t[ r ] = { i: r, l: ! 1, exports: {} } );
		return (
			e[ r ].call( i.exports, i, i.exports, n ), ( i.l = ! 0 ), i.exports
		);
	}
	( n.m = e ),
		( n.c = t ),
		( n.d = function ( e, t, r ) {
			n.o( e, t ) ||
				Object.defineProperty( e, t, { enumerable: ! 0, get: r } );
		} ),
		( n.r = function ( e ) {
			'undefined' != typeof Symbol &&
				Symbol.toStringTag &&
				Object.defineProperty( e, Symbol.toStringTag, {
					value: 'Module',
				} ),
				Object.defineProperty( e, '__esModule', { value: ! 0 } );
		} ),
		( n.t = function ( e, t ) {
			if ( ( 1 & t && ( e = n( e ) ), 8 & t ) ) return e;
			if ( 4 & t && 'object' == typeof e && e && e.__esModule ) return e;
			var r = Object.create( null );
			if (
				( n.r( r ),
				Object.defineProperty( r, 'default', {
					enumerable: ! 0,
					value: e,
				} ),
				2 & t && 'string' != typeof e )
			)
				for ( var i in e )
					n.d(
						r,
						i,
						function ( t ) {
							return e[ t ];
						}.bind( null, i )
					);
			return r;
		} ),
		( n.n = function ( e ) {
			var t =
				e && e.__esModule
					? function () {
							return e.default;
					  }
					: function () {
							return e;
					  };
			return n.d( t, 'a', t ), t;
		} ),
		( n.o = function ( e, t ) {
			return Object.prototype.hasOwnProperty.call( e, t );
		} ),
		( n.p = '' ),
		n( ( n.s = 10 ) );
} )( [
	function ( e, t ) {
		e.exports = window.wp.i18n;
	},
	function ( e, t ) {
		e.exports = window.wp.element;
	},
	function ( e, t ) {
		e.exports = window.wc.blocksCheckout;
	},
	function ( e, t, n ) {
		var r = n( 7 );
		e.exports = function ( e, t ) {
			if ( null == e ) return {};
			var n,
				i,
				c = r( e, t );
			if ( Object.getOwnPropertySymbols ) {
				var o = Object.getOwnPropertySymbols( e );
				for ( i = 0; i < o.length; i++ )
					( n = o[ i ] ),
						t.indexOf( n ) >= 0 ||
							( Object.prototype.propertyIsEnumerable.call(
								e,
								n
							) &&
								( c[ n ] = e[ n ] ) );
			}
			return c;
		};
	},
	function ( e, t ) {
		e.exports = window.wp.plugins;
	},
	function ( e, t ) {
		e.exports = window.wc.priceFormat;
	},
	function ( e, t ) {
		e.exports = window.wc.wcSettings;
	},
	function ( e, t ) {
		e.exports = function ( e, t ) {
			if ( null == e ) return {};
			var n,
				r,
				i = {},
				c = Object.keys( e );
			for ( r = 0; r < c.length; r++ )
				( n = c[ r ] ), t.indexOf( n ) >= 0 || ( i[ n ] = e[ n ] );
			return i;
		};
	},
	function ( e, t, n ) {},
	function ( e, t, n ) {},
	function ( e, t, n ) {
		'use strict';
		n.r( t );
		var r = n( 1 ),
			i = n( 4 ),
			c = n( 2 ),
			o = n( 3 ),
			s = n.n( o ),
			l = n( 0 ),
			a = n( 5 ),
			u = n( 6 );
		function p( e ) {
			return {
				day: Object( l._nx )(
					'day',
					'days',
					e,
					'Used in recurring totals section in Cart. 2+ will need plural, 1 will need singular.',
					'woocommerce-subscriptions'
				),
				week: Object( l._nx )(
					'week',
					'weeks',
					e,
					'Used in recurring totals section in Cart. 2+ will need plural, 1 will need singular.',
					'woocommerce-subscriptions'
				),
				month: Object( l._nx )(
					'month',
					'months',
					e,
					'Used in recurring totals section in Cart. 2+ will need plural, 1 will need singular.',
					'woocommerce-subscriptions'
				),
				year: Object( l._nx )(
					'year',
					'years',
					e,
					'Used in recurring totals section in Cart. 2+ will need plural, 1 will need singular.',
					'woocommerce-subscriptions'
				),
			};
		}
		function b( e, t, n ) {
			var r = e.billing_interval,
				i = e.billing_period,
				c = p( r )[ i ];
			switch ( ( ( t = t.trim() ), r ) ) {
				case 1:
					return ''.concat( n, ' ' ).concat( t, ' ' ).concat( c );
				default:
					return Object( l.sprintf )(
						/* translators: %1$s is the price of the product. %2$s is the separator used e.g "every" or "/", %3$d is the length, %4$s is week, month, year */
						Object( l.__ )(
							'%1$s %2$s %3$d %4$s',
							'woocommerce-subscriptions'
						),
						n,
						t,
						r,
						c
					);
			}
		}
		function m( e ) {
			return e.subscriptionLength === e.billingInterval;
		}
		n( 8 );
		var g = Object( u.getSetting )( 'displayCartPricesIncludingTax', ! 1 ),
			d = function ( e ) {
				var t = e.currency,
					n = e.values,
					i = n.total_discount,
					o = n.total_discount_tax,
					s = parseInt( i, 10 );
				if ( ! s ) return null;
				var a = parseInt( o, 10 ),
					u = g ? s + a : s;
				return Object( r.createElement )( c.TotalsItem, {
					className: 'wc-block-components-totals-discount',
					currency: t,
					label: Object( l.__ )(
						'Discount',
						'woocommerce-subscriptions'
					),
					value: -1 * u,
				} );
			},
			_ = function ( e ) {
				var t = e.values,
					n = e.currency,
					i = e.selectedRate,
					o = e.needsShipping,
					s = e.calculatedShipping;
				if ( ! o || ! s ) return null;
				var a = g
					? parseInt( t.total_shipping, 10 ) +
					  parseInt( t.total_shipping_tax, 10 )
					: parseInt( t.total_shipping, 10 );
				return Object( r.createElement )( c.TotalsItem, {
					value: a,
					label: Object( l.__ )(
						'Shipping',
						'woocommerce-subscriptions'
					),
					currency: n,
					description:
						!! i &&
						Object( l.sprintf )(
							// translators: %s selected shipping rate (ex: flat rate)
							Object( l.__ )(
								'via %s',
								'woocommerce-subscriptions'
							),
							i
						),
				} );
			},
			f = function ( e ) {
				var t = e.nextPaymentDate,
					n = e.subscriptionLength,
					i = e.billingPeriod,
					c = e.billingInterval,
					o = ( function ( e ) {
						var t = e.subscriptionLength,
							n = e.billingPeriod,
							r = p( t );
						return Object( l.sprintf )(
							'For %1$d %2$s',
							t,
							r[ n ],
							'woocommerce-subscriptions'
						);
					} )( { subscriptionLength: n, billingPeriod: i } ),
					s = m( { subscriptionLength: n, billingInterval: c } )
						? Object( l.sprintf )(
								/* Translators: %1$s is a date. */
								Object( l.__ )(
									'Due: %1$s',
									'woocommerce-subscriptions'
								),
								t
						  )
						: Object( l.sprintf )(
								/* Translators: %1$s is a date. */
								Object( l.__ )(
									'Starting: %1$s',
									'woocommerce-subscriptions'
								),
								t
						  );
				return Object( r.createElement )(
					'span',
					null,
					!! t && s,
					' ',
					!! n &&
						n >= c &&
						Object( r.createElement )(
							'span',
							{
								className:
									'wcs-recurring-totals__subscription-length',
							},
							o
						)
				);
			},
			O = function ( e ) {
				var t = e.currency,
					n = e.billingInterval,
					i = e.billingPeriod,
					o = e.nextPaymentDate,
					s = e.subscriptionLength,
					a = e.totals,
					u = m( { billingInterval: n, subscriptionLength: s } )
						? Object( l.__ )( 'Total', 'woocommerce-subscriptions' )
						: ( function ( e ) {
								var t = e.billingInterval,
									n = e.billingPeriod;
								switch ( t ) {
									case 1:
										if ( 'day' === n )
											return Object( l.__ )(
												'Daily recurring total',
												'woocommerce-subscriptions'
											);
										if ( 'week' === n )
											return Object( l.__ )(
												'Weekly recurring total',
												'woocommerce-subscriptions'
											);
										if ( 'month' === n )
											return Object( l.__ )(
												'Monthly recurring total',
												'woocommerce-subscriptions'
											);
										if ( 'year' === n )
											return Object( l.__ )(
												'Yearly recurring total',
												'woocommerce-subscriptions'
											);
										break;
									case 2:
										return Object( l.sprintf )(
											/* translators: %1$s is week, month, year */
											Object( l.__ )(
												'Recurring total every 2nd %1$s',
												'woocommerce-subscriptions'
											),
											n
										);
									case 3:
										return Object( l.sprintf )(
											/* Translators: %1$s is week, month, year */
											Object( l.__ )(
												'Recurring total every 3rd %1$s',
												'woocommerce-subscriptions'
											),
											n
										);
									default:
										return Object( l.sprintf )(
											/* Translators: %1$d is number of weeks, months, days, years. %2$s is week, month, year */
											Object( l.__ )(
												'Recurring total every %1$dth %2$s',
												'woocommerce-subscriptions'
											),
											t,
											n
										);
								}
						  } )( { billingInterval: n, billingPeriod: i } );
				return Object( r.createElement )( c.TotalsItem, {
					className: 'wcs-recurring-totals-panel__title',
					currency: t,
					label: u,
					value: a,
					description: Object( r.createElement )( f, {
						nextPaymentDate: o,
						subscriptionLength: s,
						billingInterval: n,
						billingPeriod: i,
					} ),
				} );
			},
			w = function ( e ) {
				var t,
					n,
					i,
					o = e.subscription,
					s = e.needsShipping,
					u = e.calculatedShipping,
					p = o.totals,
					b = o.billing_interval,
					m = o.billing_period,
					f = o.next_payment_date,
					w = o.subscription_length,
					j = o.shipping_rates;
				if ( ! f ) return null;
				var v =
						null == j ||
						null === ( t = j[ 0 ] ) ||
						void 0 === t ||
						null === ( n = t.shipping_rates ) ||
						void 0 === n ||
						null ===
							( i = n.find( function ( e ) {
								return e.selected;
							} ) ) ||
						void 0 === i
							? void 0
							: i.name,
					y = Object( a.getCurrencyFromPriceResponse )( p );
				return Object( r.createElement )(
					'div',
					{ className: 'wcs-recurring-totals-panel' },
					Object( r.createElement )( O, {
						billingInterval: b,
						billingPeriod: m,
						nextPaymentDate: f,
						subscriptionLength: w,
						totals: parseInt( p.total_price, 10 ),
						currency: y,
					} ),
					Object( r.createElement )(
						c.Panel,
						{
							className: 'wcs-recurring-totals-panel__details',
							initialOpen: ! 1,
							title: Object( l.__ )(
								'Details',
								'woocommerce-subscriptions'
							),
						},
						Object( r.createElement )( c.Subtotal, {
							currency: y,
							values: p,
						} ),
						Object( r.createElement )( d, {
							currency: y,
							values: p,
						} ),
						Object( r.createElement )( _, {
							currency: y,
							needsShipping: s,
							calculatedShipping: u,
							values: p,
							selectedRate: v,
						} ),
						! g &&
							Object( r.createElement )( c.TotalsTaxes, {
								currency: y,
								values: p,
							} ),
						Object( r.createElement )( c.TotalsItem, {
							className:
								'wcs-recurring-totals-panel__details-total',
							currency: y,
							label: Object( l.__ )(
								'Total',
								'woocommerce-subscriptions'
							),
							value: parseInt( p.total_price, 10 ),
						} )
					)
				);
			},
			j = function ( e ) {
				var t = e.extensions,
					n = e.cart,
					i = t.subscriptions,
					c = n.cartNeedsShipping,
					o = n.cartHasCalculatedShipping;
				return i && 0 !== i.length
					? i.map( function ( e ) {
							var t = e.key,
								n = s()( e, [ 'key' ] );
							return Object( r.createElement )( w, {
								subscription: n,
								needsShipping: c,
								calculatedShipping: o,
								key: t,
							} );
					  } )
					: null;
			},
			v = function ( e ) {
				var t = e.extensions,
					n = e.collapsible,
					i = e.collapse,
					c = e.showItems,
					o = e.noResultsMessage,
					l = e.renderOption,
					a = e.components,
					u = t.subscriptions,
					p = void 0 === u ? [] : u,
					b = a.ShippingRatesControlPackage,
					m = Object( r.useMemo )(
						function () {
							return Object.values( p )
								.map( function ( e ) {
									return e.shipping_rates;
								} )
								.filter( Boolean )
								.flat();
						},
						[ p ]
					),
					g = Object( r.useMemo )(
						function () {
							return m.length > 1 || i;
						},
						[ m.length, i ]
					),
					d = Object( r.useMemo )(
						function () {
							return m.length > 1 || c;
						},
						[ m.length, c ]
					);
				return m.map( function ( e ) {
					var t = e.package_id,
						i = s()( e, [ 'package_id' ] );
					return Object( r.createElement )( b, {
						key: t,
						packageId: t,
						packageData: i,
						collapsible: n,
						collapse: g,
						showItems: d,
						noResultsMessage: o,
						renderOption: l,
					} );
				} );
			};
		n( 9 ),
			Object( i.registerPlugin )( 'woocommerce-subscriptions', {
				render: function () {
					return Object( r.createElement )(
						r.Fragment,
						null,
						Object( r.createElement )(
							c.ExperimentalOrderShippingPackages,
							null,
							Object( r.createElement )( v, null )
						),
						Object( r.createElement )(
							c.ExperimentalOrderMeta,
							null,
							Object( r.createElement )( j, null )
						)
					);
				},
				scope: 'woocommerce-checkout',
			} ),
			Object( c.__experimentalRegisterCheckoutFilters )(
				'woocommerce-subscriptions',
				{
					totalLabel: function ( e, t ) {
						var n = t.subscriptions;
						return ( null == n ? void 0 : n.length ) > 0
							? Object( l.__ )(
									'Total due today',
									'woocommerce-subscriptions'
							  )
							: e;
					},
					subtotalPriceFormat: function ( e, t ) {
						var n = t.subscriptions;
						if (
							null != n &&
							n.billing_period &&
							null != n &&
							n.billing_interval
						) {
							var r = n.billing_interval,
								i = n.subscription_length;
							return m( {
								subscriptionLength: i,
								billingInterval: r,
							} )
								? b(
										n,
										1 === i // translators: the word used to describe billing frequency, e.g. "fo1" 1 day or "for" 1 month.
											? Object( l.__ )(
													'for 1',
													'woocommerce-subscriptions'
											  ) // translators: the word used to describe billing frequency, e.g. "for" 6 days or "for" 2 weeks.
											: Object( l.__ )(
													'for',
													'woocommerce-subscriptions'
											  ),
										e
								  )
								: b(
										n, // translators: the word used to describe billing frequency, e.g. "every" 6 days or "every" 2 weeks.
										Object( l.__ )(
											'every',
											'woocommerce-subscriptions'
										),
										e
								  );
						}
						return e;
					},
					saleBadgePriceFormat: function ( e, t ) {
						var n = t.subscriptions;
						return null != n &&
							n.billing_period &&
							null != n &&
							n.billing_interval
							? b( n, '/', e )
							: e;
					},
					itemName: function ( e, t ) {
						var n = t.subscriptions;
						return null != n && n.is_resubscribe
							? Object( l.sprintf )(
									// translators: %s Product name.
									Object( l.__ )(
										'%s (resubscription)',
										'woocommerce-subscriptions'
									),
									e
							  )
							: null != n && n.switch_type
							? Object( l.sprintf )(
									// translators: %1$s Product name, %2$s Switch type (upgraded, downgraded, or crossgraded).
									Object( l.__ )(
										'%1$s (%2$s)',
										'woocommerce-subscriptions'
									),
									e,
									( function ( e ) {
										switch ( e ) {
											case 'upgraded':
												return Object( l.__ )(
													'Upgrade',
													'woocommerce-subscriptions'
												);
											case 'downgraded':
												return Object( l.__ )(
													'Downgrade',
													'woocommerce-subscriptions'
												);
											case 'crossgraded':
												return Object( l.__ )(
													'Crossgrade',
													'woocommerce-subscriptions'
												);
											default:
												return '';
										}
									} )( n.switch_type )
							  )
							: e;
					},
					cartItemPrice: function ( e, t, n ) {
						var r = t.subscriptions,
							i = n.context;
						return null != r && r.sign_up_fees
							? 'cart' === i
								? Object( l.sprintf )(
										/* translators: %s is the subscription price to pay immediately (ie: $10). */
										Object( l.__ )(
											'Due today %s',
											'woocommerce-subscriptions'
										),
										e
								  )
								: Object( l.sprintf )(
										/* translators: %s is the subscription price to pay immediately (ie: $10). */
										Object( l.__ )(
											'%s due today',
											'woocommerce-subscriptions'
										),
										e
								  )
							: e;
					},
				}
			);
	},
] );
