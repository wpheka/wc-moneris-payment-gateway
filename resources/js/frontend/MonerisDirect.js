import {__} from '@wordpress/i18n';
import {decodeEntities} from '@wordpress/html-entities';
import React, {useState, useEffect, useRef} from 'react';
import {getSetting} from '@woocommerce/settings';
import monerisPopulateBrowserParams from './MonerisPopulateBrowserParams';
import { getMonerisServerData } from './utils';
import { getMonerisCreditCardIcons } from './icons';
import CreditCardFields from './CreditCardFields';

const createElement = window.wp.element.createElement;

const ReactElement = (type, props = {}, ...childs) => {
    return Object(createElement)(type, props, ...childs);
}

const directSettings = getSetting('moneris_data', {});
const METHOD_NAME = 'moneris';

const CreditCardForm = (props) => {
	const [creditCardData, setCreditCardData] = useState({});
	const cardWrapperRef = useRef(null);
	const browserParams = monerisPopulateBrowserParams.execute(METHOD_NAME);
	const {eventRegistration, emitResponse} = props;
	const {onPaymentSetup} = eventRegistration;

	useEffect(() => {
		const unsubscribe = onPaymentSetup(async () => {
			const paymentMethodData = {
				...browserParams,
				...creditCardData,
			};

			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData,
				},
			};
		});

		return () => {
			unsubscribe();
		};
	}, [emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS, onPaymentSetup, creditCardData]);

	const handleInputChange = (e) => {
		setCreditCardData(prevData => ({
			...prevData,
			[e.target.name]: e.target.value
		}));
	};

	return (
		<CreditCardFields
			handleInputChange={handleInputChange}
			METHOD_NAME={METHOD_NAME}
			directSettings={directSettings}
		/>
	);
};

let Wpheka_Moneris_Gateway = {};

if (Object.keys(directSettings).length) {
	const defaultLabel = __("Moneris", "wpheka-gateway-moneris");
	const label = decodeEntities(directSettings.title) || defaultLabel;

    const Label = ({components}) => {
        const {PaymentMethodLabel, PaymentMethodIcons} = components;

        const labelComp = ReactElement(PaymentMethodLabel, {
            text: label,
        });

        const iconsComp = ReactElement(PaymentMethodIcons, {
            icons: getMonerisCreditCardIcons(),
        });

        return ReactElement('div', {
            className: METHOD_NAME + '-payment-gateway-label',
        }, labelComp, iconsComp);
    }

	Wpheka_Moneris_Gateway = {
		name: METHOD_NAME,
		label: ReactElement(Label),
		content: <CreditCardForm />,
		edit: <CreditCardForm />,
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: directSettings.supports
		},
	};
}

export default Wpheka_Moneris_Gateway;
