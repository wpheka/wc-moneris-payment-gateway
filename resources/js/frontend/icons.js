import { getMonerisServerData } from './utils';

export const getMonerisCreditCardIcons = () => {
    return Object.entries( getMonerisServerData().icons ).map(
        ( [ id, { src, alt } ] ) => {
            return {
                id,
                src,
                alt,
            };
        }
    );
};