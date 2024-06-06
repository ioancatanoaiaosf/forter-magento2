<?php

namespace Forter\Forter\Model\ThirdParty\Adyen\Gateway\Request;

use Forter\Forter\Model\EntityFactory as ForterEntityFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Forter\Forter\Helper\EntityHelper;
class RecommendationsDataBuilder implements BuilderInterface
{
    protected const VERIFICATION_REQUIRED_3DS_CHALLENGE = "VERIFICATION_REQUIRED_3DS_CHALLENGE";
    protected const REQUEST_SCA_EXEMPTION_LOW_VALUE = "REQUEST_SCA_EXEMPTION_LOW_VALUE";
    protected const REQUEST_SCA_EXEMPTION_TRA = "REQUEST_SCA_EXEMPTION_TRA";
    protected const REQUEST_SCA_EXCLUSION_MOTO = "REQUEST_SCA_EXCLUSION_MOTO";
    protected const REQUEST_SCA_EXEMPTION_CORP = "REQUEST_SCA_EXEMPTION_CORP";
    protected const CANCELED_ORDER_PAYMENT_MESSAGE = "Canceled order online";

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EntityHelper
     */
    protected $entityHelper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EntityHelper $entityHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->entityHelper = $entityHelper;
    }

    /**
     * Add shopper data into request
     *
     * @param array $buildSubject
     * @return array|null
     */
    public function build(array $buildSubject): ?array
    {
        $request = [];
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/ForterDebug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('IN recommendation class START');
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);

        if ($paymentDataObject instanceof PaymentDataObjectInterface) {
            $forterPreAuth = $this->isForterPreAuth() === '1' || $this->isForterPreAuth() === '4' ? true : false;
            $logger->info('forterPreAuth: ' . $forterPreAuth);

            if ($forterPreAuth) {
                $payment = $paymentDataObject->getPayment();
                $logger->info('paymentLastTransID: ' . (bool)$payment->getLastTransId() ?? 'no paymentLastTransID');
                $logger->info('paymentLastTransID is null : ' . is_null($payment->getLastTransId()) ?? 'paymentLastTransID is null');
                $logger->info('orderState: ' . (bool)$payment->getOrder()->getState() ?? 'no orderState');
                $logger->info('orderState is null: ' . is_null($payment->getOrder()->getState()) ?? 'true');
                $logger->info('paymentMethod: ' . $payment->getMethod() ?? 'no paymentMethod');
                $logger->info('paymetnData: ' . json_encode($payment->getData() ?? 'no paymentData'));
                // Ensuring payment method is adyen_cc before proceeding
                if ($payment && ($payment->getMethod() === "adyen_cc" || $payment->getMethod() === "adyen_cc_vault")  && !$payment->getLastTransId() && !$payment->getOrder()->getState()) {

                    $message = $payment->getMessage() ? $payment->getMessage()->getText() : null;
                    if ($message === self::CANCELED_ORDER_PAYMENT_MESSAGE) {
                        return $request;
                    }

                    $order = $payment->getOrder();

                    $forterEntity = $this->entityHelper->getForterEntityByIncrementId($order->getIncrementId());
                    $logger->info('orderID' . $order->getIncrementId() ?? 'no orderID');

                    if (!$forterEntity) {
                        $logger->info('No Forter Entity');
                        return $request;
                    }

                    $forterResponse = $forterEntity->getForterResponse();
                    $logger->info('forterResponse: ' . $forterResponse ?? 'no forterResponse');
                    //de facut
                    if ($forterResponse !== null) {
                        $response = json_decode($forterResponse, true);

                        if (isset($response['recommendations']) && is_array($response['recommendations'])) {
                            array_walk_recursive($response['recommendations'], function ($value) use (&$request) {
                                switch ($value) {
                                    case self::VERIFICATION_REQUIRED_3DS_CHALLENGE:
                                        $request['body']["threeDS2RequestData"]["threeDSRequestorChallengeInd"] = "04";
                                        $request['body']["authenticationData"]["attemptAuthentication"] = "always";
                                        $request['body']["authenticationData"]["threeDSRequestData"]["nativeThreeDS"] = "preferred";
                                        break;
                                    case self::REQUEST_SCA_EXEMPTION_CORP:
                                        $request['body']["additionalData"]["scaExemption"] = "secureCorporate";
                                        break;
                                    case self::REQUEST_SCA_EXEMPTION_LOW_VALUE:
                                        $request['body']["additionalData"]["scaExemption"] = "lowValue";
                                        break;
                                    case self::REQUEST_SCA_EXEMPTION_TRA:
                                        $request['body']["additionalData"]["scaExemption"] = "transactionRiskAnalysis";
                                        break;
                                }
                            });
                            return $request;
                        }
                    }
                }
            }
        }
        return $request;
    }

    protected function isForterPreAuth()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/pre_post_select', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
