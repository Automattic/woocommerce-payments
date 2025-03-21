/**
 * External dependencies
 */
import tinycolor from 'tinycolor2';

document.addEventListener( 'DOMContentLoaded', () => {
	const multibancoInstructionsContainer = document.getElementById(
		'wc-payment-gateway-multibanco-instructions-container'
	);

	if ( multibancoInstructionsContainer ) {
		// Get the computed text color
		const computedTextColor = window.getComputedStyle(
			multibancoInstructionsContainer
		).color;

		// Get the parent's background color, accounting for transparency
		const getEffectiveBackgroundColor = ( element ) => {
			let currentElement = element;
			let backgroundColor = window.getComputedStyle( currentElement )
				.backgroundColor;

			// Keep going up the DOM tree until we find a non-transparent background
			while ( currentElement ) {
				const color = tinycolor( backgroundColor );
				// Skip colors with 0 alpha
				if ( color.getAlpha() > 0 ) {
					return backgroundColor;
				}
				currentElement = currentElement.parentElement;
				if ( ! currentElement ) {
					return 'rgb(255, 255, 255)'; // Default to white if we reach the root
				}
				backgroundColor = window.getComputedStyle( currentElement )
					.backgroundColor;
			}
			return 'rgb(255, 255, 255)'; // Default to white as fallback
		};

		const parentBgColor = getEffectiveBackgroundColor(
			multibancoInstructionsContainer.parentElement
		);

		// Convert RGB color to RGBA with different opacities
		const convertRgbToRgba = ( rgbColor, opacity ) => {
			const color = tinycolor( rgbColor );
			color.setAlpha( opacity );
			return color.toRgbString();
		};

		// Set the CSS variables on the container
		multibancoInstructionsContainer.style.setProperty(
			'--woopayments-multibanco-text-color',
			computedTextColor
		);
		multibancoInstructionsContainer.style.setProperty(
			'--woopayments-multibanco-bg-color',
			convertRgbToRgba( computedTextColor, 0.06 )
		);
		multibancoInstructionsContainer.style.setProperty(
			'--woopayments-multibanco-border-color',
			convertRgbToRgba( computedTextColor, 0.16 )
		);
		multibancoInstructionsContainer.style.setProperty(
			'--woopayments-multibanco-card-bg-color',
			parentBgColor
		);

		// Add click handlers for copy buttons
		const copyButtons = multibancoInstructionsContainer.querySelectorAll(
			'.copy-btn'
		);
		copyButtons.forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const textToCopy = button.dataset.copyValue;
				if ( textToCopy ) {
					navigator.clipboard
						.writeText( textToCopy )
						.then( () => {
							button.classList.add( 'copied' );
							setTimeout( () => {
								button.classList.remove( 'copied' );
							}, 2000 );
						} )
						.catch( () => {
							// show a prompt with the data-copy-value selected in a field and tell the user to copy it
							prompt(
								`Failed to copy text. Please copy it manually:`,
								textToCopy
							);
						} );
				}
			} );
		} );

		// Add click handler for print button
		const printButton = multibancoInstructionsContainer.querySelector(
			'.print-btn'
		);
		if ( printButton ) {
			printButton.addEventListener( 'click', () => {
				window.print();
			} );
		}
	}
} );
