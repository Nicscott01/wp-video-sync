<?php

namespace WPVS;


class AlbumSync extends Sync {


    public $album_post;
    public $album_data;
    public $videos_to_update = [];



    /**
     * Magic construct
     * 
     *  @param string $link Playlist/Showcase link
     *  @param string $course_id
     * 
     */

    public function __construct( $uri = '', $course_id = '' ) {
        
        parent::__construct( $uri, $course_id );


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

        error_log( 'Finished running album_sync_init on course_id=' . $this->course_id );
            
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

        if ( is_array( $this->album_data ) ) {

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




    public function fetch_latest_data( $post_id = null ) {


        //Get all posts with meta _video_album
        $args = [
            'post_type' => 'any',
            'post_status' => 'any',
            'meta_query' => [
                [
                'key' => '_video_album',
                'value' => 'vimeo',
                'compare' => 'LIKE'
                ]
            ]
        ];

        if ( isset( $post_id )) {

            $args['post__in'] = explode(',', $post_id );

        }

        $video_posts = get_posts( $args );

        

        var_dump( $video_posts );
        
        if ( !empty( $video_posts ) ) {

            foreach( $video_posts as $video_post ) {

                $this->batch = [];

                //Get the meta
                $video_album = get_post_meta( $video_post->ID, '_video_album', true );

                //Get the ID from the URL
                $album_id = WPVS_Vimeo()->get_vimeo_id_from_url( $video_album );

                //var_dump( $album_id );

                $this->current_album_id = $album_id;

                //Get the album via API
                $this->response = WPVS_Vimeo()->client()->request( '/me/albums/' . $album_id . '/videos', [ 'fields' => 'uri,modified_time', 'per_page' => 10 ], 'GET' );
                
                $this->add_to_batch();                

                $this->maybe_get_more();


                mydump( $this->batch );

                update_post_meta( $video_post->ID, '_video_api_resp', $this->batch );                

                as_schedule_single_action( time(), 'wpvs_process_batch', [ 'post_id' => $video_post->ID ], 'video_update' );

            }


        }
        

    }


    public function add_to_batch() {
        
        if ( !empty( $this->response ) && $this->response['status'] == '200' ) {

            foreach( $this->response['body']['data'] as $vid ) {
                
                $vid['modified_time'] = strtotime( $vid['modified_time'] );

                $this->batch[] = $vid;

            }

            
        }

       
    }


    public function maybe_get_more() {

        if ( !empty( $this->response ) && $this->response['status'] == '200' && !empty( $this->response['body']['paging']['next'] )) {

            //Do the next page
            $this->response = WPVS_Vimeo()->client()->request( $this->response['body']['paging']['next'] );
            
            $this->add_to_batch();

            $this->maybe_get_more();

        }


    }




    public function process_batch( $post_id ) {

        //Get the meta with vimeo resp
        $vids = get_post_meta( $post_id, '_video_api_resp', true );

        if ( empty( $vids ) ) {
            error_log( 'Trying to run an empty batch on post ' . $post_id );
            return;
        }



        foreach( $vids as $video ) {
            
            $video[ 'parent_id' ] = $post_id;

            //Schedule an action to update the video post
            as_schedule_single_action( time(), 'wpvs_update_post', $video, 'update_single_post' );

        }

    }




    public function update_post( $video_uri, $modified_time, $parent_id ) {


        error_log( 'Begining to update post with $vid = ' . $video_uri . ' ' . $modified_time . ' ' . $parent_id );
        //Find the post w/ the right $vid id

        $posts = get_posts( [
            'post_type' => 'any',
            'post_status' => 'any',
            'meta_query' => [
                [
                'key' => '_video_uri',
                'value' => $video_uri,
                'compare' => 'LIKE'
                ]
            ]
        ]);



        if ( empty( $posts ) ) {

            //Assume it doesn't exist, and go make it
            $this->create_post( $video_uri, $modified_time, $parent_id );

        } else {

            $this->_update_post( $posts[0], $video_uri, $modified_time, $parent_id );
        }



    }



    public function create_post( $video_uri, $modified_time, $parent_id ) {

        //Get the video resp
        error_log( 'Ready to create a new video post for vid ' . $video_uri );

        $args = [
            'post_type' => 'sfwd-lessons',
            'post_title' => 'Temp title for ' . $video_uri,
            //'post_parent' => $parent_id,
            'post_status' => 'publish',
            'meta_input' => [
                'course_id' => $parent_id,
                '_video_uri' => $video_uri
            ]
        ];

        $new_post = wp_insert_post( $args, true, true );

        learndash_update_setting( $new_post, 'lesson_video_enabled', 'on' );
        //learndash_update_setting( $new_post, 'lesson_video_url', sanitize_text_field( 'https://vimeo.com/' . $video_uri ) );


        if ( is_wp_error( $new_post ) ) {

            error_log( 'Tried to create a new post but got: ' . $new_post->get_error_message() );

        } else {
    
            $post = get_post( $new_post );

            //Run our vimeo update script
            do_action( 'save_post_sfwd-lessons', $new_post, $post, true );

            error_log( 'Created new video post for vid '. $video_uri . ' post ID: ' . $new_post );

        }


    }



    public function _update_post( $post, $video_uri, $modified_time, $parent_id ) {

        error_log( 'Ready to update post video ' . $post->ID . ' video_uri: ' . $video_uri );

        //do_action( 'save_post_sfwd-lessons', $post->ID, $post, true );
        WPVS_Vimeo()->save_vimeo_meta( $post->ID, $post, true );

    }





    
}


