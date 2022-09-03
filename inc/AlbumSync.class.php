<?php

namespace WPVS;


class AlbumSync extends Sync {


    public $album_post;
    public $album_data;
    public $videos_to_update = [];
    public $force_update = false;



    /**
     * Magic construct
     * 
     *  @param string $link Playlist/Showcase link
     *  @param string $course_id
     * 
     */

    public function __construct( $uri = '', $course_id = '', $force = false ) {
        
        parent::__construct( $uri, $course_id );

        if ( $force == true ) {

            $this->force_update = true;


        }


        if ( !empty( $this->uri ) && !empty( $this->course_id ) ) {

            $this->album_sync_init();

        }

    }


    /**
     *  Start up the engines
     * 
     */

    public function album_sync_init() {

        $this->get_vimeo_id_from_url()->get_album_post()->check_album_data()->maybe_schedule_updates();

        $message = sprintf( 'Finished running %s album_sync_init on course_id=%d', $this->force_update == true ? 'forced' : 'standard', $this->course_id  );

        error_log( $message );
            
    }


    /**
     *  Get the post with the album
     * 
     */

    public function get_album_post() {

        $this->album_post = get_post( $this->course_id );

        if ( !empty( $this->album_post ) ) {

            $this->album_post->album_url = get_post_meta( $this->course_id, '_album_url', true );

            if ( $this->album_post->album_url !== $this->uri ) {

                error_log( 'Trying to update an album post with non-matching urls' );
                
                return false;

            }
            
            
        } else {

            error_log( 'Could not find an album post with ID ' . $this->course_id );

        }



        return $this;


    }


    /**
     *  Check the Album Data
     * 
     * 
     * 
     * 
     */

    public function check_album_data() {

        //Always grab the latest from the API
        $this->api_resp = WPVS_Vimeo()->request( '/me/albums/' . $this->video_id . '/videos', [ 'fields' => 'uri,modified_time', 'per_page' => 25 ], 'GET' );

       // var_dump( $this->api_resp );

        $this->api_resp = $this->set_timestamps( $this->api_resp, 'modified_time' );

        error_log( 'This is the API Response after timestamps' );
        error_log( json_encode( $this->api_resp ) );

        //Now grab what's stored
        $this->album_data = get_post_meta( $this->course_id, '_album_data' , true );


        $this->compare_album_data();


        return $this;

    }


    /**
     *  Compare the Album Data between
     *  the DB and API resp
     * 
     */

    public function compare_album_data() {


        $this->videos_to_update = [];


        if ( !$this->force_update && is_array( $this->album_data ) ) {

            foreach( $this->album_data as $key => $data ) {

                if ( isset( $this->api_resp[$key] ) && is_array( $this->api_resp[$key] ) ) {
                 
                    $result = array_diff( $data, $this->api_resp[$key] );

                }


               if ( !empty( $result ) ) {

                    $this->videos_to_update[] = $this->api_resp[$key];

               }
              

            }

        } else {
            //Assume the album data is empty and needs to be updated

            $this->videos_to_update = $this->api_resp;


        }
    
    }



    /**
     *  Maybe Schedule some updates
     * 
     * 
     */

     public function maybe_schedule_updates() {

        error_log( 'Logging the following data for maybe_schedule_updates' );
        error_log( json_encode( $this->api_resp ) );
        error_log( json_encode( $this->album_data ) );
        error_log( json_encode( $this->videos_to_update ) );

        if ( !empty( $this->videos_to_update ) ) {

            foreach( $this->videos_to_update as $video_to_update ) {

               // new \WPVS\VideoSync( $video_to_update['uri'], $this->course_id );
                WPVS_Scheduler()->schedule_video_post_update( $video_to_update['uri'], $this->course_id );

            }


            //Update the database with latest from api
            update_post_meta( $this->course_id, '_album_data', $this->api_resp );

        }


     }




    
}


