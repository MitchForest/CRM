<?php

namespace App\Helpers;

class DateHelper
{
    /**
     * Calculate difference in days between two dates
     */
    public static function diffInDays($date1, $date2): int
    {
        $datetime1 = $date1 instanceof \DateTime ? $date1 : new \DateTime($date1);
        $datetime2 = $date2 instanceof \DateTime ? $date2 : new \DateTime($date2);
        
        $interval = $datetime1->diff($datetime2);
        return $interval->days;
    }
    
    /**
     * Calculate difference in hours between two dates
     */
    public static function diffInHours($date1, $date2): float
    {
        $datetime1 = $date1 instanceof \DateTime ? $date1 : new \DateTime($date1);
        $datetime2 = $date2 instanceof \DateTime ? $date2 : new \DateTime($date2);
        
        $diff = abs($datetime2->getTimestamp() - $datetime1->getTimestamp());
        return $diff / 3600;
    }
    
    /**
     * Calculate difference in minutes between two dates
     */
    public static function diffInMinutes($date1, $date2): int
    {
        $datetime1 = $date1 instanceof \DateTime ? $date1 : new \DateTime($date1);
        $datetime2 = $date2 instanceof \DateTime ? $date2 : new \DateTime($date2);
        
        $diff = abs($datetime2->getTimestamp() - $datetime1->getTimestamp());
        return intval($diff / 60);
    }
}