<?php
/**
 * Создание заявки на приём с полной серверной валидацией.
 *
 * Уникальность слота гарантируется сгенерированной колонкой active_slot_time
 * и UNIQUE-индексом uq_active_slot (отменённые заявки слот освобождают),
 * поэтому конкурентная запись двух пациентов на одно время исключена.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Appointment
{
    /**
     * Валидация и создание заявки.
     *
     * @return array{ok:bool, errors?: array<string,string>, appointment?: array}
     */
    public static function create(array $in): array
    {
        $errors = [];

        /* ---- Персональные данные ---- */
        $name = trim((string)($in['patient_name'] ?? ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            $errors['patient_name'] = __('bk.err_name');
        } elseif (!preg_match("/^[\p{Lu}][\p{L}\s'’\-.]+$/u", $name)) {
            $errors['patient_name'] = __('bk.err_name');
        }

        $phone = Helpers::normalizePhone((string)($in['patient_phone'] ?? ''));
        if ($phone === null) {
            $errors['patient_phone'] = __('bk.err_phone');
        }

        $birth = trim((string)($in['patient_birth_date'] ?? ''));
        if ($birth !== '') {
            if (!ScheduleService::isValidDate($birth) || $birth < '1900-01-01' || $birth > date('Y-m-d')) {
                $errors['patient_birth_date'] = __('bk.err_birth');
            }
        }

        $passport = strtoupper(preg_replace('/\s+/', '', (string)($in['patient_passport'] ?? '')) ?? '');
        if ($passport !== '' && !preg_match('/^[A-Z]{2}\d{7}$/', $passport)) {
            $errors['patient_passport'] = __('bk.err_passport');
        }

        $pinfl = preg_replace('/\D+/', '', (string)($in['patient_pinfl'] ?? '')) ?? '';
        if ($pinfl !== '' && !preg_match('/^\d{14}$/', $pinfl)) {
            $errors['patient_pinfl'] = __('bk.err_pinfl');
        }

        $comment = trim((string)($in['patient_comment'] ?? ''));
        if (mb_strlen($comment) > 1000) {
            $errors['patient_comment'] = __('bk.err_comment');
        }

        if (empty($in['consent'])) {
            $errors['consent'] = __('bk.err_consent');
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        /* ---- Врач, дата, время ---- */
        $doctorId = (int)($in['doctor_id'] ?? 0);
        $db = Database::get();
        $st = $db->prepare(
            "SELECT d.id, d.department_id, d.name_ru, d.office, dep.name_ru AS dept_name_ru,
                    dep.name_uz AS dept_name_uz, dep.name_en AS dept_name_en
             FROM doctors d
             JOIN departments dep ON dep.id = d.department_id
             WHERE d.id = ? AND d.is_active = 1"
        );
        $st->execute([$doctorId]);
        $doctor = $st->fetch();
        if (!$doctor) {
            return ['ok' => false, 'errors' => ['doctor_id' => __('bk.err_generic')]];
        }

        $date = (string)($in['appointment_date'] ?? '');
        $time = substr((string)($in['appointment_time'] ?? ''), 0, 5);
        $today = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime('+' . BOOKING_MAX_DAYS . ' days'));

        if (
            !ScheduleService::isValidDate($date)
            || $date < $today
            || $date > $maxDate
            || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)
        ) {
            return ['ok' => false, 'errors' => ['appointment_time' => __('bk.err_generic')]];
        }

        // серверная проверка слота — данные клиента не считаются доверенными
        if (!ScheduleService::isWorkingDay($doctorId, $date)) {
            return ['ok' => false, 'errors' => ['appointment_time' => __('bk.err_day')]];
        }

        $available = ScheduleService::daySlots($doctorId, $date);
        if (!in_array($time, $available, true)) {
            return ['ok' => false, 'errors' => ['appointment_time' => __('bk.err_slot')]];
        }

        /* ---- Создание заявки ---- */
        $deptName = match (Lang::current()) {
            'uz' => ($doctor['dept_name_uz'] ?? '') ?: $doctor['dept_name_ru'],
            'en' => ($doctor['dept_name_en'] ?? '') ?: $doctor['dept_name_ru'],
            default => $doctor['dept_name_ru'],
        };

        try {
            $db->beginTransaction();

            $st = $db->prepare(
                "INSERT INTO appointments
                    (ticket_number, doctor_id, department_id, patient_name, patient_phone,
                     patient_birth_date, patient_passport, patient_pinfl, patient_comment,
                     appointment_date, appointment_time, status, ip_address, user_agent)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?)"
            );
            $st->execute([
                $doctorId,
                (int)$doctor['department_id'],
                $name,
                $phone,
                $birth !== '' ? $birth : null,
                $passport !== '' ? $passport : null,
                $pinfl !== '' ? $pinfl : null,
                $comment !== '' ? $comment : null,
                $date,
                $time . ':00',
                Helpers::clientIp(),
                mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);

            $id = (int)$db->lastInsertId();
            $ticket = TICKET_PREFIX . date('Y') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);

            $st = $db->prepare("UPDATE appointments SET ticket_number = ? WHERE id = ?");
            $st->execute([$ticket, $id]);

            $db->commit();
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e->getCode() === '23000') {
                // конкурентная запись на тот же слот
                return ['ok' => false, 'errors' => ['appointment_time' => __('bk.err_slot')]];
            }
            throw $e;
        }

        return [
            'ok' => true,
            'appointment' => [
                'ticket'        => '#' . $ticket,
                'doctor'        => $doctor['name_ru'],
                'department'    => $deptName,
                'office'        => (string)$doctor['office'],
                'date'          => $date,
                'date_human'    => Helpers::formatDate($date),
                'time'          => $time,
                'patient_name'  => $name,
                'patient_phone' => Helpers::formatPhone($phone),
            ],
        ];
    }
}
