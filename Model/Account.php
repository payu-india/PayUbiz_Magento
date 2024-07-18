<?php

namespace PayUIndia\Payu\Model;

class Account implements \Magento\Framework\Option\ArrayInterface {

    const ACC_BIZ = 'payubiz';
    const ACC_MONEY = 'payumoney';

    /**
     * Possible environment types
     * 
     * @return array
     */
    public function toOptionArray() {
		
		//Using only PayUBiz
		return [
            [
                'value' => self::ACC_BIZ,
                'label' => 'PayUBiz',
            ]
        ];
    }

}
