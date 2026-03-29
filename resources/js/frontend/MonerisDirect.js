import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { useState, useEffect } from 'react';
import { getSetting } from '@woocommerce/settings';
import { getMonerisCreditCardIcons } from './icons';
import CreditCardFields from './CreditCardFields';

const directSettings = getSetting( 'moneris_data', {} );
const METHOD_NAME = 'moneris';

const CreditCardForm = ( props ) => {
	const [ creditCardData, setCreditCardData ] = useState( {} );
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;

	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						...creditCardData,
					},
				},
			};
		} );

		return () => {
			unsubscribe();
		};
	}, [ emitResponse.responseTypes.SUCCESS, onPaymentSetup, creditCardData ] );

	const handleInputChange = ( e ) => {
		setCreditCardData( ( prevData ) => ( {
			...prevData,
			[ e.target.name ]: e.target.value,
		} ) );
	};

	return (
		<CreditCardFields
			handleInputChange={ handleInputChange }
			METHOD_NAME={ METHOD_NAME }
			directSettings={ directSettings }
		/>
	);
};

let Wpheka_Moneris_Gateway = {};

if ( Object.keys( directSettings ).length ) {
	const defaultLabel = __( 'Moneris', 'wpheka-gateway-moneris' );
	const label = decodeEntities( directSettings.title ) || defaultLabel;

	const Label = ( { components } ) => {
		const { PaymentMethodLabel, PaymentMethodIcons } = components;

		return (
			<div className={ METHOD_NAME + '-payment-gateway-label' }>
				<PaymentMethodLabel text={ label } />
				<PaymentMethodIcons icons={ getMonerisCreditCardIcons() } />
			</div>
		);
	};

	Wpheka_Moneris_Gateway = {
		name: METHOD_NAME,
		label: <Label />,
		content: <CreditCardForm />,
		edit: <CreditCardForm />,
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: directSettings.supports,
		},
	};
}

export default Wpheka_Moneris_Gateway;
