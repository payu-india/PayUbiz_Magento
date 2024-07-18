<?php
//Viatechs - 16-08-2022
namespace PayUIndia\Payu\Cron;
use Magento\Sales\Model\Order;

class CronJob
{ 
	protected $logger;
    protected $orderRepository;
    protected $searchCriteriaBuilder;
	protected $sortOrderBuilder;
	protected $model_payu;
	
	protected $cur_id;

    public function __construct(        
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
		\Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
		\PayUIndia\Payu\Model\Payu $payu
    ) {
		$this->cur_id=0;
		$writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron.log');
		$this->logger = new \Zend_Log();
		$this->logger->addWriter($writer);
        
		$this->model_payu = $payu;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
		$this->sortOrderBuilder      = $sortOrderBuilder;
    }

	public function execute()
	{
		try 
		{
			$this->logger->info("PayU Cron Initiated...");
			$date = (new \DateTime())->modify('-15 minutes');
			$sortOrder = $this->sortOrderBuilder->setField('entity_id')->setDirection('DESC')->create();
			$searchCriteria = $this->searchCriteriaBuilder
				->addFilter(
					'status',
					'pending%',
					'like'
				)->addFilter(
					'created_at',
					$date->format('Y-m-d H:i:s'),
					'lt'
				)->setSortOrders(
					[$sortOrder]
				)->create();
			
			
			$orders = $this->orderRepository->getList($searchCriteria);
			
			foreach ($orders->getItems() as $order) {
				
				$this->cur_id = $order->getIncrementId();
				$payment = $order->getPayment();
				$method = $payment->getMethodInstance();
				$methodTitle = strtolower($method->getTitle());
				
				if($order->canCancel() && 
				trim(strtolower($methodTitle)) == trim(strtolower($this->model_payu->getMethodTitle())))
				{
					if($this->model_payu->verifyPayment($order,$order->getIncrementId()))
					{
						$order->setTotalPaid(round($order->getBaseGrandTotal(), 2));  
						$order->setState(Order::STATE_PROCESSING,true)->setStatus(Order::STATE_PROCESSING);				
						$order->setCanSendNewEmailFlag(true);
						$order->save();		
				
						$invoice = $payment->getCreatedInvoice();				
						if ($invoice && !$order->getEmailSent()) {
							$this->orderSender->send($order);
							$order->addStatusHistoryComment(
							__('Thank you for your order. Your Invoice #%1.', $invoice->getIncrementId())
							)->setIsCustomerNotified(
								true
							)->save();
						}
					}
					else
					{
						$order->cancel();
						$order->setStatus('canceled');
						$order->save(); 
						$this->logger->info("Order# {$order->getIncrementId()} - Payment Method: {$methodTitle} - Creation Date: {$order->getCreatedAt()} - Canceled");	
					}
				}
				
			}

		}
		catch(Exception $e){
			$logger->info('At Current ID:'.$this->cur_id.' Error:'.$e->getMessage());
			return false;
		}
		return $this;
	}
}