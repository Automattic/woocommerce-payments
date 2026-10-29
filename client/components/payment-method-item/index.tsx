/**
 * Internal dependencies
 */
import PaymentMethodItemRoot from './payment-method-item';
import PaymentMethodItemCheckbox from './checkbox';
import PaymentMethodItemBody from './body';
import PaymentMethodItemSubgroup from './subgroup';
import PaymentMethodItemAction from './action';

export { default as PaymentMethodItemToggleContext } from './toggle-context';

const PaymentMethodItem = Object.assign( PaymentMethodItemRoot, {
	Checkbox: PaymentMethodItemCheckbox,
	Body: PaymentMethodItemBody,
	Subgroup: PaymentMethodItemSubgroup,
	Action: PaymentMethodItemAction,
} );

export default PaymentMethodItem;
