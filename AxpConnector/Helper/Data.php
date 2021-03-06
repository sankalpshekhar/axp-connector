<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Adobe\AxpConnector\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class Data.
 *
 * @deprecated
 */
class Data extends AbstractHelper
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Data constructor.
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        EncryptorInterface $encryptor,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Push data on add to cart (?)
     *
     * @param int $qty
     * @param ProductInterface $product
     * @return array
     */
    public function addToCartPushData($qty, $product)
    {
        $result = [];

        $result['event'] = 'Product Added';
        $result['product'] = [];

        $item = [];
        $item['quantity'] = strval($qty);
        $item['productInfo'] = [];
        $item['productInfo']['sku'] = $product->getSku();
        $item['productInfo']['productID'] = $product->getData('sku');

        array_push($result['product'], $item);

        return $result;
    }

    /**
     * Push data on removal from the cart.
     *
     * @param int $qty
     * @param ProductInterface $product
     * @return array
     */
    public function removeFromCartPushData($qty, $product)
    {
        // It's very similar to product added
        $result = $this->addToCartPushData($qty, $product);
        $result['event'] = 'Product Removed';

        return $result;
    }

    /**
     * Push data on order placed.
     *
     * @param array $orderIds
     * @return array
     */
    public function orderPlacedPushData($orderIds)
    {
        $result = [];

        foreach ($orderIds as $orderId) {
            $order = $this->orderRepository->get($orderId);

            $this->logger->addInfo("Order Retrieved: {$order->getIncrementId()}");

            $orderObject = [
                'event' => 'Order Placed',
                'transaction' => [
                    'transactionID' => $order->getIncrementId(),
                    'total' => [
                        'currency' => $order->getOrderCurrencyCode()
                    ],
                    'shippingGroup' => [],
                    'profile' => [
                        'address' => []
                    ],
                    'item' => []
                ]
            ];

            // TODO - Multi-shipping
            $shippingGroup = [
                'tax' => $order->getShippingTaxAmount(),
                'shippingCost' => $order->getShippingAmount(),
                'groupId' => '1'
            ];
            $orderObject['transaction']['shippingGroup'][] = $shippingGroup;

            $billingAddress = $order->getBillingAddress();
            $orderObject['transaction']['profile']['address']['stateProvince'] = $billingAddress->getRegionCode();
            $orderObject['transaction']['profile']['address']['postalCode'] = $billingAddress->getPostcode();

            foreach ($order->getAllVisibleItems() as $item) {
                $itemData = [
                    'shippingGroupID' => '1',
                    'quantity' => $item->getQtyOrdered(),
                    'productInfo' => [
                        'sku' => $item->getSku(),
                        'productID' => $item->getProduct()->getData('sku')
                    ],
                    'price' => [
                        'sellingPrice' => $item->getPrice()
                    ]
                ];

                $orderObject['transaction']['item'][] = $itemData;
            }

            $result[] = $orderObject;
        }

        $logData = $this->jsonify($result);
        $this->logger->addInfo("Result Object: {$logData}");

        return $result;
    }

    /**
     * Json Encode (??)
     *
     * @param mixed $obj
     * @return string
     */
    public function jsonify($obj)
    {
        return $this->jsonHelper->jsonEncode($obj);
    }
}
