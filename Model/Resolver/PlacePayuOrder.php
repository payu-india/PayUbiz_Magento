<?php
declare (strict_types = 1);
namespace PayUIndia\Payu\Model\Resolver;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Quote\Api\CartManagementInterface;
use PayUIndia\Payu\Model\Payu;
 /**
 * Compatibility code for PHP 7.4
 * use \AllowDynamicProperties;
 * #[AllowDynamicProperties]
 *
*/
class PlacePayuOrder implements ResolverInterface
{

    protected $scopeConfig;
    protected $cartManagement;
	protected $getCartForUser;
    protected $_objectManager;

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, GetCartForUser $getCartForUser,
        \Magento\Quote\Api\CartManagementInterface $cartManagement, Payu $paymentMethod) {
        $this->scopeConfig    = $scopeConfig;
        $this->getCartForUser = $getCartForUser;
        $this->cartManagement = $cartManagement;        
        $this->_objectManager   = \Magento\Framework\App\ObjectManager::getInstance();
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        try
        {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $storeId = (int) $context->getExtensionAttributes()->getStore()->getId();
            $maskedCartId = $args['cart_id'];
            $cart         = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
            $receipt_id   = $cart->getId();
            $amount       = (int) (number_format($cart->getGrandTotal() * 100, 0, ".", ""));

            $orderLinkCollection = $this->_objectManager->get('PayUIndia\Payu\Model\DBLink')
                                   ->getCollection()
                                   ->addFilter('quote_id', $receipt_id)
                                   ->getFirstItem();
            $orderLinkData = $orderLinkCollection->getData();

			if (empty($orderLinkData['entity_id']) === false)
            {
                $orderLinkCollection->setPayuPaymentId($payu_payment_id)                                    
                                    ->save();
            }
            else
            {
                $orderLnik = $this->_objectManager->create('PayUIndia\Payu\Model\DBLink');
                $orderLnik->setQuoteId($cart->getId())
                          ->setPayuPaymentId($payu_payment_id)                          
                          ->save();
            }
            return [
                    'success' => true,
                    'message' => "PayU Order created ",
                ];
        }
        catch (\Exception $e)
        {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
