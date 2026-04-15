<?php

namespace block_completion_monitor\service;

defined('MOODLE_INTERNAL') || die();

use block_completion_monitor\repository\completion_monitor_repository;
class add_block_service
{

    
    /**
     * Activities Completion Course Monitoring repository
     * @var completion_monitor_repository
     */
    protected completion_monitor_repository $completionmonitorrepository;

    public function __construct() {
        $this->completionmonitorrepository = new completion_monitor_repository();
    }

    public function sync_block(): void
    {
        global $DB, $CFG;

        $batchsize = isset($CFG->batch_size) ? $CFG->batch_size : 1000;
        $batchcount = 0;
        $total = 0;

        $transaction = $DB->start_delegated_transaction();

        foreach ($this->completionmonitorrepository->get_sessions_trainings_without_block() as $course) {
            $service = new completion_monitor_service($course);
            $service->add_block_to_course();

            $batchcount++;
            $total++;

            if ($batchcount >= $batchsize) {
                $transaction->allow_commit();

                mtrace("Batch traité : {$batchcount} (total: {$total})");
                $transaction = $DB->start_delegated_transaction();
                $batchcount = 0;
            }
        }

        $transaction->allow_commit();

        mtrace("Traitement terminé : {$total} cours.");
    }
}
