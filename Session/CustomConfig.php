<?php

namespace PayUIndia\Payu\Session;

use Magento\Framework\Session\Config as DefaultConfig;

class CustomConfig extends DefaultConfig
{
    public function setCookiePath($path, $default = null)
    {   
        parent::setCookiePath($path, $default);
		
        return $this;
    }
}
