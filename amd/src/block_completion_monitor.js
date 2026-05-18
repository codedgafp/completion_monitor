define([
    'jquery',
    'core/templates',
    'core_user/repository',
    'block_completion_monitor/progress_bar',
    'block_completion_monitor/activity_detail',
    'block_completion_monitor/legend',
], function ($, Templates, UserRepository, ProgressBar, ActivityDetail, Legend) {
    let block_completion_monitor = {
        /**
         * Init JS
         */
        init: function (courseid) {
            this.courseid = courseid;
            let that = this;

            $(document).ready(function () {
                that.initProgressBar();
                that.initLegend();

                $('.block_completion_monitor .open-block').on('click', function (e) {
                    var openButton = $(e.currentTarget);
                    var block = $('.block_completion_monitor .completion_monitor-content')[0];

                    block.classList.contains('hidden-block')
                        ? that.openCollapse(block, openButton)
                        : that.closeCollapse(block, openButton);
                });
            });
        },
        /**
         * 
         * Toggle block content
         * @param {*} open 
         */
        toggleBlock: function (open) {

            var block = $('.block_completion_monitor .completion_monitor-content')[0];
            var openButton = $('.block_completion_monitor .open-block');

            open == 1
                ? this.showMore(block, openButton)
                : this.showLess(block, openButton);
        },
        /**
         * Set user preference
         *
         * @param value
         */
        setBlockOpenedPreference: function (value) {
            UserRepository.setUserPreference('block_completion_monitor_' + this.courseid + "_opened", value);
        },

        showLess: function (block, openButton) {
            block.classList.add('hidden-block');

            Templates.renderForPromise('block_completion_monitor/progress_header/show_more_button')
            .then( ({html}) => openButton.html(html) );
        },
        showMore: function (block, openButton) {
            block.classList.remove('hidden-block');

            Templates.renderForPromise('block_completion_monitor/progress_header/show_less_button')
            .then( ({html}) => openButton.html(html) );
        },

        openCollapse: function(block, openButton) {
            this.showMore(block, openButton);
            this.setBlockOpenedPreference(1);
        },
        closeCollapse: function(block, openButton) {
            this.showLess(block, openButton);
            this.setBlockOpenedPreference(0);
        },

        initProgressBar: function () {
            const $wrapper = $('.block_completion_monitor [data-region="progressbar"]');

            if (!$wrapper.length) return;

            this.progressBar = new ProgressBar($wrapper);
            this.activityDetail = new ActivityDetail(
                $('.block_completion_monitor .progressbar_detail-container')
            );

            $wrapper.on('progressbar:activity:selected', (e, vm) => {
                this.activityDetail.toggle(vm);
            });
        },

        initLegend: function () {
            this.legend = new Legend(
                $('.block_completion_monitor .progressbar_legend-container')
            );
        }
    }

    window.block_completion_monitor = block_completion_monitor;
    return block_completion_monitor;
});
