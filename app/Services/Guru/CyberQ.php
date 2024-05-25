<?php

namespace App\Services\Guru;

use App\Models\Guru;
use App\Models\Probe;
use Illuminate\Support\Facades\Http;

class CyberQ
{
    protected $guru;
    public $temperatures;

    public function __construct(Guru $guru)
    {
        $this->guru = $guru;
    }

    public function getProbeTemperature(Probe $probe)
    {
        return $this->getTemperatures()[$probe->identifier];
    }

    public function getTemperatures()
    {
        if (! $this->temperatures) {
		$response = Http::withBasicAuth($this->guru->username, $this->guru->password)
			->get(sprintf('http://%s/status.xml', $this->guru->ip));
            $this->temperatures = json_decode(json_encode(simplexml_load_string($response->body())), true);
        }

        return $this->temperatures;
    }
}
