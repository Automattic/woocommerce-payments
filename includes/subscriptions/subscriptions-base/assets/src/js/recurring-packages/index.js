/**
 * External dependencies
 */
import { useMemo } from '@wordpress/element';

/**
 * This component is responsible for rending recurring shippings.
 * It has to be the highest level item directly inside the SlotFill
 * to receive properties passed from Cart and Checkout.
 *
 * extensions is data registered into `/cart` endpoint.
 *
 * @param {Object}   props                       Passed props from SlotFill to this component.
 * @param {Object}   props.extensions            Data registered into `/cart` endpoint.
 * @param {boolean}  props.collapsible           If shipping rates can collapse.
 * @param {boolean}  props.collapse              If shipping rates should collapse.
 * @param {boolean}  props.showItems             If shipping rates should show items inside them.
 * @param {Element}  props.noResultsMessage      Message shown when no rate are found.
 * @param {Function} props.renderOption          Function that decides how rates are going to render.
 * @param {Function} props.useSelectShippingRate useSelectShippingRate hook.
 */
export const SubscriptionsRecurringPackages = ( {
	extensions,
	collapsible,
	collapse,
	showItems,
	noResultsMessage,
	renderOption,
	components,
} ) => {
	const { subscriptions = [] } = extensions;
	const { ShippingRatesControlPackage } = components;

	// Flatten all packages from recurring carts.
	const packages = useMemo(
		() =>
			Object.values( subscriptions )
				.map( ( recurringCart ) => recurringCart.shipping_rates )
				.filter( Boolean )
				.flat(),
		[ subscriptions ]
	);
	const shouldCollapse = useMemo( () => packages.length > 1 || collapse, [
		packages.length,
		collapse,
	] );
	const shouldShowItems = useMemo( () => packages.length > 1 || showItems, [
		packages.length,
		showItems,
	] );
	return packages.map( ( { package_id: packageId, ...packageData } ) => (
		<ShippingRatesControlPackage
			key={ packageId }
			packageId={ packageId }
			packageData={ packageData }
			collapsible={ collapsible }
			collapse={ shouldCollapse }
			showItems={ shouldShowItems }
			noResultsMessage={ noResultsMessage }
			renderOption={ renderOption }
		/>
	) );
};
