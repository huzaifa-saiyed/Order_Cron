<?php

namespace Kitchen365\OrderCron\Cron;

use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Translate\Inline\StateInterface;

class OrderStatusCron
{
    private $orderFactory;
    private $transportBuilder;
    private $logger;
    protected $scopeConfig;
    protected $inlineTranslation;

    public function __construct(
        OrderFactory $orderFactory,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        StateInterface $inlineTranslation,
        LoggerInterface $logger
    ) {
        $this->orderFactory = $orderFactory;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;  
        $this->inlineTranslation = $inlineTranslation;
    }

    public function execute()
    {
        // Get orders with pending status
        echo('Cron is Start!! ');
        $orders = $this->orderFactory->create()->getCollection()
            ->addFieldToFilter('status', 'pending');

        foreach ($orders as $order) {
            // Log the customer email for debugging
            $this->logger->info('Cron running for order: ' . $order->getIncrementId());
            $this->logger->info('Customer email: ' . $order->getCustomerEmail());

            // Send email to customer
            $this->sendCustomerEmail($order);

            // Send email to admin
            $this->sendAdminEmail($order);
        }
    }

    private function sendCustomerEmail($order)
    {
        // Send email to customer using template
        $transport = $this->transportBuilder->setTemplateIdentifier('customer_order_status')
            ->setTemplateOptions(['area' => 'frontend', 'store' => $order->getStoreId()])
            ->setTemplateVars(['order' => $order]) 
            ->addTo($order->getCustomerEmail())
            ->getTransport();

        $transport->sendMessage();
        $this->logger->info('Customer email sent for order: ' . $order->getIncrementId());
    }

    private function sendAdminEmail($order)
    {
        $templateOptions = array('area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => 1);

        $adminEmail = $this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE);
        $adminName = $this->scopeConfig->getValue('trans_email/ident_general/name', ScopeInterface::SCOPE_STORE);

        $this->inlineTranslation->suspend();

        try {
            $transport = $this->transportBuilder->setTemplateIdentifier('admin_order_status')
                ->setTemplateOptions($templateOptions)
                ->setTemplateVars(['order' => $order])
                ->setFrom([
                    'name' => $adminName,
                    'email' => $adminEmail
                ])
                ->addTo([$adminEmail]) // Admin email
                ->getTransport();
    
            $transport->sendMessage();
            $this->logger->info('Admin email sent for order: ' . $order->getIncrementId());

        } catch (\Exception $e) {
            $this->logger->error('Error while sending email: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
