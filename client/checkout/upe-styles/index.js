/**
 * Internal dependencies
 */
import { upeRestrictedProperties } from './upe-styles';
import { generateHoverRules, generateOutlineStyle } from './utils.js';

const appearanceSelectors = {
	default: {
		hiddenContainer: '#wcpay-hidden-div',
		hiddenInput: '#wcpay-hidden-input',
		hiddenInvalidInput: '#wcpay-hidden-invalid-input',
	},
	classicCheckout: {
		appendTarget: '.woocommerce-billing-fields__field-wrapper',
		upeThemeInputSelector: '#billing_first_name',
		upeThemeLabelSelector: '.woocommerce-checkout .form-row label',
		rowElement: 'p',
		validClasses: [ 'form-row' ],
		invalidClasses: [
			'form-row',
			'woocommerce-invalid',
			'woocommerce-invalid-required-field',
		],
	},
	blocksCheckout: {
		appendTarget: '#shipping.wc-block-components-address-form',
		upeThemeInputSelector: '#shipping-first_name',
		upeThemeLabelSelector: '.wc-block-components-text-input label',
		rowElement: 'div',
		validClasses: [ 'wc-block-components-text-input' ],
		invalidClasses: [ 'wc-block-components-text-input', 'has-error' ],
	},
	getSelectors: function ( isBlocksCheckout = false ) {
		if ( isBlocksCheckout ) {
			return { ...this.default, ...this.blocksCheckout };
		}

		return { ...this.default, ...this.classicCheckout };
	},
};

const dashedToCamelCase = ( string ) => {
	return string.replace( /-([a-z])/g, function ( g ) {
		return g[ 1 ].toUpperCase();
	} );
};

const hiddenElementsForUPE = {
	/**
	 * Create hidden container for generating UPE styles.
	 *
	 * @param {string} elementID ID of element to create.
	 *
	 * @return {Object} Object of the created hidden container element.
	 */
	getHiddenContainer: function ( elementID ) {
		const hiddenDiv = document.createElement( 'div' );
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
	 * @param {string} element Type of element to create.
	 * @param {Array} classes Array of classes to be added to the element. Default: empty array.
	 *
	 * @return {Object} Object of the created invalid row element.
	 */
	createRow: function ( elementType, classes = [] ) {
		const newRow = document.createElement( elementType );
		if ( classes.length ) {
			newRow.classList.add( ...classes );
		}
		return newRow;
	},

	/**
	 * Append elements to target container.
	 *
	 * @param {Object} appendTarget Element object where clone should be appended.
	 * @param {string} elementToClone Selector of the element to be cloned.
	 * @param {string} newElementID Selector for the cloned element.
	 */
	appendClone: function ( appendTarget, elementToClone, newElementID ) {
		const cloneTarget = document.querySelector( elementToClone );
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
	 * @param {boolean} isBlocksCheckout True if Blocks Checkout. Default false.
	 */
	init: function ( isBlocksCheckout = false ) {
		const selectors = appearanceSelectors.getSelectors( isBlocksCheckout ),
			appendTarget = document.querySelector( selectors.appendTarget ),
			elementToClone = document.querySelector(
				selectors.upeThemeInputSelector
			);

		// Exit early if elements are not present.
		if ( ! appendTarget || ! elementToClone ) {
			return;
		}

		// Remove hidden container is already present on DOM.
		if ( document.querySelector( selectors.hiddenContainer ) ) {
			this.cleanup();
		}

		// Create hidden container & append to target.
		const hiddenContainer = this.getHiddenContainer(
			selectors.hiddenContainer
		);
		appendTarget.appendChild( hiddenContainer );

		// Create hidden valid row & append to hidden container.
		const hiddenValidRow = this.createRow(
			selectors.rowElement,
			selectors.validClasses
		);
		hiddenContainer.appendChild( hiddenValidRow );

		// Create hidden invalid row & append to hidden container.
		const hiddenInvalidRow = this.createRow(
			selectors.rowElement,
			selectors.invalidClasses
		);
		hiddenContainer.appendChild( hiddenInvalidRow );

		// Clone & append target element to hidden valid row.
		this.appendClone(
			hiddenValidRow,
			selectors.upeThemeInputSelector,
			selectors.hiddenInput
		);

		// Clone & append target element to hidden invalid row.
		this.appendClone(
			hiddenInvalidRow,
			selectors.upeThemeInputSelector,
			selectors.hiddenInvalidInput
		);

		// Remove transitions & focus on hidden element.
		const wcpayHiddenInput = document.querySelector(
			selectors.hiddenInput
		);
		wcpayHiddenInput.style.transition = 'none';
		//wcpayHiddenInput.focus();
	},

	/**
	 * Remove hidden container from DROM.
	 */
	cleanup: function () {
		const element = document.querySelector(
			appearanceSelectors.default.hiddenContainer
		);
		if ( element ) {
			element.remove();
		}
	},
};

export const getFieldStyles = ( selector, upeElement, focus = false ) => {
	if ( ! document.querySelector( selector ) ) {
		return {};
	}

	const validProperties = upeRestrictedProperties[ upeElement ];

	const elem = document.querySelector( selector );
	if ( focus ) {
		elem.focus();
	}

	const styles = window.getComputedStyle( elem );

	const filteredStyles = {};

	for ( let i = 0; i < styles.length; i++ ) {
		const camelCase = dashedToCamelCase( styles[ i ] );
		if ( validProperties.includes( camelCase ) ) {
			filteredStyles[ camelCase ] = styles.getPropertyValue(
				styles[ i ]
			);
		}
	}

	if ( '.Input' === upeElement || '.Tab--selected' === upeElement ) {
		const outline = generateOutlineStyle(
			filteredStyles.outlineWidth,
			filteredStyles.outlineStyle,
			filteredStyles.outlineColor
		);
		if ( '' !== outline ) {
			filteredStyles.outline = outline;
		}
		delete filteredStyles.outlineWidth;
		delete filteredStyles.outlineColor;
		delete filteredStyles.outlineStyle;
	}

	return filteredStyles;
};

export const getFontRulesFromPage = () => {
	const fontRules = [],
		sheets = document.styleSheets,
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
		if ( -1 !== fontDomains.indexOf( url.hostname ) ) {
			fontRules.push( {
				cssSrc: sheets[ i ].href,
			} );
		}
	}

	return fontRules;
};

export const getAppearance = ( isBlocksCheckout = false ) => {
	const selectors = appearanceSelectors.getSelectors( isBlocksCheckout );

	// Add hidden fields to DOM for generating styles.
	hiddenElementsForUPE.init( isBlocksCheckout );

	const inputRules = getFieldStyles( selectors.hiddenInput, '.Input' );
	const inputFocusRules = getFieldStyles(
		selectors.hiddenInput,
		'.Input',
		true
	);
	const inputInvalidRules = getFieldStyles(
		selectors.hiddenInvalidInput,
		'.Input'
	);

	const labelRules = getFieldStyles(
		selectors.upeThemeLabelSelector,
		'.Label'
	);

	const tabRules = getFieldStyles( selectors.upeThemeInputSelector, '.Tab' );
	const selectedTabRules = getFieldStyles(
		selectors.hiddenInput,
		'.Tab--selected'
	);
	const tabHoverRules = generateHoverRules( tabRules );

	const tabIconHoverRules = {
		color: tabHoverRules.color,
	};
	const selectedTabIconRules = {
		color: selectedTabRules.color,
	};

	const appearance = {
		rules: {
			'.Input': inputRules,
			'.Input:focus': inputFocusRules,
			'.Input--invalid': inputInvalidRules,
			'.Label': labelRules,
			'.Tab': tabRules,
			'.Tab:hover': tabHoverRules,
			'.Tab--selected': selectedTabRules,
			'.TabIcon:hover': tabIconHoverRules,
			'.TabIcon--selected': selectedTabIconRules,
			'.Text': labelRules,
			'.Text--redirect': labelRules,
		},
	};

	// Remove hidden fields from DOM.
	hiddenElementsForUPE.cleanup();
	return appearance;
};
