/**
 * Internal dependencies
 */
import {
	usePlatformCheckoutButtonType,
	usePlatformCheckoutButtonSize,
	usePlatformCheckoutButtonTheme,
} from 'wcpay/data';
import { formatStringValue } from 'utils';

const PlatformCheckoutButtonPreview = () => {
	const [ buttonType ] = usePlatformCheckoutButtonType();
	const [ size ] = usePlatformCheckoutButtonSize();
	const [ theme ] = usePlatformCheckoutButtonTheme();

	return (
		<button
			key={ `${ buttonType }-${ theme }-${ size }` }
			onClick={ ( e ) => {
				e.preventDefault();
			} }
			className={ `woopay-express-button ${ buttonType }-${ theme }-${ size }` }
		>
			{ 'default' !== buttonType
				? `${ formatStringValue( buttonType ) } with `
				: '' }
			WooPay
		</button>
	);
};

export default PlatformCheckoutButtonPreview;
