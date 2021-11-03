<?php
/**
 * Created by PhpStorm.
 * User: khanhld
 * Date: 23/09/2019
 * Time: 10:36
 */

namespace SM\Shell\Console\Command;

use Magento\Directory\Model\Currency;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use SM\Shift\Model\ResourceModel\RetailTransaction\CollectionFactory as RetailCollectionFactory;
use SM\Shift\Model\RetailTransactionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConvertBaseAmountCommand
 *
 * @package SM\Shell\Console\Command
 */
class ConvertBaseAmountCommand extends Command
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */

    protected $storeManager;

    /**
     * @var Currency
     */
    private $currencyModel;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \SM\Shift\Model\RetailTransactionFactory
     */
    protected $retailTransactionFactory;

    /**
     * @var \SM\Shift\Model\ResourceModel\RetailTransaction\CollectionFactory
     */
    protected $transactionCollectionFactory;

    /**
     * @var State
     */
    protected $appState;

    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        State $appState,
        RetailTransactionFactory $retailTransactionFactory,
        RetailCollectionFactory $transactionCollectionFactory,
        Currency $currencyModel
    ) {
        $this->orderCollectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->currencyModel = $currencyModel;
        $this->objectManager = $objectManager;
        $this->retailTransactionFactory = $retailTransactionFactory;
        $this->transactionCollectionFactory = $transactionCollectionFactory;
        $this->appState = $appState;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('cpos:convert_base_amount')->setDescription('Convert base amount command');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->getAreaCode();
            $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () {
                $baseCurrencyCode = $this->storeManager->getStore()->getBaseCurrencyCode();
                $allowedCurrencies = $this->currencyModel->getConfigAllowCurrencies();
                $rates = $this->currencyModel->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));

                $orderCollection = $this->orderCollectionFactory->create();
                foreach ($orderCollection as $order) {
                    $transactionCollection = $this->transactionCollectionFactory->create();
                    $orderCurrency = $order->getOrderCurrencyCode();

                    $transactionCollection->addFieldToFilter('order_id', $order->getId());
                    foreach ($transactionCollection as $transaction) {
                        $transaction->setData('base_amount', isset($rates[$orderCurrency]) && $rates[$orderCurrency] != 0 ? $transaction->getData('amount') / $rates[$orderCurrency] : null);
                        $transaction->save();
                    }
                }
            }, []);
        } catch (\Throwable $e) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('Psr\Log\LoggerInterface');
            $logger->info("====> [CPOS] Failed to execute command convert base amount: {$e->getMessage()}");
            $logger->info($e->getTraceAsString());
        }
    }
}
