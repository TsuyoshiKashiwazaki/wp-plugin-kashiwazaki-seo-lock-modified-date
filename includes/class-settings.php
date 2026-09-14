<?php
/**
 * 設定画面クラス
 *
 * @package Kashiwazaki_SEO_Lock_Modified_Date
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class KSEO_Settings
 *
 * 設定画面の表示と保存、一括操作、日付の不整合修復を管理
 */
class KSEO_Settings {

    /**
     * 修復処理 1 回あたりの件数
     */
    const REPAIR_BATCH = 10;

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . KSEO_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));

        add_action('wp_ajax_kseo_bulk_lock_all', array($this, 'ajax_bulk_lock_all'));
        add_action('wp_ajax_kseo_repair_dates', array($this, 'ajax_repair_dates'));
    }

    /**
     * 設定ページの追加
     */
    public function add_settings_page() {
        add_menu_page(
            'Kashiwazaki SEO Lock Modified Date 設定',
            'Kashiwazaki SEO Lock Modified Date',
            'manage_options',
            'kseo-lock-modified-date',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            81
        );
    }

    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting('kseo_lock_modified_date_group', KSEO_Lock_State::OPTION_POST_TYPES, array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_post_types'),
            'default' => array('post', 'page'),
        ));
        register_setting('kseo_lock_modified_date_group', KSEO_Lock_State::OPTION_DEFAULT_LOCKED, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_default_locked'),
            'default' => '1',
        ));
    }

    /**
     * 投稿タイプ設定のサニタイズ（常に配列）
     *
     * @param mixed $value 入力値
     * @return string[]
     */
    public function sanitize_post_types($value) {
        // 登録元プラグインの一時停止などで未登録になっている保存済みタイプも消さない
        $allowed = array_merge(array_keys(get_post_types(array('public' => true))), $this->unregistered_saved_types());
        $clean = array();
        foreach ((array) $value as $type) {
            if (!is_string($type)) {
                continue;
            }
            $type = sanitize_key($type);
            if (in_array($type, $allowed, true)) {
                $clean[] = $type;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * 保存済みだが、現在の公開投稿タイプ一覧に無い投稿タイプ（未登録・非公開化を問わない）
     *
     * @return string[]
     */
    private function unregistered_saved_types() {
        $saved = get_option(KSEO_Lock_State::OPTION_POST_TYPES, null);
        if (!is_array($saved)) {
            return array();
        }
        $public = array_keys(get_post_types(array('public' => true)));
        $others = array();
        foreach ($saved as $type) {
            if (is_string($type) && $type !== '') {
                $type = sanitize_key($type);
                if (!in_array($type, $public, true)) {
                    $others[] = $type;
                }
            }
        }
        return array_values(array_unique($others));
    }

    /**
     * デフォルトロック設定のサニタイズ（'1' / '0'）
     *
     * @param mixed $value 入力値
     * @return string
     */
    public function sanitize_default_locked($value) {
        return ('1' === (string) $value) ? '1' : '0';
    }

    /**
     * 日付の不整合がある投稿を数える
     *
     * @return array{gmt_zero:int, before_publish:int}
     */
    private function count_inconsistent_dates() {
        global $wpdb;

        $types = KSEO_Lock_State::enabled_post_types();
        if (empty($types)) {
            return array('gmt_zero' => 0, 'before_publish' => 0);
        }

        list($where, $params) = $this->published_where($types);

        $gmt_zero = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where} AND post_modified_gmt = %s",
            array_merge($params, array(KSEO_Lock_State::ZERO_DATE))
        ));
        $before_publish = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where} AND post_modified < post_date"
            . " AND ( ( post_date_gmt <> %s AND post_date_gmt <= %s ) OR ( post_date_gmt = %s AND post_date <= %s ) )",
            array_merge($params, array(KSEO_Lock_State::ZERO_DATE, gmdate('Y-m-d H:i:s'), KSEO_Lock_State::ZERO_DATE, current_time('mysql')))
        ));

        return array('gmt_zero' => $gmt_zero, 'before_publish' => $before_publish);
    }

    /**
     * 有効な投稿タイプの公開済み投稿を表す WHERE 句
     *
     * @param string[] $types 投稿タイプ
     * @return array array( WHERE 句, パラメータ )
     */
    private function published_where($types) {
        $type_placeholders = implode(',', array_fill(0, count($types), '%s'));
        // 公開済みでも公開日時が 60 秒以上未来の行は対象外（GMT が未記録ならサイトのタイムゾーンの日時で判定）
        $future_gmt = gmdate('Y-m-d H:i:s', time() + MINUTE_IN_SECONDS);
        $future_local = wp_date('Y-m-d H:i:s', time() + MINUTE_IN_SECONDS);
        $where = "( ( post_type IN ({$type_placeholders}) AND post_type <> 'attachment' AND post_status IN ('publish','private')"
            . " AND NOT ( post_status = 'publish' AND ( ( post_date_gmt <> '0000-00-00 00:00:00' AND post_date_gmt >= %s )"
            . " OR ( post_date_gmt = '0000-00-00 00:00:00' AND post_date >= %s ) ) ) )"
            . " OR ( post_type = 'attachment' AND %s = '1' AND post_status IN ('inherit','private') ) )";
        $params = array_merge($types, array($future_gmt, $future_local, in_array('attachment', $types, true) ? '1' : '0'));
        return array($where, $params);
    }

    /**
     * 設定ページのレンダリング
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $post_types = get_post_types(array('public' => true), 'objects');
        $selected_post_types = KSEO_Lock_State::enabled_post_types();
        $default_locked = KSEO_Lock_State::default_locked();
        $counts = $this->count_inconsistent_dates();
        ?>
        <div class="wrap">
            <h1>Kashiwazaki SEO Lock Modified Date 設定</h1>
            <form method="post" action="options.php">
                <?php settings_fields('kseo_lock_modified_date_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">デフォルトでロックする</th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(KSEO_Lock_State::OPTION_DEFAULT_LOCKED); ?>" value="0">
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr(KSEO_Lock_State::OPTION_DEFAULT_LOCKED); ?>"
                                       value="1"
                                       <?php checked($default_locked); ?>>
                                新規投稿作成時に「更新日をロックする」をデフォルトでONにする
                            </label>
                            <p class="description">
                                チェックを外すと、新規投稿作成時にロックがOFFの状態から始まります。ロック状態が記録されていない既存の投稿にも、この設定が使われます。
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ロック機能を使う投稿タイプ</th>
                        <td>
                            <?php foreach ($post_types as $post_type) : ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr(KSEO_Lock_State::OPTION_POST_TYPES); ?>[]"
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $selected_post_types, true)); ?>>
                                    <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                                </label>
                            <?php endforeach; ?>
                            <?php foreach ($this->unregistered_saved_types() as $type_name) : ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr(KSEO_Lock_State::OPTION_POST_TYPES); ?>[]"
                                           value="<?php echo esc_attr($type_name); ?>"
                                           checked>
                                    <?php echo esc_html($type_name); ?><?php echo post_type_exists($type_name) ? '（非公開）' : '（未登録）'; ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                チェックした投稿タイプの編集画面にメタボックスが表示され、更新日のロックが有効になります。<br>
                                チェックを外した投稿タイプでは、ロック設定が残っていても更新日は通常どおり変わります（再度チェックすると元の設定で有効に戻ります）。
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('設定を保存'); ?>
            </form>

            <hr style="margin: 30px 0;">

            <h2>一括操作</h2>
            <p class="description">対象の投稿タイプに含まれるすべての投稿に対して一括でロック状態を変更します。</p>

            <table class="form-table">
                <tr>
                    <th scope="row">全投稿を一括処理</th>
                    <td>
                        <button type="button" id="kseo_bulk_lock_all" class="button button-primary">すべてロック</button>
                        <button type="button" id="kseo_bulk_unlock_all" class="button">すべてロック解除</button>
                        <span id="kseo_bulk_spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                        <p id="kseo_bulk_message" style="margin-top: 10px;"></p>
                    </td>
                </tr>
            </table>

            <hr style="margin: 30px 0;">

            <h2>日付の不整合を確認・修復</h2>
            <p class="description">
                対象の投稿タイプの公開済み投稿のうち、更新日の記録が壊れている投稿を修復します（ロック OFF の投稿も含みます）。自動では書き換えません。修復しても本文やロックの状態は変わりません。修復した投稿ごとに、投稿を更新したときと同じ処理（サイトマップやキャッシュ系プラグインへの更新通知など）が実行されます。
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row">更新日（GMT）が未設定</th>
                    <td><span id="kseo_repair_gmt_zero"><?php echo esc_html(number_format_i18n($counts['gmt_zero'])); ?></span> 件
                        <p class="description">サイトのタイムゾーンの更新日から GMT の値を計算して保存します（表示される更新日は変わりません）。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">更新日が公開日より前</th>
                    <td><span id="kseo_repair_before_publish"><?php echo esc_html(number_format_i18n($counts['before_publish'])); ?></span> 件
                        <p class="description">更新日を公開日時に合わせます。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">修復</th>
                    <td>
                        <button type="button" id="kseo_repair_dates" class="button" <?php disabled(0 === $counts['gmt_zero'] + $counts['before_publish']); ?>>修復する</button>
                        <span id="kseo_repair_spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                        <p id="kseo_repair_message" style="margin-top: 10px;"></p>
                    </td>
                </tr>
            </table>

            <script>
            jQuery(function($) {
                var nonces = {
                    bulk: <?php echo wp_json_encode(wp_create_nonce('kseo_bulk_lock_all_nonce')); ?>,
                    repair: <?php echo wp_json_encode(wp_create_nonce('kseo_repair_dates_nonce')); ?>
                };

                function bulkAction(action) {
                    var $spinner = $('#kseo_bulk_spinner');
                    var $message = $('#kseo_bulk_message');
                    var $buttons = $('#kseo_bulk_lock_all, #kseo_bulk_unlock_all');

                    var confirmMsg = action === 'lock'
                        ? '対象の投稿タイプに含まれるすべての投稿をロックします。よろしいですか？'
                        : '対象の投稿タイプに含まれるすべての投稿のロックを解除します。よろしいですか？';

                    if (!confirm(confirmMsg)) {
                        return;
                    }

                    $spinner.addClass('is-active');
                    $buttons.prop('disabled', true);
                    $message.text('処理中...').css('color', '#666');

                    $.post(ajaxurl, { action: 'kseo_bulk_lock_all', lock_action: action, nonce: nonces.bulk })
                        .done(function(response) {
                            var ok = response && response.success;
                            $message.text(response && response.data ? response.data.message : 'エラーが発生しました。')
                                .css('color', ok ? '#46b450' : '#dc3232');
                        })
                        .fail(function() {
                            $message.text('エラーが発生しました。').css('color', '#dc3232');
                        })
                        .always(function() {
                            $spinner.removeClass('is-active');
                            $buttons.prop('disabled', false);
                        });
                }

                $('#kseo_bulk_lock_all').on('click', function() { bulkAction('lock'); });
                $('#kseo_bulk_unlock_all').on('click', function() { bulkAction('unlock'); });

                $('#kseo_repair_dates').on('click', function() {
                    var $button = $(this);
                    var $spinner = $('#kseo_repair_spinner');
                    var $message = $('#kseo_repair_message');
                    var repaired = 0;
                    var afterId = 0;
                    var failed = 0;

                    if (!confirm('日付の不整合がある投稿の更新日を修復します。よろしいですか？')) {
                        return;
                    }

                    $button.prop('disabled', true);
                    $spinner.addClass('is-active');

                    function step() {
                        $.ajax({ url: ajaxurl, type: 'POST', dataType: 'json', timeout: 60000, data: { action: 'kseo_repair_dates', nonce: nonces.repair, after_id: afterId } })
                            .done(function(response) {
                                if (!response || !response.success) {
                                    $message.text(response && response.data ? response.data.message : 'エラーが発生しました。').css('color', '#dc3232');
                                    $spinner.removeClass('is-active');
                                    $button.prop('disabled', false);
                                    return;
                                }
                                repaired += response.data.repaired;
                                $('#kseo_repair_gmt_zero').text(response.data.counts.gmt_zero);
                                $('#kseo_repair_before_publish').text(response.data.counts.before_publish);
                                failed += response.data.failed;
                                $message.text(repaired + '件を修復しました。' + (failed > 0 ? '（' + failed + '件は書き込めませんでした）' : '')).css('color', failed > 0 ? '#dc3232' : '#46b450');
                                afterId = response.data.last_id;
                                if (response.data.has_more) {
                                    step();
                                } else {
                                    $spinner.removeClass('is-active');
                                    $button.prop('disabled', response.data.remaining === 0);
                                }
                            })
                            .fail(function() {
                                $message.text(repaired + '件を修復した時点で通信エラーまたはタイムアウトになりました。もう一度「修復する」を押すと続きから処理します。').css('color', '#dc3232');
                                $spinner.removeClass('is-active');
                                $button.prop('disabled', false);
                            });
                    }

                    step();
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * AJAX: 全投稿の一括ロック/解除
     */
    public function ajax_bulk_lock_all() {
        if (!check_ajax_referer('kseo_bulk_lock_all_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => '不正なリクエストです。'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません。'));
        }

        $lock_action = isset($_POST['lock_action']) ? sanitize_text_field(wp_unslash($_POST['lock_action'])) : '';
        if (!in_array($lock_action, array('lock', 'unlock'), true)) {
            wp_send_json_error(array('message' => '無効なアクションです。'));
        }

        $types = KSEO_Lock_State::enabled_post_types();
        if (empty($types)) {
            wp_send_json_error(array('message' => '対象の投稿タイプが設定されていません。'));
        }

        $posts = get_posts(array(
            'post_type' => $types,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        $locked = ($lock_action === 'lock');
        foreach ($posts as $post_id) {
            KSEO_Lock_State::set_state($post_id, $locked);
        }

        $action_text = $locked ? 'ロック' : 'ロック解除';
        wp_send_json_success(array(
            'message' => sprintf('%d件の投稿を%sしました。', count($posts), $action_text),
        ));
    }

    /**
     * AJAX: 日付の不整合を修復（1 回あたり REPAIR_BATCH 件）
     */
    public function ajax_repair_dates() {
        global $wpdb;

        if (!check_ajax_referer('kseo_repair_dates_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => '不正なリクエストです。ページを再読み込みしてください。'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません。'));
        }

        $types = KSEO_Lock_State::enabled_post_types();
        if (empty($types)) {
            wp_send_json_success(array('repaired' => 0, 'failed' => 0, 'has_more' => false, 'last_id' => 0, 'remaining' => 0, 'counts' => array('gmt_zero' => 0, 'before_publish' => 0)));
        }

        $after_id = isset($_POST['after_id']) ? absint($_POST['after_id']) : 0;
        $now_gmt = gmdate('Y-m-d H:i:s');

        list($where, $params) = $this->published_where($types);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}"
            . " WHERE {$where} AND ID > %d"
            . " AND ( post_modified_gmt = %s OR ( post_modified < post_date"
            . " AND ( ( post_date_gmt <> %s AND post_date_gmt <= %s ) OR ( post_date_gmt = %s AND post_date <= %s ) ) ) )"
            . " ORDER BY ID ASC LIMIT %d",
            array_merge($params, array($after_id, KSEO_Lock_State::ZERO_DATE, KSEO_Lock_State::ZERO_DATE, $now_gmt, KSEO_Lock_State::ZERO_DATE, current_time('mysql'), self::REPAIR_BATCH))
        ));
        $last_id = $after_id;

        $repaired = 0;
        $failed = 0;
        foreach ((array) $rows as $row) {
            $post_id = (int) $row->ID;
            $last_id = max($last_id, $post_id);

            $post = get_post($post_id);
            if (!$post || KSEO_Lock_Handler::has_future_publish_date($post)) {
                continue;
            }

            list($local, $gmt) = KSEO_Lock_State::normalize_dates($post->post_modified, $post->post_modified_gmt, $post->post_date, $post->post_date_gmt);
            if ($local === $post->post_modified && $gmt === $post->post_modified_gmt) {
                continue;
            }

            // 更新日の 2 列だけを書き込み、更新フックを発火する。ロック状態は読みも書きもしない
            $write = KSEO_Lock_Handler::write_modified_date($post_id, $local, $gmt, false);
            if ($write['ok']) {
                $repaired++;
            } else {
                $failed++;
            }
        }

        $counts = $this->count_inconsistent_dates();
        wp_send_json_success(array(
            'repaired' => $repaired,
            'failed' => $failed,
            'has_more' => count((array) $rows) === self::REPAIR_BATCH,
            'last_id' => $last_id,
            'remaining' => $counts['gmt_zero'] + $counts['before_publish'],
            'counts' => $counts,
        ));
    }

    /**
     * プラグイン一覧に設定リンクを追加
     *
     * @param array $links 現在のリンク配列
     * @return array 修正されたリンク配列
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=kseo-lock-modified-date')) . '">設定</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
