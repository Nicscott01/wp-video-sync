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

            update_post_meta( $post_id, '_album_url', $_POST['_album_url']);

            $AlbumSync = new \WPVS\AlbumSync( sanitize_text_field( $_POST['_album_url'] ), $post_id );
            
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
