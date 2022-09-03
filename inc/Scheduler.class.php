<?php
/**
 *  Class to control the scheduling
 *  of updates
 * 
 */
 
 namespace WPVS;

 class Scheduler {


    public static $instance = null;



    public function __construct() {
        
        // add_action( 'init', [ $this, 'test_as' ] );
 
         //add_action( 'log_test_as', [ $this, 'log_test_as_data' ] );
 
 
         //add_action( 'wpvs_fetch_latest_data', [ $this, 'fetch_latest_data' ] );
 
         //add_action( 'wpvs_process_batch', [ $this, 'process_batch' ], 10, 1 );
         
        // add_action( 'wpvs_update_post', [ $this, 'update_post' ], 10, 3 );
 

        /**
         *  Initialize check for changes
         * 
         */

        add_action( 'init', [ $this, 'check_for_changes' ] );


        /**
         *      Check for album changes
         * 
         */
        add_action( 'wpvs_albumsync_check', [ $this, 'albumsync_check' ] );

         /**
          *     Update a video post
          */
         add_action( 'wpvs_update_video_post', [ $this, 'update_video_post'], 10, 2 );

     }
 



    /**
     *  Schedule the check for changes
     * 
     * 
     */

     public function check_for_changes() {

        if ( as_has_scheduled_action( 'wpvs_albumsync_check' ) === false ) {

            as_schedule_recurring_action( strtotime( 'now' ), MINUTE_IN_SECONDS, 'wpvs_albumsync_check', [], 'sync_check' );

        }

        

     }







    /**
     *  Schedule a video post to get
     *  updated
     * 
     * 
     */

    public function schedule_video_post_update( $uri, $course_id ) {

        as_schedule_single_action( time(), 'wpvs_update_video_post', [ $uri, $course_id ], 'post_update' );


    }






    /**
     *  Do the Video Post Update
     * 
     * 
     */

    public function update_video_post( $uri, $course_id ) {

        new \WPVS\VideoSync( $uri, $course_id );

    }



    /**
     *     Check for albumsync changes
    */

    public function albumsync_check() {

        $album_posts = $this->get_album_posts();

        if ( !empty( $album_posts ) ) {

            foreach( $album_posts as $album_post ) {

                error_log( 'doing a sync check for post_id ' . $album_post->ID );

                $url = get_post_meta( $album_post->ID, '_album_url', true );


                $AlbumSync = new \WPVS\AlbumSync( $url, $album_post->ID );
               
        
            }

        }

        return $album_posts;

    }



     /**
      *  Helper to initialize "auto sync"
      *
      */
      public function init_auto_sync() {

       

    }



    /**
     *  Helper function to get posts with albums
     * 
     */

    public function get_album_posts() {

        $args = [
            'post_type' => learndash_get_post_type_slug( 'course' ),
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                'key' => '_album_url',
                'value' => 'showcase',
                'compare' => 'LIKE'
                ]
            ]
        ];

        $album_posts_query = new \WP_Query( $args );

        if ( $album_posts_query->post_count > 0 ) {

            return $album_posts_query->posts;

        }

        
    }
















    public function test_as() {

        if ( false === as_has_scheduled_action( 'log_test_as' ) ) {

            as_schedule_recurring_action( strtotime( 'now' ), HOUR_IN_SECONDS, 'log_test_as' );
        }


        if ( isset( $_GET['test']  ) ) {

            if ( isset( $_GET['post_id'] ) ) {

                //$this->fetch_latest_data( $_GET['post_id' ] );
                $this->process_batch(  $_GET['post_id' ] );

            } else {
                
                $this->fetch_latest_data();

            }

            echo 'Did the test';

            die();

        } elseif ( isset( $_GET['as_scheduled' ] ) ) {

            var_dump( as_get_scheduled_actions( [ 'status' => 'pending' ]) );

            die();

        } elseif( isset( $_GET['update_post'] ) ) {

            $this->update_post( '/videos/617335390', 1661987393, 8 );

            echo 'Updating...';

            die();

        } elseif ( isset( $_GET['uri_regex'] ) ) {

            var_dump( WPVS_Vimeo()->get_vimeo_id_from_uri( $_GET['uri'] ) );

            die();
        }



    }


    public function log_test_as_data() {

        $now = new DateTime( 'now', new DateTimeZone( 'America/New_York' ) );

        error_log( 'ActionScheduler ran at ' . $now->format( 'M j, Y H:i:s' ) );

    }






    public static function get_instance()
    {

        if (self::$instance == null) {

            self::$instance = new self;
        }

        return self::$instance;
    }



 }