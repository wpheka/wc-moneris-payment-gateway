import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { formatCardNumber, formatExpiry, formatCvc } from './card-format.mjs';

const inputStyle = {
    width: '100%',
    padding: '0.75rem',
    border: '1px solid #d1d5db',
    borderRadius: '0.5rem',
    fontSize: '1rem',
    boxSizing: 'border-box',
    transition: 'border-color 0.2s ease-in-out',
    marginBottom: '0.5rem',
};

const inputErrorStyle = {
    ...inputStyle,
    border: '1px solid #ef4444',
};

const CreditCardFields = ( { handleInputChange, METHOD_NAME, directSettings } ) => {
    const { formState: { errors } } = useForm();
    const [ values, setValues ] = useState( { number: '', expiry: '', cvc: '' } );

    // Formats whatever arrives (typing, paste, autofill) and hands the result on as the
    // field's value, the same shape the server has always read.
    const change = ( key, format ) => ( e ) => {
        const value = format( e.target.value );
        setValues( ( prev ) => ( { ...prev, [ key ]: value } ) );
        handleInputChange( { target: { name: e.target.name, value } } );
    };

    return (
        <div style={ { padding: '0.5rem 0' } }>
            <p>{ decodeEntities( directSettings.description || '' ) }</p>

            {/* Card Number */}
            <label style={ { display: 'block', marginBottom: '0.5rem', fontWeight: '600' } } htmlFor={ `${METHOD_NAME}-card-number` }>
                { __( 'Card Number', 'wpheka-gateway-moneris' ) }
            </label>
            <input
                type="text"
                id={ `${METHOD_NAME}-card-number` }
                name={ `${METHOD_NAME}-card-number` }
                inputMode="numeric"
                autoComplete="cc-number"
                placeholder="1234 5678 9012 3456"
                style={ errors.cardNumber ? inputErrorStyle : inputStyle }
                value={ values.number }
                onChange={ change( 'number', formatCardNumber ) }
            />
            { errors.cardNumber && (
                <p style={ { color: 'red', marginTop: '-0.3rem', marginBottom: '0.75rem', fontSize: '0.875rem' } }>
                    { errors.cardNumber.message }
                </p>
            ) }

            {/* Expiry */}
            <label style={ { display: 'block', marginTop: '0.5rem', marginBottom: '0.5rem', fontWeight: '600' } } htmlFor={ `${METHOD_NAME}-card-expiry` }>
                { __( 'Expiry (MM/YY)', 'wpheka-gateway-moneris' ) }
            </label>
            <input
                type="text"
                id={ `${METHOD_NAME}-card-expiry` }
                name={ `${METHOD_NAME}-card-expiry` }
                inputMode="numeric"
                autoComplete="cc-exp"
                placeholder="MM/YY"
                style={ errors.expiry ? inputErrorStyle : inputStyle }
                value={ values.expiry }
                onChange={ change( 'expiry', formatExpiry ) }
            />
            { errors.expiry && (
                <p style={ { color: 'red', marginTop: '-0.3rem', marginBottom: '0.75rem', fontSize: '0.875rem' } }>
                    { errors.expiry.message }
                </p>
            ) }

            {/* CVC */}
            <label style={ { display: 'block', marginTop: '0.5rem', marginBottom: '0.5rem', fontWeight: '600' } } htmlFor={ `${METHOD_NAME}-card-cvc` }>
                { __( 'CVC', 'wpheka-gateway-moneris' ) }
            </label>
            <input
                type="text"
                id={ `${METHOD_NAME}-card-cvc` }
                name={ `${METHOD_NAME}-card-cvc` }
                inputMode="numeric"
                autoComplete="cc-csc"
                placeholder="CVC"
                style={ errors.cvc ? inputErrorStyle : inputStyle }
                value={ values.cvc }
                onChange={ change( 'cvc', formatCvc ) }
            />
            { errors.cvc && (
                <p style={ { color: 'red', marginTop: '-0.3rem', marginBottom: '0.75rem', fontSize: '0.875rem' } }>
                    { errors.cvc.message }
                </p>
            ) }
        </div>
    );
};

export default CreditCardFields;
