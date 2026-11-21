<?php

declare(strict_types=1);

namespace App\Manager;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiviaManager
{
    private const ORIGIN_URL = 'https://nws-main.hove.io';
    private const CLIENT_NAME = 'divia';
    private const COVERAGE = 'fr-ne-dijon';
    private const PBKDF2_SALT = 'mon-sel-fixe-ou-dynamique';
    private const PBKDF2_ITERATIONS = 100_000;
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * @param 'bus'|'tramway' $mode
     * @return list<array<string, mixed>>
     */
    public function listLines(string $mode = 'tramway'): array
    {
        $normalizedMode = strtolower($mode) === 'bus' ? 'Bus' : 'Tramway';

        $res = $this->fetchData(
            pathname: sprintf('/v1/coverage/%s/lines', self::COVERAGE),
            query: [
                'count' => '300',
                'disable_geojson' => 'true',
                'filter' => sprintf('physical_mode.id=physical_mode:%s', $normalizedMode),
            ],
        );

        $lines = $res['lines'] ?? [];
        if (!is_array($lines)) {
            return [];
        }

        return array_map(fn(array $line): array => $this->formatLine($line), $lines);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listStops(string $lineId): array
    {
        $res = $this->fetchData(
            pathname: sprintf('/v1/coverage/%s/lines/%s/stop_areas', self::COVERAGE, $lineId),
            query: [
                'count' => '100',
                'depth' => '3',
            ],
        );

        $stopAreas = $res['stop_areas'] ?? [];
        if (!is_array($stopAreas)) {
            return [];
        }

        return array_map(fn(array $stopArea): array => $this->formatStopArea($stopArea), $stopAreas);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSchedules(
        string $lineId,
        string $stopAreaId,
        ?string $directionPrefix = null,
        ?int $limitPerDirection = null,
        bool $onlyWithDepartures = true,
    ): array {
        $nowParis = new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
        $formattedDatetime = $nowParis->format('Ymd\THis');

        $res = $this->fetchData(
            pathname: sprintf(
                '/v1/coverage/%s/lines/%s/stop_areas/%s/terminus_schedules',
                self::COVERAGE,
                $lineId,
                $stopAreaId,
            ),
            query: [
                'count' => '10',
                'data_freshness' => 'realtime',
                'disable_geojson' => 'true',
                'from_datetime' => $formattedDatetime,
                'items_per_schedule' => '600',
            ],
        );

        $schedules = $res['terminus_schedules'] ?? [];
        if (!is_array($schedules)) {
            return [];
        }

        $formatted = array_map(
            fn(array $schedule): array => $this->formatSchedule($schedule, $limitPerDirection),
            $schedules,
        );

        if ($onlyWithDepartures) {
            $formatted = array_values(array_filter(
                $formatted,
                static fn(array $item): bool => !empty($item['date_times']),
            ));
        }

        if ($directionPrefix !== null && trim($directionPrefix) !== '') {
            $needle = mb_strtolower(trim($directionPrefix));

            $formatted = array_values(array_filter(
                $formatted,
                static function (array $item) use ($needle): bool {
                    $candidates = [
                        (string) ($item['route']['direction']['name'] ?? ''),
                        (string) ($item['display_informations']['direction'] ?? ''),
                        (string) ($item['display_informations']['headsign'] ?? ''),
                    ];

                    foreach ($candidates as $candidate) {
                        if ($candidate !== '' && str_starts_with(mb_strtolower($candidate), $needle)) {
                            return true;
                        }
                    }

                    return false;
                },
            ));
        }

        return $formatted;
    }

    /**
     * Format léger prêt à consommer côté Ionic Vue.
     *
     * @param list<array<string, mixed>> $schedules
     * @return list<array<string, mixed>>
     */
    public function simplifySchedules(array $schedules): array
    {
        return array_map(static function (array $item): array {
            $display = is_array($item['display_informations'] ?? null) ? $item['display_informations'] : [];
            $routeDir = is_array($item['route']['direction'] ?? null) ? $item['route']['direction'] : [];
            $stopPoint = is_array($item['stop_point'] ?? null) ? $item['stop_point'] : [];
            $dateTimes = is_array($item['date_times'] ?? null) ? $item['date_times'] : [];

            return [
                'line_code' => $display['code'] ?? $display['label'] ?? null,
                'color' => $display['color'] ?? null,
                'text_color' => $display['text_color'] ?? null,
                'stop_name' => $stopPoint['name'] ?? null,
                'stop_point_id' => $stopPoint['id'] ?? null,
                'direction' => $display['direction'] ?? ($routeDir['name'] ?? null),
                'headsign' => $display['headsign'] ?? null,
                'departures' => array_map(static fn(array $dt): array => [
                    'date_time' => $dt['date_time'] ?? null,
                    'base_date_time' => $dt['base_date_time'] ?? null,
                    'data_freshness' => $dt['data_freshness'] ?? null,
                ], $dateTimes),
            ];
        }, $schedules);
    }

    /**
     * Raccourci pratique : résout ligne + arrêt + direction en un appel (avec cache court).
     *
     * @param 'bus'|'tramway' $mode
     * @return list<array<string, mixed>>
     */
    public function getUpcomingDepartures(
        string $mode,
        string $lineCode,
        string $stopName,
        ?string $directionPrefix = null,
        int $limit = 3,
    ): array {
        $cacheKey = 'divia_departures_' . sha1(json_encode([
            $mode,
            $lineCode,
            $stopName,
            $directionPrefix,
            $limit,
        ], JSON_THROW_ON_ERROR));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use (
            $mode,
            $lineCode,
            $stopName,
            $directionPrefix,
            $limit,
        ): array {
            $item->expiresAfter(15);

            $lines = $this->listLines($mode);
            $line = null;
            foreach ($lines as $candidateLine) {
                if (($candidateLine['code'] ?? null) === $lineCode) {
                    $line = $candidateLine;
                    break;
                }
            }

            if ($line === null) {
                throw new RuntimeException(sprintf('Ligne "%s" introuvable pour le mode "%s".', $lineCode, $mode));
            }

            $stops = $this->listStops((string) $line['id']);
            $stop = null;
            foreach ($stops as $candidateStop) {
                if (($candidateStop['name'] ?? null) === $stopName) {
                    $stop = $candidateStop;
                    break;
                }
            }

            if ($stop === null) {
                throw new RuntimeException(sprintf('Arrêt "%s" introuvable sur la ligne "%s".', $stopName, $lineCode));
            }

            return $this->getSchedules(
                lineId: (string) $line['id'],
                stopAreaId: (string) $stop['id'],
                directionPrefix: $directionPrefix,
                limitPerDirection: $limit,
                onlyWithDepartures: true
            );
        });
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function fetchData(string $pathname, array $query = []): array
    {
        $token = $this->generateToken();

        $presignResponse = $this->httpClient->request('POST', self::ORIGIN_URL . '/api/presign', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Auth' => $token,
                'Origin' => self::ORIGIN_URL,
                'Referer' => self::ORIGIN_URL . '/standalone/schedules/divia',
            ],
            'json' => [
                'clientName' => self::CLIENT_NAME,
                'method' => 'POST',
                'path' => $pathname,
                'query' => $query,
            ],
        ]);

        /** @var array<string, mixed> $presignData */
        $presignData = $presignResponse->toArray();
        $presignedUrl = $presignData['url'] ?? null;

        if (!is_string($presignedUrl) || $presignedUrl === '') {
            throw new RuntimeException('URL pré-signée manquante dans la réponse /api/presign.');
        }

        $queryString = http_build_query($query);
        $href = 'https://api.navitia.io' . $pathname . ($queryString !== '' ? '?' . $queryString : '');

        $proxyResponse = $this->httpClient->request('POST', self::ORIGIN_URL . '/api/proxy', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-auth-token' => $token,
            ],
            'json' => [
                'clientName' => self::CLIENT_NAME,
                'href' => $href,
                'presignedUrl' => $presignedUrl,
            ],
        ]);

        /** @var array<string, mixed> $data */
        $data = $proxyResponse->toArray();

        return $data;
    }

    /**
     * Reproduit generateToken() de api.ts (PBKDF2-SHA256 + AES-256-GCM -> "<iv_b64>.<ct+tag_b64>").
     *
     * @throws JsonException
     */
    private function generateToken(): string
    {
        $aesKey = hash_pbkdf2(
            algo: 'sha256',
            password: self::ORIGIN_URL,
            salt: self::PBKDF2_SALT,
            iterations: self::PBKDF2_ITERATIONS,
            length: 32,
            binary: true,
        );

        $payload = json_encode([
            'clientName' => self::CLIENT_NAME,
            'tokenId' => $this->uuidV4(),
            'iat' => time(),
        ], JSON_THROW_ON_ERROR);

        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            data: $payload,
            cipher_algo: 'aes-256-gcm',
            passphrase: $aesKey,
            options: OPENSSL_RAW_DATA,
            iv: $iv,
            tag: $tag,
            aad: '',
            tag_length: 16,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Échec du chiffrement AES-256-GCM du jeton.');
        }

        return base64_encode($iv) . '.' . base64_encode($ciphertext . $tag);
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private function formatLine(array $line): array
    {
        $routes = is_array($line['routes'] ?? null) ? $line['routes'] : [];

        return [
            'id' => $line['id'] ?? null,
            'name' => $line['name'] ?? null,
            'code' => $line['code'] ?? null,
            'color' => $line['color'] ?? null,
            'text_color' => $line['text_color'] ?? null,
            'codes' => $line['codes'] ?? [],
            'routes' => array_map(fn(array $route): array => $this->formatRoute($route), $routes),
            'opening_time' => isset($line['opening_time']) ? $this->formatTimeString((string) $line['opening_time']) : null,
            'closing_time' => isset($line['closing_time']) ? $this->formatTimeString((string) $line['closing_time']) : null,
        ];
    }

    /**
     * @param array<string, mixed> $route
     * @return array<string, mixed>
     */
    private function formatRoute(array $route): array
    {
        $direction = is_array($route['direction'] ?? null) ? $route['direction'] : [];

        return [
            'id' => $route['id'] ?? null,
            'name' => $route['name'] ?? null,
            'is_frequence' => ($route['is_frequence'] ?? null) === 'True' || ($route['is_frequence'] ?? null) === true,
            'direction_type' => $route['direction_type'] ?? null,
            'codes' => $route['codes'] ?? [],
            'direction' => $this->formatDirection($direction),
        ];
    }

    /**
     * @param array<string, mixed> $direction
     * @return array<string, mixed>
     */
    private function formatDirection(array $direction): array
    {
        $stopArea = is_array($direction['stop_area'] ?? null) ? $direction['stop_area'] : [];

        return [
            'id' => $direction['id'] ?? null,
            'name' => $direction['name'] ?? null,
            'quality' => $direction['quality'] ?? null,
            'stop_area' => $this->formatStopArea($stopArea),
        ];
    }

    /**
     * @param array<string, mixed> $stopArea
     * @return array<string, mixed>
     */
    private function formatStopArea(array $stopArea): array
    {
        $coord = is_array($stopArea['coord'] ?? null) ? $stopArea['coord'] : [];

        return [
            'id' => $stopArea['id'] ?? null,
            'name' => $stopArea['name'] ?? null,
            'codes' => $stopArea['codes'] ?? [],
            'timezone' => $stopArea['timezone'] ?? null,
            'label' => $stopArea['label'] ?? null,
            'coord' => [
                'lon' => isset($coord['lon']) ? (float) $coord['lon'] : null,
                'lat' => isset($coord['lat']) ? (float) $coord['lat'] : null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $schedule
     * @return array<string, mixed>
     */
    private function formatSchedule(array $schedule, ?int $limitPerDirection = null): array
    {
        $dateTimes = is_array($schedule['date_times'] ?? null) ? $schedule['date_times'] : [];
        $formattedDateTimes = array_map(fn(array $dt): array => $this->formatDateTime($dt), $dateTimes);

        if ($limitPerDirection !== null && $limitPerDirection > 0) {
            $formattedDateTimes = array_slice($formattedDateTimes, 0, $limitPerDirection);
        }

        $stopPoint = is_array($schedule['stop_point'] ?? null) ? $schedule['stop_point'] : [];
        $route = is_array($schedule['route'] ?? null) ? $schedule['route'] : [];

        return [
            'stop_point' => $this->formatStopPoint($stopPoint),
            'route' => $this->formatRoute($route),
            'display_informations' => $schedule['display_informations'] ?? [],
            'date_times' => $formattedDateTimes,
            'links' => $schedule['links'] ?? [],
            'first_datetime' => isset($schedule['first_datetime']) && is_array($schedule['first_datetime'])
                ? $this->formatDateTime($schedule['first_datetime'])
                : null,
            'last_datetime' => isset($schedule['last_datetime']) && is_array($schedule['last_datetime'])
                ? $this->formatDateTime($schedule['last_datetime'])
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $stopPoint
     * @return array<string, mixed>
     */
    private function formatStopPoint(array $stopPoint): array
    {
        $coord = is_array($stopPoint['coord'] ?? null) ? $stopPoint['coord'] : [];
        $stopArea = is_array($stopPoint['stop_area'] ?? null) ? $stopPoint['stop_area'] : [];

        return [
            'id' => $stopPoint['id'] ?? null,
            'name' => $stopPoint['name'] ?? null,
            'codes' => $stopPoint['codes'] ?? [],
            'label' => $stopPoint['label'] ?? null,
            'coord' => [
                'lon' => isset($coord['lon']) ? (float) $coord['lon'] : null,
                'lat' => isset($coord['lat']) ? (float) $coord['lat'] : null,
            ],
            'stop_area' => $this->formatStopArea($stopArea),
            'equipments' => $stopPoint['equipments'] ?? [],
            'address' => $stopPoint['address'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $dateTime
     * @return array<string, mixed>
     */
    private function formatDateTime(array $dateTime): array
    {
        return [
            'date_time' => isset($dateTime['date_time']) ? $this->formatDateTimeString((string) $dateTime['date_time']) : null,
            'base_date_time' => isset($dateTime['base_date_time']) ? $this->formatDateTimeString((string) $dateTime['base_date_time']) : null,
            'data_freshness' => $dateTime['data_freshness'] ?? null,
        ];
    }

    private function formatTimeString(string $time): ?string
    {
        if (strlen($time) < 6) {
            return null;
        }

        $hours = (int) substr($time, 0, 2);
        $minutes = (int) substr($time, 2, 2);
        $seconds = (int) substr($time, 4, 2);

        $dt = (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))
            ->setTime($hours, $minutes, $seconds);

        return $dt->format(DateTimeImmutable::ATOM);
    }

    private function formatDateTimeString(string $dateTime): ?string
    {
        $parsed = DateTimeImmutable::createFromFormat(
            'Ymd\THis',
            substr($dateTime, 0, 15),
            new DateTimeZone(self::TIMEZONE),
        );

        if ($parsed === false) {
            return null;
        }

        return $parsed->format(DateTimeImmutable::ATOM);
    }
}
