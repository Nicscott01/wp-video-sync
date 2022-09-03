<?php
/**
 *  Abstract Class for Sync
 * 
 * 
 */


 namespace WPVS;

 use DateTime;
 use DateTimeZone;
 
 use function PHPSTORM_META\map;
 
 class Sync {


    public $uri;
    public $course_id;
    public $api_resp;
    public $video_id;

    /**
     *  Construct function
     * 
     *  @param string $uri  The Vimeo video URI 
     *  
     */

    public function __construct( $uri = '', $course_id = '' ) {

        if ( !defined( 'LEARNDASH_VERSION' ) ) {
            $this->error = 'Learndash version constant not available. Bailing.';
            return $this;
        }

        if ( isset( $uri ) && !empty( $uri ) ) {

            $this->set_uri( $uri );

        }
        if ( isset( $course_id ) && !empty( $course_id ) ) {

            $this->set_course_id( $course_id );
        }

        return $this;

    }


    /**
     *  Set the URI
     * 
     *  @param string $uri  The Vimeo video uri
     */

    public function set_uri( $uri ) {

        $this->uri = $uri;

        return $this;

    }




    /**
     *  Set the course id
     * 
     *  @param string $course_id  The Vimeo video uri
     */

    public function set_course_id( $course_id ) {

        $this->course_id = (int) $course_id;

        return $this;

    }


    


    /**
     *  Parse the uri to pull out video id
     * 
     * 
     */

    public function get_video_id() {

        $re = '/^\/videos\/(\d+)(?:[a-zA-Z0-9_\-]+)?/mi';

        preg_match($re, $this->uri, $matches, PREG_OFFSET_CAPTURE, 0);

        if ( isset( $matches[1][0] ) ) {
            
            $this->video_id = $matches[1][0];

        } 

        return $this;

     }





    /**
     *  Get the Viemo Video ID
     * 
     */

    public function get_vimeo_id_from_url() {

        //OK, we know its vimeo url. Now we need to rip out the id
        $re = '/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|showcase\/|)(\d+)(?:[a-zA-Z0-9_\-]+)?/mi';
        
        preg_match_all($re, $this->uri, $matches, PREG_SET_ORDER, 0);

        if ( !empty( $matches[0][1] ) ) {
            
            $this->video_id = $matches[0][1];

        } else {

            $this->video_id = false;
        }

        return $this;

     }



    /**
     *  Help function to Get Timestamp
     * 
     * 
     */

    public function get_timestamp($time_string)
    {

        $time = new DateTime($time_string, new DateTimeZone('UTC'));

        return $time->getTimestamp();
  
    }

    /**
     *  Helper Function to set timestamp integers
     * 
     *  @param array $data 
     *  @param string $field
     * 
     */

    public function set_timestamps( $data, $field ) {

        if ( !empty( $data ) && is_array( $data ) ) {

            foreach ( $data as &$row ) {

                if ( isset( $row[$field] ) ) {

                    $row[$field] = $this->get_timestamp( $row[$field] );
                
                }
            }
            
        }

        return $data;

    }

 


 }