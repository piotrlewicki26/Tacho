<?php
declare(strict_types=1);
namespace Parsers;

/**
 * Binary DDD tachograph file parser.
 *
 * Supports EU digital tachograph files as per:
 *  - EC Regulation 3821/85 (Generation 1)
 *  - EC Regulation 165/2014 (Generation 2)
 *
 * Activity record (2 bytes, big-endian):
 *   bit 15     : slot  (0 = driver, 1 = co-driver)
 *   bits 14-12 : type  (0 = work, 1 = available, 2 = rest/break, 3 = driving)
 *   bits 11-0  : time  (minutes since midnight, 0-1439)
 */
class DDDParser
{
    private const FILE_TYPE_CARD   = 'driver_card';
    private const FILE_TYPE_VU     = 'tachograph';

    // Known DDD block tags (subset of the standard)
    private const TAG_CARD_ICC     = 0x0520; // CardIccIdentification
    private const TAG_CARD_NUMBER  = 0x0521; // DriverCardHolderIdentification
    private const TAG_DRV_ID       = 0x0522; // DriverCardHolderName
    private const TAG_ACTIVITY     = 0x0530; // CardActivityDailyRecord
    private const TAG_VEH_USED     = 0x0567; // CardVehicleEntry

    private string $data;
    private int    $len;

    public function __construct(string $binaryData)
    {
        $this->data = $binaryData;
        $this->len  = strlen($binaryData);
    }

    /** @throws \RuntimeException on parse failure */
    public function parse(): array
    {
        if ($this->len < 4) {
            throw new \RuntimeException('File too small to be a valid DDD file.');
        }

        $fileType   = $this->detectFileType();
        $driverInfo = $this->parseDriverIdentification();
        $vehicleInfo = $this->parseVehicleIdentification();
        $activities = $this->parseActivities();

        return [
            'file_type'    => $fileType,
            'driver'       => $driverInfo,
            'vehicle'      => $vehicleInfo,
            'activities'   => $activities,
            'record_count' => count($activities),
        ];
    }

    // ── File type detection ────────────────────────────────────────────────

    private function detectFileType(): string
    {
        // Driver cards start with specific byte sequences
        // VU files have different magic bytes
        $header = substr($this->data, 0, 4);
        $first  = ord($header[0]);

        // Heuristic: most driver card files begin with 0x05 (tag high byte)
        if ($first === 0x05) return self::FILE_TYPE_CARD;

        // VU files often start with vehicle unit identification block
        if ($first === 0x76 || $first === 0x00) return self::FILE_TYPE_VU;

        // Default
        return self::FILE_TYPE_CARD;
    }

    // ── Driver / Vehicle identification ───────────────────────────────────

    private function parseDriverIdentification(): array
    {
        $info = [
            'surname'       => '',
            'first_name'    => '',
            'birth_date'    => null,
            'card_number'   => '',
            'nationality'   => '',
        ];

        $offset = 0;
        while ($offset < $this->len - 4) {
            $tag    = $this->readUint16($offset);
            $length = $this->readUint16($offset + 2);
            $offset += 4;

            if ($length <= 0 || $offset + $length > $this->len) break;

            if ($tag === self::TAG_DRV_ID && $length >= 26) {
                // Structure: driverSurname (36 bytes), driverFirstNames (36 bytes)
                $info['surname']    = rtrim(substr($this->data, $offset, 36));
                $info['first_name'] = rtrim(substr($this->data, $offset + 36, 36));
            }

            if ($tag === self::TAG_CARD_NUMBER && $length >= 18) {
                // cardNumber: nation(1) + type(1) + issuingMemberState(3) + serial(14)
                $info['nationality']  = rtrim(substr($this->data, $offset + 2, 3));
                $info['card_number']  = rtrim(substr($this->data, $offset + 5, 13));
            }

            $offset += $length;
        }

        return $info;
    }

    private function parseVehicleIdentification(): array
    {
        $info = ['registration' => '', 'vin' => '', 'nation' => ''];

        $offset = 0;
        while ($offset < $this->len - 4) {
            $tag    = $this->readUint16($offset);
            $length = $this->readUint16($offset + 2);
            $offset += 4;

            if ($length <= 0 || $offset + $length > $this->len) break;

            if ($tag === self::TAG_VEH_USED && $length >= 8) {
                // VehicleRegistrationIdentification: nation(3) + VRN(14) + VIN(17)
                $info['nation']       = rtrim(substr($this->data, $offset, 3));
                $info['registration'] = rtrim(substr($this->data, $offset + 3, 14));
                if ($length >= 34) {
                    $info['vin'] = rtrim(substr($this->data, $offset + 17, 17));
                }
            }

            $offset += $length;
        }

        return $info;
    }

    // ── Activity parsing ──────────────────────────────────────────────────

    private function parseActivities(): array
    {
        $activities = [];
        $offset     = 0;

        while ($offset < $this->len - 4) {
            $tag    = $this->readUint16($offset);
            $length = $this->readUint16($offset + 2);
            $offset += 4;

            if ($length <= 0 || $offset + $length > $this->len) break;

            if ($tag === self::TAG_ACTIVITY) {
                $block      = substr($this->data, $offset, $length);
                $blockActs  = $this->parseActivityBlock($block);
                $activities = array_merge($activities, $blockActs);
            }

            $offset += $length;
        }

        // If no TLV-structured activities found, try raw scan
        if (empty($activities)) {
            $activities = $this->rawActivityScan();
        }

        return $activities;
    }

    /**
     * Parse a CardActivityDailyRecord block.
     * Structure: date(4) + presencecounter(2) + distancecovered(2) + activityRecords(n×2)
     */
    private function parseActivityBlock(string $block): array
    {
        $bLen = strlen($block);
        if ($bLen < 8) return [];

        // Date: seconds since 1970-01-01 (4 bytes big-endian)
        $timestamp  = $this->readUint32BE($block, 0);
        $date       = $timestamp > 0 ? date('Y-m-d', $timestamp) : date('Y-m-d');

        $actOffset  = 8; // skip date(4) + presence(2) + distance(2)
        $activities = [];
        $prevMinute = 0;

        while ($actOffset + 1 < $bLen) {
            $word    = (ord($block[$actOffset]) << 8) | ord($block[$actOffset + 1]);
            $actOffset += 2;

            // bit 15: slot (0=driver, 1=co-driver) – only process driver slot
            $slot     = ($word >> 15) & 0x1;
            // bits 14-12: activity type
            $typeCode = ($word >> 12) & 0x7;
            // bits 11-0: time in minutes from midnight
            $minutes  = $word & 0x0FFF;

            if ($slot !== 0) continue; // skip co-driver slot

            $duration    = $minutes - $prevMinute;
            $prevMinute  = $minutes;

            if ($duration <= 0) continue;

            $startTime = sprintf('%02d:%02d:00', intdiv($minutes - $duration, 60), ($minutes - $duration) % 60);
            $endTime   = sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);

            $activities[] = [
                'activity_date'    => $date,
                'start_time'       => $startTime,
                'end_time'         => $endTime,
                'duration_minutes' => $duration,
                'activity_type'    => $this->codeToType($typeCode),
                'slot'             => $slot + 1,
                'country_code'     => null,
            ];
        }

        return $activities;
    }

    /**
     * Fallback: scan file for plausible 2-byte activity records.
     * Used when TLV parsing finds no activity blocks.
     */
    private function rawActivityScan(): array
    {
        $activities  = [];
        $dateBase    = date('Y-m-d');
        $prevMinutes = 0;
        $dayMinutes  = 0;

        // Walk through in 2-byte steps looking for realistic activity records
        for ($i = 0; $i + 1 < $this->len; $i += 2) {
            $word     = (ord($this->data[$i]) << 8) | ord($this->data[$i + 1]);
            $slot     = ($word >> 15) & 0x1;
            $typeCode = ($word >> 12) & 0x7;
            $minutes  = $word & 0x0FFF;

            if ($slot !== 0) continue;
            if ($minutes === 0 || $minutes > 1440) continue;
            if ($typeCode > 3) continue;

            if ($minutes <= $prevMinutes) {
                // New day
                $dayMinutes  = $prevMinutes;
                $prevMinutes = 0;
            }

            $duration = $minutes - $prevMinutes;
            if ($duration <= 0 || $duration > 660) { // max 11 h per record
                $prevMinutes = $minutes;
                continue;
            }

            $start = $prevMinutes;
            $activities[] = [
                'activity_date'    => $dateBase,
                'start_time'       => sprintf('%02d:%02d:00', intdiv($start, 60), $start % 60),
                'end_time'         => sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60),
                'duration_minutes' => $duration,
                'activity_type'    => $this->codeToType($typeCode),
                'slot'             => 1,
                'country_code'     => null,
            ];

            $prevMinutes = $minutes;
        }

        return array_slice($activities, 0, 500); // safety limit
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function codeToType(int $code): string
    {
        switch ($code) {
            case 0: return 'work';
            case 1: return 'availability';
            case 2: return 'rest';
            case 3: return 'driving';
            default: return 'work';
        }
    }

    private function readUint16(int $offset): int
    {
        if ($offset + 1 >= $this->len) return 0;
        return (ord($this->data[$offset]) << 8) | ord($this->data[$offset + 1]);
    }

    private function readUint32BE(string $buf, int $offset): int
    {
        if ($offset + 3 >= strlen($buf)) return 0;
        return (ord($buf[$offset]) << 24) | (ord($buf[$offset+1]) << 16)
             | (ord($buf[$offset+2]) << 8)  |  ord($buf[$offset+3]);
    }
}
