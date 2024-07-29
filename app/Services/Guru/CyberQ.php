<?php

namespace App\Services\Guru;

use App\Models\Guru;
use App\Models\Probe;
use Illuminate\Support\Facades\Http;

class CyberQ
{
    protected $guru;
    public $temperatures;
    public $setPoints;

    public function __construct(Guru $guru)
    {
        $this->guru = $guru;
    }

    public function getProbeTemperature(Probe $probe)
    {
        return $this->getTemperatures()[$probe->identifier];
    }

    public function getProbeSetPoint(Probe $probe)
    {
        return isset($this->getSetPoints()[str_replace('TEMP', 'SET', $probe->identifier)]) ?
            $this->getSetPoints()[str_replace('TEMP', 'SET', $probe->identifier)] :
            null;
    }

    public function getTemperatures()
    {
        if (! $this->temperatures) {
            $this->temperatures = json_decode(json_encode(simplexml_load_string( $this->get('status.xml'))), true);
        }

        return $this->temperatures;
    }

    public function getSetPoints()
    {
        if (! $this->setPoints) {
            $this->setPoints = collect(explode("\r\n", $this->get('')))
                ->filter(fn($line) => preg_match('/document.mainForm._.*_SET.value = /', $line))
                ->mapWithKeys(function ($line, $key) {
                    $setPoint = explode(',', preg_replace('/.*document.mainForm._(.*).value.*TempPICToHTML\((\d+).*/', "$1,$2", $line));
                    return [
                        $setPoint[0] => $setPoint[1]
                    ];
                })
                ->toArray();
        }

        return $this->setPoints;
    }

    protected function get($url): string
    {
        return Http::withBasicAuth($this->guru->username, $this->guru->password)
            ->get(sprintf('http://%s/' . $url, $this->guru->ip))
            ->body();
    }

    protected function post()
    {

    }
}
