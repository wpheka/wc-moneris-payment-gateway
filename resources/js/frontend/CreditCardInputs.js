import React, { useEffect } from 'react';
import { decodeEntities } from '@wordpress/html-entities';

const CreditCardInputs = ({ handleInputChange, METHOD_NAME, directSettings, cardWrapperRef }) => {
	return (
		<div className="moneris-direct-card-form">
			<p>{decodeEntities(directSettings.description || '')}</p>
			<div id="moneris-direct-card-wrapper" ref={cardWrapperRef}></div>
			<div>
				<input
					type="text"
					name={`${METHOD_NAME}-card-number`}
					placeholder="Card Number"
					onChange={handleInputChange}
					onBlur={handleInputChange}
					autoComplete="off"
					className="moneris-input-wide"
				/>
				<div className="moneris-input-half-wrapper">
					<input
						type="text"
						name={`${METHOD_NAME}-card-expiry`}
						placeholder="Expiry Date"
						onChange={handleInputChange}
						onBlur={handleInputChange}
						autoComplete="off"
						className="moneris-input-half"
					/>
					<input
						type="text"
						name={`${METHOD_NAME}-card-cvc`}
						placeholder="CVC"
						onChange={handleInputChange}
						onBlur={handleInputChange}
						autoComplete="off"
						className="moneris-input-half"
					/>
				</div>
			</div>
		</div>
	);
};

export default CreditCardInputs;
