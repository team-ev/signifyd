<?php

/**
 * A signifyd wrapper for their restful http api.
 */
class SignifydClient {

/* Fields */

	/**
	 * Signifyd Api end point
	 * @var string
	 */
	protected $baseUrl = 'https://api.signifyd.com/v2/';

	/**
	 * Signifyd errors codes and their meaning
	 * @var array
	 */
	protected $statusDictionary = array(
		'200' => 'OK - Everything worked as expected.',
		'201' => 'CREATED - The resource requested was successfully created.',
		'202' => 'Case is still processing.',
		'400' => 'Bad Request - Often missing a required parameter.',
		'401' => 'Unauthorized - No valid API key provided.',
		'402' => 'Request Failed - Parameters were valid but request failed.',
		'404' => 'Not Found - The requested item doesn\'t exist.',
		'500' => 'Server errors - something went wrong on Signifyd\'s end.', //502, 503, 504 
	);

	/**
	 * Api key for Signifyd
	 * @var string
	 */
	protected $apiKey = '';

	/**
	 * cUrl library instance
	 * @var curl
	 */
	protected $curl;

/* End Fields */

/* Functions */

	/**
	 * Constructor
	 * @param string $apiKey 	Key for Signifyd-API
	 */
	function __construct($apiKey) {
		$this->apiKey = $apiKey;
	}
	
	/**
	 * Initializing curl class
	 */
	private function initCurl() {
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_USERPWD, "{$this->apiKey}:");
		curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($this->curl, CURLOPT_HEADER, 1);
	}

	/**
	 * Method for making a request to Signifyd servers
	 * @param  string $url			Additional path, which will be added to the baseUrl
	 * @param  string $accept   	Accept Type
	 * @param  array $postData		Data which to send through POST request.
	 * @param  string $contentType 	Contetn-type. Default is application/json
	 * @return array              	Response Array.
	 */
	protected function request($url, $accept, $postData = null, $contentType = 'application/json')	{
		$this->initCurl();

		curl_setopt($this->curl, CURLOPT_URL, $this->baseUrl . $url);

		if (stripos($url, 'https://') === 0) {
            curl_setopt($this->curl, CURLOPT_PORT, 443);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        }
        $headers = array();
        if ($accept) {
        	$headers[] = "Accept: $accept";
        }

		if (isset($postData)) {
			curl_setopt($this->curl, CURLOPT_POST, 1);
			$postDataString = json_encode($postData);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postDataString);
			$headers[] = "Content-Type: $contentType";
			$headers[] = "Content-length: " . strlen($postDataString);
		}

		if (count($headers)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        }
        $response = $this->parseResponse(curl_exec($this->curl));

        curl_close($this->curl);

		return $response;
	}

	/**
	 * Method for parsing the request response
	 * @param  string $response 	Response string
	 * @return array           		Parsed response
	 */
	protected function parseResponse($response)	{
		$data = array();
		$data['statusCode'] = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

		if ($data['statusCode'] >= 500) {
			$data['statusMessage'] = $this->statusDictionary[500];
		} else {
			$data['statusMessage'] = $this->statusDictionary[$data['statusCode']];
		}

		$data['isSuccesfull'] = false;
		$data['responseObject'] = null;
		
		if ($data['statusCode'] <= 201) {
			$data['responseObject'] = json_decode($response, true);
			$data['isSuccesfull'] = true;
		}			
		$data['fullResponse'] = $response;

		return $data;
	}

	/**
	 * Method for retrieving a case by its id
	 * @param  int $caseID		Id of the case to be retrieved.
	 * @return array			Case data
	 */
	public function getCaseByCaseId($caseID) {
		return $this->request('cases/'.$caseID, 'application/json');
	}

	/**
	 * Method for retrieving a case by Order Id
	 * @param  string $orderID 	Unique id for the order to be retrieved its case.
	 * @return array 	      	Case data
	 */
	public function getCaseByOrderId($orderID) {
		return $this->request("orders/$orderID/case", 'application/json');
	}

	/**
	 * Method for retrieving a case entries by Case ID
	 * @param  int $caseID		Id of the case to be retrieved its entries
	 * @return array         	Case entries
	 */
	public function getCaseEntriesByCaseId($caseID)	{
		return $this->request("cases/$caseID/entries", 'application/json');
	}

	/**
	 * Method for retrieving a case entries by Order ID
	 * @param  string $orderID	Unique id for the order to be retrieved its case entries
	 * @return array         	Case entries
	 */
	public function getCaseEntriesByOrderId($orderID)	{
		return $this->request("orders/$orderID/case/entries", 'application/json');
	}

	/**
	 * Method for retrieving a case analysis by Order ID
	 * @param  string $orderID	Unique id for the order to be retrieved its case analysis
	 * @return array         	Case entries
	 */
	public function getCaseAnalysisByOrderId($orderID) {
		return $this->request("orders/$orderID/case/analysis", 'application/json');
	}

	/**
	 * Method for retrieving a case analysis by Case ID
	 * @param  int $caseID  	Id of the case to be retrieved its analysis
	 * @return array         	Case analysis
	 */
	public function getCaseAnalysisByCaseId($caseID){
		return $this->request("cases/$caseID/analysis", 'application/json');
	}

	/**
	 * Method for retrieving a case data by Case ID
	 * @param  string $orderID	Id of the case to be retrieved its data
	 * @return array         	Case data
	 */
	public function getFullCaseDataByCaseId($caseID) {

		$responseData = $this->getCaseByCaseId($caseID);
		$fullData['caseBasic'] = $responseData['responseObject'];
		$fullData['isSuccesfull'] = $responseData['isSuccesfull'];

		$responseData = $this->getCaseAnalysisByCaseId($caseID);
		$fullData['caseAnalysis'] = $responseData['responseObject'];
		$fullData['isSuccesfull'] = $fullData['isSuccesfull'] && $responseData['isSuccesfull'];

		$responseData = $this->getCaseEntriesByCaseId($caseID);
		$fullData['caseEntries'] = $responseData['responseObject'];
		$fullData['isSuccesfull'] = $fullData['isSuccesfull'] && $responseData['isSuccesfull'];

		return $fullData;
	}

	/**
	 * Method for creating a case
	 * @param  array $data 		The data of the order
	 * @return int       		Case id
	 */
	public function createCase($data) {
		return $responseArray = $this->request('cases', null, $data);
	}

/* End Functions */

}