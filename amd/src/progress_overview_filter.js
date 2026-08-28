/**
 * Progress overview filter management.
 *
 * @module     block_completion_monitor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/datafilter',
    'core_table/dynamic',
    'core/datafilter/selectors',
    'core/notification',
    'core/pending'
], function ($, CoreFilter, DynamicTable, Selectors, Notification, Pending) {
    let progress_overview_filter = {

        init: function(filterRegionId) {
            const filterSet = document.getElementById(filterRegionId);

            // Create and initialize filter.
            const coreFilter = new CoreFilter(filterSet, function(filters, pendingPromise) {
                DynamicTable.setFilters(
                    DynamicTable.getTableFromId(filterSet.dataset.tableRegion),
                    {
                        jointype: parseInt(filterSet.querySelector(Selectors.filterset.fields.join).value, 10),
                        filters,
                    }
                )
                    .then(result => {
                        pendingPromise.resolve();

                        return result;
                    })
                    .catch(Notification.exception);
            });
            coreFilter.init();

            // Initialize DynamicTable for showing result.
            const tableRoot = DynamicTable.getTableFromId(filterSet.dataset.tableRegion);
            const initialFilters = DynamicTable.getFilters(tableRoot);

            if (initialFilters) {
                const initialFilterPromise = new Pending('core/filter:setFilterFromConfig');
                // Apply the initial filter configuration.
                this.setFilterFromConfig(initialFilters, coreFilter, filterSet)
                    .then(() => initialFilterPromise.resolve())
                    .catch();
            }
        },

        /**
         * Set the current filter options based on a provided configuration.
         *
         * @param {Object} config
         * @param {Number} config.jointype
         * @param {Object} config.filters
         * @returns {Promise}
         */
        setFilterFromConfig: function(config, coreFilter, filterSet) {
            const filterConfig = Object.entries(config.filters);

            if (!filterConfig.length) {
                // There are no filters to set from.
                return Promise.resolve();
            }

            // Set the main join type.
            filterSet.querySelector(Selectors.filterset.fields.join).value = config.jointype;

            const filterPromises = filterConfig.map(([filterType, filterData]) => {
                if (filterType === 'courseid') {
                    // The courseid is a special case.
                    return false;
                }

                const filterValues = filterData.values;

                if (!filterValues.length) {
                    // There are no values for this filter.
                    // Skip it.
                    return false;
                }
                return coreFilter.addFilterRow()
                    .then(([filterRow]) => {
                        coreFilter.addFilter(filterRow, filterType, filterValues);
                        return;
                    });
            }).filter(promise => promise);

            if (!filterPromises.length) {
                return Promise.resolve();
            }

            return Promise.all(filterPromises)
                .then(() => {
                    return coreFilter.removeEmptyFilters();
                })
                .then(() => {
                    coreFilter.updateFiltersOptions();
                    return;
                })
                .then(() => {
                    coreFilter.updateTableFromFilter();
                    return;
                });
        }
    }

    window.progress_overview_filter = progress_overview_filter;
    return progress_overview_filter;
});
