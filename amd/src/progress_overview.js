define([
    'jquery',
    'core/custom_interaction_events',
    'core_table/local/dynamic/selectors',
    'core_table/dynamic',
    'core/checkbox-toggleall',
    'core/pending',
    'core/str'
], function ($, CustomEvents, DynamicTableSelectors, DynamicTable, CheckboxToggleAll, Pending, Str) {
    const Selectors = {
        form: "#progressoverviewform",
        bulkactionselect: '#formactionid',
        tablecheckboxes: "input[data-togglegroup^='progress-overview-table'][data-toggle='slave']",
        checkAllButton: "#checkall",
        showCountText: '[data-region="progress-overview-count"]'
    };

    const form = document.querySelector(Selectors.form);
    const getTableFromUniqueId = uniqueId => form.querySelector(DynamicTableSelectors.main.fromRegionId(uniqueId));

    let progress_overview = {
        /**
         * Main function of progress overview page.
         * 
         * @param {int} tableId 
         */
        init: function ({ tableId }) {
            if (!form) {
                throw new Error(Selectors.form + "does not exist.");
            }

            this.whenCheckboxChecked();
            this.checkAllButton(tableId);
            this.refreshedTable();

            CustomEvents.define(Selectors.bulkactionselect, [CustomEvents.events.accessibleChange]);

            this.bulkActionsSelected(tableId);
        },

        /**
         * Observe every checkbox on the table, and enable/disable the bulk actions select.
         */
        whenCheckboxChecked: function () {
            const checkboxes = form.querySelectorAll(Selectors.tablecheckboxes);
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('click', this.manageBulkActionSelect());
            });

        },

        /**
         * When the "check all rows from the table" button is clicked, enable the bulk action select,
         * display every rows from the table, and check every checkbox.
         * You can't click another time until the action is finished.
         * 
         * @param {int} tableId 
         */
        checkAllButton: function (tableId) {
            form.addEventListener('click', e => {
                // Handle clicking of the "Select all" actions.
                const checkAllButton = form.querySelector(Selectors.checkAllButton);
                const checkAllButtonClicked = checkAllButton && checkAllButton.contains(e.target);

                if (checkAllButtonClicked) {
                    e.preventDefault();

                    this.manageBulkActionSelect();

                    const tableRoot = getTableFromUniqueId(tableId);
                    if (!tableRoot) {
                        return;
                    }

                    DynamicTable.setPageSize(tableRoot, checkAllButton.dataset.targetPageSize)
                        .then(tableRoot => {
                            // Update all checkbox state.
                            CheckboxToggleAll.setGroupState(form, 'progress-overview-table', true);

                            return tableRoot;
                        })
                        .catch(Notification.exception);

                    // Disable the "Select all" button
                    Str.get_string('loading', 'block_completion_monitor')
                        .then(function (str) {
                            checkAllButton.value = str;
                        })
                        .catch(Notification.exception);

                    checkAllButton.setAttribute("disabled", "disabled");
                }
            });
        },

        /**
         * When the form content is refreshed, update the row counts in various places and the
         * "Select all" button.
         */
        refreshedTable: function () {
            form.addEventListener(DynamicTable.Events.tableContentRefreshed, e => {
                const checkAllButton = form.querySelector(Selectors.checkAllButton);

                if (!checkAllButton) {
                    throw new Error(Selectors.checkAllButton + "does not exist");
                }

                const tableRoot = e.target;

                const defaultPageSize = parseInt(tableRoot.dataset.tableDefaultPerPage, 10);
                const currentTablePageSize = parseInt(tableRoot.dataset.tablePageSize, 10);
                const tableTotalRows = parseInt(tableRoot.dataset.tableTotalRows, 10);

                CheckboxToggleAll.updateSlavesFromMasterState(form, 'progress-overview-table');

                const pageCountStrings = [
                    {
                        key: 'countparticipantsfound',
                        component: 'core_user',
                        param: tableTotalRows,
                    },
                ];
                const selectAllUsersString = number => pageCountStrings.push({
                    key: 'selectalluserswithcount',
                    component: 'core',
                    param: number,
                });

                if (tableTotalRows <= currentTablePageSize) {
                    selectAllUsersString(defaultPageSize);

                    checkAllButton.classList.add('hidden');
                } else {
                    selectAllUsersString(tableTotalRows);

                    checkAllButton.classList.remove('hidden');
                    checkAllButton.removeAttribute("disabled");
                }

                Str.get_strings(pageCountStrings)
                    .then(([showingParticipantCountString, selectCountString]) => {
                        const showingParticipantCount = form.querySelector(Selectors.showCountText);
                        showingParticipantCount.innerHTML = showingParticipantCountString;

                        if (selectCountString && checkAllButton) {
                            checkAllButton.value = selectCountString;
                        }

                        return;
                    })
                    .catch(Notification.exception);
            });
        },

        /**
         * Enabled or disabled the bulk actions select.
         */
        manageBulkActionSelect: function () {
            const checkedcheckboxes = form.querySelectorAll(Selectors.tablecheckboxes + ":checked");

            if (checkedcheckboxes.length > 0) {
                form.querySelector(Selectors.bulkactionselect).removeAttribute("disabled");
            } else {
                form.querySelector(Selectors.bulkactionselect).setAttribute("disabled", "disabled");
            }
        },

        /**
         * If the user choose an action to processed, submit the form.
         * 
         * @param {int} tableId 
         */
        bulkActionsSelected: function (tableId) {
            $(Selectors.bulkactionselect).on(CustomEvents.events.accessibleChange, e => {
                const bulkActionSelect = e.target.closest('select');
                const action = bulkActionSelect.value;
                const tableRoot = getTableFromUniqueId(tableId);
                const checkboxes = tableRoot.querySelectorAll(Selectors.tablecheckboxes + ":checked");
                const pendingPromise = new Pending('block_completion_monitor/progress_overview:bulkActionSelect');

                if (action !== "" && checkboxes.length > 0) {
                    bulkActionSelect.form.submit();
                }

                if (bulkActionSelect.value !== "") {
                    this.resetBulkAction(bulkActionSelect);
                }

                pendingPromise.resolve();
            });
        },

        resetBulkAction: function (bulkActionSelect) {
            bulkActionSelect.value = '';
        }
    }

    window.progress_overview = progress_overview;
    return progress_overview;
});
