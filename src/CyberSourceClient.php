<?php declare(strict_types=1);

namespace Cdtweb;

use Exception;
use SoapClient;
use SoapHeader;
use SoapVar;
use stdClass;

/**
 * CyberSource Simple Order API Client
 *
 * API Docs: http://apps.cybersource.com/library/documentation/dev_guides/CC_Svcs_SO_API/Credit_Cards_SO_API.pdf
 *
 * Test Card Numbers
 * ---------------
 * amex         => 378282246310005
 * discover     => 6011111111111117
 * mastercard   => 5555555555554444
 * visa         => 4111111111111111
 */
class CyberSourceClient
{
    /**
     * Test WSDL
     */
    const WSDL_TEST = 'https://ics2wstesta.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.120.wsdl';

    /**
     * Live WSDL
     */
    const WSDL_LIVE = 'https://ics2wsa.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.120.wsdl';

    /**
     * CyberSource API Version
     */
    const VERSION_API = '1.120';

    /**
     * Connection and response timeout in seconds
     */
    const TIMEOUT = 30;

    /**
     * @var string $wsdl
     */
    protected $wsdl = self::WSDL_LIVE;

    /**
     * @var string $merchantId
     */
    protected $merchantId = '';

    /**
     * @var string $apiKey
     */
    protected $apiKey = '';

    /**
     * @var array $reasonCodes
     */
    protected $reasonCodes = [
        '100' => 'Successful transaction.',
        '101' => 'The request is missing one or more required fields.',
        '102' => 'One or more fields in the request contains invalid data.',
        '104' => 'The merchant reference code for this authorization request matches the merchant reference code of another authorization request that you sent within the past 15 minutes.',
        '110' => 'Only a partial amount was approved.',
        '150' => 'General system failure.',
        '151' => 'The request was received but there was a server timeout.',
        '152' => 'The request was received, but a service did not finish running in time.',
        '200' => 'The authorization request was approved by the issuing bank but declined by CyberSource because it did not pass the Address Verification Service (AVS) check.',
        '201' => 'The issuing bank has questions about the request.',
        '202' => 'Expired card.',
        '203' => 'General decline of the card.',
        '204' => 'Insufficient funds in the account.',
        '205' => 'Stolen or lost card.',
        '207' => 'Issuing bank unavailable.',
        '208' => 'Inactive card or card not authorized for card-not-present transactions.',
        '209' => 'CVN did not match.',
        '210' => 'The card has reached the credit limit.',
        '211' => 'Invalid CVN.',
        '221' => 'The customer matched an entry on the processor\'s negative file.',
        '230' => 'The authorization request was approved by the issuing bank but declined by CyberSource because it did not pass the CVN check.',
        '231' => 'Invalid credit card number.',
        '232' => 'The card type is not accepted by the payment processor.',
        '233' => 'General decline by the processor.',
        '234' => 'There is a problem with your CyberSource merchant configuration.',
        '235' => 'The requested capture amount exceeds the originally authorized amount.',
        '236' => 'Processor failure.',
        '237' => 'The authorization has already been reversed.',
        '238' => 'The authorization has already been captured.',
        '239' => 'The requested transaction amount must match the previous transaction amount.',
        '240' => 'The card type sent is invalid or does not correlate with the credit card number.',
        '241' => 'The request ID is invalid.',
        '242' => 'You requested a capture, but there is no corresponding, unused authorization record.',
        '243' => 'The transaction has already been settled or reversed.',
        '246' => 'The capture or credit is not voidable because the capture or credit information has laready been submitted to your processor. Or, you requested a void for a type of transaction that cannot be voided.',
        '247' => 'You requested a credit for a capture that was previously voided.',
        '250' => 'The request was received, but there was a timeout at the payment processor.',
        '254' => 'Stand-alone credits are not allowed.',
        '480' => 'The order is marked for review by Decision Manager.',
        '481' => 'The order is rejected by Decision Manager.',
    ];

    /**
     * @var array $creditCardTypes
     */
    public $creditCardTypes = [
        'Visa' => '001',
        'MasterCard' => '002',
        'American Express' => '003',
        'Discover' => '004',
        'Diners Club' => '005',
        'Carte Blanche' => '006',
        'JCB' => '007',
    ];

    /**
     * @var string $merchantReferenceCode
     */
    protected $merchantReferenceCode = '';

    /**
     * @var array $dataFields
     */
    protected $dataFields = [];

    /**
     * @var array $creditCard
     */
    protected $creditCard = [];

    /**
     * @var array $billTo
     */
    protected $billTo = [];

    /**
     * @var string $currencyCode
     */
    protected $currencyCode = '';

    /**
     * @var mixed $lastRequest
     */
    protected $lastRequest;

    /**
     * @var mixed $lastResponse
     */
    protected $lastResponse;

    /**
     * @param string $merchantId
     * @param string $apiKey
     * @param bool $test
     */
    public function __construct(string $merchantId, string $apiKey, bool $test = false)
    {
        $this->merchantId = $merchantId;
        $this->apiKey = $apiKey;
        if ($test) {
            $this->wsdl = self::WSDL_TEST;
        }
    }

    /**
     * @return string
     */
    public function getWsdl(): string
    {
        return $this->wsdl;
    }

    /**
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @param mixed $reasonCode
     * @return string
     */
    public function getReason($reasonCode): string
    {
        return $this->reasonCodes[$reasonCode] ?? 'Undefined';
    }

    /**
     * @param string $merchantReferenceCode
     */
    public function setMerchantReferenceCode(string $merchantReferenceCode)
    {
        if (!is_string($merchantReferenceCode) && !is_numeric($merchantReferenceCode)) {
            throw new \InvalidArgumentException('Reference code must be string or number.');
        }
        $this->merchantReferenceCode = $merchantReferenceCode;
    }

    /**
     * @return string
     */
    public function getMerchantReferenceCode(): string
    {
        return $this->merchantReferenceCode;
    }

    /**
     * @param array $data
     */
    public function setDataFields(array $data)
    {
        $this->dataFields = $data;
    }

    /**
     * @return array
     */
    public function getDataFields(): array
    {
        return $this->dataFields;
    }

    /**
     * @param string $number Credit card number
     * @param string $expMonth 2-digit YY
     * @param string $expYear 4-digit YYYY
     * @param string|null $cvn Card verification number
     * @param string|null $type Credit card type
     */
    public function setCreditCard(string $number, string $expMonth, string $expYear, ?string $cvn = null, ?string $type = null)
    {
        $this->creditCard = [
            'accountNumber' => $number,
            'expirationMonth' => $expMonth,
            'expirationYear' => $expYear,
        ];

        if (!is_null($cvn)) {
            $this->creditCard['cvNumber'] = $cvn;
        }

        if (!is_null($type)) {
            if (in_array($type, $this->creditCardTypes)) {
                $this->creditCard['cardType'] = $type;
            } elseif (in_array($type, array_keys($this->creditCardTypes))) {
                $this->creditCard['cardType'] = $this->creditCardTypes[$type];
            }
        }
    }

    /**
     * @return array
     */
    public function getCreditCard(): array
    {
        return $this->creditCard;
    }

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $street1
     * @param string $street2
     * @param string $city
     * @param string $state
     * @param string $postalCode
     * @param string $country
     * @param string $email
     * @param string $ipAddress
     */
    public function setBillTo(
        string $firstName,
        string $lastName,
        string $street1,
        string $street2,
        string $city,
        string $state,
        string $postalCode,
        string $country,
        string $email,
        string $ipAddress
    ) {
        $this->billTo = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'street1' => $street1,
            'street2' => $street2,
            'city' => $city,
            'state' => $state,
            'postalCode' => $postalCode,
            'country' => $country,
            'email' => $email,
            'ipAddress' => $ipAddress,
        ];
    }

    /**
     * @return array
     */
    public function getBillTo(): array
    {
        return $this->billTo;
    }

    /**
     * @param string $currencyCode
     */
    public function setCurrencyCode(string $currencyCode)
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    /**
     * @return mixed
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @return mixed
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Validate a credit card by sending a 0-dollar authorization
     *
     * @return array
     * @throws Exception
     */
    public function validateCreditCard(): array
    {
        $this->checkRequired(['creditCard', 'billTo', 'currencyCode']);

        // Create request
        $request = $this->createRequest();
        $request->ccAuthService = new stdClass;
        $request->ccAuthService->run = 'true';

        // Add credit card
        $request->card = new stdClass;
        foreach ($this->creditCard as $key => $value) {
            $request->card->$key = $value;
        }

        // Add bill to
        $request->billTo = new stdClass;
        foreach ($this->billTo as $key => $value) {
            $request->billTo->$key = $value;
        }

        // Add purchase totals
        $request->purchaseTotals = new stdClass;
        $request->purchaseTotals->currency = $this->getCurrencyCode();
        $request->purchaseTotals->grandTotalAmount = 0; // Set amount to 0

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Create an authorization
     *
     * @param float $amount Authorization amount
     * @param bool $authCapture Flag to capture authorization
     *
     * @return array
     * @throws Exception
     */
    public function authorize(float $amount, bool $authCapture = false): array
    {
        $this->checkRequired(['creditCard', 'billTo', 'currencyCode']);

        // Create request
        $request = $this->createRequest();
        $request->ccAuthService = new stdClass;
        $request->ccAuthService->run = 'true';

        if ($authCapture) {
            $request->ccCaptureService = new stdClass;
            $request->ccCaptureService->run = 'true';
        }

        // Add credit card
        $request->card = new stdClass;
        foreach ($this->creditCard as $key => $value) {
            $request->card->$key = $value;
        }

        // Add bill to
        $request->billTo = new stdClass;
        foreach ($this->billTo as $key => $value) {
            $request->billTo->$key = $value;
        }

        // Add purchase totals
        $request->purchaseTotals = new stdClass;
        $request->purchaseTotals->currency = $this->getCurrencyCode();
        $request->purchaseTotals->grandTotalAmount = $amount;

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Reverse an authorization
     *
     * @param string $requestId
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function reverseAuthorization(string $requestId, float $amount): array
    {
        $this->checkRequired(['currencyCode']);

        // Create request
        $request = $this->createRequest();
        $request->ccAuthReversalService = new stdClass;
        $request->ccAuthReversalService->run = 'true';
        $request->ccAuthReversalService->authRequestID = $requestId;

        // Add purchase totals
        $request->purchaseTotals = new stdClass;
        $request->purchaseTotals->currency = $this->getCurrencyCode();
        $request->purchaseTotals->grandTotalAmount = $amount;

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Capture an authorization
     *
     * @param string $requestId Authorization Request ID
     * @param float $amount Amount to capture
     * @return array
     * @throws Exception
     */
    public function capture(string $requestId, float $amount): array
    {
        $this->checkRequired(['currencyCode']);

        // Create request
        $request = $this->createRequest();
        $request->ccCaptureService = new stdClass;
        $request->ccCaptureService->run = 'true';
        $request->ccCaptureService->authRequestID = $requestId;

        // Add purchase totals
        $request->purchaseTotals = new stdClass;
        $request->purchaseTotals->currency = $this->getCurrencyCode();
        $request->purchaseTotals->grandTotalAmount = $amount;

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Credit a capture
     *
     * @param string $requestId
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function credit(string $requestId, float $amount): array
    {
        $this->checkRequired(['currencyCode']);

        // Create request
        $request = $this->createRequest();
        $request->ccCreditService = new stdClass;
        $request->ccCreditService->run = 'true';
        $request->ccCreditService->captureRequestID = $requestId;

        // Add purchase totals
        $request->purchaseTotals = new stdClass;
        $request->purchaseTotals->currency = $this->getCurrencyCode();
        $request->purchaseTotals->grandTotalAmount = $amount;

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Void a capture or credit
     *
     * @param string $requestId Capture or credit request ID
     * @return array
     */
    public function void(string $requestId): array
    {
        // Create request
        $request = $this->createRequest();
        $request->voidService = new stdClass;
        $request->voidService->run = 'true';
        $request->voidService->voidRequestID = $requestId;

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Get subscription
     *
     * @param string $subscriptionId
     * @return array
     */
    public function getSubscription(string $subscriptionId): array
    {
        // Create request
        $request = $this->createRequest();
        $request->paySubscriptionRetrieveService = new stdClass;
        $request->paySubscriptionRetrieveService->run = 'true';
        $request->recurringSubscriptionInfo = new stdClass;
        $request->recurringSubscriptionInfo->subscriptionID = $subscriptionId;

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Create subscription
     *
     * @param string|null $requestId Request ID from an existing authorization
     * @param bool|null $autoAuth Set to false to avoid an authorization and simply store the
     *      card. Pass null to omit the value, at which point CyberSource will use the setting
     *      on the account.
     * @return array
     * @throws Exception
     */
    public function createSubscription(?string $requestId = null, ?bool $autoAuth = null): array
    {
        if (is_null($requestId)) {
            $this->checkRequired(['creditCard', 'billTo', 'currencyCode']);
        } else {
            $this->checkRequired(['currencyCode']);
        }

        // Create request
        $request = $this->createRequest();
        $request->paySubscriptionCreateService = new stdClass;
        $request->paySubscriptionCreateService->run = 'true';
        if (!is_null($requestId)) {
            $request->paySubscriptionCreateService->paymentRequestID = $requestId;
        } else {
            if ($autoAuth === false) {
                $request->paySubscriptionCreateService->disableAutoAuth = 'true';
            } elseif ($autoAuth === true) {
                $request->paySubscriptionCreateService->disableAutoAuth = 'false';
            }
        }
        $request->recurringSubscriptionInfo = new stdClass;
        $request->recurringSubscriptionInfo->frequency = 'on-demand';

        // Use the credit card and bill to info to create a subscription if $requestId null
        if (is_null($requestId)) {
            // Add credit card
            $request->card = new stdClass;
            foreach ($this->creditCard as $key => $value) {
                $request->card->$key = $value;
            }

            // Add bill to
            $request->billTo = new stdClass;
            foreach ($this->billTo as $key => $value) {
                $request->billTo->$key = $value;
            }
        }

        // Add purchase totals
        $request->purchaseTotals = new stdClass;
        $request->purchaseTotals->currency = $this->getCurrencyCode();

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Charge a subscription (Auth + Capture)
     *
     * @param string $subscriptionId
     * @param float $amount Amount to charge
     * @param bool $skipDM Flag to skip decision manager
     * @return array
     * @throws Exception
     */
    public function chargeSubscription(string $subscriptionId, float $amount, bool $skipDM = false): array
    {
        $this->checkRequired(['currencyCode']);

        // Create request
        $request = $this->createRequest();
        $request->ccAuthService = new stdClass;
        $request->ccAuthService->run = 'true';
        $request->ccCaptureService = new stdClass;
        $request->ccCaptureService->run = 'true';
        $request->recurringSubscriptionInfo = new stdClass;
        $request->recurringSubscriptionInfo->subscriptionID = $subscriptionId;

        // Add purchase totals
        $request->purchaseTotals = new stdClass;
        $request->purchaseTotals->currency = $this->getCurrencyCode();
        $request->purchaseTotals->grandTotalAmount = $amount;

        if ($skipDM) {
            // Bypass decision manager for subscription charges
            $request->decisionManager = new stdClass;
            $request->decisionManager->enabled = 'false';
        }

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Update subscription billing info
     *
     * @param string $subscriptionId
     * @return array
     * @throws Exception
     */
    public function updateSubscription(string $subscriptionId): array
    {
        $this->checkRequired(['billTo']);

        // Create request
        $request = $this->createRequest();
        $request->paySubscriptionUpdateService = new stdClass;
        $request->paySubscriptionUpdateService->run = 'true';
        $request->recurringSubscriptionInfo = new stdClass;
        $request->recurringSubscriptionInfo->subscriptionID = $subscriptionId;

        // Add bill to
        $request->billTo = new stdClass;
        foreach ($this->billTo as $key => $value) {
            $request->billTo->$key = $value;
        }

        $response = $this->sendRequest($request);
        return $response;
    }

    /**
     * Delete subscription
     *
     * @param string $subscriptionId
     * @return array
     */
    public function deleteSubscription(string $subscriptionId): array
    {
        // Create request
        $request = $this->createRequest();
        $request->paySubscriptionDeleteService = new stdClass;
        $request->paySubscriptionDeleteService->run = 'true';
        $request->recurringSubscriptionInfo = new stdClass;
        $request->recurringSubscriptionInfo->subscriptionID = $subscriptionId;

        $response = $this->sendRequest($request);
        return $response;
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * Create request object with consistent data
     *
     * @return stdClass
     */
    private function createRequest()
    {
        $request = new stdClass;

        // Add merchant id
        $request->merchantID = $this->getMerchantId();

        // Add merchant reference code
        $request->merchantReferenceCode = $this->getMerchantReferenceCode();

        // Add merchant defined data fields
        $dataFields = $this->getDataFields();
        if (!empty($dataFields)) {
            $request->merchantDefinedData = new stdClass;
            $request->merchantDefinedData->mddField = [];
            foreach ($dataFields as $key => $value) {
                $dataField = new stdClass;
                $dataField->id = $key;
                $dataField->_ = $value;
                array_push($request->merchantDefinedData->mddField, $dataField);
            }
        }

        // Add client information
        $request->clientLibrary = 'CyberSourceClient';
        $request->clientLibraryVersion = '1';
        $request->clientEnvironment = php_uname();

        return $request;
    }

    /**
     * Send request to CyberSource API
     *
     * @param stdClass $request
     * @return mixed
     */
    private function sendRequest(stdClass $request)
    {
        // Set last request
        $this->lastRequest = $request;

        // Create SoapClient
        $client = new SoapClient($this->wsdl, [
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
            'encoding' => 'utf-8',
            'exceptions' => true,
            'connection_timeout' => self::TIMEOUT,
            'stream_context' => stream_context_create([
                'http' => [
                    'timeout' => self::TIMEOUT,
                ],
            ]),
            'cache_wsdl' => ($this->wsdl == self::WSDL_TEST) ? WSDL_CACHE_NONE : WSDL_CACHE_BOTH
        ]);

        // Add WSS token to SoapClient
        $this->addWSSToken($client);

        // Send request
        $response = $client->runTransaction($request);
        $this->lastResponse = (array) $response;

        return $this->lastResponse;
    }

    /**
     * Add WSS token to SoapClient
     *
     * @param SoapClient $client
     */
    private function addWSSToken(SoapClient $client)
    {
        $wsseNamespace = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $passNamespace = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';

        // Create var for username and password
        $user = new SoapVar($this->getMerchantId(), XSD_STRING, '', $wsseNamespace, '', $wsseNamespace);
        $pass = new SoapVar($this->getApiKey(), XSD_STRING, '', $passNamespace, '', $wsseNamespace);

        // Create username token object
        $usernameToken = new stdClass;
        $usernameToken->Username = $user;
        $usernameToken->Password = $pass;

        // Create security object
        $security = new stdClass;
        $security->UsernameToken = new SoapVar($usernameToken, SOAP_ENC_OBJECT, '', $wsseNamespace, 'UsernameToken', $wsseNamespace);

        // Add security header to SoapClient
        $client->__setSoapHeaders(new SoapHeader($wsseNamespace, 'Security', new SoapVar($security, SOAP_ENC_OBJECT, '', $wsseNamespace, 'Security', $wsseNamespace), true));
    }

    /**
     * Check for required data
     *
     * @param array $required
     * @throws Exception
     */
    private function checkRequired(array $required = [])
    {
        $alwaysRequired = ['merchantReferenceCode'];

        $required += $alwaysRequired;

        foreach ($required as $key) {
            if (empty($this->$key)) {
                throw new Exception("$key is required");
            }
        }
    }
}
