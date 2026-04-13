define([
    'jquery',
    'core_user/repository'
], function ($, UserRepository) {
    let block_completion_monitor = {
        /**
         * Init JS
         */
        init: function (courseid) {
            this.courseid = courseid;
            let that = this;

            $(document).ready(function () {
                that.getBlockOpenedPreference().then(function (preference) {
                    that.toggleBlock(preference);
                });

                $('.block_completion_monitor .open-block').on('click', function (e) {
                    let openButton = $(e.currentTarget);
                    let block = $('.block_completion_monitor .completion_monitor-content')[0];

                    if (block.classList.contains('hidden-block')) {
                        that.showMore(block, openButton);
                        that.setBlockOpenedPreference(1);
                    } else {
                        that.showLess(block, openButton);
                        that.setBlockOpenedPreference(0);
                    }
                });
            });
        },
        /**
         * 
         * Toggle block content
         * @param {*} open 
         */
        toggleBlock: function (open) {

            let block = $('.block_completion_monitor .completion_monitor-content')[0];
            let openButton = $('.block_completion_monitor .open-block');

            if (open == 1) {
                this.showMore(block, openButton);
            } else {
                this.showLess(block, openButton);
            }
        },
        /**
         * Set user preference
         *
         * @param value
         */
        setBlockOpenedPreference: function (value) {
            UserRepository.setUserPreference('block_completion_monitor_' + this.courseid + "_opened", value);
        },
        /**
         * Get user preference
         * 
         */
        getBlockOpenedPreference: async function () {
            return await UserRepository.getUserPreference('block_completion_monitor_' + this.courseid + '_opened');

        },
        showLess: function (block, openButton) {
            block.classList.add('hidden-block');
            openButton.html('<button class="button-showless-showmore" aria-expanded="false">'
                + M.util.get_string('showmore', 'block_completion_monitor')
                + ' <i class="fa fa-chevron-down"></i></button>');
        },
        showMore: function (block, openButton) {
            block.classList.remove('hidden-block');
            openButton.html('<button class="button-showless-showmore" aria-expanded="true">'
                + M.util.get_string('showless', 'block_completion_monitor')
                + ' <i class="fa fa-chevron-up"></i></button>');
        }
    }

    window.block_completion_monitor = block_completion_monitor;
    return block_completion_monitor;
});