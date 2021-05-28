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

const defaultText = __( 'Default currency', 'woocommerce-payments' );

// TODO: The currency.symbol may be a HTML element, so that needs to be fixed.
// TODO: currency can be deconstructed into its properties.
const EnabledCurrenciesListItem = ( { currency, onDeleteClick } ) => {
	const code = currency.is_default
		? `${ currency.code } - ${ defaultText }`
		: currency.code;

	const getEditUrl = ( id ) => {
		return `admin.php?page=wc-settings&tab=wcpay_multi_currency&section=${ id.toLowerCase() }`;
	};

	return (
		<li className={ classNames( 'enabled-currency', currency.id ) }>
			<div className="enabled-currency__container">
				<div className="enabled-currency__flag">{ currency.flag }</div>
				<div className="enabled-currency__label">{ currency.name }</div>
				<div className="enabled-currency__code">
					({ currency.symbol } { code })
				</div>
			</div>
			<div className="enabled-currency__actions">
				<Button
					isLink
					href={ getEditUrl( currency.id ) }
					aria-label={ sprintf(
						__(
							/* translators: %1: Currency to be edited. */
							'Edit %1$s',
							'woocommerce-payments'
						),
						currency.name
					) }
					className="enabled-currency__action edit"
				>
					<Icon icon="edit" />
				</Button>
				{ onDeleteClick && (
					<DeleteButton
						className="enabled-currency__action delete"
						onClick={ onDeleteClick }
						label={ currency.name }
						code={ currency.code }
					/>
				) }
			</div>
		</li>
	);
};

export default EnabledCurrenciesListItem;
