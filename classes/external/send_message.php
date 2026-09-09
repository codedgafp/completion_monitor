<?php

namespace block_completion_monitor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_course;
use core_user;
use core\message\message;

defined('MOODLE_INTERNAL') || die();

class send_message extends external_api
{

    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id du cours'),
            'userids'  => new external_multiple_structure(
                new external_value(PARAM_INT, 'Id utilisateur destinataire')
            ),
            'message'  => new external_value(PARAM_RAW, 'Contenu du message (texte brut)'),
        ]);
    }

    public static function execute(int $courseid, array $userids, string $message): array
    {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userids'  => $userids,
            'message'  => $message,
        ]);

        $course  = get_course($params['courseid']);
        $context = context_course::instance($course->id);

        require_login($course, false);
        self::validate_context($context);
        require_capability('report/progress:view', $context);

        if (empty($CFG->messaging)) {
            throw new \moodle_exception('disabled', 'message');
        }

        $text = trim(clean_param($params['message'], PARAM_TEXT));
        if ($text === '') {
            throw new \invalid_parameter_exception('Message vide');
        }
        if (empty($params['userids'])) {
            throw new \invalid_parameter_exception('Aucun destinataire');
        }

        $sent   = 0;
        $failed = [];

        foreach (array_unique($params['userids']) as $userid) {
            if ($userid == $USER->id) {
                $failed[] = $userid;
                continue;
            }

            try {
                $touser = core_user::get_user($userid, '*', MUST_EXIST);
            } catch (\dml_exception $e) {
                $failed[] = $userid;
                continue;
            }

            if (!$touser || $touser->deleted || $touser->suspended) {
                $failed[] = $userid;
                continue;
            }

            // Respecte les préférences de confidentialité / blocages de l'utilisateur.
            if (!\core_message\api::can_send_message($touser->id, $USER->id)) {
                $failed[] = $userid;
                continue;
            }

            $eventmessage = self::create_message($touser, $USER, $course, $text);

            if (message_send($eventmessage)) {
                $sent++;
            } else {
                $failed[] = $userid;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    private static function create_message(\stdClass $touser, \stdClass $userfrom, \stdClass $course, string $text): message
    {
        $eventmessage                     = new message();
        $eventmessage->component           = 'moodle';
        $eventmessage->name                = 'instantmessage';
        $eventmessage->userfrom            = $userfrom;
        $eventmessage->userto              = $touser;
        $eventmessage->subject             = get_string('sendmessage_subject', 'block_completion_monitor', $course->shortname);
        $eventmessage->fullmessage         = $text;
        $eventmessage->fullmessageformat   = FORMAT_PLAIN;
        $eventmessage->fullmessagehtml     = nl2br(s($text));
        $eventmessage->smallmessage        = $text;
        $eventmessage->notification        = '0';
        $eventmessage->courseid            = $course->id;

        return $eventmessage;
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'sent'   => new external_value(PARAM_INT, 'Nombre de messages envoyés'),
            'failed' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Id utilisateur en échec'),
                'Destinataires pour lesquels l\'envoi a échoué',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}
