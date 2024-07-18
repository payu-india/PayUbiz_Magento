<?php

namespace PayUIndia\Payu\Model;


class Action implements \Magento\Framework\Option\ArrayInterface
{
    const ACTION_BOLT    	= 'bolt';
    const ACTION_REDIRECT  	= 'redirect';

    /**
     * Possible action types
     * 
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::ACTION_BOLT,
                'label' => 'BOLT',
            ],
            [
                'value' => self::ACTION_REDIRECT,
                'label' => 'Redirect'
            ]
        ];
    }
}
