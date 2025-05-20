<?php

namespace App\TravelClick\Jobs\OutboundJobs;

use App\TravelClick\Builders\RateXmlBuilder;
use App\TravelClick\DTOs\RateData;
use App\TravelClick\DTOs\RatePlanData;
use App\TravelClick\DTOs\SoapResponseDto;
use App\TravelClick\Enums\MessageType;
use App\TravelClick\Enums\ProcessingStatus;
use App\TravelClick\Enums\RateOperationType;
use App\TravelClick\Events\RateSyncCompleted;
use App\TravelClick\Events\RateSyncFailed;
use App\TravelClick\Exceptions\SoapException;
use App\TravelClick\Exceptions\TravelClickAuthenticationException;
use App\TravelClick\Exceptions\TravelClickConnectionException;
use App\TravelClick\Models\TravelClickLog;
use App\TravelClick\Models\TravelClickSyncStatus;
use App\TravelClick\Services\SoapService;
use App\TravelClick\Support\RetryHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateRatesJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [30, 60, 120];

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 2;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * The unique ID of the job.
     *
     * @var string|null
     */
    private ?string $jobUniqueId = null;

    /**
     * Create a new job instance.
     *
     * @param  Collection<int, RateData>|array<int, RateData>  $rates
     * @param  string  $hotelCode
     * @param  RateOperationType  $operationType
     * @param  bool  $isDeltaUpdate
     * @param  int  $batchSize
     * @param  string|null  $trackingId
     */
    public function __construct(
        private readonly Collection|array $rates,
        private readonly string $hotelCode,
        private readonly RateOperationType $operationType = RateOperationType::RATE_UPDATE,
        private readonly bool $isDeltaUpdate = true,
        private readonly int $batchSize = 0,
        private readonly ?string $trackingId = null
    ) {
        $this->jobUniqueId = $this->generateUniqueId();
        $this->onQueue(config('travelclick.queues.outbound', 'travelclick-outbound'));
    }

    /**
     * Execute the job.
     *
     * @param  SoapService  $soapService
     * @param  RateXmlBuilder  $xmlBuilder
     * @param  RetryHelper  $retryHelper
     * @return void
     */
    public function handle(SoapService $soapService, RateXmlBuilder $xmlBuilder, RetryHelper $retryHelper): void
    {
        $rates = $this->rates instanceof Collection ? $this->rates : collect($this->rates);

        if ($rates->isEmpty()) {
            Log::warning('UpdateRatesJob: No rates provided.', [
                'hotel_code' => $this->hotelCode,
                'operation_type' => $this->operationType->value,
                'tracking_id' => $this->trackingId,
            ]);
            return;
        }

        $startTime = microtime(true);

        try {
            // Update sync status to processing
            $this->updateSyncStatus(ProcessingStatus::PROCESSING);

            Log::info('UpdateRatesJob: Processing rates', [
                'hotel_code' => $this->hotelCode,
                'operation_type' => $this->operationType->value,
                'rates_count' => $rates->count(),
                'tracking_id' => $this->trackingId,
            ]);

            // Process rates in batches if needed
            $batchSize = $this->getBatchSize();

            $result = $retryHelper->executeWithRetry(
                fn() => $this->processBatches($rates, $batchSize, $soapService, $xmlBuilder),
                'rate_update',
                "hotel_{$this->hotelCode}"
            );

            $duration = round((microtime(true) - $startTime) * 1000);

            Log::info('UpdateRatesJob: Completed successfully', [
                'hotel_code' => $this->hotelCode,
                'operation_type' => $this->operationType->value,
                'duration_ms' => $duration,
                'batches_processed' => $result['batches_processed'],
                'rates_processed' => $result['rates_processed'],
                'tracking_id' => $this->trackingId,
            ]);

            // Update sync status to processed
            $this->updateSyncStatus(ProcessingStatus::PROCESSED);

            // Dispatch completion event
            event(new RateSyncCompleted(
                $this->hotelCode,
                $this->operationType,
                $result['rates_processed'],
                $duration,
                $this->trackingId
            ));

        } catch (TravelClickConnectionException $e) {
            $this->handleConnectionException($e);
        } catch (TravelClickAuthenticationException $e) {
            $this->handleAuthenticationException($e);
        } catch (SoapException $e) {
            $this->handleSoapException($e);
        } catch (Throwable $e) {
            $this->handleGenericException($e);
        }
    }

    /**
     * Process rates in batches.
     *
     * @param  Collection<int, RateData>  $rates
     * @param  int  $batchSize
     * @param  SoapService  $soapService
     * @param  RateXmlBuilder  $xmlBuilder
     * @return array<string, int>
     */
    private function processBatches(
        Collection $rates,
        int $batchSize,
        SoapService $soapService,
        RateXmlBuilder $xmlBuilder
    ): array {
        $batchCount = 0;
        $ratesProcessed = 0;

        // Ya no necesitamos agrupar por tipo de habitación, la clase RatePlanData
        // maneja esto automáticamente

        // Agrupar primero por código de plan de tarifa
        $ratesByPlan = $rates->groupBy(function (RateData $rate) {
            return $rate->ratePlanCode;
        });

        // Procesar cada plan de tarifa
        foreach ($ratesByPlan as $planCode => $planRates) {
            // Crear un RatePlanData para este grupo de tarifas
            $ratePlan = new RatePlanData(
                ratePlanCode: $planCode,
                hotelCode: $this->hotelCode,
                operationType: $this->operationType,
                rates: $planRates,
                ratePlanName: null,
                currencyCode: null, // Se detectará automáticamente de las tarifas
                isLinkedRate: $planRates->first()->isLinkedRate ?? false,
                masterRatePlanCode: $planRates->first()->masterRatePlanCode ?? null,
                isDeltaUpdate: $this->isDeltaUpdate
            );

            // Si el plan de tarifa es muy grande, dividirlo en planes más pequeños
            $planBatches = $ratePlan->splitByDateRanges(30); // Dividir en períodos de 30 días

            // Procesar cada lote
            foreach ($planBatches as $index => $batchPlan) {
                $batchCount++;
                $ratesProcessed += $batchPlan->rates->count();

                // Preparar XML builder con configuración correcta
                $configuredXmlBuilder = clone $xmlBuilder;
                $configuredXmlBuilder->withOperationType($this->operationType)
                    ->withDeltaUpdate($this->isDeltaUpdate);

                // Construir XML de mensaje
                $messageData = [
                    'rate_plans' => [$batchPlan], // Ya tenemos un objeto RatePlanData
                ];

                $xml = $configuredXmlBuilder->buildWithValidation($messageData);

                // Actualizar estado de sincronización a enviando
                $this->updateSyncStatus(ProcessingStatus::SENT);

                // Enviar a TravelClick
                $response = $soapService->updateRates($xml, $this->hotelCode);

                // Manejar respuesta
                $this->handleRateResponse($response, $batchPlan->rates->count());

                // Opcional: pequeña pausa entre lotes para evitar rate limiting
                if ($index < $planBatches->count() - 1) {
                    usleep(500000); // 500ms
                }
            }
        }

        return [
            'batches_processed' => $batchCount,
            'rates_processed' => $ratesProcessed,
        ];
    }

    /**
     * Group rates by room type for optimal processing.
     *
     * @param  Collection<int, RateData>  $rates
     * @return Collection<string, Collection<int, RateData>>
     */
    private function groupRatesByRoomType(Collection $rates): Collection
    {
        return $rates->groupBy(function (RateData $rate) {
            return $rate->roomTypeCode;
        });
    }

    /**
     * Build rate plans from a batch of rates.
     *
     * @param  Collection<int, RateData>  $rates
     * @param  string  $roomTypeCode
     * @return Collection<int, RatePlanData>
     */
    private function buildRatePlansFromBatch(Collection $rates, string $roomTypeCode): Collection
    {
        // Agrupar por plan de tarifa
        $ratesByPlan = $rates->groupBy(function (RateData $rate) {
            return $rate->ratePlanCode;
        });

        return $ratesByPlan->map(function (Collection $planRates, string $planCode) {
            // Obtener la primera tarifa para información básica
            $firstRate = $planRates->first();

            // Crear un plan de tarifa usando el constructor correcto
            return new RatePlanData(
                ratePlanCode: $planCode,
                hotelCode: $this->hotelCode,
                operationType: $this->operationType,
                rates: $planRates,
                ratePlanName: null,  // Opcional
                currencyCode: $firstRate->currencyCode,
                isLinkedRate: $firstRate->isLinkedRate ?? false,
                masterRatePlanCode: $firstRate->masterRatePlanCode ?? null,
                maxGuestApplicable: $firstRate->maxGuestApplicable ?? null,
                isCommissionable: $firstRate->isCommissionable ?? null,
                marketCodes: [], // Array vacío por defecto
                isDeltaUpdate: $this->isDeltaUpdate,
                lastModified: null // Opcional
            );
        });
    }

    /**
     * Handle the rate response.
     *
     * @param  SoapResponseDto  $response
     * @param  int  $rateCount
     * @return void
     */
    private function handleRateResponse(SoapResponseDto $response, int $rateCount): void
    {
        if (!$response->isSuccess) {
            throw new SoapException(
                "Failed to update rates: {$response->errorMessage}",
                $response->messageId,
                context: [
                    'hotel_code' => $this->hotelCode,
                    'operation_type' => $this->operationType->value,
                    'tracking_id' => $this->trackingId,
                    'rates_count' => $rateCount,
                ]
            );
        }

        if ($response->hasWarnings()) {
            Log::warning('UpdateRatesJob: Warnings received from TravelClick', [
                'hotel_code' => $this->hotelCode,
                'operation_type' => $this->operationType->value,
                'message_id' => $response->messageId,
                'warnings' => $response->warnings,
                'tracking_id' => $this->trackingId,
            ]);
        }

        // Update sync status to received
        $this->updateSyncStatus(ProcessingStatus::RECEIVED);

        Log::info('UpdateRatesJob: Batch processed successfully', [
            'hotel_code' => $this->hotelCode,
            'operation_type' => $this->operationType->value,
            'message_id' => $response->messageId,
            'rates_count' => $rateCount,
            'duration_ms' => $response->durationMs,
            'tracking_id' => $this->trackingId,
        ]);
    }

    /**
     * Handle connection exception.
     *
     * @param  TravelClickConnectionException  $exception
     * @return void
     */
    private function handleConnectionException(TravelClickConnectionException $exception): void
    {
        Log::error('UpdateRatesJob: Connection error', [
            'hotel_code' => $this->hotelCode,
            'operation_type' => $this->operationType->value,
            'message' => $exception->getMessage(),
            'tracking_id' => $this->trackingId,
        ]);

        $this->updateSyncStatus(ProcessingStatus::FAILED);
        $this->dispatchFailureEvent($exception);

        // Rethrow for retry mechanism
        throw $exception;
    }

    /**
     * Handle authentication exception.
     *
     * @param  TravelClickAuthenticationException  $exception
     * @return void
     */
    private function handleAuthenticationException(TravelClickAuthenticationException $exception): void
    {
        Log::error('UpdateRatesJob: Authentication error', [
            'hotel_code' => $this->hotelCode,
            'operation_type' => $this->operationType->value,
            'message' => $exception->getMessage(),
            'tracking_id' => $this->trackingId,
        ]);

        $this->updateSyncStatus(ProcessingStatus::FAILED);
        $this->dispatchFailureEvent($exception);

        // Don't retry authentication errors as they will likely fail again
        $this->fail($exception);
    }

    /**
     * Handle SOAP exception.
     *
     * @param  SoapException  $exception
     * @return void
     */
    private function handleSoapException(SoapException $exception): void
    {
        Log::error('UpdateRatesJob: SOAP error', [
            'hotel_code' => $this->hotelCode,
            'operation_type' => $this->operationType->value,
            'message' => $exception->getMessage(),
            'tracking_id' => $this->trackingId,
            'message_id' => $exception->messageId,
        ]);

        $this->updateSyncStatus(ProcessingStatus::FAILED);
        $this->dispatchFailureEvent($exception);

        // Rethrow for retry mechanism
        throw $exception;
    }

    /**
     * Handle generic exception.
     *
     * @param  Throwable  $exception
     * @return void
     */
    private function handleGenericException(Throwable $exception): void
    {
        Log::error('UpdateRatesJob: Unexpected error', [
            'hotel_code' => $this->hotelCode,
            'operation_type' => $this->operationType->value,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'tracking_id' => $this->trackingId,
        ]);

        $this->updateSyncStatus(ProcessingStatus::FAILED);
        $this->dispatchFailureEvent($exception);

        // Rethrow for retry mechanism
        throw $exception;
    }

    /**
     * Dispatch failure event.
     *
     * @param  Throwable  $exception
     * @return void
     */
    private function dispatchFailureEvent(Throwable $exception): void
    {
        event(new RateSyncFailed(
            $this->hotelCode,
            $this->operationType,
            $exception->getMessage(),
            get_class($exception),
            $this->trackingId
        ));
    }

    /**
     * Update sync status.
     *
     * @param  ProcessingStatus  $status
     * @return void
     */
    private function updateSyncStatus(ProcessingStatus $status): void
    {
        try {
            TravelClickSyncStatus::updateOrCreate(
                [
                    'hotel_code' => $this->hotelCode,
                    'message_type' => MessageType::RATES->value,
                ],
                [
                    'status' => $status->value,
                    'last_updated_at' => Carbon::now(),
                    'tracking_id' => $this->trackingId ?? $this->jobUniqueId,
                ]
            );
        } catch (Throwable $e) {
            Log::warning('UpdateRatesJob: Failed to update sync status', [
                'hotel_code' => $this->hotelCode,
                'status' => $status->value,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the batch size for processing.
     *
     * @return int
     */
    private function getBatchSize(): int
    {
        if ($this->batchSize > 0) {
            return $this->batchSize;
        }

        return $this->operationType->getRecommendedBatchSize();
    }

    /**
     * Generate a unique ID for the job.
     *
     * @return string
     */
    private function generateUniqueId(): string
    {
        return sprintf(
            'rate_%s_%s_%s',
            $this->hotelCode,
            $this->operationType->value,
            uniqid('', true)
        );
    }

    /**
     * Get the unique ID for the job.
     *
     * @return string|null
     */
    public function getUniqueId(): ?string
    {
        return $this->jobUniqueId;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new \Illuminate\Queue\Middleware\WithoutOverlapping("rate_update_{$this->hotelCode}"),
            new \Illuminate\Queue\Middleware\RateLimited('travelclick-api'),
        ];
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            "travelclick",
            "rates",
            "hotel:{$this->hotelCode}",
            "op:{$this->operationType->value}",
        ];
    }
}