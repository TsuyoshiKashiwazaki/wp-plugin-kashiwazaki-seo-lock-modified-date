/**
 * Kashiwazaki SEO Lock Modified Date - 管理画面用JavaScript
 *
 * @package Kashiwazaki_SEO_Lock_Modified_Date
 */

(function($) {
    'use strict';

    $(function() {
        var $box = $('#kseo-lmd-box');
        if (!$box.length || typeof kseoData === 'undefined') {
            return;
        }

        var postId = $box.data('post-id');
        var $toggle = $('#kseo_lock_toggle');
        var $input = $('#kseo_manual_modified_date');
        var $pending = $('#kseo_manual_pending');
        var $postDateButton = $('#kseo_set_to_post_date');
        var $updateButton = $('#kseo_update_modified_date');
        var $lockMessage = $('#kseo_lock_message');
        var $updateMessage = $('#kseo_update_message');

        // 「公開日と同じにする」を押した後、入力を触るまでは公開日（秒まで）で変更する
        var mode = 'custom';
        // 最後にサーバーから受け取った入力欄の値（未反映判定に使う）
        var savedInput = $input.val();

        function showMessage($target, text, type) {
            $target.removeClass('success error').addClass(type).text(text || '');
        }

        function errorText(xhr) {
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                return xhr.responseJSON.data.message;
            }
            if (xhr && xhr.status === 403) {
                return 'セッションの有効期限が切れた可能性があります。ページを再読み込みしてください。';
            }
            return '通信エラーが発生しました。ページを再読み込みしてからもう一度お試しください。';
        }

        // 応答の順序が前後しても古い状態で表示を戻さないための通し番号
        var requestSeq = 0;
        var appliedSeq = 0;

        function request(action, data) {
            requestSeq++;
            var seq = requestSeq;
            var xhr = $.ajax({
                url: kseoData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
                data: $.extend({ action: action, nonce: kseoData.nonce, post_id: postId }, data || {})
            });
            xhr.kseoSeq = seq;
            return xhr;
        }

        function isLatest(xhr) {
            if (!xhr || xhr.kseoSeq < appliedSeq) {
                return false;
            }
            appliedSeq = xhr.kseoSeq;
            return true;
        }

        function isPending() {
            return mode === 'post_date' || $input.val() !== savedInput;
        }

        function updatePending() {
            $pending.prop('hidden', !isPending() || $input.prop('disabled'));
        }

        function render(state, preserveInput) {
            if (!state) {
                return;
            }
            var keepInput = preserveInput && isPending();
            $toggle.prop('checked', !!state.locked);
            $('#kseo_current_modified').text(state.modified_display || '');
            $('#kseo_current_diff').text(state.time_diff || '');
            $('#kseo_unpublished_note').prop('hidden', !!state.published);

            $input.attr('max', state.max_input || '');
            published = !!state.published;
            applyControlsState();

            savedInput = state.modified_input || '';
            if (!keepInput) {
                $input.val(savedInput);
                mode = 'custom';
            }
            updatePending();
        }

        var hasBlockEditor = !!(window.wp && wp.data && typeof wp.data.select === 'function' && wp.data.select('core/editor'));
        // 投稿の保存中（ブロックエディタの保存 / クラシック画面のフォーム送信後）は操作させない
        var postSaving = false;
        // このメタボックスの要求（ロック切り替え・手動変更）は同時に 1 つだけ
        var requestBusy = false;
        var published = !$input.prop('disabled');

        function applyControlsState() {
            var blocked = postSaving || requestBusy;
            $toggle.prop('disabled', blocked);
            $input.prop('disabled', blocked || !published);
            $postDateButton.prop('disabled', blocked || !published);
            $updateButton.prop('disabled', blocked || !published);
            updatePending();
        }

        // ロック切り替えの保存中は、投稿の保存を止める（古いロック状態で保存されないように）
        function setSavingBlocked(blocked) {
            if (hasBlockEditor) {
                var editorDispatch = wp.data.dispatch('core/editor');
                if (editorDispatch && typeof editorDispatch.lockPostSaving === 'function') {
                    if (blocked) {
                        editorDispatch.lockPostSaving('kseo-lmd');
                    } else {
                        editorDispatch.unlockPostSaving('kseo-lmd');
                    }
                }
            }
            $('#publish, #save-post').prop('disabled', blocked);
        }

        function refreshState() {
            var xhr = request('kseo_get_modified_state');
            xhr.done(function(res) {
                if (res && res.success && isLatest(xhr)) {
                    render(res.data, true);
                }
            });
        }

        // ロックの切り替え（その場で保存）
        $toggle.on('change', function() {
            var locked = $toggle.is(':checked');
            requestBusy = true;
            applyControlsState();
            setSavingBlocked(true);
            showMessage($lockMessage, '保存中...', 'success');

            var lockXhr = request('kseo_set_lock', { locked: locked ? '1' : '0' });
            lockXhr
                .done(function(res) {
                    if (res && res.success) {
                        if (isLatest(lockXhr)) {
                            render(res.data, true);
                        }
                        showMessage($lockMessage, res.data.message, 'success');
                    } else {
                        $toggle.prop('checked', !locked);
                        showMessage($lockMessage, res && res.data ? res.data.message : '保存できませんでした。', 'error');
                    }
                })
                .fail(function(xhr) {
                    $toggle.prop('checked', !locked);
                    showMessage($lockMessage, errorText(xhr), 'error');
                    refreshState();
                })
                .always(function() {
                    requestBusy = false;
                    setSavingBlocked(false);
                    applyControlsState();
                });
        });

        // 入力を触ったら、指定した日時で変更するモードに戻す
        $input.on('input change', function() {
            mode = 'custom';
            updatePending();
        });

        // 「公開日と同じにする」
        $postDateButton.on('click', function() {
            mode = 'post_date';
            showMessage($updateMessage, '公開日時（秒まで）に合わせます。「更新日を変更」を押すと反映されます。', 'success');
            updatePending();
        });

        // 「更新日を変更」
        $updateButton.on('click', function() {
            var data = { mode: mode };
            if (mode !== 'post_date') {
                if (!$input.val()) {
                    showMessage($updateMessage, '日時を入力してください。', 'error');
                    return;
                }
                data.new_date = $input.val();
            }

            requestBusy = true;
            applyControlsState();
            $updateButton.text('変更中...');
            setSavingBlocked(true);
            showMessage($updateMessage, '', 'success');

            var updateXhr = request('kseo_update_modified_date', data);
            updateXhr
                .done(function(res) {
                    if (res && res.success) {
                        if (isLatest(updateXhr)) {
                            render(res.data);
                        }
                        showMessage($updateMessage, res.data.message, 'success');
                        showMessage($lockMessage, '', 'success');
                    } else {
                        showMessage($updateMessage, res && res.data ? res.data.message : '更新に失敗しました。', 'error');
                    }
                })
                .fail(function(xhr) {
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && typeof xhr.responseJSON.data.locked !== 'undefined') {
                        if (isLatest(updateXhr)) {
                            render(xhr.responseJSON.data, true);
                        }
                    } else {
                        refreshState();
                    }
                    showMessage($updateMessage, errorText(xhr), 'error');
                })
                .always(function() {
                    requestBusy = false;
                    setSavingBlocked(false);
                    $updateButton.text('更新日を変更');
                    applyControlsState();
                });
        });

        // クラシック画面（添付ファイル編集など）: 未反映の入力があるまま保存しようとしたら確認する
        // （WordPress 本体の送信処理がボタンを無効化する前に確認するため、クリック時に判定する）
        if (!hasBlockEditor) {
            var submitConfirmed = false;
            var confirmMessage = '手動で指定した更新日時はまだ反映されていません。反映せずに保存しますか？\n（反映するには「キャンセル」を押し、メタボックスの「更新日を変更」を押してください）';

            $('#publish, #save-post').on('click', function(event) {
                submitConfirmed = false;
                if (!isPending() || !published) {
                    return;
                }
                if (window.confirm(confirmMessage)) {
                    submitConfirmed = true;
                } else {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            });

            // Enter キーなどボタン以外からの送信も確認する
            $('form#post').on('submit', function(event) {
                // プレビュー（別タブへの送信）はこの画面の保存ではないので、確認も保存中の扱いもしない
                if ('dopreview' === $('#wp-preview').val()) {
                    return;
                }
                if (isPending() && published && !submitConfirmed && !window.confirm(confirmMessage)) {
                    event.preventDefault();
                    return;
                }
                submitConfirmed = false;
                // 他の処理が送信を取り消した場合は保存中にしない
                window.setTimeout(function() {
                    if (!event.isDefaultPrevented()) {
                        postSaving = true;
                        applyControlsState();
                    }
                }, 0);
            });
        }

        // ブロックエディタ: 保存完了後に表示を DB と同期し、未反映の入力があれば知らせる
        if (hasBlockEditor) {
            var wasSaving = false;

            wp.data.subscribe(function() {
                var editor = wp.data.select('core/editor');
                var editPost = wp.data.select('core/edit-post');
                if (!editor) {
                    return;
                }

                var savingPost = editor.isSavingPost() && !editor.isAutosavingPost();
                var savingMetaBoxes = !!(editPost && typeof editPost.isSavingMetaBoxes === 'function' && editPost.isSavingMetaBoxes());

                if (savingPost || savingMetaBoxes) {
                    if (wasSaving) {
                        return;
                    }
                    // 状態を先に確定させる（通知の追加でストアが変わり、この関数が再度呼ばれるため）
                    wasSaving = true;
                    postSaving = true;
                    applyControlsState();
                    if (isPending() && wp.data.dispatch('core/notices')) {
                        wp.data.dispatch('core/notices').createWarningNotice(
                            '手動で指定した更新日時はまだ反映されていません。メタボックスの「更新日を変更」を押してください。',
                            { id: 'kseo-lmd-pending', isDismissible: true }
                        );
                    }
                    return;
                }

                if (wasSaving) {
                    wasSaving = false;
                    postSaving = false;
                    applyControlsState();
                    refreshState();
                }
            });
        }

        updatePending();
    });

})(jQuery);
