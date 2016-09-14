<?php
    namespace Octo;

    use Closure;
    use DateTime;
    use DateTimeZone;
    use DateInterval;
    use DatePeriod;

    class Time extends DateTime
    {
        const SUNDAY    = 0;
        const MONDAY    = 1;
        const TUESDAY   = 2;
        const WEDNESDAY = 3;
        const THURSDAY  = 4;
        const FRIDAY    = 5;
        const SATURDAY  = 6;

        protected static $days = array(
            self::SUNDAY    => 'Sunday',
            self::MONDAY    => 'Monday',
            self::TUESDAY   => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY  => 'Thursday',
            self::FRIDAY    => 'Friday',
            self::SATURDAY  => 'Saturday'
        );

        protected static $relativeKeywords = array(
            'this',
            'next',
            'last',
            'tomorrow',
            'yesterday',
            '+',
            '-',
            'first',
            'last',
            'ago'
        );

        const YEARS_PER_CENTURY = 100;
        const YEARS_PER_DECADE = 10;
        const MONTHS_PER_YEAR = 12;
        const WEEKS_PER_YEAR = 52;
        const DAYS_PER_WEEK = 7;
        const HOURS_PER_DAY = 24;
        const MINUTES_PER_HOUR = 60;
        const SECONDS_PER_MINUTE = 60;

        const DEFAULT_TO_STRING_FORMAT = 'Y-m-d H:i:s';

        protected static $toStringFormat = self::DEFAULT_TO_STRING_FORMAT;

        protected static $testNow;

        protected static function safeCreateDateTimeZone($object)
        {
            if ($object instanceof DateTimeZone) {
                return $object;
            }

            $tz = @timezone_open((string) $object);

            if ($tz === false) {
                throw new InvalidArgumentException('Unknown or bad timezone (' . $object . ')');
            }

            return $tz;
        }

        public function __construct($time = null, $tz = null)
        {
            // If the class has a test now set and we are trying to create a now()
            // instance then override as required
            if (self::hasTestNow() && (empty($time) || $time === 'now' || self::hasRelativeKeywords($time))) {
                $testInstance = clone self::getTestNow();

                if (self::hasRelativeKeywords($time)) {
                    $testInstance->modify($time);
                }

                //shift the time according to the given time zone
                if ($tz !== null && $tz != self::getTestNow()->tz) {
                    $testInstance->setTimezone($tz);
                } else {
                    $tz = $testInstance->tz;
                }

                $time = $testInstance->toDateTimeString();
            }

            if ($tz !== null) {
                parent::__construct($time, self::safeCreateDateTimeZone($tz));
            } else {
                parent::__construct($time);
            }
        }

        public static function instance($dt)
        {
            if (is_string($dt)) {
                $dt = new DateTime($dt);
            }

            return new static($dt->format('Y-m-d H:i:s.u'), $dt->getTimeZone());
        }

        public static function parse($time = null, $tz = null)
        {
            return new static($time, $tz);
        }

        public static function now($tz = null)
        {
            return new static(null, $tz);
        }

        public static function today($tz = null)
        {
            return self::now($tz)->startOfDay();
        }

        public static function tomorrow($tz = null)
        {
            return self::today($tz)->addDay();
        }

        public static function yesterday($tz = null)
        {
            return self::today($tz)->subDay();
        }

        public static function maxValue()
        {
            return self::createFromTimestamp(PHP_INT_MAX);
        }

        public static function minValue()
        {
            return self::createFromTimestamp(~PHP_INT_MAX);
        }

        public static function create($year = null, $month = null, $day = null, $hour = null, $minute = null, $second = null, $tz = null)
        {
            $year = ($year === null) ? date('Y') : $year;
            $month = ($month === null) ? date('n') : $month;
            $day = ($day === null) ? date('j') : $day;

            if ($hour === null) {
                $hour = date('G');
                $minute = ($minute === null) ? date('i') : $minute;
                $second = ($second === null) ? date('s') : $second;
            } else {
                $minute = ($minute === null) ? 0 : $minute;
                $second = ($second === null) ? 0 : $second;
            }

            return self::createFromFormat('Y-n-j G:i:s', sprintf('%s-%s-%s %s:%02s:%02s', $year, $month, $day, $hour, $minute, $second), $tz);
        }

        public static function createFromDate($year = null, $month = null, $day = null, $tz = null)
        {
            return self::create($year, $month, $day, null, null, null, $tz);
        }

        public static function createFromTime($hour = null, $minute = null, $second = null, $tz = null)
        {
            return self::create(null, null, null, $hour, $minute, $second, $tz);
        }

        public static function createFromFormat($format, $time, $tz = null)
        {
            if ($tz !== null) {
                $dt = parent::createFromFormat($format, $time, self::safeCreateDateTimeZone($tz));
            } else {
                $dt = parent::createFromFormat($format, $time);
            }

            if ($dt instanceof DateTime) {
                return self::instance($dt);
            }

            $errors = self::getLastErrors();
            throw new InvalidArgumentException(implode(PHP_EOL, $errors['errors']));
        }

        public static function createFromTimestamp($timestamp, $tz = null)
        {
            return self::now($tz)->setTimestamp($timestamp);
        }

        public static function createFromTimestampUTC($timestamp)
        {
            return new static('@' . $timestamp);
        }

        public function copy()
        {
            return self::instance($this);
        }

        public function __get($name)
        {
            switch(true) {
                case array_key_exists($name, $formats = array(
                    'year' => 'Y',
                    'month' => 'n',
                    'day' => 'j',
                    'hour' => 'G',
                    'minute' => 'i',
                    'second' => 's',
                    'micro' => 'u',
                    'dayOfWeek' => 'w',
                    'dayOfYear' => 'z',
                    'weekOfYear' => 'W',
                    'daysInMonth' => 't',
                    'timestamp' => 'U',
                )):
                    return (int) $this->format($formats[$name]);

                case $name === 'weekOfMonth':
                    return (int) ceil($this->day / self::DAYS_PER_WEEK);

                case $name === 'age':
                    return (int) $this->diffInYears();

                case $name === 'quarter':
                    return (int) ceil($this->month / 3);

                case $name === 'offset':
                    return $this->getOffset();

                case $name === 'offsetHours':
                    return $this->getOffset() / self::SECONDS_PER_MINUTE / self::MINUTES_PER_HOUR;

                case $name === 'dst':
                    return $this->format('I') == '1';

                case $name === 'local':
                    return $this->offset == $this->copy()->setTimezone(date_default_timezone_get())->offset;

                case $name === 'utc':
                    return $this->offset == 0;

                case $name === 'timezone' || $name === 'tz':
                    return $this->getTimezone();

                case $name === 'timezoneName' || $name === 'tzName':
                    return $this->getTimezone()->getName();

                default:
                    throw new InvalidArgumentException(sprintf("Unknown getter '%s'", $name));
            }
        }

        public function __isset($name)
        {
            try {
                $this->__get($name);
            } catch (InvalidArgumentException $e) {
                return false;
            }

            return true;
        }

        public function __set($name, $value)
        {
            switch ($name) {
                case 'year':
                    parent::setDate($value, $this->month, $this->day);
                    break;

                case 'month':
                    parent::setDate($this->year, $value, $this->day);
                    break;

                case 'day':
                    parent::setDate($this->year, $this->month, $value);
                    break;

                case 'hour':
                    parent::setTime($value, $this->minute, $this->second);
                    break;

                case 'minute':
                    parent::setTime($this->hour, $value, $this->second);
                    break;

                case 'second':
                    parent::setTime($this->hour, $this->minute, $value);
                    break;

                case 'timestamp':
                    parent::setTimestamp($value);
                    break;

                case 'timezone':
                case 'tz':
                    $this->setTimezone($value);
                    break;

                default:
                    throw new InvalidArgumentException(sprintf("Unknown setter '%s'", $name));
            }
        }

        public function year($value)
        {
            $this->year = $value;

            return $this;
        }

        public function month($value)
        {
            $this->month = $value;

            return $this;
        }

        public function day($value)
        {
            $this->day = $value;

            return $this;
        }

        public function setDate($year, $month, $day)
        {
            parent::setDate($year, $month, $day);

            return $this;
        }

        public function hour($value)
        {
            $this->hour = $value;

            return $this;
        }

        public function minute($value)
        {
            $this->minute = $value;

            return $this;
        }

        public function second($value)
        {
            $this->second = $value;

            return $this;
        }

        public function setTime($hour, $minute, $second = 0)
        {
            parent::setTime($hour, $minute, $second);

            return $this;
        }

        public function setDateTime($year, $month, $day, $hour, $minute, $second = 0)
        {
            return $this->setDate($year, $month, $day)->setTime($hour, $minute, $second);
        }

        public function timestamp($value)
        {
            $this->timestamp = $value;

            return $this;
        }

        public function timezone($value)
        {
            return $this->setTimezone($value);
        }

        public function tz($value)
        {
            return $this->setTimezone($value);
        }

        public function setTimezone($value)
        {
            parent::setTimezone(self::safeCreateDateTimeZone($value));

            return $this;
        }

        public static function setTestNow(Time $testNow = null)
        {
            self::$testNow = $testNow;
        }

        public static function getTestNow()
        {
            return self::$testNow;
        }

        public static function hasTestNow()
        {
            return self::getTestNow() !== null;
        }

        public static function hasRelativeKeywords($time)
        {
            // skip common format with a '-' in it
            if (preg_match('/[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}/', $time) !== 1) {
                foreach (self::$relativeKeywords as $keyword) {
                    if (stripos($time, $keyword) !== false) {
                        return true;
                    }
                }
            }

            return false;
        }

        public function formatLocalized($format)
        {
            // Check for Windows to find and replace the %e
            // modifier correctly
            if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
                 $format = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $format);
            }

            return strftime($format, strtotime($this));
        }

        public static function resetToStringFormat()
        {
            self::setToStringFormat(self::DEFAULT_TO_STRING_FORMAT);
        }

        public static function setToStringFormat($format)
        {
            self::$toStringFormat = $format;
        }

        public function __toString()
        {
            return $this->format(self::$toStringFormat);
        }

        public function toDateString()
        {
            return $this->format('Y-m-d');
        }

        public function toFormattedDateString()
        {
            return $this->format('M j, Y');
        }

        public function toTimeString()
        {
            return $this->format('H:i:s');
        }

        public function toDateTimeString()
        {
            return $this->format('Y-m-d H:i:s');
        }

        public function toDayDateTimeString()
        {
            return $this->format('D, M j, Y g:i A');
        }

        public function toAtomString()
        {
            return $this->format(self::ATOM);
        }

        public function toCookieString()
        {
            return $this->format(self::COOKIE);
        }

        public function toIso8601String()
        {
            return $this->format(self::ISO8601);
        }

        public function toRfc822String()
        {
            return $this->format(self::RFC822);
        }

        public function toRfc850String()
        {
            return $this->format(self::RFC850);
        }

        public function toRfc1036String()
        {
            return $this->format(self::RFC1036);
        }

        public function toRfc1123String()
        {
            return $this->format(self::RFC1123);
        }

        public function toRfc2822String()
        {
            return $this->format(self::RFC2822);
        }

        public function toRfc3339String()
        {
            return $this->format(self::RFC3339);
        }

        public function toRssString()
        {
            return $this->format(self::RSS);
        }

        public function toW3cString()
        {
            return $this->format(self::W3C);
        }

        public function eq(Time $dt)
        {
            return $this == $dt;
        }

        public function ne(Time $dt)
        {
            return !$this->eq($dt);
        }

        public function gt(Time $dt)
        {
            return $this > $dt;
        }

        public function gte(Time $dt)
        {
            return $this >= $dt;
        }

        public function lt(Time $dt)
        {
            return $this < $dt;
        }

        public function lte(Time $dt)
        {
            return $this <= $dt;
        }

        public function between(Time $dt1, Time $dt2, $equal = true)
        {
            if ($dt1->gt($dt2)) {
                $temp = $dt1;
                $dt1 = $dt2;
                $dt2 = $temp;
            }

            if ($equal) {
                return $this->gte($dt1) && $this->lte($dt2);
            } else {
                return $this->gt($dt1) && $this->lt($dt2);
            }
        }

        public function min(Time $dt = null)
        {
            $dt = ($dt === null) ? self::now($this->tz) : $dt;

            return $this->lt($dt) ? $this : $dt;
        }

        public function max(Time $dt = null)
        {
            $dt = ($dt === null) ? self::now($this->tz) : $dt;

            return $this->gt($dt) ? $this : $dt;
        }

        public function isWeekday()
        {
            return ($this->dayOfWeek != self::SUNDAY && $this->dayOfWeek != self::SATURDAY);
        }

        public function isWeekend()
        {
            return !$this->isWeekDay();
        }

        public function isYesterday()
        {
            return $this->toDateString() === self::yesterday($this->tz)->toDateString();
        }

        public function isToday()
        {
            return $this->toDateString() === self::now($this->tz)->toDateString();
        }

        public function isTomorrow()
        {
            return $this->toDateString() === self::tomorrow($this->tz)->toDateString();
        }

        public function isFuture()
        {
            return $this->gt(self::now($this->tz));
        }

        public function isPast()
        {
            return $this->lt(self::now($this->tz));
        }

        public function isLeapYear()
        {
            return $this->format('L') == '1';
        }

        public function isSameDay(Time $dt)
        {
            return $this->toDateString() === $dt->toDateString();
        }

        public function addYears($value)
        {
            return $this->modify((int) $value . ' year');
        }

        public function addYear()
        {
            return $this->addYears(1);
        }

        public function subYear()
        {
            return $this->addYears(-1);
        }

        public function subYears($value)
        {
            return $this->addYears(-1 * $value);
        }

        public function addMonths($value)
        {
            return $this->modify((int) $value . ' month');
        }

        public function addMonth()
        {
            return $this->addMonths(1);
        }

        public function subMonth()
        {
            return $this->addMonths(-1);
        }

        public function subMonths($value)
        {
            return $this->addMonths(-1 * $value);
        }

        public function addMonthsNoOverflow($value)
        {
            $newYear   = $this->year + floor(($this->month + $value) / 12);
            $newMonth = ($this->month + $value) % 12;
            $newDate = self::create($newYear, $newMonth, $this->day, $this->hour, $this->minute, $this->second, $this->getTimeZone());

            if ($newDate->day != $this->day) {
                $newDate->day(1)->subMonth();
                $newDate->day($newDate->daysInMonth);
            }

            return $newDate;
        }

        public function addMonthNoOverflow()
        {
            return $this->addMonthsNoOverflow(1);
        }

        public function subMonthNoOverflow()
        {
            return $this->addMonthsNoOverflow(-1);
        }

        public function subMonthsNoOverflow($value)
        {
            return $this->addMonthsNoOverflow(-1 * $value);
        }

        public function addDays($value)
        {
            return $this->modify((int) $value . ' day');
        }

        public function addDay()
        {
            return $this->addDays(1);
        }

        public function subDay()
        {
            return $this->addDays(-1);
        }

        public function subDays($value)
        {
            return $this->addDays(-1 * $value);
        }

        public function addWeekdays($value)
        {
            return $this->modify((int) $value . ' weekday');
        }

        public function addWeekday()
        {
            return $this->addWeekdays(1);
        }

        public function subWeekday()
        {
            return $this->addWeekdays(-1);
        }

        public function subWeekdays($value)
        {
            return $this->addWeekdays(-1 * $value);
        }

        public function addWeeks($value)
        {
            return $this->modify((int) $value . ' week');
        }

        public function addWeek()
        {
            return $this->addWeeks(1);
        }

        public function subWeek()
        {
            return $this->addWeeks(-1);
        }

        public function subWeeks($value)
        {
            return $this->addWeeks(-1 * $value);
        }

        public function addHours($value)
        {
            return $this->modify((int) $value . ' hour');
        }

        public function addHour()
        {
            return $this->addHours(1);
        }

        public function subHour()
        {
            return $this->addHours(-1);
        }

        public function subHours($value)
        {
            return $this->addHours(-1 * $value);
        }

        public function addMinutes($value)
        {
            return $this->modify((int) $value . ' minute');
        }

        public function addMinute()
        {
            return $this->addMinutes(1);
        }

        public function subMinute()
        {
            return $this->addMinutes(-1);
        }

        public function subMinutes($value)
        {
            return $this->addMinutes(-1 * $value);
        }

        public function addSeconds($value)
        {
            return $this->modify((int) $value . ' second');
        }

        public function addSecond()
        {
            return $this->addSeconds(1);
        }

        public function subSecond()
        {
            return $this->addSeconds(-1);
        }

        public function subSeconds($value)
        {
            return $this->addSeconds(-1 * $value);
        }

        public function diffInYears(Time $dt = null, $abs = true)
        {
            $dt = ($dt === null) ? self::now($this->tz) : $dt;

            return (int) $this->diff($dt, $abs)->format('%r%y');
        }

        public function diffInMonths(Time $dt = null, $abs = true)
        {
            $dt = ($dt === null) ? self::now($this->tz) : $dt;

            return $this->diffInYears($dt, $abs) * self::MONTHS_PER_YEAR + $this->diff($dt, $abs)->format('%r%m');
        }

        public function diffInWeeks(Time $dt = null, $abs = true)
        {
            return (int) ($this->diffInDays($dt, $abs) / self::DAYS_PER_WEEK);
        }

        public function diffInDays(Time $dt = null, $abs = true)
        {
            $dt = ($dt === null) ? self::now($this->tz) : $dt;

            return (int) $this->diff($dt, $abs)->format('%r%a');
        }

        public function diffInDaysFiltered(Closure $callback, Time $dt = null, $abs = true)
        {
            $start = $this;
            $end = ($dt === null) ? self::now($this->tz) : $dt;
            $inverse = false;

            if ($end < $start) {
                $start = $end;
                $end = $this;
                $inverse = true;
            }

            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            $days = array_filter(iterator_to_array($period), function (DateTime $date) use ($callback) {
                return call_user_func($callback, Time::instance($date));
            });

            $diff = count($days);

            return $inverse && !$abs ? -$diff : $diff;
        }

        public function diffInWeekdays(Time $dt = null, $abs = true)
        {
            return $this->diffInDaysFiltered(function (Time $date) {
                   return $date->isWeekday();
            }, $dt, $abs);
        }

        public function diffInWeekendDays(Time $dt = null, $abs = true)
        {
            return $this->diffInDaysFiltered(function (Time $date) {
                return $date->isWeekend();
            }, $dt, $abs);
        }

        public function diffInHours(Time $dt = null, $abs = true)
        {
            return (int) ($this->diffInSeconds($dt, $abs) / self::SECONDS_PER_MINUTE / self::MINUTES_PER_HOUR);
        }

        public function diffInMinutes(Time $dt = null, $abs = true)
        {
            return (int) ($this->diffInSeconds($dt, $abs) / self::SECONDS_PER_MINUTE);
        }

        public function diffInSeconds(Time $dt = null, $abs = true)
        {
            $value = (($dt === null) ? time() : $dt->getTimestamp()) - $this->getTimestamp();

            return $abs ? abs($value) : $value;
        }

        public function secondsSinceMidnight()
        {
            return $this->diffInSeconds($this->copy()->startOfDay());
        }

        public function secondsUntilEndOfDay()
        {
            return $this->diffInSeconds($this->copy()->endOfDay());
        }

        public function diffForHumans(Time $other = null, $absolute = false)
        {
            $isNow = $other === null;

            if ($isNow) {
                $other = self::now($this->tz);
            }

            $isFuture = $this->gt($other);

            $delta = $other->diffInSeconds($this);

            $divs = array(
                'second'    => self::SECONDS_PER_MINUTE,
                'minute'    => self::MINUTES_PER_HOUR,
                'hour'      => self::HOURS_PER_DAY,
                'day'       => self::DAYS_PER_WEEK,
                'week'      => 30 / self::DAYS_PER_WEEK,
                'month'     => self::MONTHS_PER_YEAR
            );

            $unit = 'year';

            foreach ($divs as $divUnit => $divValue) {
                if ($delta < $divValue) {
                    $unit = $divUnit;
                    break;
                }

                $delta = $delta / $divValue;
            }

            $delta = (int) $delta;

            if ($delta == 0) {
                $delta = 1;
            }

            $txt = $delta . ' ' . $unit;
            $txt .= $delta == 1 ? '' : 's';

            if ($absolute) {
                return $txt;
            }

            if ($isNow) {
                if ($isFuture) {
                    return $txt . ' from now';
                }

                return $txt . ' ago';
            }

            if ($isFuture) {
                return $txt . ' after';
            }

            return $txt . ' before';
        }

        public function startOfDay()
        {
            return $this->hour(0)->minute(0)->second(0);
        }

        public function endOfDay()
        {
            return $this->hour(23)->minute(59)->second(59);
        }

        public function startOfMonth()
        {
            return $this->startOfDay()->day(1);
        }

        public function endOfMonth()
        {
            return $this->day($this->daysInMonth)->endOfDay();
        }

        public function startOfYear()
        {
            return $this->month(1)->startOfMonth();
        }

        public function endOfYear()
        {
            return $this->month(self::MONTHS_PER_YEAR)->endOfMonth();
        }

        public function startOfDecade()
        {
            return $this->startOfYear()->year($this->year - $this->year % self::YEARS_PER_DECADE);
        }

        public function endOfDecade()
        {
            return $this->endOfYear()->year($this->year - $this->year % self::YEARS_PER_DECADE + self::YEARS_PER_DECADE - 1);
        }

        public function startOfCentury()
        {
            return $this->startOfYear()->year($this->year - $this->year % self::YEARS_PER_CENTURY);
        }

        public function endOfCentury()
        {
            return $this->endOfYear()->year($this->year - $this->year % self::YEARS_PER_CENTURY + self::YEARS_PER_CENTURY - 1);
        }

        public function startOfWeek()
        {
            if ($this->dayOfWeek != self::MONDAY) $this->previous(self::MONDAY);

            return $this->startOfDay();
        }

        public function endOfWeek()
        {
            if ($this->dayOfWeek != self::SUNDAY) $this->next(self::SUNDAY);

            return $this->endOfDay();
        }

        public function next($dayOfWeek = null)
        {
            if ($dayOfWeek === null) {
                $dayOfWeek = $this->dayOfWeek;
            }

            return $this->startOfDay()->modify('next ' . self::$days[$dayOfWeek]);
        }

        public function previous($dayOfWeek = null)
        {
            if ($dayOfWeek === null) {
                $dayOfWeek = $this->dayOfWeek;
            }

            return $this->startOfDay()->modify('last ' . self::$days[$dayOfWeek]);
        }

        public function firstOfMonth($dayOfWeek = null)
        {
            $this->startOfDay();

            if ($dayOfWeek === null) {
                return $this->day(1);
            }

            return $this->modify('first ' . self::$days[$dayOfWeek] . ' of ' . $this->format('F') . ' ' . $this->year);
        }

        public function lastOfMonth($dayOfWeek = null)
        {
            $this->startOfDay();

            if ($dayOfWeek === null) {
                return $this->day($this->daysInMonth);
            }

            return $this->modify('last ' . self::$days[$dayOfWeek] . ' of ' . $this->format('F') . ' ' . $this->year);
        }

        public function nthOfMonth($nth, $dayOfWeek)
        {
            $dt     = $this->copy()->firstOfMonth();
            $check  = $dt->format('Y-m');

            $dt->modify('+' . $nth . ' ' . self::$days[$dayOfWeek]);

            return ($dt->format('Y-m') === $check) ? $this->modify($dt) : false;
        }

        public function firstOfQuarter($dayOfWeek = null)
        {
            return $this->day(1)->month($this->quarter * 3 - 2)->firstOfMonth($dayOfWeek);
        }

        public function lastOfQuarter($dayOfWeek = null)
        {
            return $this->day(1)->month($this->quarter * 3)->lastOfMonth($dayOfWeek);
        }

        public function nthOfQuarter($nth, $dayOfWeek)
        {
            $dt         = $this->copy()->day(1)->month($this->quarter * 3);
            $last_month = $dt->month;
            $year       = $dt->year;

            $dt->firstOfQuarter()->modify('+' . $nth . ' ' . self::$days[$dayOfWeek]);

            return ($last_month < $dt->month || $year !== $dt->year) ? false : $this->modify($dt);
        }

        public function firstOfYear($dayOfWeek = null)
        {
            return $this->month(1)->firstOfMonth($dayOfWeek);
        }

        public function lastOfYear($dayOfWeek = null)
        {
            return $this->month(self::MONTHS_PER_YEAR)->lastOfMonth($dayOfWeek);
        }

        public function nthOfYear($nth, $dayOfWeek)
        {
            $dt = $this->copy()->firstOfYear()->modify('+' . $nth . ' ' . self::$days[$dayOfWeek]);

            return $this->year == $dt->year ? $this->modify($dt) : false;
        }

        public function average(Time $dt = null)
        {
            $dt = ($dt === null) ? self::now($this->tz) : $dt;

            return $this->addSeconds((int) ($this->diffInSeconds($dt, false) / 2));
        }

        public function isBirthday(Time $dt)
        {
            return $this->month === $dt->month && $this->day === $dt->day;
        }

        public function frenchDay($short = false)
        {
            switch ($this->dayOfWeek) {
                case 0:
                    return $short ? 'di' : 'dimanche';
                case 1:
                    return $short ? 'lu' : 'lundi';
                case 2:
                    return $short ? 'ma' : 'mardi';
                case 3:
                    return $short ? 'me' : 'mercredi';
                case 4:
                    return $short ? 'je' : 'jeudi';
                case 5:
                    return $short ? 've' : 'vendredi';
                case 6:
                    return $short ? 'sa' : 'samedi';
            }
        }

        public function deriveInterval($interval)
        {
            $interval   = trim(substr(trim($interval), 8));
            $parts      = explode(' ', $interval);

            foreach ($parts as $part) {
                if (!empty($part)) {
                    $_parts[] = $part;
                }
            }

            $type = strtolower(end($_parts));

            switch ($type) {
                case "second": $unit = 'S'; return 'PT' . $_parts[0] . $unit; break;
                case "minute": $unit = 'M'; return 'PT' . $_parts[0] . $unit; break;
                case "hour":   $unit = 'H'; return 'PT' . $_parts[0] . $unit; break;
                case "day":    $unit = 'D'; return 'P'  . $_parts[0] . $unit; break;
                case "week":   $unit = 'W'; return 'P'  . $_parts[0] . $unit; break;
                case "month":  $unit = 'M'; return 'P'  . $_parts[0] . $unit; break;
                case "year":   $unit = 'Y'; return 'P'  . $_parts[0] . $unit; break;
                case "minute_second":
                    list($minutes, $seconds) = explode(':', $_parts[0]);
                    return 'PT' . $minutes . 'M' . $seconds . 'S';
                case "hour_second":
                    list($hours, $minutes, $seconds) = explode (':', $_parts[0]);
                    return 'PT' . $hours . 'H' . $minutes . 'M' . $seconds . 'S';
                case "hour_minute":
                    list($hours, $minutes) = explode (':', $_parts[0]);
                    return 'PT' . $hours . 'H' . $minutes . 'M';
                case "day_second":
                    $days = intval($_parts[0]);
                    list($hours, $minutes, $seconds) = explode (':', $_parts[1]);
                    return 'P' . $days . 'D' . 'T' . $hours . 'H' . $minutes . 'M' . $seconds . 'S';
                case "day_minute":
                    $days = intval($_parts[0]);
                    list($hours, $minutes) = explode(':', $parts[1]);
                    return 'P' . $days . 'D' . 'T' . $hours . 'H' . $minutes . 'M';
                case "day_hour":
                    $days  = intval($_parts[0]);
                    $hours = intval($_parts[1]);
                    return 'P' . $days . 'D' . 'T' . $hours . 'H';
                case "year_month":
                    list($years, $months) = explode ('-', $_parts[0]);
                    return 'P' . $years . 'Y' . $months . 'M';
            }
        }

        public function timezoneList()
        {
            $timezoneIdentifiers = \DateTimeZone::listIdentifiers();
            $utcTime = new \DateTime('now', new \DateTimeZone('UTC'));

            $tempTimezones = [];

            foreach ($timezoneIdentifiers as $timezoneIdentifier) {
                $currentTimezone = new \DateTimeZone($timezoneIdentifier);

                $tempTimezones[] = [
                    'offset'        => (int)$currentTimezone->getOffset($utcTime),
                    'identifier'    => $timezoneIdentifier
                ];
            }

            usort($tempTimezones, function($a, $b) {
                return ($a['offset'] == $b['offset'])
                ? strcmp($a['identifier'], $b['identifier'])
                : $a['offset'] - $b['offset'];
            });

            $timezoneList = [];

            foreach ($tempTimezones as $tz) {
                $sign                               = ($tz['offset'] > 0) ? '+' : '-';
                $offset                             = gmdate('H:i', abs($tz['offset']));
                $timezoneList[$tz['identifier']]    = '(UTC ' . $sign . $offset . ') ' . $tz['identifier'];
            }

            return $timezoneList;
        }
    }
