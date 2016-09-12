<?php
/**
 * # Request data
 * Default setup for all Seats.io API requests
 */
class SeatsioApiClientRequest
{
    /**
     * URL for API request
     **/
    public $url;
    /**
     * Data for POST requests
     * */
    public $data;

    /**
     * HTTP headers for all API requests
     * */
    public $headers = array("Accept" => "application/json");

    public function __construct()
    {
        $this->data = new stdClass();
    }
}
