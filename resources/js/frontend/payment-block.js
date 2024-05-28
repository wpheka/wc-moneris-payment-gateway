import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import Wpheka_Moneris_Gateway from './MonerisDirect';

if (Object.keys(Wpheka_Moneris_Gateway).length > 0) {
  registerPaymentMethod(Wpheka_Moneris_Gateway);
}