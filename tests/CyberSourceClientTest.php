<?php declare(strict_types=1);

namespace Cdtweb;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Cdtweb\CyberSourceClient
 */
class CyberSourceClientTest extends TestCase
{
    /**
     * @var bool Flag to run transaction tests against a CyberSource account
     */
    protected $runTransactionTests = false;

    /**
     * @var CyberSourceClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $testMerchantId = '';

    /**
     * @var string
     */
    protected $testApiKey = '';

    /**
     * @var float
     */
    protected $testAmount = 100.00;

    public function setUp()
    {
        $this->testMerchantId = getenv('CYBERSOURCE_TEST_MERCHANT_ID') ?: '';
        $this->testApiKey = getenv('CYBERSOURCE_TEST_API_KEY') ?: '';
        if (!empty($this->testMerchantId) && !empty($this->testApiKey)) {
            $this->runTransactionTests = true;
        }

        $this->client = new CyberSourceClient($this->testMerchantId, $this->testApiKey, true);
        $this->client->setMerchantReferenceCode('TEST' . time());
    }

    /**
     * Return a new authorization response.
     *
     * @return array
     * @throws \Exception
     */
    public function newAuthorization()
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $this->client->setCreditCard('4111111111111111', '02', (string) date('Y', strtotime('+1 year')));
        $this->client->setBillTo('John', 'Doe', '123 Main St', '', 'Midvale', 'UT', '84044', 'US', 'john.doe@example.org', '127.0.0.1');
        $this->client->setCurrencyCode('USD');
        return $this->client->authorize($this->testAmount);
    }

    /**
     * @covers ::getWsdl()
     * @covers ::getMerchantId()
     * @covers ::getApiKey()
     */
    public function testInitData()
    {
        $this->assertEquals(CyberSourceClient::WSDL_TEST, $this->client->getWsdl());
        $this->assertEquals($this->testMerchantId, $this->client->getMerchantId());
        $this->assertEquals($this->testApiKey, $this->client->getApiKey());
    }

    /**
     * @covers ::setReferenceCode()
     * @covers ::getReferenceCode()
     */
    public function testReferenceCode()
    {
        $referenceCode = 'TEST1234';
        $this->client->setMerchantReferenceCode($referenceCode);
        $this->assertEquals($referenceCode, $this->client->getMerchantReferenceCode());
    }

    /**
     * @covers ::setCurrencyCode()
     * @covers ::getCurrencyCode()
     */
    public function testCurrencyCode()
    {
        $currencyCode = 'USD';
        $this->client->setCurrencyCode($currencyCode);
        $this->assertEquals($currencyCode, $this->client->getCurrencyCode());
    }

    /**
     * @covers ::setCreditCard()
     * @covers ::getCreditCard()
     */
    public function testCreditCard()
    {
        // Test data
        $ccNumber = '4111111111111111';
        $ccExpMonth = '02';
        $ccExpYear = (string) date('Y', strtotime('+1 year'));
        $ccCvn = '123';
        $ccType = 'Visa';
        $ccTypeNumber = '001';

        // Set credit card with string type
        $this->client->setCreditCard($ccNumber, $ccExpMonth, $ccExpYear, $ccCvn, $ccType);
        $creditCard = $this->client->getCreditCard();

        $this->assertEquals($ccNumber, $creditCard['accountNumber']);
        $this->assertEquals($ccExpMonth, $creditCard['expirationMonth']);
        $this->assertEquals($ccExpYear, $creditCard['expirationYear']);
        $this->assertEquals($ccCvn, $creditCard['cvNumber']);
        $this->assertEquals($ccTypeNumber, $creditCard['cardType']);

        // Set credit card with numeric type
        $this->client->setCreditCard($ccNumber, $ccExpMonth, $ccExpYear, $ccCvn, $ccTypeNumber);
        $creditCard = $this->client->getCreditCard();

        $this->assertEquals($ccTypeNumber, $creditCard['cardType']);
    }

    /**
     * @covers ::setBillTo()
     * @covers ::getBillTo()
     */
    public function testBillTo()
    {
        // Test data
        $firstName = 'John';
        $lastName = 'Doe';
        $street1 = '123 Main St.';
        $street2 = '';
        $city = 'Midvale';
        $state = 'UT';
        $postalCode = '84048';
        $country = 'US';
        $email = 'john.doe@example.org';
        $ipAddress = '127.0.0.1';

        // Set bill to
        $this->client->setBillTo($firstName, $lastName, $street1, $street2, $city, $state, $postalCode, $country, $email, $ipAddress);
        $billTo = $this->client->getBillTo();

        $this->assertEquals($firstName, $billTo['firstName']);
        $this->assertEquals($lastName, $billTo['lastName']);
        $this->assertEquals($street1, $billTo['street1']);
        $this->assertEquals($street2, $billTo['street2']);
        $this->assertEquals($city, $billTo['city']);
        $this->assertEquals($state, $billTo['state']);
        $this->assertEquals($postalCode, $billTo['postalCode']);
        $this->assertEquals($country, $billTo['country']);
        $this->assertEquals($email, $billTo['email']);
        $this->assertEquals($ipAddress, $billTo['ipAddress']);
    }

    /**
     * @covers ::setDataFields()
     * @covers ::getDataFields()
     */
    public function testDataFields()
    {
        $dataFields = [
            '1' => 1,
            '2' => 'Hello'
        ];

        $this->client->setDataFields($dataFields);
        $this->assertEquals($dataFields, $this->client->getDataFields());
    }

    /**
     * @covers ::validateCreditCard()
     */
    public function testValidateCreditCard()
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $this->client->setCreditCard('4111111111111111', '02', (string) date('Y', strtotime('+1 year')));
        $this->client->setBillTo('John', 'Doe', '123 Main St', '', 'Midvale', 'UT', '84044', 'US', 'john.doe@example.org', '127.0.0.1');
        $this->client->setCurrencyCode('USD');
        $response = $this->client->validateCreditCard();
        $this->assertEquals(100, $response['ccAuthReply']->reasonCode);
    }

    /**
     * @covers ::authorize()
     */
    public function testAuthorization()
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $response = $this->newAuthorization();
        $this->assertTrue(isset($response['ccAuthReply']));
        $this->assertEquals(100, $response['ccAuthReply']->reasonCode);
        $this->assertEquals($this->testAmount, $response['ccAuthReply']->amount);
        return $response;
    }

    /**
     * @covers ::authorize()
     */
    public function testAuthorizeAndCapture()
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $this->client->setCreditCard('4111111111111111', '02', (string) date('Y', strtotime('+1 year')));
        $this->client->setBillTo('John', 'Doe', '123 Main St', '', 'Midvale', 'UT', '84044', 'US', 'john.doe@example.org', '127.0.0.1');
        $this->client->setCurrencyCode('USD');
        $response = $this->client->authorize($this->testAmount, true);
        $this->assertTrue(isset($response['ccAuthReply']));
        $this->assertEquals(100, $response['ccAuthReply']->reasonCode);
        $this->assertEquals($this->testAmount, $response['ccAuthReply']->amount);
        $this->assertTrue(isset($response['ccCaptureReply']));
        $this->assertEquals(100, $response['ccCaptureReply']->reasonCode);
        $this->assertEquals($this->testAmount, $response['ccCaptureReply']->amount);
        return $response;
    }

    /**
     * @covers  ::reverseAuthorization()
     */
    public function testReverseAuthorization()
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $authResponse = $this->newAuthorization();
        $response = $this->client->reverseAuthorization($authResponse['requestID'], $this->testAmount);
        $this->assertTrue(isset($response['ccAuthReversalReply']));
        $this->assertEquals(100, $response['ccAuthReversalReply']->reasonCode);
        $this->assertEquals($this->testAmount, $response['ccAuthReversalReply']->amount);
        return $response;
    }

    /**
     * @covers  ::capture()
     */
    public function testCapture()
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $authResponse = $this->newAuthorization();
        $response = $this->client->capture($authResponse['requestID'], $this->testAmount);
        $this->assertTrue(isset($response['ccCaptureReply']));
        $this->assertEquals(100, $response['ccCaptureReply']->reasonCode);
        $this->assertEquals($this->testAmount, $response['ccCaptureReply']->amount);
        return $response;
    }

    /**
     * @covers  ::credit()
     * @depends testAuthorizeAndCapture
     */
    public function testCredit(array $captureResponse)
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $this->client->setCurrencyCode('USD');
        $response = $this->client->credit($captureResponse['requestID'], $this->testAmount);
        $this->assertTrue(isset($response['ccCreditReply']));
        $this->assertEquals(100, $response['ccCreditReply']->reasonCode);
        $this->assertEquals($this->testAmount, $response['ccCreditReply']->amount);
        return $response;
    }

    /**
     * @covers  ::void()
     * @depends testAuthorizeAndCapture
     */
    public function testVoidCapture(array $captureResponse)
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $response = $this->client->void($captureResponse['requestID']);
        $this->assertTrue(isset($response['voidReply']));
        $this->assertEquals(100, $response['voidReply']->reasonCode);
        return $response;
    }

    /**
     * @covers  ::void()
     * @depends testCredit
     */
    public function testVoidCredit(array $creditResponse)
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $response = $this->client->void($creditResponse['requestID']);
        $this->assertTrue(isset($response['voidReply']));
        $this->assertEquals(100, $response['voidReply']->reasonCode);
        return $response;
    }

    /**
     * @covers ::createSubscription()
     */
    public function testCreateSubscription()
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $this->client->setCreditCard('4111111111111111', '02', (string) date('Y', strtotime('+1 year')), '123', 'Visa');
        $this->client->setBillTo('John', 'Doe', '123 Main St', '', 'Midvale', 'UT', '84044', 'US', 'john.doe@example.org', '127.0.0.1');
        $this->client->setCurrencyCode('USD');
        $response = $this->client->createSubscription(null, false);
        $this->assertTrue(isset($response['paySubscriptionCreateReply']));
        $this->assertEquals(100, $response['paySubscriptionCreateReply']->reasonCode);
        return $response;
    }

    /**
     * @covers  ::createSubscription()
     */
    public function testCreateSubscriptionFromAuthorization()
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $authResponse = $this->newAuthorization();
        $response = $this->client->createSubscription($authResponse['requestID']);
        $this->assertTrue(isset($response['paySubscriptionCreateReply']));
        $this->assertEquals(100, $response['paySubscriptionCreateReply']->reasonCode);
    }

    /**
     * @covers  ::getSubscription()
     * @depends testCreateSubscription
     */
    public function testGetSubscription(array $createResponse)
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $response = $this->client->getSubscription($createResponse['requestID']);
        $this->assertTrue(isset($response['paySubscriptionRetrieveReply']));
        $this->assertEquals(100, $response['paySubscriptionRetrieveReply']->reasonCode);
        $this->assertEquals($createResponse['paySubscriptionCreateReply']->subscriptionID, $response['paySubscriptionRetrieveReply']->subscriptionID);
        return $response;
    }

    /**
     * @covers ::chargeSubscription()
     * @depends testGetSubscription
     */
    public function testChargeSubscription(array $subscriptionResponse)
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $this->client->setCurrencyCode('USD');
        $response = $this->client->chargeSubscription($subscriptionResponse['paySubscriptionRetrieveReply']->subscriptionID, $this->testAmount, true);
        $this->assertTrue(isset($response['ccAuthReply']));
        $this->assertEquals(100, $response['ccAuthReply']->reasonCode);
        $this->assertEquals($this->testAmount, $response['ccAuthReply']->amount);
        $this->assertTrue(isset($response['ccCaptureReply']));
        $this->assertEquals(100, $response['ccCaptureReply']->reasonCode);
        $this->assertEquals($this->testAmount, $response['ccCaptureReply']->amount);
    }

    /**
     * @covers ::updateSubscription()
     * @depends testGetSubscription
     */
    public function testUpdateSubscription(array $originalSubscription)
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $this->client->setBillTo('John1', 'Doe1', '123 Main St', '', 'Midvale', 'UT', '84044', 'US', 'john.doe@example.org', '127.0.0.1');
        $response = $this->client->updateSubscription($originalSubscription['paySubscriptionRetrieveReply']->subscriptionID);
        $this->assertTrue(isset($response['paySubscriptionUpdateReply']));
        $this->assertEquals(100, $response['paySubscriptionUpdateReply']->reasonCode);
        $updatedSubscription = $this->client->getSubscription($response['paySubscriptionUpdateReply']->subscriptionID);
        $this->assertEquals('john1', strtolower($updatedSubscription['paySubscriptionRetrieveReply']->firstName));
        $this->assertEquals('doe1', strtolower($updatedSubscription['paySubscriptionRetrieveReply']->lastName));
    }

    /**
     * @covers ::deleteSubscription()
     * @depends testGetSubscription
     */
    public function testDeleteSubscription(array $subscriptionResponse)
    {
        if (!$this->runTransactionTests) {
            $this->markTestSkipped('Not configured to run remote tests');
        }
        $response = $this->client->deleteSubscription($subscriptionResponse['paySubscriptionRetrieveReply']->subscriptionID);
        $this->assertTrue(isset($response['paySubscriptionDeleteReply']));
        $this->assertEquals(100, $response['paySubscriptionDeleteReply']->reasonCode);
        $this->assertEquals($subscriptionResponse['paySubscriptionRetrieveReply']->subscriptionID, $response['paySubscriptionDeleteReply']->subscriptionID);
    }

    /**
     * @covers ::checkRequired
     * @expectedException \Exception
     */
    public function testCheckRequired()
    {
        $class = new \ReflectionClass('\Cdtweb\CyberSourceClient');
        $method = $class->getMethod('checkRequired');
        $method->setAccessible(true);
        $method->invokeArgs($this->client, [['creditCard']]);
    }
}
