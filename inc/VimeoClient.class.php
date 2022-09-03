<?php

/**
 *  Get the vimeo videos
 * 
 * 
 */

namespace WPVS;

use \DateTime;
use \DateTimeZone;
use \Vimeo\Vimeo;



class VimeoClient
{

    private const CLIENT_ID = '856b19ccb3f5b22e96e3c876a3354bb6421b6daa';
    private const CLIENT_SECRET = 'o2dWNQb5fgZd9Ry8qLrZNq/pk7qBW9JJjbxw9WII4vumWWoWqHTwkw+f96HIQo5LDCYs9Ca58zL8Y099G0bM3TahYfgrFEvAsdSKx1WldeRJbdXdgHwy0AR9t3e5stRP';
    private const ACCESS_TOKEN = '186d57b167962cfdb8581fee033f0408';

    public static $instance = null;

    //Set the variables so we can use the db to store values later
    private $client_id;
    private $client_secret;
    private $access_token;

    public $client;
    public $resp;
    public $temp_resp;


    public function __construct()
    {

        $this->client_id = self::CLIENT_ID;
        $this->client_secret = self::CLIENT_SECRET;
        $this->access_token = self::ACCESS_TOKEN;

    }


    /**
     *  Setup the Vimeo SDK with our access
     * 
     * 
     */

    public function client()
    {

        if (empty($this->client)) {

            $this->client = new Vimeo($this->client_id, $this->client_secret, $this->access_token);
        }

        return $this->client;
    }




    /**
     *  Send a request to the Vimeo SDK
     * 
     *  @param string $req      The request string
     *  @param array $options   Options
     *  @param string $type     GET, POST, etc.
     */

    public function request($req, $options, $type)
    {
        error_log( 'Working on this request: ' . $req );

        
        if ( empty( $req ) ) {
            
            error_log( 'Cannot process an empty request' );
            
            return false;

        }

        $this->resp = $this->client()->request($req, $options, $type);

        //var_dump( $this->resp );

        //Check for more pages and keep on getting them.
        if ( $this->resp['status'] == 200 && isset( $this->resp['body']['paging'] ) && !empty( $this->resp['body']['paging']['next'] ) ) {

           $this->add_to_temp_resp( $this->resp['body']['paging']['next'] );

           $this->request( $this->resp['body']['paging']['next'], [], $type );

            return $this->resp;

        } elseif ( $this->resp['status'] == 200 && isset( $this->resp['body']['paging'] ) && empty( $this->resp['body']['paging']['next'] ) ) {
            //We're all done getting more requests.

            $this->add_to_temp_resp( $this->resp['body']['paging']['next'] );

            return $this->resp;

        } else {


            return $this->resp;

        }


      
    }


    /**
     *  Helper function to add to
     *  temp resp 
     * 
     */


    public function add_to_temp_resp( $next = '' ) {

        if ( !empty( $next ) ) {

            $has_more = true;

        } else {

            $has_more = false;
        }




        if ( empty( $this->temp_resp ) ) {

            $this->temp_resp = $this->resp['body']['data'];

       } else {

            foreach( $this->resp['body']['data'] as $item ) {

                $this->temp_resp[] = $item;

            }

       }

       if ( !$has_more ) {

            $this->resp = $this->temp_resp;

            $this->temp_resp = [];

       }


       return $this;

    }





    /**
     *  Get a Value from the Video Object
     * 
     *  @param string/array   $item       The key of the thing we want
     *  @param array    $resp       Vimeo API response for video
     * 
     * 
     */

    public function get_video_obj_value($item)
    {

        if (is_array($item)) {

            $value = [];


            foreach ($item as $level) {

                if (empty($value) && isset($this->resp['body'][$level])) {

                    $value = $this->resp['body'][$level];
                } elseif (!empty($value) && isset($value[$level])) {

                    $value = $value[$level];
                }
            }

            return $value;
        } elseif (is_string($item) && isset($this->resp['body'][$item])) {

            return $this->resp['body'][$item];
        } else {

            return null;
        }
    }



    /**
     *  Singleton
     * 
     */

    public static function get_instance()
    {

        if (self::$instance == null) {

            self::$instance = new self;
        }

        return self::$instance;
    }
}
