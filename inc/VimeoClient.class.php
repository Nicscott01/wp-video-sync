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

    public $do_meta_update;
    public $saved_meta;

    public function __construct()
    {

        $this->client_id = self::CLIENT_ID;
        $this->client_secret = self::CLIENT_SECRET;
        $this->access_token = self::ACCESS_TOKEN;


        //Hook the save post to grab the vimeo meta
        //add_action( 'save_post_sfwd-lessons', [ $this, 'save_vimeo_meta' ], 51, 3 );


        // add_action( 'init', [ $this, 'bulk_add' ] );


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
 /*       var_dump( 'Working on this request: ' . $req );
        var_dump( $options );
        var_dump( $type );
**/

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





    public function test_connection()
    {

        $client = $this->client();

        $response = $client->request('/tutorial', [], 'GET');

        var_dump($response);
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















    //MOST OF THE STUFF UNDER HERE WILL BE MOVED/DELETED






    public function get_vimeo_id($post_id)
    {


        //Otherwise...
        //Get the video from the lesson meta
        $sfwd_meta = get_post_meta($post_id, '_sfwd-lessons', true);

        $video_url = learndash_get_setting($post_id, 'lesson_video_url');

        if (!empty($video_url)) {

            //Check if it's a vimeo video
            if (strpos($video_url, '//vimeo.com') !== false) {

                return $this->get_vimeo_id_from_url($sfwd_meta['sfwd-lessons_lesson_video_url']);
            }
        } else {

            //First check for our own meta
            $video_uri = get_post_meta($post_id, '_video_uri', true);

            if (!empty($video_uri)) {

                return $this->get_vimeo_id_from_uri($video_uri);
            } else {

                return false;
            }
        }




        return false;
    }





    /**
     * Maybe update the Video meta
     *
     */

    public function maybe_update_video_meta($vimeo_id, $post_id)
    {

        //Get the vimeo metadata
        $video_meta = $this->client()->request('/videos/' . $vimeo_id, ['filter' => 'duration'], 'GET');

        //Get the updated time from the db
        $last_updated_db = get_post_meta($post_id, '_vimeo_modified_time', true);

        //Get the timestamp of the API's modifed time
        $this->vimeo_timestamp = $this->get_timestamp($video_meta['body']['modified_time']);


        if (empty($last_updated_db) || $this->vimeo_timestamp > $last_updated_db || !has_post_thumbnail($post_id)) {
            //First time, so we'll add it in and set the update flag to true OR the vimeo object has been modified recently

            $this->do_meta_update = true;

            $this->update_video_meta($video_meta, $vimeo_id, $post_id);
        } else {
            //Don't need an update

            $this->do_meta_update = false;
        }
    }





    public function update_video_meta($video_meta, $vimeo_id, $post_id)
    {


        //Save the duration
        $duration = $video_meta['body']['duration'];

        $this->saved_meta['duration'] = \update_post_meta($post_id, '_video_duration', $duration);

        //Sve the description
        $title = $video_meta['body']['name'];
        $description = $video_meta['body']['description'];

        //Do an update post
        $this->saved_meta['post_data'] = wp_update_post([
            'ID' => $post_id,
            'post_title' => $title,
            'post_excerpt' => $description
        ], true);


        if (is_wp_error($this->saved_meta['post_data'])) {
            error_log($this->saved_meta['post_data']->get_error_message());
        }

        //Save the URL (in case it wasn't saved before)
        learndash_update_setting($post_id, 'lesson_video_enabled', 'on');
        learndash_update_setting($post_id, 'lesson_video_url', $video_meta['body']['link']);
        learndash_update_setting($post_id, 'lesson_video_shown', 'BEFORE');
        learndash_update_setting($post_id, 'lesson_video_hide_complete_button', 'on');


        //Save the Thumbnail
        $thumbnail_id = $this->save_new_video_thumbnail($video_meta, $post_id);

        if ($thumbnail_id) {

            error_log('Saving post thumbnail ' . $thumbnail_id . ' on post ' . $post_id);

            $this->saved_meta['post_thumbnail'] = \set_post_thumbnail($post_id, $thumbnail_id);
        }
    }




    public function maybe_file_exists($file_name)
    {

        global $wpdb;

        $image_src = \sanitize_file_name($file_name);

        $query = "SELECT * FROM {$wpdb->posts} WHERE guid LIKE '%$image_src'";

        $results = $wpdb->get_results($query);

        if (!empty($results)) {

            return $results[0];
        } else {

            return false;
        }
    }




    public function save_new_video_thumbnail($video_meta, $post_id)
    {


        error_log('Download URL?: ' . function_exists('download_url'));

        //$video_pictures = $this->client()->request( '/videos/' . $vimeo_id . '/pictures' );
        $video_pictures = $video_meta['body']['pictures']['sizes'];



        //Find the biggest one
        if (isset($video_pictures)) { //get the first one

            $biggest_size = end($video_pictures);
        }


        if (isset($biggest_size)) {

            //Download the file
            error_log('Attempting to download ' . $biggest_size['link']);

            $tmp = \download_url($biggest_size['link']);


            $file_array = [
                'name' => basename($biggest_size['link'] . '.jpg'),
                'tmp_name' => $tmp,
                'type' => 'image/jpeg'
            ];

            //The filename
            error_log($file_array['name']);

            //Let's see if that image already exists
            $maybe_existing_file = $this->maybe_file_exists($file_array['name']);

            error_log('Maybe existing: 
            ' . json_encode($maybe_existing_file));

            //If it does, we'll bypass the whole sideload thing
            if (!empty($maybe_existing_file)) {

                return $maybe_existing_file->ID;
            }


            /**
             * Check for download errors
             * if there are error unlink the temp file name
             */
            if (is_wp_error($tmp)) {
                @unlink($file_array['tmp_name']);
                error_log('Error saving file for attachment: ' . $file_array['tmp_name']);
                return $tmp;
            }

            /**
             * now we can actually use media_handle_sideload
             * we pass it the file array of the file to handle
             * and the post id of the post to attach it to
             * $post_id can be set to '0' to not attach it to any particular post
             */

            $description = 'Video poster for Lesson ID ' . $post_id;

            if (isset($this->current_post) && $this->current_post->ID == $post_id) {

                $description = $this->current_post->post_title . ' video poster';
            }


            $thumbnail_id = \media_handle_sideload($file_array, $post_id, $description);

            /**
             * We don't want to pass something to $id
             * if there were upload errors.
             * So this checks for errors
             */
            if (is_wp_error($thumbnail_id)) {

                @unlink($file_array['tmp_name']);
                error_log('Error saving attachment id: ' . $thumbnail_id->get_error_message());
                return false;
            } else {

                return $thumbnail_id;
            }
        }
    }




    /**
     *  Initiate Save Meta
     * 
     * 
     */

    public function save_vimeo_meta($post_id, $post, $update)
    {

        /**
         * 8/29/22
         * 
         * Not sure what's going on here, but this function happens to
         * not be loaded when the save_post hook runs. When the sfwd-lessons posts
         * save, for some reason save_post is getting called twice,
         * and the second time seems to be when all of WP is loaded.
         * 
         */
        if (!function_exists('\download_url')) {

            error_log('The \\download_url function doesn\'t exist');

            return;
        }


        $saving_post_value =  isset($this->saving_post) ? $this->saving_post : 'API';

        //:Log
        error_log('Saving post? ' . $post_id . ': ' . $saving_post_value . ' update? ' . $update);
        error_log('Function exists download_url: ' . function_exists('download_url'));

        $this->current_post = $post;

        $vimeo_id = $this->get_vimeo_id($post_id);


        //Log
        error_log('Vimeo ID: ' . $vimeo_id);



        if (!empty($vimeo_id)) {

            remove_action('save_post_sfwd-lessons', [$this, 'save_vimeo_meta'], 51, 3);

            $this->maybe_update_video_meta($vimeo_id, $post_id);


            if ($this->do_meta_update) {

                $save_ok = $this->saved_meta['post_thumbnail'];

                /* error_log( json_encode( $this->saved_meta ) );

                //Make sure all the meta saved before we do the time
                foreach( $this->saved_meta as $meta ) {

                    if ( $meta == false ) {
                        $save_ok = $meta;
                    }

                    break;

                }*/

                if ($save_ok) {

                    \update_post_meta($post_id, '_vimeo_modified_time', $this->vimeo_timestamp);
                } else {

                    \update_post_meta($post_id, '_vimeo_modified_time', false);
                }
            }
        }
    }






    public function bulk_add()
    {

        return;

        if (isset($_GET['lessons_bulk_add'])) {

            //Get all the lessons
            $posts = get_posts([
                'post_type' => 'sfwd-lessons',
                'posts_per_page' => -1,

            ]);
        }
    }






    public static function get_instance()
    {

        if (self::$instance == null) {

            self::$instance = new self;
        }

        return self::$instance;
    }
}
