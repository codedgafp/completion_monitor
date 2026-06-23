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
        init: function (courseid, userid) {
            this.courseid = courseid;
            this.userid = userid;
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

        showLess: function (block, openButton) {
            block.classList.add('hidden-block');

            this.progressBar._updateArrows();

            Templates.renderForPromise('block_completion_monitor/progress_header/show_more_button')
            .then( ({html}) => openButton.html(html) );
        },
        showMore: function (block, openButton) {
            block.classList.remove('hidden-block');

            this.progressBar._updateArrows();

            Templates.renderForPromise('block_completion_monitor/progress_header/show_less_button')
            .then( ({html}) => openButton.html(html) );
        },

        openCollapse: function(block, openButton) {
            this.showMore(block, openButton);
        },
        closeCollapse: function(block, openButton) {
            this.showLess(block, openButton);
        },

        initProgressBar: function () {
            const $wrapper = $('.block_completion_monitor [data-region="progressbar"]');

            if (!$wrapper.length) return;

            this.progressBar = new ProgressBar($wrapper, this.courseid, this.userid);

            this.activityDetail = new ActivityDetail(
                $('.block_completion_monitor .progressbar_detail-container'),
                this.courseid,
                this.userid
            );

            let activityToDisplay = this.progressBar.activityList.activityToDisplay;
            if (activityToDisplay !== null && activityToDisplay.length > 0) {
                let $activity = $(activityToDisplay[0]);
                this.toggleDefaultActivity($activity, activityToDisplay);
            }

            $wrapper.on('progressbar:activity:selected', (e, vm) => {
                this.activityDetail.toggle(vm);
            });
        },

        toggleDefaultActivity: function($activity, activityToDisplay) {
            let activityToToggle = {
                id: $activity.data("id"),
                name: $activity.data("name"),
                type: $activity.data("type"),
                url: $activity.data("url"),
                issectionurl: $activity.data('issectionurl') == 1,
                opennewtab: $activity.data("opennewtab") == 1,
                icon: $activity.data("icon"),
                status: $activity.data("status"),
                required: $activity.data("required") == 1,
                completionconditions: JSON.parse($activity.attr('data-conditions') || '[]'),
                el: activityToDisplay,
            };

            this.activityDetail.toggle(activityToToggle);
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
