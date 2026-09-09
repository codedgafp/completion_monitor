define(['core/modal_save_cancel', 'core/modal_events', 'core/ajax', 'core/notification', 'core/str', 'core/templates'],
function(ModalSaveCancel, ModalEvents, Ajax, Notification, Str, Templates) {

    let SELECTORS = {
        SEND_BUTTON: '[data-action="toggle"][data-toggle="action"][id="send-message-button"]'
    };

    /**
     * Récupère les ids des utilisateurs cochés.
     * Les checkboxes de ligne ont id/name = "user{userid}" et la classe "usercheckbox".
     *
     * @param {HTMLElement} button
     * @return {Number[]}
     */
    let getSelectedUserIds = function(button) {
        let group = button.dataset.togglegroup;
        let boxes = document.querySelectorAll(
        '.usercheckbox[data-togglegroup="' + group + '"]:checked'
        );
        let ids = [];
        boxes.forEach(function(cb) {
            let match = /^user(\d+)$/.exec(cb.id);
            if (match) {
                ids.push(parseInt(match[1], 10));
            }
        });
        return ids;
    };

    let sendMessage = function(courseid, userids, message) {
        return Ajax.call([{
            methodname: 'block_completion_monitor_send_message',
            args: {courseid: courseid, userids: userids, message: message}
        }])[0];
    };


    let openModal = function(button) {

        let userids = getSelectedUserIds(button);
        if (!userids.length) {
            return;
        }
        
        let titleKey = (userids.length > 1) ? 'sendmessage_title_multiple' : 'sendmessage_title_single';

        let courseid = parseInt(button.dataset.courseid, 10);

            Str.get_strings([
                {key: titleKey, component: 'block_completion_monitor', param: userids.length},
                {key: 'sendmessage_button', component: 'block_completion_monitor'},
                {key: 'sendmessage_error', component: 'block_completion_monitor'}
            ]).then(function(strings) {
                let title = strings[0];
                let saveLabel = strings[1];
                let errorMsg = strings[2];

                return ModalSaveCancel.create({
                    title: title,
                    body: Templates.render('core_user/send_bulk_message', {}),
                    buttons: {save: saveLabel},
                    removeOnClose: true,
                    show: true
                }).then(function(modal) {
                    modal.getRoot().on(ModalEvents.save, function(e) {
                    const text = modal.getRoot().find('form textarea').val();
                if (text.trim() === '') {
                    modal.getRoot().find('[data-role="messagetextrequired"]').removeAttr('hidden');
                    e.preventDefault();
                    return;
                }

                        sendMessage(courseid, userids, text).then(function(result) {
                            modal.hide();

                            if (result.failed && result.failed.length) {
                                return Notification.addNotification({
                                    message: errorMsg,
                                    type: 'warning'
                                });
                            }

                            return Str.get_string('sendmessage_success_' + (userids.length > 1 ? 'multiple' : 'single'), 'block_completion_monitor', result.sent)
                                .then(function(successMsg) {
                                    return Notification.addNotification({
                                        message: successMsg,
                                        type: 'success'
                                    });
                                });
                        }).catch(function(error) {
                            modal.setButtonDisabled('save', false);
                            Notification.exception(error);
                        });
                    });

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });

                    return modal;
                });
            }).catch(Notification.exception);
    };

    return {
        init: function() {
            document.addEventListener('click', function(e) {
                let button = e.target.closest(SELECTORS.SEND_BUTTON);
                if (!button || button.disabled) {
                    return;
                }
                e.preventDefault();
                openModal(button);
            });
        }
    };
});