<?php

require_once __DIR__ . '/BaseProvider.php';

class StratoProvider extends BaseProvider {
    public function updateIp(string $ip): bool {
        $user = $this->credentials['stratoUser'] ?? null;
        $pass = $this->credentials['stratoPass'] ?? null;

        if (!$user || !$pass) {
            $this->logger->error("Missing credentials for Strato: {$this->domain}");
            return false;
        }

        // IPv4-only
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $this->logger->error("Invalid IPv4 for {$this->domain}: $ip");
            return false;
        }

        $myip = $ip . ',::'; // IPv6 disabled → AAAA stays "::"

        $url = "https://{$user}:{$pass}@dyndns.strato.com/nic/update?hostname={$this->domain}&myip={$myip}";

        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: CronDNS v1.0 (https://github.com/TRC-Loop/CronDNS)\r\n",
                'ignore_errors' => true,
                'timeout' => 10
            ],
            'socket' => [
                'bindto' => '0.0.0.0:0' // force IPv4
            ]
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $this->logger->error("Strato update failed for {$this->domain}");
            return false;
        }

        $result = trim(strtolower($result));

        // Strato success: "good" or "nochg"
        if (str_starts_with($result, 'good') || str_starts_with($result, 'nochg')) {
            $this->logger->info("Strato updated {$this->domain} → $result (IPv4: $ip, IPv6 disabled)");
            return true;
        }

        $this->logger->error("Strato returned unexpected response for {$this->domain}: $result");
        return false;
    }
}
