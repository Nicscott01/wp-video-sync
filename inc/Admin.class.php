<?php

/**
 *  Handle WP Admin tasks
 * 
 * 
 */

namespace WPVS;

class Admin
{

    public static $instance = null;


    public function __construct()
    {



        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);

        add_action('save_post', [$this, 'save_post_meta']);


        if ( function_exists( 'learndash_get_post_type_slug' ) ) {
            
            add_filter( 'bulk_actions-edit-' . learndash_get_post_type_slug('course'), [ $this, 'bulk_actions_force_update' ] );

            add_filter('handle_bulk_actions-edit-'. learndash_get_post_type_slug('course'), [ $this, 'handle_bulk_actions_force_update' ], 10, 3 );

            add_action( 'admin_notices', [ $this, 'admin_notices' ] );
        }

    }



    /**
     *  Add our metaboxes
     * 
     * 
     */

    public function add_meta_boxes()
    {

        add_meta_box(
            'video-album',
            __('Vimeo Showcase URL'),
            [$this, 'album_metabox'],
            ['sfwd-courses'],
            'advanced',
            'high',
            []
        );
    }





    /**
     *  Output the Metabox
     * 
     * 
     *  
     */


    public function album_metabox()
    {

        global $post;

        $value = get_post_meta($post->ID, '_album_url', true);

?>
        <div class="album sfwd sfwd_options ">
            <label for="album-url">Showcase URL</label>
            <input id="album-url" type="text" name="_album_url" value="<?php echo $value ?>" placeholder="Vimeo Showcase URL">
        </div>
<?php

    }





    /**
     *  Save the Album URL
     * 
     */

    public function save_post_meta($post_id)
    {

        if (isset($_POST['_album_url']) && !empty($_POST['_album_url'])) {

            $album_url = sanitize_text_field( $_POST['_album_url']);

            update_post_meta( $post_id, '_album_url', $album_url );

            $AlbumSync = new \WPVS\AlbumSync( $album_url, $post_id );
            
        }

    }




    /**
     *  Add the bulk action to force an update
     * 
     */

     public function bulk_actions_force_update( $bulk_actions ) {

        $bulk_actions['wpvs_force_update'] = __( 'Force Update Video Showcase' );

        return $bulk_actions;

     }



    /**
     *  Handler for Bulk Action Force Update
     * 
     * 
     */

    public function handle_bulk_actions_force_update( $redirect_url, $action, $post_ids ) {

        if ( $action == 'wpvs_force_update' ) {

            foreach( $post_ids as $post_id ) {

                $video_album = get_post_meta( $post_id, '_album_url', true );

                new AlbumSync( $video_album, $post_id, true );

                error_log( 'Triggered bulk action to force update post ' . $post_id . ' with album ' . $video_album );

            }

            $redirect_url = add_query_arg( 'scheduled_force_update', count( $post_ids ), $redirect_url );

        }

        return $redirect_url;
    }



    /**
     *  Display Admin Notices
     * 
     * 
     */

    public function admin_notices() {

        if ( isset( $_REQUEST['scheduled_force_update' ] ) && !empty( $_REQUEST['scheduled_force_update'] ) )  {

            $num_changed = (int) $_REQUEST['scheduled_force_update' ];

            printf( '<div id="wpvs-admin-notice-force-update" class="updated notice is-dismissable"><p>' . __( 'Scheduled %d posts to do forced sync.' ) . '</p></div>', $num_changed  );

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


//Fire it up
Admin::get_instance();
