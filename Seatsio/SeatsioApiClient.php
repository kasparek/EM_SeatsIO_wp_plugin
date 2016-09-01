<?php
require('SeatsioApiClientRequest.php');

/**
 * # Seats.io PHP API Client
 * Floor plan plugin for your event software
 * For more information see: http://www.seats.io/docs
 * 
 * @see : API docs: http://www.seats.io/docs/api
 */
class SeatsioApiClient {

	//REST API endpoint
	private $endpoint = 'https://app.seats.io/api/';

	//API key provided by Seats.io for each user account
	private $secretKey = null;

	//Request data
	public $request;

	//Response data
	public $response; //->code ->headers

	function __construct($secretKey) {
		Unirest\Request::verifyPeer(false);
		$this->secretKey = $secretKey;
		$this->resetRequest();
	}

	public function setEndpoint($endpoint) {
		$this->endpoint = $endpoint;
	}

	/**
	 * # Booking objects
	 * Use this API call to tell us whenever a ticket sale is confirmed
	 * @see 	http://www.seats.io/docs/api#bookingObjects
	 * @param  string 			$eventKey 			Id of the event
	 * @param  array 			$objectList 		Array of objects ids to book. See examples.
	 * @param  string optional 	$orderId 			Custom order id. To be able to retrieve the objects IDs per order later on.
	 * @param  string optional 	$reservationToken 	The reservation token must be supplied when booking a seat that has been temporarily reserved
	 * @return null			HTTP 200 - empty response for success
	 *                      HTTP 400 - when already booked
	 *                      HTTP 400 - booking same objects at the same time
	 */
	public function book($eventKey,$objectList,$orderId=null,$reservationToken=null) {
		$url = $this->endpoint . 'book';
		$this->resetRequest();
		$this->request->data->event = $eventKey;
		$this->request->data->objects = $objectList;
		if(!empty($orderId)) $this->request->data->orderId = $orderId;
		if(!empty($reservationToken)) $this->request->data->reservationToken = $reservationToken;
		return $this->postRequest($url);
	}

	/**
	 * # Releasing objects
	 * Set status free on previously booked objects
	 * @see 	http://www.seats.io/docs/api#releasingObjects
	 * @param  string 			$eventKey 			Id of the event
	 * @param  array 			$objectList			Array of objects ids to release. See examples.
	 * @param  string optional 	$reservationToken 	The reservation token must be supplied when releasing a seat that has been temporarily reserved
	 * @return null				HTTP 200 - empty response for success
	 *                      	HTTP 400 - when already released
	 */
	public function release($eventKey, $objectList,$reservationToken=null) {
		$url = $this->endpoint . 'release';
		$this->resetRequest();
		$this->request->data->event = $eventKey;
		$this->request->data->objects = $objectList;
		if(!empty($reservationToken)) $this->request->data->reservationToken = $reservationToken;
		return $this->postRequest($url);
	}

	/**
	 * # Reports on event bookings
	 * List seats with their current status
	 * @see 	http://www.seats.io/docs/api#api-reference-reporting
	 * @param  string 			$eventKey 			Id of the event
	 * @param  array 			$reportType			byStatus|byCategoryLabel|byCategoryKey|byLabel|byUuid
	 * @param  string optional 	$reservationToken 	The reservation token must be supplied when releasing a seat that has been temporarily reserved
	 * @return object			HTTP 200 - object with seats objects
	 */
	public function report($eventKey, $reportType='byStatus') {
		$url = $this->endpoint . 'event/'.$this->secretKey.'/'.$eventKey.'/report/'.$reportType;
		$this->resetRequest();
		return $this->getRequest($url);
	}

	public function categories($chartKey) {
		$chart = $this->chart($chartKey);
		return $chart->categories->list;
	}

	/**
	 * # Fetching charts of a user
	 * This API call returns an array of chart keys, names and categories. It does not return the drawings.
	 * @see 	http://www.seats.io/docs/api#fetchingCharts
	 * @return array		HTTP 200 - array of charts
	 *                      HTTP 400 - When invalid secret key
	 */
	public function charts() {
		$url = $this->endpoint . 'charts/'.$this->secretKey;
		return $this->getRequest($url);
	}

	/**
	 * # Fetching a chart
	 * Returns the full drawing: seats, categories, width, height etc.
	 * @see 	http://www.seats.io/docs/api#fetchingChart
	 * @param  string 		$chartKey		Id of the chart
	 * @return object		HTTP 200 - complete chart setup
	 *                      HTTP 400 - when chart key is invalid
	 */
	public function chart($chartKey) {
		$url = $this->endpoint . 'chart/'.$chartKey.'.json';
		return $this->getRequest($url);
	}

	/**
	 * # Fetching an event details
	 * Returns the full drawing: seats, categories, width, height etc.
	 * @see 	http://www.seats.io/docs/api#api-reference-events-event-details
	 * @param  string 		$eventKey		Id of the event
	 * @return object		HTTP 200 - {chartKey:String, bookWholeTables:boolean}
	 *                      HTTP 400 - when chart key is invalid
	 */
	public function event($eventKey) {
		$url = $this->endpoint . 'event/' . $this->secretKey . '/' . $eventKey.'/details';
		return $this->getRequest($url);
	}

	/**
	 * # Fetching the chart linked to an event
	 * This API call returns the chart key, name and categories for the chart that's linked to an event. It does not return the drawing.
	 * @see 	http://www.seats.io/docs/api#fetchingChartForEvent
	 * @param  string 		$eventKey 		Id of the event
	 * @return object		HTTP 200 - chart - name and categories
	 *                      HTTP 400 - when invalid event key
	 */
	public function eventChart($eventKey) {
		$url = $this->endpoint . 'chart/' . $this->secretKey . '/event/' . $eventKey;
		return $this->getRequest($url);
	}

	/**
	 * # Creating and Updating Events
	 * Create/Update a single event
	 * Charts must be linked to events before you can show them to tickets buyers. You (the ticket seller) have to provide an event key; that's the ID the event in your own database. It could be for example the primary key of the event record.
	 * @see 	http://www.seats.io/docs/api#creatingAndUpdatingEvents
	 * @param  string 		$chartKey 		Id of the chart
	 * @param  string 		$eventKey 		Id of the event
	 * @return null			HTTP 200 - empty response for success
	 */
	public function linkChartToEvent($chartKey, $eventKey) {
		$url = $this->endpoint . 'linkChartToEvent';
		$this->resetRequest();
		$this->request->data->chartKey = $chartKey;
		$this->request->data->eventKey = $eventKey;
		return $this->postRequest($url);
	}

	/**
	 * # Create/Update a multiple events at once
	 * @see 	http://www.seats.io/docs/api#creatingAndUpdatingEvents
	 * @param  string 		$chartKey 		Id of the chart
	 * @param  array 		$eventKeyList 	Array of custom event IDs
	 * @return null			HTTP 200 - empty response for success
	 */
	public function linkChartToEvents($chartKey, $eventKeyList) {
		$url = $this->endpoint . 'linkChartToEvents';
		$this->resetRequest();
		$this->request->data->chartKey = $chartKey;
		$this->request->data->eventKeys = $eventKeyList;
		return $this->postRequest($url);
	}

	/**
	 * # Creating users
	 * The API lets you create new user accounts. This is useful if you own a ticketing site, and you want your clients to draw their own charts without having to register manually at seats.io.
	 * @see 	http://www.seats.io/docs/api#creatingUsers
	 * @return object		HTTP 200 - object with secretKey, publicKey of new user.
	 */
	public function createUser() {
		$url = $this->endpoint . 'createUser';
		$this->resetRequest();
		return $this->postRequest($url);
	}

	/**
	 * # Copying charts
	 * Creates a copy of a chart, named "<original name> (copy)". Events are not copied.
	 * @see 	http://www.seats.io/docs/api#copyingCharts
	 * @param  string 		$chartKey 		Id of the chart
	 * @return string		HTTP 200 - chartKey of the new chart
	 */
	public function copyChart($chartKey) {
		$url = $this->endpoint . 'chart/copy';
		$this->resetRequest();
		$this->request->data->chartKey = $chartKey;
		return $this->postRequest($url);
	}

	/**
	 * # Changing object status
	 * Set custom status
	 * Two default API statuses are 'free' and 'booked' (with API calls "release" and "book").
	 * However, you can also assign other, custom statusses. For example: 'reserved'.
	 * @see 	http://www.seats.io/docs/api#changingObjectStatus
	 *
	 * @param  string		$eventKey   		event id
	 * @param  string		$status     		custom status
	 * @param  array		$objectList 		object list can have two types of values
	 *                                			1. array of seat ids ['A-3', 'A-5', 'A-7']
	 *                                   		2. for general admission array of objects with specified quantity [{'objectId': 'area-identifier', 'quantity': 3}] 
	 * @param  string optional $reservationToken 	The reservation token must be supplied when changing the status of a seat that has been temporarily reserved.
	 * @return null			HTTP 200 - empty response for success
	 *                      HTTP 400 - when changing object in status X to that same status X
	 *                      HTTP 400 - when two requests change the status of the same object at the same time
	 */
	public function changeStatus($eventKey, $status, $objectList,$reservationToken=null) {
		$url = $this->endpoint . 'changeStatus';
		$this->resetRequest();
		$this->request->data->event = $eventKey;
		$this->request->data->status = $status;
		$this->request->data->objects = $objectList;
		if(!empty($reservationToken)) $this->request->data->reservationToken = $reservationToken;
		return $this->postRequest($url);
	}

	/**
	 * # Orders
	 * When booking objects, you can optionally pass in an orderId. If you do so, you can retrieve all object IDs per orderId whenever you need (e.g. when printing tickets later on).
	 * @see 	http://www.seats.io/docs/api#orders
	 * @param 		string 	$eventKey 	Event id
	 * @param 		string 	$orderId 	Custom order id
	 * @response 	array	List of booked objects
	 */
	public function orders($eventKey, $orderId) {
		$url = $this->endpoint . 'event/' . $eventKey . '/orders/' . $orderId . '/'. $this->secretKey;
		return $this->getRequest($url);
	}

	/**
	 * Reset default settings for API request
	 * @access private
	 * */
	private function resetRequest() {
		$this->request = new SeatsioApiClientRequest();
		$this->request->data->secretKey = $this->secretKey;
	}

	/**
	 * Execute GET request to Seats.io RESTful API
	 * Full response in $this->response
     * @see Unirest for PHP http://unirest.io/php.html
	 * 
	 * @param string 	$url endpoint URL
     * @return mixed 	Data - body of response
     * 
     * @throws exceptionclass Throws exception when HTTP response is not 200
     * 
	 * @access private
	 */
	private function getRequest($url=null) {
		if(!empty($url)) $this->request->url = $url;
		$this->request->data = null;
		$this->response = Unirest\Request::get($this->request->url, $this->request->headers);
		if($this->response->code !== 200) {
			throw new Exception('getRequest - Exception: ' . (!empty($this->response->body) ? $this->response->body : implode("\n",$this->response->headers)));
		}
		return $this->response->body;
	}

	/**
	 * Execute POST request to Seats.io RESTful API
	 * Full response in $this->response
     * @see Unirest for PHP http://unirest.io/php.html
     *
	 * @param string 	$url endpoint URL
     * @return mixed 	Data - body of response
     *
     * @throws exceptionclass Throws exception when HTTP response is not 200
     *
     * @access private
	 */
	private function postRequest($url=null) {
		if(!empty($url)) $this->request->url = $url;
		$this->response = Unirest\Request::post($this->request->url, $this->request->headers, json_encode($this->request->data));
		if($this->response->code !== 200) {
			throw new Exception('postRequest - Exception: ' . (!empty($this->response->body) ? $this->response->body->message : $this->response->headers[0]));
		}
		return $this->response->body;
	}
}