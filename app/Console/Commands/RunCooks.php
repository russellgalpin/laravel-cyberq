<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use App\Services\Guru\CyberQ;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunCooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cooks:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the temperatures for all active cooks';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Cook::query()
            ->where('started_at', '<=', Carbon::now())
            ->where(function ($query) {
                $query->where('ended_at', '>=', Carbon::now())
                    ->orWhereNull('ended_at');
            })
            ->with(['guru', 'guru.probes'])
            ->get()
            ->each(function (Cook $cook) {
                $cyberQ = app(CyberQ::class, ['guru' => $cook->guru]);
                $cook->guru->probes->each(function (Probe $probe) use ($cyberQ, $cook) {
                    Reading::create([
                        'cook_id' => $cook->id,
                        'probe_id' => $probe->id,
                        'temperature' => $cyberQ->getProbeTemperature($probe)
                    ]);
                });
            });

        return Command::SUCCESS;
    }
}
