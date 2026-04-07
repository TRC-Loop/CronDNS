<?php

require_once __DIR__ . '/BaseProvider.php';

class HetznerProvider extends BaseProvider {
    public function updateIp(string $ip): bool {
        $apiToken = $this->credentials['hetznerApiToken'] ?? null;
        $zoneId   = $this->credentials['hetznerZoneId'] ?? null;

        if (!$apiToken || !$zoneId) {
            $this->logger->error("Missing credentials for Hetzner: {$this->domain}");
            return false;
        }

        $recordsUrl = "https://dns.hetzner.com/api/v1/records?zone_id=$zoneId";
        $headers = [
            "Auth-API-Token: $apiToken",
            "Content-Type: application/json"
        ];

        $zoneInfoUrl = "https://dns.hetzner.com/api/v1/zones/$zoneId";
        $chZone = curl_init($zoneInfoUrl);
        curl_setopt_array($chZone, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $zoneResp = curl_exec($chZone);
        curl_close($chZone);

        $zoneName = json_decode($zoneResp, true)['zone']['name'] ?? null;
        if (!$zoneName) {
            $this->logger->error("Failed to get zone name for Hetzner zone $zoneId");
            return false;
        }

        if ($this->domain === $zoneName) {
            $relativeName = '@';
        } elseif (str_ends_with($this->domain, '.' . $zoneName)) {
            $relativeName = substr($this->domain, 0, -(strlen($zoneName) + 1));
        } else {
            $relativeName = $this->domain;
        }

        $ch = curl_init($recordsUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $resp = curl_exec($ch);
        if (!$resp) {
            $this->logger->error("Failed Hetzner DNS fetch for {$this->domain}");
            return false;
        }

        $data = json_decode($resp, true)['records'] ?? [];
        $record = null;
        foreach ($data as $r) {
            if ($r['name'] === $relativeName && $r['type'] === 'A') {
                $record = $r;
                break;
            }
        }

        if (!$record) {
            $this->logger->error("No DNS record found for {$this->domain}");
            return false;
        }

        $updateUrl = "https://dns.hetzner.com/api/v1/records/{$record['id']}";
        $payload = json_encode([
            'value'   => $ip,
            'name'    => $record['name'],
            'type'    => $record['type'],
            'ttl'     => 120,
            'zone_id' => $zoneId
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL => $updateUrl,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => $payload
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $ok = strpos($resp, '"record"') !== false;
        if ($ok) $this->logger->info("Hetzner updated {$this->domain} to $ip");
        else     $this->logger->error("Hetzner update failed for {$this->domain}: $resp");

        return $ok;
    }
}
