<?php
/**
 * Job Killer Feeds Store
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle feed storage and retrieval
 */
class JK_Feeds_Store {
    
    /**
     * Insert new feed
     */
    public static function insert($data) {
        // Validate required fields
        if (empty($data['name']) || empty($data['provider'])) {
            return new WP_Error('missing_data', __('Feed name and provider are required', 'job-killer'));
        }
        
        // Create feed post
        $post_data = array(
            'post_type' => 'jk_feed',
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($data['name']),
            'post_content' => '',
            'meta_input' => array(
                '_jk_feed_provider' => sanitize_text_field($data['provider']),
                '_jk_feed_url' => esc_url_raw($data['url'] ?? ''),
                '_jk_feed_args' => wp_json_encode($data['args'] ?? array()),
                '_jk_feed_auth' => wp_json_encode($data['auth'] ?? array()),
                '_jk_feed_active' => !empty($data['active']) ? 1 : 0,
                '_jk_feed_created_at' => current_time('mysql'),
                '_jk_feed_updated_at' => current_time('mysql')
            )
        );
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        jk_log('feed_inserted', array(
            'feed_id' => $post_id,
            'name' => $data['name'],
            'provider' => $data['provider']
        ));
        
        return $post_id;
    }
    
    /**
     * Get feed by ID
     */
    public static function get($id) {
        $post = get_post($id);
        
        if (!$post || $post->post_type !== 'jk_feed') {
            return null;
        }
        
        return array(
            'id' => $post->ID,
            'name' => $post->post_title,
            'provider' => get_post_meta($post->ID, '_jk_feed_provider', true),
            'url' => get_post_meta($post->ID, '_jk_feed_url', true),
            'args' => json_decode(get_post_meta($post->ID, '_jk_feed_args', true), true) ?: array(),
            'auth' => json_decode(get_post_meta($post->ID, '_jk_feed_auth', true), true) ?: array(),
            'active' => get_post_meta($post->ID, '_jk_feed_active', true),
            'created_at' => get_post_meta($post->ID, '_jk_feed_created_at', true),
            'updated_at' => get_post_meta($post->ID, '_jk_feed_updated_at', true)
        );
    }
    
    /**
     * Update feed
     */
    public static function update($id, $data) {
        $post = get_post($id);
        
        if (!$post || $post->post_type !== 'jk_feed') {
            return new WP_Error('feed_not_found', __('Feed not found', 'job-killer'));
        }
        
        // Update post
        $post_data = array(
            'ID' => $id,
            'post_title' => sanitize_text_field($data['name'])
        );
        
        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update meta
        update_post_meta($id, '_jk_feed_provider', sanitize_text_field($data['provider']));
        update_post_meta($id, '_jk_feed_url', esc_url_raw($data['url'] ?? ''));
        update_post_meta($id, '_jk_feed_args', wp_json_encode($data['args'] ?? array()));
        update_post_meta($id, '_jk_feed_auth', wp_json_encode($data['auth'] ?? array()));
        update_post_meta($id, '_jk_feed_active', !empty($data['active']) ? 1 : 0);
        update_post_meta($id, '_jk_feed_updated_at', current_time('mysql'));
        
        jk_log('feed_updated', array(
            'feed_id' => $id,
            'name' => $data['name']
        ));
        
        return $id;
    }
    
    /**
     * Delete feed
     */
    public static function delete($id) {
        $post = get_post($id);
        
        if (!$post || $post->post_type !== 'jk_feed') {
            return new WP_Error('feed_not_found', __('Feed not found', 'job-killer'));
        }
        
        $result = wp_delete_post($id, true);
        
        if ($result) {
            jk_log('feed_deleted', array(
                'feed_id' => $id,
                'name' => $post->post_title
            ));
        }
        
        return $result;
    }
    
    /**
     * Get all active feeds
     */
    public static function get_active_feeds() {
        $feeds = get_posts(array(
            'post_type' => 'jk_feed',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_jk_feed_active',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        $active_feeds = array();
        foreach ($feeds as $feed) {
            $active_feeds[] = self::get($feed->ID);
        }
        
        return $active_feeds;
    }
    
    /**
     * Get all feeds
     */
    public static function get_all_feeds() {
        $feeds = get_posts(array(
            'post_type' => 'jk_feed',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));
        
        $all_feeds = array();
        foreach ($feeds as $feed) {
            $all_feeds[] = self::get($feed->ID);
        }
        
        return $all_feeds;
    }
}

/**
 * Helper functions
 */
function jk_feeds_insert($data) {
    return JK_Feeds_Store::insert($data);
}

function jk_feeds_get($id) {
    return JK_Feeds_Store::get($id);
}

function jk_feeds_update($id, $data) {
    return JK_Feeds_Store::update($id, $data);
}

function jk_feeds_delete($id) {
    return JK_Feeds_Store::delete($id);
}