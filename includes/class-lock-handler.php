<?php
/**
 * ロックハンドラークラス
 *
 * @package Kashiwazaki_SEO_Lock_Modified_Date
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class KSEO_Lock_Handler
 *
 * 保存時の更新日ロックと、投稿一覧の一括操作を管理
 */
class KSEO_Lock_Handler {

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 更新日ロック（優先度99で他のフィルターより後に実行）
        add_filter('wp_insert_post_data', array($this, 'lock_modified_date'), 99, 4);
        add_filter('wp_insert_attachment_data', array($this, 'lock_modified_date'), 99, 4);

        // 新規作成時にロック状態を記録する（添付ファイルは wp_insert_post アクションに到達しないため別途）
        add_action('wp_insert_post', array($this, 'set_default_lock'), 10, 3);
        add_action('add_attachment', array($this, 'set_default_lock_for_attachment'));

        // 予約投稿が自動公開されたとき、更新日が公開日より前に残らないようにする
        add_action('transition_post_status', array($this, 'fix_scheduled_publish_dates'), 0, 3);

        // 一括操作の登録
        add_action('admin_init', array($this, 'register_bulk_actions'));

        // 一括操作の結果通知
        add_action('admin_notices', array($this, 'bulk_action_admin_notice'));
    }

    /**
     * 指定した更新日を書き込み、WordPress の更新フックを発火させる
     *
     * 本文などには一切触れず、post_modified / post_modified_gmt の 2 列だけを更新する
     * （同じ投稿への並行した保存内容を上書きしないため）。フックは、通常の投稿の更新
     * （wp_insert_post の更新経路）と同じ順序で発火する。添付ファイルは添付ファイルの更新と同じフックを発火する。
     *
     * @param int    $post_id    投稿ID
     * @param string $local      更新日（サイトのタイムゾーン）
     * @param string $gmt        更新日（GMT）
     * @param bool   $set_locked 書き込み成功後にロックを有効にするか
     * @return array{ok:bool, error:string}
     */
    public static function write_modified_date($post_id, $local, $gmt, $set_locked = false) {
        global $wpdb;

        $post_id = (int) $post_id;
        $post_before = get_post($post_id);
        if (!$post_before) {
            return array('ok' => false, 'error' => '投稿が見つかりません。');
        }

        $data = array('post_modified' => $local, 'post_modified_gmt' => $gmt);

        // フックには通常の更新と同じ形（投稿の全列）で渡す。実際に書き換えるのは更新日の 2 列だけ
        $hook_data = array_merge(get_post($post_id, ARRAY_A), $data);
        unset($hook_data['ancestors'], $hook_data['page_template'], $hook_data['post_category'], $hook_data['tags_input']);
        /** This action is documented in wp-includes/post.php */
        do_action('pre_post_update', $post_id, $hook_data);

        $updated = $wpdb->update($wpdb->posts, $data, array('ID' => $post_id), array('%s', '%s'), array('%d'));
        clean_post_cache($post_id);

        $saved = $wpdb->get_row($wpdb->prepare(
            "SELECT post_modified, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d",
            $post_id
        ));
        if (false === $updated || !$saved || $saved->post_modified !== $local || $saved->post_modified_gmt !== $gmt) {
            return array('ok' => false, 'error' => 'データベースに書き込めませんでした。');
        }

        // 手動で指定した日付を保持するため、フック内で同じ投稿が保存されても変わらないよう先にロックする
        if ($set_locked) {
            KSEO_Lock_State::set_state($post_id, true);
        }

        $post_after = get_post($post_id);

        if ('attachment' === $post_after->post_type) {
            /** This action is documented in wp-includes/post.php */
            do_action('edit_attachment', $post_id);
            /** This action is documented in wp-includes/post.php */
            do_action('attachment_updated', $post_id, $post_after, $post_before);
        } else {
            wp_transition_post_status($post_after->post_status, $post_before->post_status, $post_after);
            /** This action is documented in wp-includes/post.php */
            do_action("edit_post_{$post_after->post_type}", $post_id, $post_after);
            /** This action is documented in wp-includes/post.php */
            do_action('edit_post', $post_id, $post_after);
            // core と同じく、edit_post の後に取り直した値を post_updated に渡す
            $post_after = get_post($post_id);
            /** This action is documented in wp-includes/post.php */
            do_action('post_updated', $post_id, $post_after, $post_before);
            /** This action is documented in wp-includes/post.php */
            do_action("save_post_{$post_after->post_type}", $post_id, $post_after, true);
            /** This action is documented in wp-includes/post.php */
            do_action('save_post', $post_id, $post_after, true);
            /** This action is documented in wp-includes/post.php */
            do_action('wp_insert_post', $post_id, $post_after, true);
            wp_after_insert_post($post_after, true, $post_before);
        }

        clean_post_cache($post_id);
        return array('ok' => true, 'error' => '');
    }

    /**
     * 公開済みだが公開日時が未来のまま残っている行か（wp_insert_post が future に戻すため変更対象外）
     *
     * @param WP_Post $post 投稿
     * @return bool
     */
    public static function has_future_publish_date($post) {
        if (!($post instanceof WP_Post) || 'attachment' === $post->post_type || 'publish' !== $post->post_status) {
            return false;
        }
        $gmt = KSEO_Lock_State::is_valid_date($post->post_date_gmt) ? $post->post_date_gmt : get_gmt_from_date($post->post_date);
        return strtotime($gmt . ' UTC') - time() >= MINUTE_IN_SECONDS;
    }

    /**
     * 一括操作の登録
     */
    public function register_bulk_actions() {
        foreach (KSEO_Lock_State::enabled_post_types() as $post_type) {
            add_filter("bulk_actions-edit-{$post_type}", array($this, 'add_bulk_actions'));
            add_filter("handle_bulk_actions-edit-{$post_type}", array($this, 'handle_bulk_actions'), 10, 3);
        }
    }

    /**
     * 一括操作メニューに項目を追加
     *
     * @param array $bulk_actions 一括操作の配列
     * @return array 修正された一括操作の配列
     */
    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['kseo_bulk_lock'] = '更新日をロック';
        $bulk_actions['kseo_bulk_unlock'] = '更新日のロックを解除';
        return $bulk_actions;
    }

    /**
     * 一括操作の処理
     *
     * @param string $redirect_to リダイレクト先URL
     * @param string $doaction    実行するアクション
     * @param array  $post_ids    投稿IDの配列
     * @return string リダイレクト先URL
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'kseo_bulk_lock' && $doaction !== 'kseo_bulk_unlock') {
            return $redirect_to;
        }

        $locked = ($doaction === 'kseo_bulk_lock');
        $count = 0;

        foreach ((array) $post_ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id > 0 && current_user_can('edit_post', $post_id)) {
                KSEO_Lock_State::set_state($post_id, $locked);
                $count++;
            }
        }

        return add_query_arg(array(
            'kseo_bulk_action' => $doaction,
            'kseo_bulk_count' => $count,
        ), $redirect_to);
    }

    /**
     * 一括操作の結果通知
     */
    public function bulk_action_admin_notice() {
        if (!isset($_REQUEST['kseo_bulk_action']) || !isset($_REQUEST['kseo_bulk_count'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_REQUEST['kseo_bulk_action']));
        $count = intval($_REQUEST['kseo_bulk_count']);

        if ($action === 'kseo_bulk_lock') {
            $message = sprintf('%d件の投稿の更新日をロックしました。', $count);
        } elseif ($action === 'kseo_bulk_unlock') {
            $message = sprintf('%d件の投稿の更新日ロックを解除しました。', $count);
        } else {
            return;
        }

        printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
    }

    /**
     * 保存時に更新日をロックする
     *
     * ロック状態は保存済みの状態だけを参照し、フォームの送信値は使わない。
     *
     * @param array $data                投稿データ
     * @param array $postarr             投稿配列
     * @param array $unsanitized_postarr 未サニタイズの投稿配列
     * @param bool  $update              既存投稿の更新か
     * @return array 修正された投稿データ
     */
    public function lock_modified_date($data, $postarr, $unsanitized_postarr = array(), $update = true) {
        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if ($post_id <= 0 || !$update) {
            return $data;
        }

        if (isset($data['post_type']) && 'revision' === $data['post_type']) {
            return $data;
        }

        $original_post = get_post($post_id);
        if (!$original_post || 'revision' === $original_post->post_type) {
            return $data;
        }

        if (!KSEO_Lock_State::is_lock_effective($original_post)) {
            return $data;
        }

        $post_date = isset($data['post_date']) ? $data['post_date'] : $original_post->post_date;
        $post_date_gmt = isset($data['post_date_gmt']) ? $data['post_date_gmt'] : $original_post->post_date_gmt;

        list($local, $gmt) = KSEO_Lock_State::normalize_dates(
            $original_post->post_modified,
            $original_post->post_modified_gmt,
            $post_date,
            $post_date_gmt
        );

        $data['post_modified'] = $local;
        $data['post_modified_gmt'] = $gmt;

        return $data;
    }

    /**
     * 添付ファイルの作成時にロック状態を記録する
     *
     * @param int $post_id 添付ファイルID
     */
    public function set_default_lock_for_attachment($post_id) {
        $post = get_post($post_id);
        if ($post) {
            $this->set_default_lock($post_id, $post, false);
        }
    }

    /**
     * 予約投稿の公開時に、更新日を公開日以上にそろえる
     *
     * wp_publish_post() は post_status だけを更新するため、wp_insert_post_data を通らない。
     *
     * @param string  $new_status 新しい状態
     * @param string  $old_status 以前の状態
     * @param WP_Post $post       投稿
     */
    public function fix_scheduled_publish_dates($new_status, $old_status, $post) {
        if ('publish' !== $new_status || 'future' !== $old_status || !($post instanceof WP_Post)) {
            return;
        }

        // ロック中の投稿だけを補正する（ロック OFF の投稿は WordPress 標準のまま）
        if (!KSEO_Lock_State::is_enabled_type($post->post_type) || !KSEO_Lock_State::stored_state($post->ID)) {
            return;
        }

        $current = get_post($post->ID);
        if (!$current) {
            return;
        }

        list($local, $gmt) = KSEO_Lock_State::normalize_dates(
            $current->post_modified,
            $current->post_modified_gmt,
            $current->post_date,
            $current->post_date_gmt
        );

        if ($local === $current->post_modified && $gmt === $current->post_modified_gmt) {
            return;
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            array('post_modified' => $local, 'post_modified_gmt' => $gmt),
            array('ID' => $post->ID),
            array('%s', '%s'),
            array('%d')
        );
        clean_post_cache($post->ID);

        // 後続のフックが同じオブジェクトを使うため、値もそろえる
        $post->post_modified = $local;
        $post->post_modified_gmt = $gmt;
    }

    /**
     * 新規作成時にロック状態を記録する
     *
     * @param int     $post_id 投稿ID
     * @param WP_Post $post    投稿オブジェクト
     * @param bool    $update  更新かどうか
     */
    public function set_default_lock($post_id, $post, $update) {
        if ($update || !($post instanceof WP_Post)) {
            return;
        }

        if ('revision' === $post->post_type || wp_is_post_revision($post_id)) {
            return;
        }

        if (!KSEO_Lock_State::is_enabled_type($post->post_type)) {
            return;
        }

        if (metadata_exists('post', $post_id, KSEO_Lock_State::META_KEY)) {
            return;
        }

        KSEO_Lock_State::set_state($post_id, KSEO_Lock_State::default_locked());
    }
}
