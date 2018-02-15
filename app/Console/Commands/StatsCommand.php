<?php

namespace App\Console\Commands;

use Spatie\Analytics\Period;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Spatie\Analytics\Analytics;
use Illuminate\Support\Collection;

class StatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print a brief overview of visitor stats';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Analytics $analytics)
    {
        $yesterday = $analytics
            ->fetchTotalVisitorsAndPageViews(Period::days(1))
            ->sum('pageViews');

        $lastWeek = $analytics
            ->fetchTotalVisitorsAndPageViews(Period::days(7))
            ->sum('pageViews');

        $last30Days = $analytics
            ->fetchTotalVisitorsAndPageViews(Period::create(Carbon::now()->subDays(30), Carbon::now()))
            ->sum('pageViews');

        $previous30Days = $analytics
            ->fetchTotalVisitorsAndPageViews(Period::create(Carbon::now()->subDays(60), Carbon::now()->subDays(30)))
            ->sum('pageViews');

        $averagePer30Days = $analytics
            ->fetchTotalVisitorsAndPageViews(Period::years(1))
            ->groupBy(function (array $result) {
                return $result['date']->month;
            })
            ->map(function (Collection $results) {
                return $results->sum('pageViews');
            })
            ->average();

        $this->table([], [
            ['Yesterday', $yesterday],
            ['Last week', $lastWeek],
            ['30 days', $last30Days],
            ['30 days before', $previous30Days],
            ['30 days 12 month average', round($averagePer30Days)],
        ]);
    }
}
