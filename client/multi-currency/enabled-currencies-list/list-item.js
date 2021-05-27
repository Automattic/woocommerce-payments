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
	currency: {
		code,
		flag,
		id,
		is_default: { isDefault },
		name,
		symbol,
	},
	onDeleteClick,
} ) => {
	const defaultText = __( 'Default currency', 'woocommerce-payments' );
	const currencyCode = isDefault ? `${ code } - ${ defaultText }` : code;
	const currencySymbol = { __html: symbol };

	const getEditUrl = ( currencyId ) => {
		return `admin.php?page=wc-settings&tab=wcpay_multi_currency&section=${ currencyId.toLowerCase() }`;
	};

	return (
		<li className={ classNames( 'enabled-currency', id ) }>
			<div className="enabled-currency__container">
				<div className="enabled-currency__flag">{ flag }</div>
				<div className="enabled-currency__label">{ name }</div>
				<div className="enabled-currency__code">
					(<span dangerouslySetInnerHTML={ currencySymbol } />{ ' ' }
					{ currencyCode })
				</div>
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
