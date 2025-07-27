<?php

namespace Database\Seeders;

use Faker\Factory as Faker;
use Illuminate\Database\Capsule\Manager as DB;

abstract class BaseSeeder
{
    protected $faker;
    
    public function __construct()
    {
        $this->faker = Faker::create();
    }
    
    abstract public function run(): void;
    
    protected function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    protected function randomDate($startDate = '-6 months', $endDate = 'now'): \DateTime
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $randomTimestamp = mt_rand($start->getTimestamp(), $end->getTimestamp());
        return (new \DateTime())->setTimestamp($randomTimestamp);
    }
    
    protected function randomBusinessHour(): \DateTime
    {
        $hour = mt_rand(9, 17); // 9 AM to 5 PM
        $minute = mt_rand(0, 59);
        return (new \DateTime())->setTime($hour, $minute);
    }
    
    protected function randomWeekday($startDate = '-6 months', $endDate = 'now'): \DateTime
    {
        $date = $this->randomDate($startDate, $endDate);
        while (in_array($date->format('N'), ['6', '7'])) { // Skip weekends
            $date->modify('+1 day');
        }
        return $date;
    }
    
    protected function randomProbability($percentage): bool
    {
        return mt_rand(1, 100) <= $percentage;
    }
}