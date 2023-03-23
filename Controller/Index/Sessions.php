<?php
namespace Forter\Forter\Controller\Index;

use Forter\Forter\Model\AbstractApi;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Validations
 * @package Forter\Forter\Controller\Api
 */
class Sessions extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    protected $cookieMetadataFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @method __construct
     * @param Context $context
     * @param AbstractApi $abstractApi
     * @param Session $customerSession
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        AbstractApi $abstractApi,
        Session $customerSession,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->abstractApi = $abstractApi;
        $this->customerSession = $customerSession;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->storeManager = $storeManager;
    }

    /**
     *
     */
    public function execute()
    {
        try {
            $forterToken = $this->getRequest()->getHeader('Forter-Token');
            if ($forterToken) {
                $baseDomain = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, true);
                $parsedUrl = parse_url($baseDomain);
                $cookieDomain = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';

                // Create the cookie metadata
                $cookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
                    ->setDomain($cookieDomain)
                    ->setPath('/')
                    ->setSecure(true)
                    ->setHttpOnly(true)
                    ->setSameSite('None');

                // Set the forterToken cookie
                $this->cookieManager->setPublicCookie('forterToken', $forterToken, $cookieMetadata);

                $this->customerSession->setForterToken($forterToken);
            }

            $bin = $this->getRequest()->getHeader('bin');
            $this->customerSession->setForterBin($bin);

            $last4cc = $this->getRequest()->getHeader('last4cc');
            $this->customerSession->setForterLast4cc($last4cc);
        } catch (Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
