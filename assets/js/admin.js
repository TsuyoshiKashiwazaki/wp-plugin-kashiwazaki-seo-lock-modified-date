/**
 * Kashiwazaki SEO Lock Modified Date - 管理画面用JavaScript
 *
 * @package Kashiwazaki_SEO_Lock_Modified_Date
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * 「公開日と同じにする」ボタン
         */
        $('#kseo_set_to_post_date').on('click', function() {
            var postDate = $(this).data('post-date');
            $('#kseo_manual_modified_date').val(postDate);
        });

        /**
         * 「更新日を変更」ボタン
         */
        $('#kseo_update_modified_date').on('click', function() {
            var button = $(this);
            var postId = button.data('post-id');
            var newDate = $('#kseo_manual_modified_date').val();
            var messageDiv = $('#kseo_update_message');

            // 入力チェック
            if (!newDate) {
                messageDiv.removeClass('success').addClass('error').text('日時を入力してください。');
                return;
            }

            // ボタンを無効化
            button.prop('disabled', true).text('変更中...');
            messageDiv.removeClass('success error').text('');

            // AJAX リクエスト
            $.ajax({
                url: kseoData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kseo_update_modified_date',
                    nonce: kseoData.nonce,
                    post_id: postId,
                    new_date: newDate
                },
                success: function(response) {
                    // ボタンを有効化
                    button.prop('disabled', false).text('更新日を変更');

                    if (response.success) {
                        // 成功メッセージ
                        messageDiv.addClass('success').text(response.data.message);

                        // 表示を更新
                        $('.kseo-current-modified-date p:eq(1)').text(response.data.new_date);
                        $('.kseo-current-modified-date p:eq(2)').text(response.data.time_diff);
                    } else {
                        // エラーメッセージ
                        messageDiv.addClass('error').text(response.data.message);
                    }
                },
                error: function() {
                    // ボタンを有効化
                    button.prop('disabled', false).text('更新日を変更');
                    messageDiv.addClass('error').text('エラーが発生しました。');
                }
            });
        });
    });

})(jQuery);
