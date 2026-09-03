/**
 * Fakes ONLY the Stripe Express Checkout Element (ECE) wallet sheet; the rest of
 * the stack runs for real. Real Stripe.js still loads (the app's
 * `assertStripeJsOrigin({ failFast: true })` guard needs the genuine
 * js.stripe.com tag), the real backend confirms the real order, and every
 * non-ECE Stripe method passes through to the real instance.
 *
 * Google Pay / Apple Pay / Amazon Pay are browser/OS UI Playwright can't reach,
 * so we intercept `elements.create( 'expressCheckout' )` and render our own DOM
 * overlay that drives the app's own event handlers (`click`,
 * `shippingaddresschange`, `shippingratechange`, `confirm`), recording every
 * payload the app hands back. The credential is a REAL Stripe confirmation token
 * / payment method minted with the page's publishable key and the per-instance
 * connected `stripeAccount`. WooPayments confirms against the connected account,
 * so a platform-scoped token fails with "No such confirmation_token".
 *
 * Plain browser JS on purpose: ships inline via a mu-plugin, so no imports, no
 * ESM, no TypeScript.
 *
 * A fake can't catch every kind of ECE drift: if Stripe or WooPayments renames a
 * payload field this proxy reads/writes, the fake keeps "working" and the test
 * stays green. `express-checkout-ece-contract.spec.ts` narrows that gap by
 * asserting the app's genuine ECE scaffolding (the
 * #wcpay-express-checkout-element wrapper the fake mounts INTO and can't
 * fabricate) still renders, so app-side integration drift still fails a test.
 * Keep the two in sync.
 */
( function () {
	// Persisted to sessionStorage because the post-confirm redirect to the
	// order-received page re-inits window state; seeding from storage keeps the
	// call log across that same-origin navigation. sessionStorage is per browsing
	// context, so test isolation relies on each spec using a fresh shopper
	// context (getShopper). Don't share a context/storageState across tests or
	// the log will bleed.
	const storageKey = '__eceStripeCalls';
	let state;
	try {
		state = JSON.parse( sessionStorage.getItem( storageKey ) ) || {
			calls: [],
			seq: 0,
		};
	} catch ( e ) {
		state = { calls: [], seq: 0 };
	}
	window.__eceStripe = state;

	// The card the fake sheet pays with. The mint interceptors read this live
	// (not a config value) so a test can switch cards mid-sheet via the card
	// <select>. Defaults to an approved Visa.
	let selectedToken = 'tok_visa';

	// The payment method the app is about to mint, set by pay() from the clicked
	// wallet. Google/Apple Pay are card wallets so they mint a card from
	// selectedToken; Amazon Pay is its own method and mints a real amazon_pay.
	let pendingMethodType = 'card';

	// Mirrors the Elements setupFutureUsage onto the minted confirmation token.
	// Subscriptions set off_session on the intent, and Stripe rejects the confirm
	// when the token's setup_future_usage doesn't match the intent's. The real SDK
	// copies it off the Elements; minting directly, we have to carry it ourselves.
	let pendingSetupFutureUsage = null;

	// True only while the fake sheet is confirming, consumed by the first mint
	// that follows. Scopes the credential fakery to the ECE confirm flow: a
	// normal card checkout on the same page (deferred-intent Payment Element also
	// calls createConfirmationToken/createPaymentMethod) passes straight through
	// to the real Stripe method instead of being swapped for `selectedToken`.
	let eceConfirmInFlight = false;

	function safeClone( value, seen ) {
		seen = seen || new WeakSet();
		if ( typeof value === 'function' ) {
			return '[Function]';
		}
		if ( value === null || typeof value !== 'object' ) {
			return value;
		}
		if ( typeof Node !== 'undefined' && value instanceof Node ) {
			return { __node: value.tagName, id: value.id };
		}
		if ( seen.has( value ) ) {
			return '[Circular]';
		}
		seen.add( value );
		if ( Array.isArray( value ) ) {
			return value.map( function ( item ) {
				return safeClone( item, seen );
			} );
		}
		const out = {};
		for ( const key in value ) {
			if ( Object.prototype.hasOwnProperty.call( value, key ) ) {
				out[ key ] = safeClone( value[ key ], seen );
			}
		}
		return out;
	}

	function record( target, method, args, instanceId ) {
		state.calls.push( {
			seq: state.seq++,
			instanceId: instanceId,
			target: target,
			method: method,
			args: args.map( function ( arg ) {
				return safeClone( arg );
			} ),
		} );
		try {
			sessionStorage.setItem( storageKey, JSON.stringify( state ) );
		} catch ( e ) {}
	}

	function stripePost( ctx, path, params ) {
		const headers = {
			Authorization: 'Bearer ' + ctx.pk,
			'Content-Type': 'application/x-www-form-urlencoded',
		};
		if ( ctx.account ) {
			headers[ 'Stripe-Account' ] = ctx.account;
		}
		const body = new URLSearchParams();
		for ( const key in params ) {
			if ( Object.prototype.hasOwnProperty.call( params, key ) ) {
				body.set( key, params[ key ] );
			}
		}
		return fetch( 'https://api.stripe.com/v1/' + path, {
			method: 'POST',
			headers: headers,
			body: body.toString(),
		} ).then( function ( response ) {
			return response.json().then( function ( json ) {
				// Surface Stripe errors loudly: an undefined credential here
				// would fail opaquely deep inside the real confirm flow.
				if ( ! response.ok || ! json.id ) {
					throw new Error(
						'ECE proxy: ' +
							path +
							' mint failed: ' +
							JSON.stringify( json.error || json )
					);
				}
				return json.id;
			} );
		} );
	}

	function mintConfirmationToken( ctx, methodType, cardToken ) {
		const params =
			methodType === 'amazon_pay'
				? { 'payment_method_data[type]': 'amazon_pay' }
				: {
						'payment_method_data[type]': 'card',
						'payment_method_data[card][token]': cardToken,
				  };
		if ( pendingSetupFutureUsage ) {
			params.setup_future_usage = pendingSetupFutureUsage;
			// amazon_pay needs a mandate to set up off_session. The real flow
			// captures it during the Amazon authorization; minting directly, we
			// supply an online acceptance like WCPay does for its mandate methods.
			if ( methodType === 'amazon_pay' ) {
				params[ 'mandate_data[customer_acceptance][type]' ] = 'online';
				params[
					'mandate_data[customer_acceptance][online][ip_address]'
				] = '127.0.0.1';
				params[
					'mandate_data[customer_acceptance][online][user_agent]'
				] = navigator.userAgent;
			}
		}
		return stripePost( ctx, 'confirmation_tokens', params );
	}

	function mintPaymentMethod( ctx, methodType, cardToken ) {
		const params =
			methodType === 'amazon_pay'
				? { type: 'amazon_pay' }
				: { type: 'card', 'card[token]': cardToken };
		return stripePost( ctx, 'payment_methods', params );
	}

	const WALLETS = [ 'googlePay', 'applePay', 'amazonPay' ];

	// Stripe's paymentMethods config and availablePaymentMethods use camelCase
	// wallet keys, but the click/confirm events report expressPaymentType in
	// snake_case. Map to the real event shape when firing so the app sees what it
	// would from genuine ECE.
	const EXPRESS_PAYMENT_TYPES = {
		googlePay: 'google_pay',
		applePay: 'apple_pay',
		amazonPay: 'amazon_pay',
	};

	const DEFAULT_ADDRESS = {
		name: 'John Doe',
		address_1: '60 29th Street #343',
		city: 'San Francisco',
		state: 'CA',
		postcode: '94110',
		country: 'US',
		phone: '3105551234',
	};

	const ADDRESS_LABELS = {
		name: 'Name',
		address_1: 'Address line 1',
		city: 'City',
		state: 'State',
		postcode: 'Postcode',
		country: 'Country',
		phone: 'Phone',
	};

	// Formats a Stripe minor-unit amount (e.g. 5050) the way the real wallet
	// sheet shows totals/line items. Dividing by 100 is a display approximation:
	// right for the 2-decimal currencies the test env uses (USD/EUR), wrong for
	// zero-decimal ones like JPY, but a fake sheet doesn't need Stripe's exact
	// currency table.
	function formatAmount( amount, currency ) {
		const code = ( currency || 'usd' ).toUpperCase();
		const value = ( typeof amount === 'number' ? amount : 0 ) / 100;
		try {
			return new Intl.NumberFormat( undefined, {
				style: 'currency',
				currency: code,
			} ).format( value );
		} catch ( e ) {
			return code + ' ' + value.toFixed( 2 );
		}
	}

	function makeFakeEceElement( ctx, elementsState, createOpts ) {
		const handlers = {};
		// Guards against opening a second sheet: the availability probe and the
		// real button are separate fake elements that both append to
		// document.body, and a double-click would stack duplicate [data-testid]
		// nodes that trip Playwright strict-mode locators.
		let sheetPending = false;
		// Guards against a re-mount of the same element stacking a second set of
		// wallet buttons and firing `ready` twice.
		let mounted = false;

		// Honor the element's requested wallets. The block Cart/Checkout mounts
		// one ExpressCheckoutElement per wallet, each passing a `paymentMethods`
		// override that enables exactly one method ('always'/'auto') and sets the
		// rest to 'never'. Real Stripe renders only the non-'never' methods, so
		// the fake must too, or every element shows all three buttons. An absent
		// key defaults to 'auto' (shown), matching Stripe.
		const requestedWallets = WALLETS.filter( function ( wallet ) {
			const modes = createOpts && createOpts.paymentMethods;
			return ! modes || modes[ wallet ] !== 'never';
		} );

		function el( tag, testid, attrs ) {
			const node = document.createElement( tag );
			if ( testid ) {
				node.setAttribute( 'data-testid', testid );
				// Mirror the testid onto `name` for form controls so they're easy
				// to target by name in a browser (manual driving, autofill,
				// document.forms), not just via data-testid.
				if ( /^(input|select|button|textarea)$/.test( tag ) ) {
					node.setAttribute( 'name', testid );
				}
			}
			if ( attrs ) {
				for ( const k in attrs ) {
					if ( Object.prototype.hasOwnProperty.call( attrs, k ) ) {
						node.setAttribute( k, attrs[ k ] );
					}
				}
			}
			return node;
		}

		// Fires the app's real handler for `eventName` and blocks until the app
		// settles it, recording the resolve/reject payload (the spy data). The
		// app MUST settle each ECE event or the awaited step never completes,
		// which surfaces as a Playwright timeout rather than a silent hang.
		function fireAndWait( eventName, base, onResolve ) {
			return new Promise( function ( done ) {
				const handler = handlers[ eventName ];
				if ( ! handler ) {
					done();
					return;
				}
				let settled = false;
				const settle = function () {
					if ( ! settled ) {
						settled = true;
						done();
					}
				};
				const payload = {};
				for ( const k in base ) {
					if ( Object.prototype.hasOwnProperty.call( base, k ) ) {
						payload[ k ] = base[ k ];
					}
				}
				payload.resolve = function ( opts ) {
					record( 'event', eventName + '.resolve', [ opts ] );
					if ( onResolve ) {
						onResolve( opts );
					}
					settle();
				};
				payload.reject = function ( err ) {
					record( 'event', eventName + '.reject', [ err ] );
					settle();
				};
				handler( payload );
			} );
		}

		function openSheet( wallet ) {
			if (
				sheetPending ||
				document.querySelector(
					'[data-testid="ece-fake-wallet-sheet"]'
				)
			) {
				return;
			}
			sheetPending = true;
			let clickOptions = {};

			fireAndWait(
				'click',
				{ expressPaymentType: EXPRESS_PAYMENT_TYPES[ wallet ] },
				function ( opts ) {
					clickOptions = opts || {};
				}
			).then( function () {
				buildSheet( wallet, clickOptions );
			} );
		}

		function buildSheet( wallet, clickOptions ) {
			const shippingRequired = !! clickOptions.shippingAddressRequired;
			// The order summary the app provides at click time (line items +
			// running total); refreshed as the shipping round-trips resolve.
			let currentLineItems = Array.isArray( clickOptions.lineItems )
				? clickOptions.lineItems
				: [];

			// A real wallet sheet is a contained panel, so make the fake one too.
			// The real ECE flow blocks the page with a full-viewport blockUI
			// overlay (z-index 1000) while the wallet is open; the fake sheet needs
			// a higher z-index so its controls sit above blockUI and stay clickable
			// (Playwright actionability needs the pay button to get pointer
			// events). Keep it NOT full-viewport, or it blankets the page and
			// swallows every click and the devtools element picker. Use a <form>
			// (not a div) so form-aware tooling and document.forms can drive the
			// named controls; submit is prevented so Enter in a field never
			// navigates the page.
			const sheet = el( 'form', 'ece-fake-wallet-sheet', {
				name: 'ece-fake-wallet-sheet',
				style:
					'position:fixed;top:20px;right:20px;z-index:2147483647;' +
					'box-sizing:border-box;width:340px;max-width:calc(100vw - 40px);' +
					'max-height:calc(100vh - 40px);overflow:auto;' +
					'background:#fff;border:1px solid #ccc;border-radius:10px;' +
					'box-shadow:0 10px 40px rgba(0,0,0,0.3);padding:20px;' +
					'display:flex;flex-direction:column;gap:12px;align-items:stretch;',
			} );
			sheet.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
			} );

			// While the wallet is "open" the page is under WooPayments' jQuery
			// blockUI, whose bindEvents binds document-level mousedown/mouseup/
			// keydown/keypress handlers that preventDefault for any target outside
			// its overlay. This sheet is a sibling of that overlay, so those
			// handlers eat its focus and keystrokes: mousedown blocks focusing an
			// <input> or opening a <select>, keydown blocks typing. (Button clicks
			// aren't in that set.) Stop these events bubbling to the document
			// handler so the sheet stays interactive.
			[ 'mousedown', 'mouseup', 'keydown', 'keypress', 'keyup' ].forEach(
				function ( type ) {
					sheet.addEventListener( type, function ( e ) {
						e.stopPropagation();
					} );
				}
			);

			// Wrap a control in a <label> so it's addressable by visible label
			// text (getByLabel, manual browser driving), not only by testid/name.
			function labelField( labelText, control ) {
				const label = document.createElement( 'label' );
				label.style.display = 'flex';
				label.style.flexDirection = 'column';
				label.style.gap = '4px';
				label.appendChild( document.createTextNode( labelText ) );
				label.appendChild( control );
				return label;
			}

			// Order summary: mirrors what a real wallet sheet shows, each line
			// item and the running total, using the same data the app hands the
			// element (lineItems from the resolves, total from elements()/update()).
			const summary = el( 'div', 'ece-fake-summary', {
				style:
					'display:flex;flex-direction:column;gap:6px;' +
					'border-bottom:1px solid #eee;padding-bottom:12px;',
			} );
			function renderSummary() {
				summary.innerHTML = '';
				currentLineItems.forEach( function ( item ) {
					const row = el( 'div', 'ece-fake-line-item', {
						style: 'display:flex;justify-content:space-between;gap:12px;',
					} );
					const name = document.createElement( 'span' );
					name.textContent = item.name;
					const amount = document.createElement( 'span' );
					amount.textContent = formatAmount(
						item.amount,
						elementsState.currency
					);
					row.appendChild( name );
					row.appendChild( amount );
					summary.appendChild( row );
				} );
				const totalRow = el( 'div', 'ece-fake-total', {
					style:
						'display:flex;justify-content:space-between;gap:12px;' +
						'font-weight:bold;',
				} );
				const totalLabel = document.createElement( 'span' );
				totalLabel.textContent = 'Total';
				const totalAmount = document.createElement( 'span' );
				totalAmount.textContent = formatAmount(
					elementsState.amount,
					elementsState.currency
				);
				totalRow.appendChild( totalLabel );
				totalRow.appendChild( totalAmount );
				summary.appendChild( totalRow );
			}
			renderSummary();
			sheet.appendChild( summary );
			// Refresh the total when the app calls elements.update(); cleared when
			// the sheet closes (pay/cancel) so a late update can't touch a
			// removed summary node.
			elementsState.onUpdate = renderSummary;

			const cardSelect = el( 'select', 'ece-fake-card' );
			const visa = el( 'option' );
			visa.value = 'tok_visa';
			visa.textContent = 'Visa 4242 (approved)';
			const declined = el( 'option' );
			declined.value = 'tok_chargeDeclined';
			declined.textContent = 'Card (declined)';
			cardSelect.appendChild( visa );
			cardSelect.appendChild( declined );
			cardSelect.value = selectedToken;
			cardSelect.addEventListener( 'change', function () {
				selectedToken = cardSelect.value;
			} );
			sheet.appendChild( labelField( 'Card', cardSelect ) );

			const addressInputs = {};
			let rateContainer = null;
			let rateById = {};

			function readAddress() {
				const address = {};
				for ( const field in addressInputs ) {
					if (
						Object.prototype.hasOwnProperty.call(
							addressInputs,
							field
						)
					) {
						address[ field ] = addressInputs[ field ].value;
					}
				}
				return address;
			}

			// Redacted like Stripe's shippingaddresschange - street lines stay
			// hidden until confirm, only region fields come through.
			function readShippingAddressChange() {
				const full = readAddress();
				return {
					name: full.name,
					address: {
						city: full.city,
						state: full.state,
						postal_code: full.postcode,
						country: full.country,
					},
				};
			}

			// Fires shippingratechange for the picked rate and refreshes the
			// summary from the app's resolved line items.
			function selectRate( rateId ) {
				fireAndWait(
					'shippingratechange',
					{ shippingRate: rateById[ rateId ] },
					function ( opts ) {
						if ( opts && Array.isArray( opts.lineItems ) ) {
							currentLineItems = opts.lineItems;
						}
						renderSummary();
					}
				);
			}

			// Renders each shipping option the way a real wallet sheet does: the
			// method name, its price on the right, and, when the rate carries one
			// (e.g. local pickup's address/instructions in deliveryEstimate), a
			// muted description sub-line the way Google Pay / Apple Pay show it.
			function populateRates( rates ) {
				if ( ! rateContainer ) {
					return;
				}
				rateContainer.innerHTML = '';
				rateById = {};
				rates.forEach( function ( rate, index ) {
					rateById[ rate.id ] = rate;
					const row = el( 'label', null, {
						style:
							'display:flex;align-items:flex-start;gap:8px;' +
							'padding:8px 0;border-bottom:1px solid #eee;cursor:pointer;',
					} );

					const radio = el( 'input', null, { type: 'radio' } );
					radio.name = 'ece-fake-shipping-rate';
					radio.value = rate.id;
					radio.checked = index === 0;
					radio.addEventListener( 'change', function () {
						if ( radio.checked ) {
							selectRate( rate.id );
						}
					} );

					const info = el( 'span', null, { style: 'flex:1;' } );
					const name = el( 'span', null, {
						style: 'display:block;font-weight:500;',
					} );
					name.textContent = rate.displayName || rate.id;
					info.appendChild( name );
					if ( rate.deliveryEstimate ) {
						const desc = el( 'span', null, {
							style: 'display:block;font-size:12px;color:#666;',
						} );
						desc.textContent = rate.deliveryEstimate;
						info.appendChild( desc );
					}

					const price = el( 'span', null, {
						style: 'font-weight:500;white-space:nowrap;',
					} );
					price.textContent = formatAmount(
						rate.amount,
						elementsState.currency
					);

					row.appendChild( radio );
					row.appendChild( info );
					row.appendChild( price );
					rateContainer.appendChild( row );
				} );
			}

			function applyAddress() {
				return fireAndWait(
					'shippingaddresschange',
					readShippingAddressChange(),
					function ( opts ) {
						if ( opts && opts.shippingRates ) {
							populateRates( opts.shippingRates );
						}
						if ( opts && Array.isArray( opts.lineItems ) ) {
							currentLineItems = opts.lineItems;
						}
						// The app runs elements.update() (new total) before this
						// resolve, so re-render picks up both the total and the
						// refreshed line items.
						renderSummary();
					}
				);
			}

			// Build the address fields unconditionally so the confirm carries a
			// billing address. A no-shipping product skips the shipping round-trip,
			// but the order still needs a billing country or the Store API rejects
			// it, so only the visible fields are gated on shipping, not the data.
			[
				'name',
				'address_1',
				'city',
				'state',
				'postcode',
				'country',
				'phone',
			].forEach( function ( field ) {
				const input = el( 'input', 'ece-fake-address-' + field );
				input.value = DEFAULT_ADDRESS[ field ] || '';
				addressInputs[ field ] = input;
				if ( shippingRequired ) {
					sheet.appendChild(
						labelField( ADDRESS_LABELS[ field ] || field, input )
					);
				}
			} );

			if ( shippingRequired ) {
				const applyBtn = el( 'button', 'ece-fake-apply-address', {
					type: 'button',
				} );
				applyBtn.textContent = 'Apply address';
				applyBtn.addEventListener( 'click', function () {
					applyAddress();
				} );
				sheet.appendChild( applyBtn );

				// Radio rows (not a <select>) so each option can carry a price and
				// a description sub-line; a plain <option> is single-line text.
				// Each row is its own <label> wrapping one radio, so clicking a
				// row toggles only that radio (a single label around all radios
				// would always target the first).
				const rateGroup = el( 'div', null, {
					style: 'display:flex;flex-direction:column;gap:4px;',
				} );
				const rateHeading = document.createElement( 'span' );
				rateHeading.textContent = 'Shipping method';
				rateGroup.appendChild( rateHeading );
				rateContainer = el( 'div', 'ece-fake-shipping-rate', {
					style: 'display:flex;flex-direction:column;',
				} );
				rateGroup.appendChild( rateContainer );
				sheet.appendChild( rateGroup );
			}

			const payBtn = el( 'button', 'ece-fake-wallet-pay', {
				type: 'button',
			} );
			payBtn.textContent = 'Pay';
			payBtn.addEventListener( 'click', function () {
				pay( wallet, readAddress(), shippingRequired );
			} );
			sheet.appendChild( payBtn );

			const cancelBtn = el( 'button', 'ece-fake-wallet-cancel', {
				type: 'button',
			} );
			cancelBtn.textContent = 'Cancel';
			cancelBtn.addEventListener( 'click', function () {
				// The real ECE flow blocks the checkout UI when the wallet opens
				// and relies on the `cancel` event to unblock it (onCancelHandler
				// → unblockUI). Fire it so a cancelled sheet doesn't wedge the
				// page with no fallback to the card form.
				if ( handlers.cancel ) {
					record( 'event', 'cancel', [] );
					handlers.cancel();
				}
				if ( sheet.parentNode ) {
					sheet.parentNode.removeChild( sheet );
				}
				sheetPending = false;
				elementsState.onUpdate = null;
			} );
			sheet.appendChild( cancelBtn );

			document.body.appendChild( sheet );

			// Kick off the shipping round-trip once so the rate list is populated
			// before the tester reaches it.
			if ( shippingRequired ) {
				applyAddress();
			}
		}

		function pay( wallet, address, shippingRequired ) {
			pendingMethodType = wallet === 'amazonPay' ? 'amazon_pay' : 'card';
			const billingAddress = {
				line1: address.address_1,
				city: address.city,
				state: address.state,
				postal_code: address.postcode,
				country: address.country,
			};
			if ( handlers.confirm ) {
				// Arm the credential fakery just for the mint the app's confirm
				// handler is about to make (consumed by the first mint).
				eceConfirmInFlight = true;
				const confirmEvent = {
					expressPaymentType: EXPRESS_PAYMENT_TYPES[ wallet ],
					billingDetails: {
						name: address.name,
						email: 'john.doe@example.com',
						phone: address.phone,
						address: billingAddress,
					},
				};
				// Real ECE only carries a shippingAddress when the product needs
				// shipping; a virtual product's confirm omits it, so mirror that.
				if ( shippingRequired ) {
					confirmEvent.shippingAddress = {
						name: address.name,
						address: billingAddress,
					};
				}
				handlers.confirm( confirmEvent );
			}
			// Hide the sheet: the success path navigates away; on decline the
			// app renders a notice on the underlying page.
			const sheet = document.querySelector(
				'[data-testid="ece-fake-wallet-sheet"]'
			);
			if ( sheet && sheet.parentNode ) {
				sheet.parentNode.removeChild( sheet );
			}
			sheetPending = false;
			elementsState.onUpdate = null;
		}

		return {
			on: function ( evt, cb ) {
				record( 'element', 'on', [ evt ], ctx.instanceId );
				handlers[ evt ] = cb;
				return this;
			},
			update: function () {
				return this;
			},
			unmount: function () {},
			destroy: function () {},
			submit: function () {
				return Promise.resolve( {} );
			},
			mount: function ( target ) {
				if ( mounted ) {
					return;
				}
				mounted = true;
				const root =
					typeof target === 'string'
						? document.querySelector( target )
						: target;
				const container = document.createElement( 'div' );
				container.setAttribute( 'data-testid', 'ece-fake-mounted' );
				requestedWallets.forEach( function ( wallet ) {
					const button = document.createElement( 'button' );
					button.type = 'button';
					button.setAttribute(
						'data-testid',
						'ece-fake-button-' + wallet
					);
					button.setAttribute( 'name', 'ece-fake-button-' + wallet );
					button.textContent = 'Fake ' + wallet;
					button.addEventListener( 'click', function () {
						openSheet( wallet );
					} );
					container.appendChild( button );
				} );
				if ( root ) {
					root.appendChild( container );
				}
				// Fire `ready` next tick so app listeners attach first; this also
				// satisfies the hidden availability probe, keeping the real button
				// container mounted. Report availability only for the wallets this
				// element requested, matching what Stripe returns for the same
				// paymentMethods config.
				setTimeout( function () {
					if ( handlers.ready ) {
						const availablePaymentMethods = {};
						requestedWallets.forEach( function ( wallet ) {
							availablePaymentMethods[ wallet ] = true;
						} );
						handlers.ready( { availablePaymentMethods } );
					}
				}, 0 );
			},
		};
	}

	function wrapElements( elements, ctx, elementsState ) {
		// Only fake this group's submit once it has actually created an
		// expressCheckout element; a card-only Elements group on the same page
		// keeps its real submit (which validates/collects the card).
		let hasEce = false;
		return new Proxy( elements, {
			get: function ( targetElements, prop ) {
				if ( prop === 'create' ) {
					return function ( type, opts ) {
						record(
							'elements',
							'create',
							[ type, opts ],
							ctx.instanceId
						);
						if ( type === 'expressCheckout' ) {
							hasEce = true;
							return makeFakeEceElement(
								ctx,
								elementsState,
								opts
							);
						}
						return targetElements.create( type, opts );
					};
				}
				if ( prop === 'update' ) {
					return function ( opts ) {
						record(
							'elements',
							'update',
							[ opts ],
							ctx.instanceId
						);
						// Keep the running total in sync and refresh the open
						// sheet's summary (the app calls update() right before it
						// resolves shippingaddresschange/shippingratechange).
						if ( opts && typeof opts.amount !== 'undefined' ) {
							elementsState.amount = opts.amount;
						}
						if ( opts && opts.currency ) {
							elementsState.currency = opts.currency;
						}
						if ( elementsState.onUpdate ) {
							elementsState.onUpdate();
						}
						return targetElements.update( opts );
					};
				}
				if ( prop === 'submit' ) {
					return function () {
						if ( ! hasEce ) {
							return targetElements.submit();
						}
						record( 'elements', 'submit', [], ctx.instanceId );
						return Promise.resolve( {} );
					};
				}
				const value = Reflect.get( targetElements, prop );
				return typeof value === 'function'
					? value.bind( targetElements )
					: value;
			},
		} );
	}

	function wrapInstance( stripe, ctx ) {
		return new Proxy( stripe, {
			get: function ( targetStripe, prop ) {
				if ( prop === 'elements' ) {
					return function ( opts ) {
						record(
							'stripe',
							'elements',
							[ opts ],
							ctx.instanceId
						);
						// Carry the sheet's total (amount/currency) live: it's
						// seeded from elements() and refreshed on every
						// elements.update() the app makes during the shipping
						// round-trips, so the fake sheet's summary reflects the
						// real running total.
						const elementsState = {
							amount: opts && opts.amount,
							currency: opts && opts.currency,
							onUpdate: null,
						};
						// Subscriptions configure the Elements with off_session; the
						// minted confirmation token has to match or the confirm fails.
						pendingSetupFutureUsage =
							( opts && opts.setupFutureUsage ) || null;
						return wrapElements(
							targetStripe.elements( opts ),
							ctx,
							elementsState
						);
					};
				}
				if ( prop === 'createConfirmationToken' ) {
					return function ( options ) {
						if ( ! eceConfirmInFlight ) {
							return targetStripe.createConfirmationToken(
								options
							);
						}
						eceConfirmInFlight = false;
						record(
							'stripe',
							'createConfirmationToken',
							[ options ],
							ctx.instanceId
						);
						return mintConfirmationToken(
							ctx,
							pendingMethodType,
							selectedToken
						).then( function ( id ) {
							return { confirmationToken: { id: id } };
						} );
					};
				}
				if ( prop === 'createPaymentMethod' ) {
					return function ( options ) {
						if ( ! eceConfirmInFlight ) {
							return targetStripe.createPaymentMethod( options );
						}
						eceConfirmInFlight = false;
						record(
							'stripe',
							'createPaymentMethod',
							[ options ],
							ctx.instanceId
						);
						return mintPaymentMethod(
							ctx,
							pendingMethodType,
							selectedToken
						).then( function ( id ) {
							return { paymentMethod: { id: id } };
						} );
					};
				}
				const value = Reflect.get( targetStripe, prop );
				return typeof value === 'function'
					? value.bind( targetStripe )
					: value;
			},
		} );
	}

	let instanceCounter = 0;
	function wrap( RealCtor ) {
		return new Proxy( RealCtor, {
			construct: function ( target, args ) {
				const pk = args[ 0 ];
				const options = args[ 1 ] || {};
				record( 'Stripe', 'construct', args );
				const ctx = {
					pk: pk,
					account: options.stripeAccount || '',
					instanceId: instanceCounter++,
				};
				const stripe = new target( ...args );
				return wrapInstance( stripe, ctx );
			},
		} );
	}

	// Wrap, don't replace: capture the genuine ctor the moment Stripe.js assigns
	// `window.Stripe`, so the real script must still load. Cache the wrapped
	// ctor so each read returns the same Proxy rather than a fresh one.
	let realStripe;
	let wrapped;
	Object.defineProperty( window, 'Stripe', {
		configurable: true,
		get: function () {
			if ( ! realStripe ) {
				return undefined;
			}
			if ( ! wrapped ) {
				wrapped = wrap( realStripe );
			}
			return wrapped;
		},
		set: function ( v ) {
			realStripe = v;
		},
	} );
} )();
