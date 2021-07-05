/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';

/**
 * Internal dependencies
 */
import DeleteButton from './delete-button';

const EnabledCurrenciesListItem = ( {
	// eslint-disable-next-line camelcase
	currency: { code, flag, id, is_default: isDefault, name, symbol, rate },
	defaultCurrency: {
		code: defaultCode,
		is_zero_decimal: isDefaultZeroDecimal,
	},
	onDeleteClick,
} ) => {
	const getEditUrl = ( currencyId ) => {
		return `admin.php?page=wc-settings&tab=wcpay_multi_currency&section=${ currencyId.toLowerCase() }`;
	};

	const formatCurrencyRate = () => {
		const formattedRate = isDefaultZeroDecimal
			? Number.parseFloat( rate * 1000 ).toFixed( 2 )
			: Number.parseFloat( rate ).toFixed( 2 );

		// eslint-disable-next-line camelcase
		if ( isDefault ) {
			return __( 'Default currency', 'woocommerce-payments' );
		}

		return isDefaultZeroDecimal
			? `1,000 ${ defaultCode } → ${ formattedRate } ${ code }`
			: `1 ${ defaultCode } → ${ formattedRate } ${ code }`;
	};

	return (
		<li className={ classNames( 'enabled-currency', id ) }>
			<div className="enabled-currency__container">
				<div className="enabled-currency__flag">{ flag }</div>
				<div className="enabled-currency__label">{ name }</div>
				<div className="enabled-currency__code">
					({ symbol } { code })
				</div>
			</div>
			<div className="enabled-currency__rate">
				{ formatCurrencyRate() }
			</div>
			<div className="enabled-currency__actions">
				<Button
					isLink
					href={ getEditUrl( id ) }
					aria-label={ sprintf(
						__(
							/* translators: %1: Currency to be edited. */
							'Edit %1$s',
							'woocommerce-payments'
						),
						name
					) }
					className="enabled-currency__action edit"
				>
					<Icon icon="edit" />
				</Button>
				{ onDeleteClick && (
					<DeleteButton
						className="enabled-currency__action delete"
						onClick={ onDeleteClick }
						label={ name }
						code={ code }
					/>
				) }
			</div>
		</li>
	);
};

export default EnabledCurrenciesListItem;
