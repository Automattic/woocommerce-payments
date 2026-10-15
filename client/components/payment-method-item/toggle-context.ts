/**
 * External dependencies
 */
import { createContext } from 'react';

/**
 * Shows the items as the payment settings screen designs them: a toggle instead of a checkbox,
 * an info icon by the fees, and no "(Required)" label or card brand logos on cards.
 */
const PaymentMethodItemToggleContext = createContext( false );

export default PaymentMethodItemToggleContext;
