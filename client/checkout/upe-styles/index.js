/**
 * Internal dependencies
 */
import { upeRestrictedProperties } from './upe-styles';
import {
	generateHoverRules,
	generateOutlineStyle,
	dashedToCamelCase,
	isColorLight,
	getBackgroundColor,
	maybeConvertRGBAtoRGB,
	handleAppearanceForFloatingLabel,
} from './utils.js';

const PMME_RELATIVE_TEXT_SIZE = 0.875;

export const appearanceSelectors = {
	default: {
		hiddenContainer: '#wcpay-hidden-div',
		hiddenInput: '#wcpay-hidden-input',
		hiddenInvalidInput: '#wcpay-hidden-invalid-input',
		hiddenValidActiveLabel: '#wcpay-hidden-valid-active-label',
	},
	classicCheckout: {
		appendTarget: '.woocommerce-billing-fields__field-wrapper',
		upeThemeInputSelector: '#billing_first_name',
		upeThemeLabelSelector: '.woocommerce-checkout .form-row label',
		upeThemeTextSelectors: [
			'#payment .payment_methods li .payment_box fieldset',
			'.woocommerce-checkout .form-row',
		],
		rowElement: 'p',
		validClasses: [ 'form-row' ],
		invalidClasses: [
			'form-row',
			'woocommerce-invalid',
			'woocommerce-invalid-required-field',
		],
		backgroundSelectors: [
			'li.wc_payment_method .wc-payment-form',
			'li.wc_payment_method .payment_box',
			'#payment',
			'#order_review',
			'form.checkout',
			'body',
		],
		headingSelectors: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		buttonSelectors: [ '#place_order' ],
		linkSelectors: [ 'a' ],
		pmmeRelativeTextSizeSelector: '.wc_payment_method > label',
	},
	blocksCheckout: {
		appendTarget: '.wc-block-checkout__contact-fields',
		upeThemeInputSelector: '.wc-block-components-text-input #email',
		upeThemeLabelSelector: '.wc-block-components-text-input label',
		upeThemeTextSelectors: [
			'.wc-block-components-checkout-step__description',
			'.wc-block-components-text-input',
		],
		rowElement: 'div',
		validClasses: [ 'wc-block-components-text-input', 'is-active' ],
		invalidClasses: [ 'wc-block-components-text-input', 'has-error' ],
		alternateSelectors: {
			appendTarget: '#billing.wc-block-components-address-form',
			upeThemeInputSelector: '#billing-first_name',
			upeThemeLabelSelector:
				'.wc-block-components-checkout-step__description',
		},
		backgroundSelectors: [
			'#payment-method .wc-block-components-radio-control-accordion-option',
			'#payment-method',
			'form.wc-block-checkout__form',
			'.wc-block-checkout',
			'body',
		],
		headingSelectors: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		buttonSelectors: [ '.wc-block-components-checkout-place-order-button' ],
		linkSelectors: [ 'a' ],
		containerSelectors: [
			'.wp-block-woocommerce-checkout-order-summary-block',
		],
		pmmeRelativeTextSizeSelector:
			'.wc-block-components-radio-control__label-group',
	},
	bnplProductPage: {
		appendTarget: '.product .cart .quantity',
		upeThemeInputSelector: '.product .cart .quantity .qty',
		upeThemeLabelSelector: '.product .cart .quantity label',
		upeThemeTextSelectors: [ '.product .cart .quantity' ],
		rowElement: 'div',
		validClasses: [ 'input-text' ],
		invalidClasses: [ 'input-text', 'has-error' ],
		backgroundSelectors: [
			'#payment-method-message',
			'#main > .product > div.summary.entry-summary',
			'#main > .product',
			'#main',
			'body',
		],
		headingSelectors: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		buttonSelectors: [ '.single_add_to_cart_button' ],
		linkSelectors: [ 'a' ],
	},
	bnplClassicCart: {
		appendTarget: '.cart .quantity',
		upeThemeInputSelector: '.cart .quantity .qty',
		upeThemeLabelSelector: '.cart .quantity label',
		upeThemeTextSelectors: [ '.cart .quantity' ],
		rowElement: 'div',
		validClasses: [ 'input-text' ],
		invalidClasses: [ 'input-text', 'has-error' ],
		backgroundSelectors: [
			'#payment-method-message',
			'#main .entry-content .cart_totals',
			'#main .entry-content',
			'#main',
			'body',
		],
		headingSelectors: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		buttonSelectors: [ '.checkout-button' ],
		linkSelectors: [ 'a' ],
		containerSelectors: [ '.shop_table' ],
	},
	bnplCartBlock: {
		appendTarget: '.wc-block-cart .wc-block-components-quantity-selector',
		upeThemeInputSelector:
			'.wc-block-cart .wc-block-components-quantity-selector .wc-block-components-quantity-selector__input',
		upeThemeLabelSelector: '.wc-block-components-text-input',
		upeThemeTextSelectors: [ '.wc-block-components-text-input' ],
		rowElement: 'div',
		validClasses: [ 'wc-block-components-text-input' ],
		invalidClasses: [ 'wc-block-components-text-input', 'has-error' ],
		backgroundSelectors: [
			'.wc-block-components-bnpl-wrapper',
			'.wc-block-components-order-meta',
			'.wc-block-components-totals-wrapper',
			'.wp-block-woocommerce-cart-order-summary-block',
			'.wp-block-woocommerce-cart-totals-block',
			'.wp-block-woocommerce-cart .wc-block-cart',
			'.wp-block-woocommerce-cart',
			'body',
		],
		headingSelectors: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		buttonSelectors: [ '.wc-block-cart__submit-button' ],
		linkSelectors: [ 'a' ],
		containerSelectors: [ '.wp-block-woocommerce-cart-line-items-block' ],
	},
	wooPayClassicCheckout: {
		appendTarget: '.woocommerce-billing-fields__field-wrapper',
		upeThemeInputSelector: '#billing_first_name',
		upeThemeLabelSelector: '.woocommerce-checkout .form-row label',
		upeThemeTextSelectors: [ '.woocommerce-checkout .form-row' ],
		rowElement: 'p',
		validClasses: [ 'form-row' ],
		invalidClasses: [
			'form-row',
			'woocommerce-invalid',
			'woocommerce-invalid-required-field',
		],
		backgroundSelectors: [
			'#customer_details',
			'#order_review',
			'form.checkout',
			'body',
		],
		headingSelectors: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		buttonSelectors: [ '#place_order' ],
		linkSelectors: [ 'a' ],
		containerSelectors: [ '.woocommerce-checkout-review-order-table' ],
		headerSelectors: [ '.site-header', 'header > div' ],
		footerSelectors: [ '.site-footer', 'footer > div' ],
		footerLink: [ '.site-footer a', 'footer a' ],
	},

	/**
	 * Update selectors to use alternate if not present on DOM.
	 *
	 * @param {Object} selectors Object of selectors for updation.
	 * @param {Object} scope     The document scope to search in.
	 *
	 * @return {Object} Updated selectors.
	 */
	updateSelectors: function ( selectors, scope ) {
		if ( selectors.hasOwnProperty( 'alternateSelectors' ) ) {
			Object.entries( selectors.alternateSelectors ).forEach(
				( altSelector ) => {
					const [ key, value ] = altSelector;

					if ( ! scope.querySelector( selectors[ key ] ) ) {
						selectors[ key ] = value;
					}
				}
			);

			delete selectors.alternateSelectors;
		}

		return selectors;
	},

	/**
	 * Returns selectors based on checkout type.
	 *
	 * @param {boolean} elementsLocation The location of the elements.
	 * @param {Object}  scope           The document scope to search in.
	 *
	 * @return {Object} Selectors for checkout type specified.
	 */
	getSelectors: function ( elementsLocation, scope ) {
		let appearanceSelector = this.blocksCheckout;

		switch ( elementsLocation ) {
			case 'blocks_checkout':
				appearanceSelector = this.blocksCheckout;
				break;
			case 'shortcode_checkout':
				appearanceSelector = this.classicCheckout;
				break;
			case 'bnpl_product_page':
				appearanceSelector = this.bnplProductPage;
				break;
			case 'bnpl_classic_cart':
				appearanceSelector = this.bnplClassicCart;
				break;
			case 'bnpl_cart_block':
				appearanceSelector = this.bnplCartBlock;
				break;
			case 'woopay_shortcode_checkout':
				appearanceSelector = this.wooPayClassicCheckout;
				break;
		}

		return {
			...this.default,
			...this.updateSelectors( appearanceSelector, scope ),
		};
	},
};

const hiddenElementsForUPE = {
	/**
	 * Create hidden container for generating UPE styles.
	 *
	 * @param {string} elementID ID of element to create.
	 * @param {Object} scope The document scope to search in.
	 *
	 * @return {Object} Object of the created hidden container element.
	 */
	getHiddenContainer: function ( elementID, scope ) {
		const hiddenDiv = scope.createElement( 'div' );
		hiddenDiv.setAttribute( 'id', this.getIDFromSelector( elementID ) );
		hiddenDiv.style.border = 0;
		hiddenDiv.style.clip = 'rect(0 0 0 0)';
		hiddenDiv.style.height = '1px';
		hiddenDiv.style.margin = '-1px';
		hiddenDiv.style.overflow = 'hidden';
		hiddenDiv.style.padding = '0';
		hiddenDiv.style.position = 'absolute';
		hiddenDiv.style.width = '1px';
		return hiddenDiv;
	},

	/**
	 * Create invalid element row for generating UPE styles.
	 *
	 * @param {string} elementType Type of element to create.
	 * @param {Array}  classes     Array of classes to be added to the element. Default: empty array.
	 * @param {Object} scope       The document scope to search in.
	 *
	 * @return {Object} Object of the created invalid row element.
	 */
	createRow: function ( elementType, classes = [], scope ) {
		const newRow = scope.createElement( elementType );
		if ( classes.length ) {
			newRow.classList.add( ...classes );
		}
		return newRow;
	},

	/**
	 * Append elements to target container.
	 *
	 * @param {Object} appendTarget   Element object where clone should be appended.
	 * @param {string} elementToClone Selector of the element to be cloned.
	 * @param {string} newElementID   Selector for the cloned element.
	 * @param {Object} scope         The document scope to search in.
	 */
	appendClone: function (
		appendTarget,
		elementToClone,
		newElementID,
		scope
	) {
		const cloneTarget = scope.querySelector( elementToClone );
		if ( cloneTarget ) {
			const clone = cloneTarget.cloneNode( true );
			clone.id = this.getIDFromSelector( newElementID );
			clone.value = '';
			appendTarget.appendChild( clone );
		}
	},

	/**
	 * Retrieve ID/Class from selector.
	 *
	 * @param {string} selector Element selector.
	 *
	 * @return {string} Extracted ID/Class from selector.
	 */
	getIDFromSelector: function ( selector ) {
		if ( selector.startsWith( '#' ) || selector.startsWith( '.' ) ) {
			return selector.slice( 1 );
		}

		return selector;
	},

	/**
	 * Initialize hidden fields to generate UPE styles.
	 *
	 * @param {boolean} elementsLocation The location of the elements.
	 * @param {Object} scope The scope of the elements.
	 */
	init: function ( elementsLocation, scope ) {
		const selectors = appearanceSelectors.getSelectors( elementsLocation ),
			appendTarget = scope.querySelector( selectors.appendTarget ),
			elementToClone = scope.querySelector(
				selectors.upeThemeInputSelector
			);

		// Exit early if elements are not present.
		if ( ! appendTarget || ! elementToClone ) {
			return;
		}

		// Remove hidden container is already present on DOM.
		if ( scope.querySelector( selectors.hiddenContainer ) ) {
			this.cleanup( scope );
		}

		// Create hidden container & append to target.
		const hiddenContainer = this.getHiddenContainer(
			selectors.hiddenContainer,
			scope
		);
		appendTarget.appendChild( hiddenContainer );

		// Create hidden valid row & append to hidden container.
		const hiddenValidRow = this.createRow(
			selectors.rowElement,
			selectors.validClasses,
			scope
		);
		hiddenContainer.appendChild( hiddenValidRow );

		// Create hidden invalid row & append to hidden container.
		const hiddenInvalidRow = this.createRow(
			selectors.rowElement,
			selectors.invalidClasses,
			scope
		);
		hiddenContainer.appendChild( hiddenInvalidRow );

		// Clone & append target input to hidden valid row.
		this.appendClone(
			hiddenValidRow,
			selectors.upeThemeInputSelector,
			selectors.hiddenInput,
			scope
		);

		// Clone & append target label to hidden valid row.
		this.appendClone(
			hiddenValidRow,
			selectors.upeThemeLabelSelector,
			selectors.hiddenValidActiveLabel,
			scope
		);

		// Clone & append target input to hidden invalid row.
		this.appendClone(
			hiddenInvalidRow,
			selectors.upeThemeInputSelector,
			selectors.hiddenInvalidInput,
			scope
		);

		// Clone & append target label to hidden invalid row.
		this.appendClone(
			hiddenInvalidRow,
			selectors.upeThemeLabelSelector,
			selectors.hiddenInvalidInput,
			scope
		);

		// Remove transitions & focus on hidden element.
		const wcpayHiddenInput = scope.querySelector( selectors.hiddenInput );
		wcpayHiddenInput.style.transition = 'none';
	},

	/**
	 * Remove hidden container from DOM.
	 *
	 * @param {Object} scope The scope of the elements.
	 */
	cleanup: function ( scope ) {
		const element = scope.querySelector(
			appearanceSelectors.default.hiddenContainer
		);
		if ( element ) {
			element.remove();
		}
	},
};

export const getFieldStyles = (
	selector,
	upeElement,
	backgroundColor = null,
	scope
) => {
	if ( ! scope.querySelector( selector ) ) {
		return {};
	}

	const windowObject = scope.defaultView || window;

	const validProperties = upeRestrictedProperties[ upeElement ];

	const elem = scope.querySelector( selector );

	const styles = windowObject.getComputedStyle( elem );

	const filteredStyles = {};
	for ( let i = 0; i < styles.length; i++ ) {
		const camelCase = dashedToCamelCase( styles[ i ] );
		if ( validProperties.includes( camelCase ) ) {
			let propertyValue = styles.getPropertyValue( styles[ i ] );
			if ( camelCase === 'color' ) {
				propertyValue = maybeConvertRGBAtoRGB( propertyValue );
			}
			filteredStyles[ camelCase ] = propertyValue;
		}
	}

	if ( upeElement === '.Input' || upeElement === '.Tab--selected' ) {
		const outline = generateOutlineStyle(
			filteredStyles.outlineWidth,
			filteredStyles.outlineStyle,
			filteredStyles.outlineColor
		);
		if ( outline !== '' ) {
			filteredStyles.outline = outline;
		}
		delete filteredStyles.outlineWidth;
		delete filteredStyles.outlineColor;
		delete filteredStyles.outlineStyle;
	}

	// Workaround for rewriting text-indents to padding-left & padding-right
	//since Stripe doesn't support text-indents.
	const textIndent = styles.getPropertyValue( 'text-indent' );
	if (
		textIndent !== '0px' &&
		filteredStyles.paddingLeft === '0px' &&
		filteredStyles.paddingRight === '0px'
	) {
		filteredStyles.paddingLeft = textIndent;
		filteredStyles.paddingRight = textIndent;
	}

	if ( upeElement === '.Block' ) {
		filteredStyles.backgroundColor = backgroundColor;
	}

	return filteredStyles;
};

export const getFontRulesFromPage = ( scope = document ) => {
	const fontRules = [],
		sheets = scope.styleSheets,
		fontDomains = [
			'fonts.googleapis.com',
			'fonts.gstatic.com',
			'fast.fonts.com',
			'use.typekit.net',
		];
	for ( let i = 0; i < sheets.length; i++ ) {
		if ( ! sheets[ i ].href ) {
			continue;
		}
		const url = new URL( sheets[ i ].href );
		if ( fontDomains.indexOf( url.hostname ) !== -1 ) {
			fontRules.push( {
				cssSrc: sheets[ i ].href,
			} );
		}
	}

	return fontRules;
};

/**
 * Ensure the font size of the element is smaller than the font size of target element.
 *
 * @param {string} selector Selector of the element to be checked.
 * @param {string} fontSize Pre-computed font size.
 * @param {number} percentage Percentage (0-1) to be used relative to the font size of the target element.
 * @param {Object} scope The scope of the elements.
 *
 * @return {string} Font size of the element.
 */
function ensureFontSizeSmallerThan(
	selector,
	fontSize,
	percentage = PMME_RELATIVE_TEXT_SIZE,
	scope
) {
	const fontSizeNumber = parseFloat( fontSize );

	if ( isNaN( fontSizeNumber ) ) {
		return fontSize;
	}

	// If the element is not found, return the font size number multiplied by the percentage.
	const elem = scope.querySelector( selector );
	if ( ! elem ) {
		return `${ fontSizeNumber * percentage }px`;
	}

	const styles = window.getComputedStyle( elem );
	const targetFontSize = styles.getPropertyValue( 'font-size' );
	const targetFontSizeNumber = parseFloat( targetFontSize ) * percentage;

	if ( isNaN( targetFontSizeNumber ) ) {
		return fontSize;
	}

	if ( fontSizeNumber > targetFontSizeNumber ) {
		return `${ targetFontSizeNumber }px`;
	}

	return `${ fontSizeNumber }px`;
}

export const getAppearance = (
	elementsLocation,
	forWooPay = false,
	scope = document
) => {
	const selectors = appearanceSelectors.getSelectors(
		elementsLocation,
		scope
	);

	// Add hidden fields to DOM for generating styles.
	hiddenElementsForUPE.init( elementsLocation, scope );

	const inputRules = getFieldStyles(
		selectors.hiddenInput,
		'.Input',
		null,
		scope
	);
	const inputInvalidRules = getFieldStyles(
		selectors.hiddenInvalidInput,
		'.Input',
		null,
		scope
	);

	const labelRules = getFieldStyles(
		selectors.upeThemeLabelSelector,
		'.Label',
		null,
		scope
	);

	const labelRestingRules = {
		fontSize: labelRules.fontSize,
	};

	const paragraphRules = getFieldStyles(
		selectors.upeThemeTextSelectors,
		'.Text',
		null,
		scope
	);

	const tabRules = getFieldStyles(
		selectors.upeThemeInputSelector,
		'.Tab',
		null,
		scope
	);
	const selectedTabRules = getFieldStyles(
		selectors.hiddenInput,
		'.Tab--selected',
		null,
		scope
	);
	const tabHoverRules = generateHoverRules( tabRules );

	const tabIconHoverRules = {
		color: tabHoverRules.color,
	};
	const selectedTabIconRules = {
		color: selectedTabRules.color,
	};

	const backgroundColor = getBackgroundColor(
		selectors.backgroundSelectors,
		scope
	);
	const headingRules = getFieldStyles(
		selectors.headingSelectors,
		'.Label',
		null,
		scope
	);
	const blockRules = getFieldStyles(
		selectors.upeThemeLabelSelector,
		'.Block',
		backgroundColor,
		scope
	);
	const buttonRules = getFieldStyles(
		selectors.buttonSelectors,
		'.Input',
		null,
		scope
	);
	const linkRules = getFieldStyles(
		selectors.linkSelectors,
		'.Label',
		null,
		scope
	);
	const containerRules = getFieldStyles(
		selectors.containerSelectors,
		'.Container',
		null,
		scope
	);
	const headerRules = getFieldStyles(
		selectors.headerSelectors,
		'.Header',
		null,
		scope
	);
	const footerRules = getFieldStyles(
		selectors.footerSelectors,
		'.Footer',
		null,
		scope
	);
	const footerLinkRules = getFieldStyles(
		selectors.footerLink,
		'.Footer--link',
		null,
		scope
	);
	const globalRules = {
		colorBackground: backgroundColor,
		colorText: paragraphRules.color,
		fontFamily: paragraphRules.fontFamily,
		fontSizeBase: paragraphRules.fontSize,
	};

	if ( selectors.pmmeRelativeTextSizeSelector && globalRules.fontSizeBase ) {
		globalRules.fontSizeBase = ensureFontSizeSmallerThan(
			selectors.pmmeRelativeTextSizeSelector,
			paragraphRules.fontSize,
			PMME_RELATIVE_TEXT_SIZE,
			scope
		);
	}

	const isFloatingLabel = elementsLocation === 'blocks_checkout';

	let appearance = {
		variables: globalRules,
		theme: isColorLight( backgroundColor ) ? 'stripe' : 'night',
		labels: isFloatingLabel ? 'floating' : 'above',
		// We need to clone the object to avoid modifying other rules when updating the appearance for floating labels.
		rules: JSON.parse(
			JSON.stringify( {
				'.Input': inputRules,
				'.Input--invalid': inputInvalidRules,
				'.Label': labelRules,
				'.Label--resting': labelRestingRules,
				'.Block': blockRules,
				'.Tab': tabRules,
				'.Tab:hover': tabHoverRules,
				'.Tab--selected': selectedTabRules,
				'.TabIcon:hover': tabIconHoverRules,
				'.TabIcon--selected': selectedTabIconRules,
				'.Text': paragraphRules,
				'.Text--redirect': paragraphRules,
			} )
		),
	};

	if ( isFloatingLabel ) {
		appearance = handleAppearanceForFloatingLabel(
			appearance,
			getFieldStyles(
				selectors.hiddenValidActiveLabel,
				'.Label--floating',
				null,
				scope
			)
		);
	}

	if ( forWooPay ) {
		appearance.rules = {
			...appearance.rules,
			'.Heading': headingRules,
			'.Header': headerRules,
			'.Footer': footerRules,
			'.Footer-link': footerLinkRules,
			'.Button': buttonRules,
			'.Link': linkRules,
			'.Container': containerRules,
		};
	}

	// Remove hidden fields from DOM.
	hiddenElementsForUPE.cleanup( scope );
	return appearance;
};
