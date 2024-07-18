<?php
declare (strict_types = 1);
namespace PayUIndia\Payu\Model\Resolver;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Cart\SetPaymentMethodOnCart as SetPaymentMethodOnCartModel;
use PayUIndia\Payu\Model\Payu;
 /**
 * Compatibility code for PHP 7.4
 * use \AllowDynamicProperties;
 * #[AllowDynamicProperties]
 *
*/
/**
 * Mutation resolver for setting payment method for shopping cart
 */
class SetPayuPaymentDetailsOnCart implements ResolverInterface
{
    private $getCartForUser;
    private $setPaymentMethodOnCart;
    private $checkCartCheckoutAllowance;
    protected $_objectManager;
    public function __construct(GetCartForUser $getCartForUser, SetPaymentMethodOnCartModel $setPaymentMethodOnCart,
        CheckCartCheckoutAllowance $checkCartCheckoutAllowance, Payu $paymentMethod) {
        $this->getCartForUser = $getCartForUser;
        $this->setPaymentMethodOnCart = $setPaymentMethodOnCart;
        $this->checkCartCheckoutAllowance = $checkCartCheckoutAllowance;        
        $this->_objectManager   = \Magento\Framework\App\ObjectManager::getInstance();
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['cart_id']))
        {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing.'));
        }

        $maskedCartId = $args['input']['cart_id'];

        if (empty($args['input']['payu_payment_id']))
        {
            throw new GraphQlInputException(__('Required parameter "payu_payment_id" is missing.'));
        }

        $payu_payment_id = $args['input']['payu_payment_id'];
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);

        try
        {            
            $orderLinkCollection = $this->_objectManager->get('PayUIndia\Payu\Model\DBLink')
                                                   ->getCollection()
                                                   ->addFilter('quote_id', $cart->getId())
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

        }
        catch (\Exception $e)
        {
            throw new GraphQlInputException(__('PayU Error: %1.', $e->getMessage()));
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
}
