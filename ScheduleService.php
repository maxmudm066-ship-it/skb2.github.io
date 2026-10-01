<?php
/**
 * Расчёт свободных слотов приёма врача.
 *
 * Слот доступен, если:
 *  - дата не прошла и не выходит за горизонт записи (BOOKING_MAX_DAYS);
 *  - дата не заблокирована (date_blocks: отпуск врача или праздник клиники);
 *  - у врача есть график на этот день недели (doctor_schedules);
 *  - слот не занят активной заявкой (appointments, статус ≠ cancelled);
 *  - слот не заблокирован вручную (blocked_slots);
 *  - для сегодняшнего дня — до приёма остаётся не менее BOOKING_LEAD_MINUTES.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

final class ScheduleService
{
    /** @var array<int, array|null> кэш врачей в рамках запроса */
    private static array $doctorCache = [];

    public static function isValidDate(string $date): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4));
    }

    private static function doctor(int $id): ?array
    {
        if (!array_key_exists($id, self::$doctorCache)) {
            $st = Database::get()->prepare(
                "SELECT id, slot_duration, is_active FROM doctors WHERE id = ?"
            );
            $st->execute([$id]);
            $row = $st->fetch();
            self::$doctorCache[$id] = $row ?: null;
        }
        $doc = self::$doctorCache[$id];
        return ($doc && (int)$doc['is_active'] === 1) ? $doc : null;
    }

    /**
     * Свободные слоты конкретного дня (['09:00', '09:30', ...]).
     */
    public static function daySlots(int $doctorId, string $date): array
    {
        if (!self::isValidDate($date) || !$doctorId > 0) {
            return [];
        }
        $today   = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime('+' . BOOKING_MAX_DAYS . ' days'));
        if ($date < $today || $date > $maxDate) {
            return [];
        }

        $db = Database::get();
        if (!self::doctor($doctorId)) {
            return [];
        }

        // блокировка даты (врач или вся клиника)
        $st = $db->prepare(
            "SELECT COUNT(*) FROM date_blocks
             WHERE block_date = ? AND (doctor_id = ? OR doctor_id IS NULL)"
        );
        $st->execute([$date, $doctorId]);
        if ((int)$st->fetchColumn() > 0) {
            return [];
        }

        // график на день недели
        $weekday = (int)(new DateTimeImmutable($date))->format('N'); // 1=пн … 7=вс
        $st = $db->prepare(
            "SELECT start_time, end_time FROM doctor_schedules
             WHERE doctor_id = ? AND weekday = ? ORDER BY start_time"
        );
        $st->execute([$doctorId, $weekday]);
        $intervals = [];
        while ($r = $st->fetch()) {
            $intervals[] = [substr((string)$r['start_time'], 0, 5), substr((string)$r['end_time'], 0, 5)];
        }
        if ($intervals === []) {
            return [];
        }

        $st = $db->prepare(
            "SELECT appointment_time FROM appointments
             WHERE doctor_id = ? AND appointment_date = ? AND status <> 'cancelled'"
        );
        $st->execute([$doctorId, $date]);
        $taken = [];
        while ($r = $st->fetch()) {
            $taken[] = substr((string)$r['appointment_time'], 0, 5);
        }

        $st = $db->prepare(
            "SELECT block_time FROM blocked_slots WHERE doctor_id = ? AND block_date = ?"
        );
        $st->execute([$doctorId, $date]);
        $blocked = [];
        while ($r = $st->fetch()) {
            $blocked[] = substr((string)$r['block_time'], 0, 5);
        }

        return self::compute(
            $intervals,
            (int)self::doctor($doctorId)['slot_duration'],
            $taken,
            $blocked,
            $date
        );
    }

    /**
     * Ведёт ли врач приём в указанный день: график на этот день недели
     * и отсутствие блокировки даты (отпуск врача или праздник клиники).
     * Занятость отдельных слотов не проверяется.
     */
    public static function isWorkingDay(int $doctorId, string $date): bool
    {
        if (!self::isValidDate($date) || $doctorId <= 0 || !self::doctor($doctorId)) {
            return false;
        }

        $db = Database::get();
        $st = $db->prepare(
            "SELECT COUNT(*) FROM date_blocks
             WHERE block_date = ? AND (doctor_id = ? OR doctor_id IS NULL)"
        );
        $st->execute([$date, $doctorId]);
        if ((int)$st->fetchColumn() > 0) {
            return false;
        }

        $weekday = (int)(new DateTimeImmutable($date))->format('N');
        $st = $db->prepare(
            "SELECT COUNT(*) FROM doctor_schedules WHERE doctor_id = ? AND weekday = ?"
        );
        $st->execute([$doctorId, $weekday]);

        return (int)$st->fetchColumn() > 0;
    }

    /**
     * Доступность всех дней месяца для календаря.
     * Формат: ['days' => ['2026-10-05' => ['working' => bool, 'free' => int], ...]]
     */
    public static function monthAvailability(int $doctorId, string $month): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $doctorId <= 0) {
            return ['days' => []];
        }

        $db = Database::get();
        $doctor = self::doctor($doctorId);
        if (!$doctor) {
            return ['days' => []];
        }
        $duration = (int)$doctor['slot_duration'];

        $first = $month . '-01';
        $lastDay = (int)(new DateTimeImmutable($first))->format('t');
        $last = $month . '-' . sprintf('%02d', $lastDay);

        $days = [];
        for ($i = 1; $i <= $lastDay; $i++) {
            $days[$month . '-' . sprintf('%02d', $i)] = ['working' => false, 'free' => 0];
        }

        $today   = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime('+' . BOOKING_MAX_DAYS . ' days'));
        $from = max($first, $today);
        $to   = min($last, $maxDate);
        if ($to < $from) {
            return ['days' => $days];
        }

        // всё необходимое — тремя-четырьмя запросами на весь месяц
        $byWeekday = [];
        $st = $db->prepare(
            "SELECT weekday, start_time, end_time FROM doctor_schedules
             WHERE doctor_id = ? ORDER BY weekday, start_time"
        );
        $st->execute([$doctorId]);
        while ($r = $st->fetch()) {
            $byWeekday[(int)$r['weekday']][] = [
                substr((string)$r['start_time'], 0, 5),
                substr((string)$r['end_time'], 0, 5),
            ];
        }

        $blockedDates = [];
        $st = $db->prepare(
            "SELECT block_date FROM date_blocks
             WHERE block_date BETWEEN ? AND ? AND (doctor_id = ? OR doctor_id IS NULL)"
        );
        $st->execute([$from, $to, $doctorId]);
        while ($r = $st->fetch()) {
            $blockedDates[$r['block_date']] = true;
        }

        $blockedSlots = [];
        $st = $db->prepare(
            "SELECT block_date, block_time FROM blocked_slots
             WHERE doctor_id = ? AND block_date BETWEEN ? AND ?"
        );
        $st->execute([$doctorId, $from, $to]);
        while ($r = $st->fetch()) {
            $blockedSlots[$r['block_date']][] = substr((string)$r['block_time'], 0, 5);
        }

        $taken = [];
        $st = $db->prepare(
            "SELECT appointment_date, appointment_time FROM appointments
             WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ? AND status <> 'cancelled'"
        );
        $st->execute([$doctorId, $from, $to]);
        while ($r = $st->fetch()) {
            $taken[$r['appointment_date']][] = substr((string)$r['appointment_time'], 0, 5);
        }

        $cursor = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        while ($cursor <= $end) {
            $d = $cursor->format('Y-m-d');
            $weekday = (int)$cursor->format('N');
            if (!isset($blockedDates[$d]) && !empty($byWeekday[$weekday])) {
                $slots = self::compute(
                    $byWeekday[$weekday],
                    $duration,
                    $taken[$d] ?? [],
                    $blockedSlots[$d] ?? [],
                    $d
                );
                $days[$d] = ['working' => true, 'free' => count($slots)];
            }
            $cursor = $cursor->modify('+1 day');
        }

        return ['days' => $days];
    }

    /**
     * Генерация сетки слотов по интервалам с исключением занятых и заблокированных.
     *
     * @param array<int, array{0:string,1:string}> $intervals [['09:00','14:00'], ...]
     * @param string[] $taken   занятые 'HH:MM'
     * @param string[] $blocked заблокированные 'HH:MM'
     * @return string[]
     */
    private static function compute(array $intervals, int $duration, array $taken, array $blocked, string $date): array
    {
        $duration = max(5, $duration);
        $slots = [];

        foreach ($intervals as [$start, $end]) {
            [$sh, $sm] = array_map('intval', explode(':', $start));
            [$eh, $em] = array_map('intval', explode(':', $end));
            $from = $sh * 60 + $sm;
            $to   = $eh * 60 + $em;
            for ($t = $from; $t + $duration <= $to; $t += $duration) {
                $slots[sprintf('%02d:%02d', intdiv($t, 60), $t % 60)] = true;
            }
        }

        foreach ($taken as $tm) {
            unset($slots[substr((string)$tm, 0, 5)]);
        }
        foreach ($blocked as $tm) {
            unset($slots[substr((string)$tm, 0, 5)]);
        }

        // для сегодняшнего дня отсекаем слоты ближе BOOKING_LEAD_MINUTES
        if ($date === date('Y-m-d')) {
            $minNow = ((int)date('G')) * 60 + (int)date('i') + BOOKING_LEAD_MINUTES;
            foreach (array_keys($slots) as $tm) {
                [$h, $m] = array_map('intval', explode(':', $tm));
                if ($h * 60 + $m < $minNow) {
                    unset($slots[$tm]);
                }
            }
        }

        $result = array_keys($slots);
        sort($result);
        return $result;
    }
}
