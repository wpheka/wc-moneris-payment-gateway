import React from 'react';
import { useForm } from 'react-hook-form';
import Cleave from 'cleave.js/react';
import cardValidator from 'card-validator';
import { decodeEntities } from '@wordpress/html-entities';

const CreditCardFields = ({ handleInputChange, METHOD_NAME, directSettings }) => {
    const {
        register,
        setValue,
        setError,
        formState: { errors },
    } = useForm();

    // Shared input styles
    const inputStyle = (hasError) => ({
        width: '100%',
        padding: '0.75rem',
        border: `1px solid ${hasError ? '#ef4444' : '#d1d5db'}`,
        borderRadius: '0.5rem',
        fontSize: '1rem',
        boxSizing: 'border-box',
        transition: 'border-color 0.2s ease-in-out',
        marginBottom: '0.5rem',
    });

    return (
        <div
            style={{
                padding: '0.5rem 0',
            }}
        >
            <p>{decodeEntities(directSettings.description || '')}</p>

            {/* Card Number */}
            <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: '600' }}>
                Card Number
            </label>
            <Cleave
                name={`${METHOD_NAME}-card-number`}
                options={{ creditCard: true }}
                placeholder="1234 5678 9012 3456"
                style={inputStyle(errors.cardNumber)}
                onChange={handleInputChange}
            />
            {errors.cardNumber && (
                <p style={{ color: 'red', marginTop: '-0.3rem', marginBottom: '0.75rem', fontSize: '0.875rem' }}>
                    {errors.cardNumber.message}
                </p>
            )}

            {/* Expiry */}
            <label style={{ display: 'block', marginTop: '0.5rem', marginBottom: '0.5rem', fontWeight: '600' }}>
                Expiry (MM/YY)
            </label>
            <Cleave
                name={`${METHOD_NAME}-card-expiry`}
                options={{ date: true, datePattern: ['m', 'y'] }}
                placeholder="MM/YY"
                style={inputStyle(errors.expiry)}
                onChange={handleInputChange}
            />
            {errors.expiry && (
                <p style={{ color: 'red', marginTop: '-0.3rem', marginBottom: '0.75rem', fontSize: '0.875rem' }}>
                    {errors.expiry.message}
                </p>
            )}

            {/* CVC */}
            <label style={{ display: 'block', marginTop: '0.5rem', marginBottom: '0.5rem', fontWeight: '600' }}>
                CVC
            </label>
            <Cleave
                name={`${METHOD_NAME}-card-cvc`}
                options={{ blocks: [4], numericOnly: true }}
                placeholder="CVC"
                style={inputStyle(errors.cvc)}
                onChange={handleInputChange}
            />
            {errors.cvc && (
                <p style={{ color: 'red', marginTop: '-0.3rem', marginBottom: '0.75rem', fontSize: '0.875rem' }}>
                    {errors.cvc.message}
                </p>
            )}
        </div>
    );
};

export default CreditCardFields;