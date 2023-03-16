<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Export\Order\Processor;


use Globalsys\EDCSDK\Model\PostOrderCustomerModel;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderCustomerModel implements ProcessorInterface
{

    public function provide(OrderEntity $order): PostOrderCustomerModel
    {
        $addressesCount = $order->getAddresses()->count();

        if ($addressesCount == 0 || $addressesCount > 2) {
            return new PostOrderCustomerModel();
        }

        $billingAddress = $order->getAddresses()->first();
        $shippingAddress = $billingAddress;

        if ($addressesCount == 2) {
            foreach ($order->getAddresses() as $address) {
                if ($order->getBillingAddressId() != $address->getId()) {
                    $shippingAddress = $address;
                }
            }
        }

        $orderCustomer = $order->getOrderCustomer();

        $billingEmail = $orderCustomer->getEmail();
        $billingSalutation = $billingAddress->getSalutation()->getDisplayName();
        $billingFirstName = $billingAddress->getFirstName();
        $billingLastName = $billingAddress->getLastName();
        $billingCompany = $billingAddress->getCompany();
        $billingStreet = $billingAddress->getStreet();
//        there is no separate street number field in the shop at the moment
//        $streetNumber = '13';
        $billingPhoneNumber = $billingAddress->getPhoneNumber();
        $billingZipCode = $billingAddress->getZipcode();
        $billingCity = $billingAddress->getCity();
        $billingCountry = $billingAddress->getCountry()->getTranslation('name');
        $billingAddressAdditional = $billingAddress->getAdditionalAddressLine1() . $billingAddress->getAdditionalAddressLine2();


        $shippingSalutation = $shippingAddress->getSalutation()->getDisplayName();
        $shippingFirstName = $shippingAddress->getFirstName();
        $shippingLastName = $shippingAddress->getLastName();
        $shippingStreet = str_replace(',', '', $shippingAddress->getStreet());
        $shippingCompany = $shippingAddress->getCompany();
//        there is no separate street number field in the shop at the moment
//        $shippingStreetNumber = '13';
        $shippingPhoneNumber = $shippingAddress->getPhoneNumber();
        $shippingZipCode = preg_replace("/\b(?:L-|AT-|A-)\b/i", '', $shippingAddress->getZipcode());
        $shippingCity = $shippingAddress->getCity();
        $shippingCountry = $shippingAddress->getCountry()->getTranslation('name');
        $shippingAddressAdditional = $shippingAddress->getAdditionalAddressLine1() . $shippingAddress->getAdditionalAddressLine2();


        $orderCustomerModel = new PostOrderCustomerModel();
        $orderCustomerModel->setCustomerNr($orderCustomer->getCustomerNumber());
        $orderCustomerModel->setCustomerEmail($billingEmail);

        $orderCustomerModel->setPaymentAddressSal($billingSalutation);
        $orderCustomerModel->setPaymentAddressFirstname($billingFirstName);
        $orderCustomerModel->setPaymentAddressLastname($billingLastName);
        $orderCustomerModel->setPaymentAddressCompany($billingCompany);
        $orderCustomerModel->setPaymentAddressStreet($billingStreet);
//        there is no separate street number field in the shop at the moment
//        $orderCustomerModel->setPaymentAddressStreetno($streetNumber);
        $orderCustomerModel->setPaymentAddressPhone($billingPhoneNumber);
        $orderCustomerModel->setPaymentAddressPostal($billingZipCode);
        $orderCustomerModel->setPaymentAddressCity($billingCity);
        $orderCustomerModel->setPaymentAddressCountry($billingCountry);
        $orderCustomerModel->setPaymentAddressAdditional($billingAddressAdditional);

        $orderCustomerModel->setDeliveryAddressSal($shippingSalutation);
        $orderCustomerModel->setDeliveryAddressFirstname($shippingFirstName);
        $orderCustomerModel->setDeliveryAddressLastname($shippingLastName);
        $orderCustomerModel->setDeliveryAddressCompany($shippingCompany);
        $orderCustomerModel->setDeliveryAddressStreet($shippingStreet);
//        there is no separate street number field in the shop at the moment
//        $orderCustomerModel->setDeliveryAddressStreetno($shippingStreetNumber);
        $orderCustomerModel->setDeliveryAddressPhone($shippingPhoneNumber);
        $orderCustomerModel->setDeliveryAddressPostal($shippingZipCode);
        $orderCustomerModel->setDeliveryAddressCity($shippingCity);
        $orderCustomerModel->setDeliveryAddressCountry($shippingCountry);
        $orderCustomerModel->setDeliveryAddressAdditional($shippingAddressAdditional);

        return $orderCustomerModel;
    }
}
