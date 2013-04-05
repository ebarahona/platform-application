<?php

namespace Acme\Bundle\TestsBundle\Tests\Functional\API;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Acme\Bundle\TestsBundle\Test\ToolsAPI;
use Acme\Bundle\TestsBundle\Test\Client;

/**
 * @outputBuffering enabled
 */
class SoapSearchApiTest extends WebTestCase
{
    /** Default value for offset and max_records */
    const DEFAULT_VALUE = 0;

    protected $client = null;

    static protected $hasLoaded = false;

    public function setUp()
    {
        $this->client = static::createClient(array(), ToolsAPI::generateWsseHeader());
        if (!self::$hasLoaded) {
            //rebuild indexes before tests
            $kernel = $this->client->getKernel();
            $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
            $application->setAutoExit(false);
            $options = array('command' => 'oro:search:reindex');
            $options['--env'] = "test";
            $application->run(new \Symfony\Component\Console\Input\ArrayInput($options));

            $this->client->startTransaction();
            $this->client->appendFixtures(__DIR__ . DIRECTORY_SEPARATOR . 'DataFixtures');
        }
        self::$hasLoaded = true;

        $this->client->soap(
            "http://localhost/api/soap",
            array(
                'location' => 'http://localhost/api/soap',
                'soap_version' => SOAP_1_2
            )
        );
    }

    public static function tearDownAfterClass()
    {
        Client::rollbackTransaction();
    }

    /**
     * @param string $request
     * @param array $response
     *
     * @dataProvider requestsApi
     */
    public function testApi($request, $response)
    {
        if (is_null($request['search'])) {
            $request['search'] ='';
        }
        if (is_null($request['offset'])) {
            $request['offset'] = self::DEFAULT_VALUE;
        }
        if (is_null($request['max_results'])) {
            $request['max_results'] = self::DEFAULT_VALUE;
        }
        $result = $this->client->soapClient->search(
            $request['search'],
            $request['offset'],
            $request['max_results']
        );
        $result = ToolsAPI::classToArray($result);
        $this->assertEqualsResponse($response, $result);
    }

    /**
     * Data provider for SOAP API tests
     *
     * @return array
     */
    public function requestsApi()
    {
        return ToolsAPI::requestsApi(__DIR__ . DIRECTORY_SEPARATOR . 'requests');
    }

    /**
     * Test API response
     *
     * @param array $response
     * @param array $result
     */
    protected function assertEqualsResponse($response, $result)
    {
        $this->assertEquals($response['records_count'], $result['recordsCount']);
        $this->assertEquals($response['count'], $result['count']);
        if (isset($response['soap']['item']) && is_array($response['soap']['item'])) {
            foreach ($response['soap']['item'] as $key => $object) {
                foreach ($object as $property => $value) {
                    if (isset($result['elements']['item'][0])) {
                        $this->assertEquals($value, $result['elements']['item'][$key][$property]);
                    } else {
                        $this->assertEquals($value, $result['elements']['item'][$property]);
                    }

                }
            }
        }
    }
}
