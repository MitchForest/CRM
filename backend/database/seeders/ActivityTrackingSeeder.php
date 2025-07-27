<?php

namespace Database\Seeders;

use Illuminate\Database\Capsule\Manager as DB;

class ActivityTrackingSeeder extends BaseSeeder
{
    private $marketingPages = [
        '/' => ['Home', 60],
        '/features' => ['Features', 30],
        '/pricing' => ['Pricing', 25],
        '/about' => ['About Us', 15],
        '/blog' => ['Blog', 20],
        '/blog/remote-team-management' => ['Blog: Remote Team Management', 10],
        '/blog/agile-best-practices' => ['Blog: Agile Best Practices', 8],
        '/blog/project-management-tips' => ['Blog: Project Management Tips', 12],
        '/case-studies' => ['Case Studies', 15],
        '/case-studies/tech-startup-success' => ['Case Study: Tech Startup', 8],
        '/integrations' => ['Integrations', 18],
        '/contact' => ['Contact', 10],
        '/demo' => ['Request Demo', 20],
        '/free-trial' => ['Free Trial', 15],
    ];
    
    private $appPages = [
        '/app/dashboard' => ['Dashboard', 80],
        '/app/projects' => ['Projects', 60],
        '/app/tasks' => ['Tasks', 70],
        '/app/calendar' => ['Calendar', 30],
        '/app/reports' => ['Reports', 25],
        '/app/team' => ['Team', 20],
        '/app/settings' => ['Settings', 15],
    ];
    
    private $userAgents = [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15',
        'Mozilla/5.0 (iPad; CPU OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15',
    ];
    
    private $referrers = [
        'https://www.google.com' => 40,
        'https://www.bing.com' => 10,
        'https://www.linkedin.com' => 15,
        'https://www.facebook.com' => 5,
        'https://www.producthunt.com' => 5,
        'direct' => 25,
    ];
    
    public function run(): void
    {
        echo "Seeding activity tracking data...\n";
        
        $leadIds = json_decode(file_get_contents(__DIR__ . '/lead_ids.json'), true);
        $contactIds = json_decode(file_get_contents(__DIR__ . '/contact_ids.json'), true);
        
        $visitorCount = 0;
        $sessionCount = 0;
        $pageViewCount = 0;
        
        // Create sessions for leads (before they became leads)
        foreach ($leadIds as $index => $leadId) {
            $lead = DB::table('leads')->where('id', $leadId)->first();
            
            // 70% of leads have tracking data
            if (!$this->randomProbability(70)) continue;
            
            // Create visitor
            $visitorId = $this->generateUuid();
            $firstVisit = new \DateTime($lead->date_entered);
            $firstVisit->modify('-' . mt_rand(1, 30) . ' days');
            
            DB::table('activity_tracking_visitors')->insert([
                'id' => $visitorId,
                'visitor_id' => 'v_' . substr(md5($lead->email1), 0, 16),
                'first_visit' => $firstVisit->format('Y-m-d H:i:s'),
                'last_visit' => $lead->date_entered,
                'total_visits' => mt_rand(1, 5),
                'lead_id' => $leadId,
                'contact_id' => null,
                'created_at' => $firstVisit->format('Y-m-d H:i:s'),
                'updated_at' => $lead->date_entered,
            ]);
            $visitorCount++;
            
            // Create 1-5 sessions
            $sessionDates = $this->generateSessionDates($firstVisit, new \DateTime($lead->date_entered));
            
            foreach ($sessionDates as $sessionIndex => $sessionDate) {
                $sessionId = $this->generateUuid();
                $duration = mt_rand(60, 1800); // 1-30 minutes
                
                DB::table('activity_tracking_sessions')->insert([
                    'id' => $sessionId,
                    'visitor_id' => $visitorId,
                    'session_id' => 's_' . substr(md5($sessionId), 0, 16),
                    'start_time' => $sessionDate->format('Y-m-d H:i:s'),
                    'end_time' => (clone $sessionDate)->modify("+{$duration} seconds")->format('Y-m-d H:i:s'),
                    'duration' => $duration,
                    'page_views' => mt_rand(2, 8),
                    'ip_address' => $this->faker->ipv4(),
                    'user_agent' => $this->faker->randomElement($this->userAgents),
                    'referrer' => $this->getRandomByDistribution($this->referrers),
                    'lead_id' => $leadId,
                    'contact_id' => null,
                    'created_at' => $sessionDate->format('Y-m-d H:i:s'),
                    'updated_at' => $sessionDate->format('Y-m-d H:i:s'),
                ]);
                $sessionCount++;
                
                // Create page views for this session
                $pageViewCount += $this->createPageViews($sessionId, $sessionDate, $duration, 'marketing');
            }
            
            if ($index % 100 == 0) {
                echo "  Created tracking data for {$index} leads...\n";
            }
        }
        
        // Create sessions for existing customers
        foreach (array_slice($contactIds, 0, 100) as $contactId) {
            $contact = DB::table('contacts')->where('id', $contactId)->first();
            
            // Create visitor
            $visitorId = $this->generateUuid();
            $firstVisit = new \DateTime($contact->date_entered);
            
            DB::table('activity_tracking_visitors')->insert([
                'id' => $visitorId,
                'visitor_id' => 'v_' . substr(md5($contact->email1), 0, 16),
                'first_visit' => $firstVisit->format('Y-m-d H:i:s'),
                'last_visit' => (new \DateTime())->format('Y-m-d H:i:s'),
                'total_visits' => mt_rand(20, 100),
                'lead_id' => null,
                'contact_id' => $contactId,
                'created_at' => $firstVisit->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
            $visitorCount++;
            
            // Create regular app usage sessions
            $currentDate = clone $firstVisit;
            $endDate = new \DateTime();
            
            while ($currentDate < $endDate) {
                // Skip weekends sometimes
                if (in_array($currentDate->format('N'), ['6', '7']) && !$this->randomProbability(20)) {
                    $currentDate->modify('+1 day');
                    continue;
                }
                
                // 60% chance of session on any given weekday
                if ($this->randomProbability(60)) {
                    $sessionId = $this->generateUuid();
                    $sessionTime = clone $currentDate;
                    $sessionTime->setTime(mt_rand(8, 18), mt_rand(0, 59));
                    $duration = mt_rand(300, 3600); // 5-60 minutes
                    
                    DB::table('activity_tracking_sessions')->insert([
                        'id' => $sessionId,
                        'visitor_id' => $visitorId,
                        'session_id' => 's_' . substr(md5($sessionId), 0, 16),
                        'start_time' => $sessionTime->format('Y-m-d H:i:s'),
                        'end_time' => (clone $sessionTime)->modify("+{$duration} seconds")->format('Y-m-d H:i:s'),
                        'duration' => $duration,
                        'page_views' => mt_rand(5, 20),
                        'ip_address' => $this->faker->ipv4(),
                        'user_agent' => $this->faker->randomElement($this->userAgents),
                        'referrer' => 'direct',
                        'lead_id' => null,
                        'contact_id' => $contactId,
                        'created_at' => $sessionTime->format('Y-m-d H:i:s'),
                        'updated_at' => $sessionTime->format('Y-m-d H:i:s'),
                    ]);
                    $sessionCount++;
                    
                    // Create page views for app usage
                    $pageViewCount += $this->createPageViews($sessionId, $sessionTime, $duration, 'app');
                }
                
                $currentDate->modify('+1 day');
            }
        }
        
        // Create anonymous visitor sessions
        for ($i = 0; $i < 500; $i++) {
            $visitorId = $this->generateUuid();
            $firstVisit = $this->randomDate('-6 months', 'now');
            
            DB::table('activity_tracking_visitors')->insert([
                'id' => $visitorId,
                'visitor_id' => 'v_' . substr(md5(uniqid()), 0, 16),
                'first_visit' => $firstVisit->format('Y-m-d H:i:s'),
                'last_visit' => $firstVisit->format('Y-m-d H:i:s'),
                'total_visits' => 1,
                'lead_id' => null,
                'contact_id' => null,
                'created_at' => $firstVisit->format('Y-m-d H:i:s'),
                'updated_at' => $firstVisit->format('Y-m-d H:i:s'),
            ]);
            $visitorCount++;
            
            // Single session for anonymous visitors
            $sessionId = $this->generateUuid();
            $duration = mt_rand(30, 300); // 30 seconds to 5 minutes
            
            DB::table('activity_tracking_sessions')->insert([
                'id' => $sessionId,
                'visitor_id' => $visitorId,
                'session_id' => 's_' . substr(md5($sessionId), 0, 16),
                'start_time' => $firstVisit->format('Y-m-d H:i:s'),
                'end_time' => (clone $firstVisit)->modify("+{$duration} seconds")->format('Y-m-d H:i:s'),
                'duration' => $duration,
                'page_views' => mt_rand(1, 3),
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->randomElement($this->userAgents),
                'referrer' => $this->getRandomByDistribution($this->referrers),
                'lead_id' => null,
                'contact_id' => null,
                'created_at' => $firstVisit->format('Y-m-d H:i:s'),
                'updated_at' => $firstVisit->format('Y-m-d H:i:s'),
            ]);
            $sessionCount++;
            
            // Create page views
            $pageViewCount += $this->createPageViews($sessionId, $firstVisit, $duration, 'marketing');
        }
        
        echo "  Created {$visitorCount} visitors\n";
        echo "  Created {$sessionCount} sessions\n";
        echo "  Created {$pageViewCount} page views\n";
    }
    
    private function generateSessionDates(\DateTime $start, \DateTime $end): array
    {
        $sessions = [];
        $sessionCount = mt_rand(1, 5);
        
        for ($i = 0; $i < $sessionCount; $i++) {
            $timestamp = mt_rand($start->getTimestamp(), $end->getTimestamp());
            $sessionDate = (new \DateTime())->setTimestamp($timestamp);
            
            // Adjust to business hours
            $hour = (int)$sessionDate->format('H');
            if ($hour < 8) {
                $sessionDate->setTime(mt_rand(8, 10), mt_rand(0, 59));
            } elseif ($hour > 20) {
                $sessionDate->setTime(mt_rand(18, 20), mt_rand(0, 59));
            }
            
            $sessions[] = $sessionDate;
        }
        
        sort($sessions);
        return $sessions;
    }
    
    private function createPageViews(string $sessionId, \DateTime $sessionStart, int $sessionDuration, string $type): int
    {
        $pages = $type === 'app' ? $this->appPages : $this->marketingPages;
        $pageCount = mt_rand(2, min(8, count($pages)));
        $viewedPages = [];
        
        // Always start with home/dashboard
        $currentTime = clone $sessionStart;
        $timePerPage = (int)($sessionDuration / $pageCount);
        
        for ($i = 0; $i < $pageCount; $i++) {
            if ($i === 0) {
                $url = $type === 'app' ? '/app/dashboard' : '/';
            } else {
                // Pick a page based on typical flow
                $url = $this->getNextPage($viewedPages[count($viewedPages) - 1], $pages);
            }
            
            $title = $pages[$url][0] ?? 'Unknown Page';
            $timeOnPage = mt_rand((int)($timePerPage * 0.5), (int)($timePerPage * 1.5));
            
            DB::table('activity_tracking_page_views')->insert([
                'id' => $this->generateUuid(),
                'session_id' => $sessionId,
                'url' => $url,
                'title' => $title,
                'time_on_page' => $timeOnPage,
                'timestamp' => $currentTime->format('Y-m-d H:i:s'),
                'created_at' => $currentTime->format('Y-m-d H:i:s'),
                'updated_at' => $currentTime->format('Y-m-d H:i:s'),
            ]);
            
            $viewedPages[] = $url;
            $currentTime->modify("+{$timeOnPage} seconds");
        }
        
        return $pageCount;
    }
    
    private function getNextPage(string $currentPage, array $pages): string
    {
        // Define typical user flows
        $flows = [
            '/' => ['/features', '/pricing', '/about', '/case-studies'],
            '/features' => ['/pricing', '/demo', '/integrations'],
            '/pricing' => ['/demo', '/free-trial', '/contact'],
            '/blog' => ['/blog/remote-team-management', '/blog/agile-best-practices', '/blog/project-management-tips'],
            '/case-studies' => ['/case-studies/tech-startup-success', '/demo', '/free-trial'],
            '/app/dashboard' => ['/app/projects', '/app/tasks', '/app/reports'],
            '/app/projects' => ['/app/tasks', '/app/calendar', '/app/team'],
            '/app/tasks' => ['/app/projects', '/app/calendar', '/app/dashboard'],
        ];
        
        if (isset($flows[$currentPage])) {
            return $this->faker->randomElement($flows[$currentPage]);
        }
        
        // Random page if no flow defined
        return $this->faker->randomKey($pages);
    }
    
    private function getRandomByDistribution(array $distribution): string
    {
        $rand = mt_rand(1, 100);
        $cumulative = 0;
        
        foreach ($distribution as $value => $percentage) {
            $cumulative += $percentage;
            if ($rand <= $cumulative) {
                return $value === 'direct' ? '' : $value;
            }
        }
        
        return '';
    }
}