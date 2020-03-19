/**
 * External dependencies
 */
import React, { useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { useSelect } from '@wordpress/data';

const CustomInput = ( { id, name, label, value, type, onChange } ) => {
	const inputId = id || name;
	return (
		<div>
			<label htmlFor={ inputId }>{label}</label>
			<input id={ inputId } type={ type || 'text' } value={ value } onChange={ evt => onChange( evt.target.value ) } />
		</div>
	);
};

const FormActions = ( { onSubmit, onClear } ) => (
	<div>
		<button type="submit" onClick={ onSubmit }>Submit</button>
		<button onClick={ onClear }>Clear</button>
	</div>
);

export const Form = ( { onSave, isSaving, error } ) => {
	const [ text, setText ] = useState( '' );
	const [ number, setNumber ] = useState( 0 );
	const onClear = () => {
		setText( '' );
		setNumber( 0 );
	};

	return (
		<form className={ isSaving ? 'disabled' : '' } onSubmit={ evt => evt.preventDefault() } data-testid="demo-form">
			<CustomInput name="textInput" label="Text input" value={ text } onChange={ setText } />
			<CustomInput name="numberInput" label="Number input" value={ number } onChange={ setNumber } />
			<FormActions onSubmit={ () => onSave( { text, number } ) } onClear={ onClear } />
			{ error
			? <p role="alert" className="error message">{error}</p>
			: null }
		</form>
	);
};

export const FormContainer = ( { queryId } ) => {
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const onSave = async ( values ) => {
		try {
			setIsSaving( true );
			setError( '' );
			await apiFetch( {
				path: `/some/update/query/${ queryId }`,
				method: 'post',
				body: values,
			} );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setIsSaving( false );
		}
	};

	return <Form onSave={ onSave } isSaving={ isSaving } error={ error } />;
};

const TransactionsList = ( { transactions, isLoading, error } ) => {
	if ( isLoading ) {
		return <div>Loading ....</div>;
	}

	return (
		<div>
			<ul>
				{ transactions.map( t => <li key={ t.transaction_id }>{ t.transaction_id }</li> ) }
			</ul>
			{ error ? <p role="alert">{ error }</p> : null}
		</div>
	);
};

const selectTransactions = ( select, { paged, perPage, depositId } ) => {
	const {
		getTransactions,
		getTransactionsError,
		isResolving,
	} = select( 'wc/payments' );

	const query = {
		paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
		perPage: Number.isNaN( parseInt( perPage, 10 ) ) ? '25' : perPage,
		depositId: depositId || null,
	};
	return {
		transactions: getTransactions( query ),
		transactionsError: getTransactionsError( query ),
		isLoading: isResolving( 'getTransactions', [ query ] ),
	};
};

export const ConnectedTransactions = ( { paged, perPage, depositId } ) => {
	const {
		transactions,
		isLoading,
		transactionsError,
	} = useSelect( selectTransactions, [ paged, perPage, depositId ] );

	return <TransactionsList transactions={ transactions || [] } isLoading={ isLoading } error={ transactionsError.message } />;
};

