<?php
/**
 * Plugin Name: Teams Webhook on Review & Publish
 * Plugin URI:  https://github.com/raspi0124/teams-notify-on-review-for-wordpress
 * Description: Sends a webhook to Microsoft Teams when a post is awaiting review.
 * Version:     1.0.0
 * Author:      raspi0124
 * Author URI:  https://raspi0124.dev/
 */

// Register the settings
function twor_register_settings() {
    add_option('twor_review_webhook_url', '');
    add_option('twor_publish_webhook_url', ''); 
    register_setting('twor_options_group', 'twor_review_webhook_url');
    register_setting('twor_options_group', 'twor_publish_webhook_url');
}
add_action( 'admin_init', 'twor_register_settings' );

// Register the options page under the Settings menu
function twor_register_options_page() {
    add_options_page('Teams Webhook on Review & Publish', 'Teams Webhook on Review & Publish', 'manage_options', 'twor', 'twor_options_page');
}
add_action('admin_menu', 'twor_register_options_page');

//　設定ページ
function twor_options_page() {
    ?>
        <div>
            <h2>Teams Webhook on Review & Publish Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('twor_options_group'); ?>
                <table>
                    <tr valign="top">
                        <th scope="row"><label for="twor_review_webhook_url">Webhook URL for Review:</label></th>
                        <td><input type="text" id="twor_review_webhook_url" name="twor_review_webhook_url" value="<?php echo get_option('twor_review_webhook_url'); ?>" size="75"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="twor_publish_webhook_url">Webhook URL for Publish:</label></th>
                        <td><input type="text" id="twor_publish_webhook_url" name="twor_publish_webhook_url" value="<?php echo get_option('twor_publish_webhook_url'); ?>" size="75"/></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }

// 実際にWebhookを送るやつ
function twor_send_teams_webhook_notification( $new_status, $old_status, $post ) {
    if ( $new_status == 'pending' && $old_status != 'pending' ) {
        $title = 'レビュー待ち投稿のお知らせ';
        $webhook_url = get_option('twor_review_webhook_url');
        $message_text = "「{$post->post_title}」 (作成: " . get_the_author_meta('display_name', $post->post_author) . ") がレビュー待ちになりました!";
        $link = get_edit_post_link($post->ID);
    } elseif ($new_status == 'publish' && $old_status != 'publish') {
        $webhook_url = get_option('twor_publish_webhook_url');
        $title = '新規投稿のお知らせ';
        $message_text = "「{$post->post_title}」 (作成: " . get_the_author_meta('display_name', $post->post_author) . ") が公開されました!";
        $link = get_permalink($post->ID);
    } else {
        return;
    }

    if ( !empty($webhook_url) ) {
        $message = json_encode([
            "@type" => "MessageCard",
            "@context" => "http://schema.org/extensions",
            "summary" => "WordPress Post Notification",
            "themeColor" => "0072C6",
            "title" => $title,
            "sections" => [
                [
                    "activityTitle" => $message_text,
                    "activitySubtitle" => $link,
                ]
            ]
        ]);

        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        curl_exec($ch);
        curl_close($ch);
    }
}
add_action('transition_post_status', 'twor_send_teams_webhook_notification', 10, 3);