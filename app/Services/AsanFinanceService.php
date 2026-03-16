<?php

namespace App\Services;

use App\Exceptions\EgovException;
use App\Exceptions\UnreportableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AsanFinanceService
{
    private const STATUS_SUCCESS          = 0;
    private const STATUS_VALIDATION_ERROR = 1;
    private const STATUS_SERVICE_ERROR    = 2;
    private const STATUS_EXTERNAL_ERROR   = 3;
    private const STATUS_RESTRICTED       = 4;
    private const STATUS_PIN_VALIDATION   = 5;

    private const MYGOV_ACTIVATION_MESSAGE =
        'MyGov tətbiqinə daxil olaraq "İcazələrin idarə edilməsi" bölməsindən ' .
        '"Fərdi məlumatlar" hissəsinə keçid edin və "InvestAZ İnvestisiya Şirkəti" ' .
        'QSC üçün sorğulanmanı aktivləşdirin. Sorğulanma aktiv edildikdən sonra, ' .
        'yenidən InvestAZ tətbiqində FİN kodu və seriya nömrəsini daxil edin.';

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(config('asanfinance.base_uri'))
            ->timeout(config('asanfinance.timeout', 10))
            ->withOptions(['verify' => config('asanfinance.verify_ssl_peer', false)])
            ->withHeaders([
                'ApiKey'       => config('asanfinance.key'),
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ]);
    }

    public function getPersonalInfoByFin(string $fin): array
    {
        $response = $this->client()
            ->withHeader('RequestIdentifier', Str::uuid()->toString())
            ->get("/api/v1/PersonalInfo/{$fin}");

        return $this->handleResponse($response->json());
    }

    public function getPersonalInfoByFinAndDoc(string $fin, string $docNumber): array
    {
        $response = $this->client()
            ->withHeader('RequestIdentifier', Str::uuid()->toString())
            ->get('/api/v1/PersonalInfo/PinAndDocNumber', [
                'pin'       => $fin,
                'docNumber' => $docNumber,
            ]);

        return $this->handleResponse($response->json());
    }

    public function getResidenceInfo(string $fin): array
    {
        $response = $this->client()
            ->withHeader('RequestIdentifier', Str::uuid()->toString())
            ->get("/api/v1/DMXInfo/{$fin}");

        return $this->handleResponse($response->json());
    }

    public function getEmployeeInfo(string $fin): array
    {
        $response = $this->client()
            ->withHeader('RequestIdentifier', Str::uuid()->toString())
            ->get("/api/v2/EmployeeInfo/{$fin}");

        return $this->handleResponse($response->json());
    }

    public function getBalance(): array
    {
        $today = now()->format('Y-m-d');

        $response = $this->client()
            ->get('/api/v1/info/balance', [
                'StartDate' => $today,
                'EndDate'   => $today,
                'Offset'    => 0,
                'Limit'     => 10,
            ]);

        return $this->handleResponse($response->json());
    }

    private function handleResponse(array $jsonResponse): array
    {
        $status = $jsonResponse['Status'];

        if (!is_null($jsonResponse['Response'])) {
            return $jsonResponse;
        }

        match ($status['Code']) {
            self::STATUS_SUCCESS          => throw new EgovException($status['Message'] ?: 'Məlumat tapılmadı', 404),
            self::STATUS_VALIDATION_ERROR => throw new EgovException($status['Message'], 422),
            self::STATUS_SERVICE_ERROR    => throw new EgovException($status['Message'], 502),
            self::STATUS_EXTERNAL_ERROR   => throw new EgovException($status['Message'], 502),
            self::STATUS_RESTRICTED       => throw new UnreportableException(self::MYGOV_ACTIVATION_MESSAGE, 450),
            self::STATUS_PIN_VALIDATION   => throw new EgovException($status['Message'], 422),
            default                       => throw new EgovException($status['Message'], 400),
        };
    }
}
