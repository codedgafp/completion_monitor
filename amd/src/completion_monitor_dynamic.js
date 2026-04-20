define([], function () {
    let completion_monitor_dynamic = {
        /**
         * Init JS
         * 
         * @param {number} userid - Current user id
         * @param {number} courseid - Current course id
         * @return {void}
         */
        init: function (userid, courseid) {
            this.userid = userid;
            this.courseid = courseid;

            this.openSSE();
        },

        /**
         * Connects to the SSE and waits to receive events sent by the server.
         * 
         * @return {void}
         */
        openSSE: function () {
            const url = new URL(
                M.cfg.wwwroot + '/blocks/completion_monitor/sse.php'
            );

            url.searchParams.set('userid', this.userid);
            url.searchParams.set('courseid', this.courseid);

            // Connects to SSE
            const source = new EventSource(url.toString(), {
                withCredentials: true
            });

            source.addEventListener('completion_update', (e) => {
                this.updateCompletionInformation(e.data);
            });

            source.addEventListener('activities_update', (e) => {
                this.updateActivities(e.data);
            });

            source.addEventListener('done', () => {
                source.close();
                this.reopen();
            });
        },

        /**
         * Connects again to the SSE after a delay
         * 
         * @return {void}
         */
        reopen: function () {
            setTimeout(() => this.openSSE(), 500);
        },

        /**
         * Updates the header of the completion_monitor block
         * 
         * @param {Object} data 
         */
        updateCompletionInformation: function (data) {
            let parsedata = JSON.parse(data);

            const completion_percentage = parsedata.completion_percentage;
            const completion_details = parsedata.completion_details;

            const circleprogress = document.querySelector(`#completion-monitor-circle-progress`);
            const courseprogresspercentagelist = document.querySelectorAll(`.block-completion-monitor .course-progress-percentage`);
            const courseprogresssteplist = document.querySelectorAll(`.block-completion-monitor .course-progress-step`);

            // Circle progress
            circleprogress.querySelector('span').textContent = `${completion_percentage}%`;
            circleprogress.querySelector('#circle-front').setAttribute("stroke-dasharray", completion_details.percentage_circle_data.circumference)
            circleprogress.querySelector('#circle-front').setAttribute("stroke-dashoffset", completion_details.percentage_circle_data.offset)

            // Completion information
            courseprogresspercentagelist.forEach(function (courseprogresspercentage) {
                courseprogresspercentage.textContent = completion_details.courseprogress_percentage;
            });
            courseprogresssteplist.forEach(function (courseprogressstep) {
                courseprogressstep.textContent = completion_details.courseprogress_step;
            });
        },

        /**
         * Updates the progress bar activities
         * 
         * @param {string} data 
         */
        updateActivities: function (data) {
            const parsed = JSON.parse(data);
            const activities = parsed?.activities_details || [];
            if (!window.block_completion_monitor?.progressBar) return;

            activities.forEach((activity) => {
                window.block_completion_monitor.progressBar.updateActivity(
                    activity.id,
                    activity.status
                );
            });
        },

    };

    window.completion_monitor_dynamic = completion_monitor_dynamic;
    return completion_monitor_dynamic;
});
