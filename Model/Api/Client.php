<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Talks to the CookieScript scan service (the same one the WordPress plugin uses).
 *
 * Every method either returns what the service answered or throws a LocalizedException
 * whose message is safe to show in the admin.
 */
class Client
{
    private const API_URL = 'https://cookie-script.com/api/wp-scan';
    private const SCRIPT_HOST = 'cookie-script.com';

    private const API_TIMEOUT_SECONDS = 45;
    private const SCRIPT_TIMEOUT_SECONDS = 30;
    private const MAX_SCRIPT_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Asks whether this site can be scanned right now.
     *
     * @return array<string, mixed> Contains an "error" key when it cannot.
     * @throws LocalizedException
     */
    public function check(string $secret): array
    {
        return $this->get('check', $secret);
    }

    /**
     * Current state of the scan: "status" (progress, analyze, finish) and, once finished,
     * "script_url" and "grouped_cookies".
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function info(string $secret): array
    {
        return $this->get('info', $secret);
    }

    /**
     * Starts scanning a website.
     *
     * @param array<string, mixed> $payload wp_id, url, privacy_policy_url and lang.
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function start(array $payload): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::API_TIMEOUT_SECONDS);
        $curl->addHeader('Content-Type', 'application/json; charset=utf-8');
        $curl->addHeader('Accept', 'application/json');

        try {
            $curl->post(self::API_URL . '/start', $this->json->serialize($payload));
        } catch (\Exception $exception) {
            throw $this->unreachable($exception);
        }

        return $this->decode((string) $curl->getBody());
    }

    /**
     * Downloads the banner script the scan produced.
     *
     * Only https URLs on cookie-script.com (or one of its subdomains) are accepted, so a tampered
     * API answer can never make the server fetch an internal address.
     *
     * @throws LocalizedException
     */
    public function downloadScript(string $url): string
    {
        $url = $this->normalizeScriptUrl($url);

        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::SCRIPT_TIMEOUT_SECONDS);
        $curl->setOption(CURLOPT_FOLLOWLOCATION, false);

        try {
            $curl->get($url);
        } catch (\Exception $exception) {
            throw $this->unreachable($exception);
        }

        $script = (string) $curl->getBody();
        if ($curl->getStatus() !== 200 || trim($script) === '' || strlen($script) > self::MAX_SCRIPT_BYTES) {
            $this->logger->error('CookieScript: unexpected banner script response.', [
                'url' => $url,
                'status' => $curl->getStatus(),
                'bytes' => strlen($script),
            ]);
            throw new LocalizedException(__('The banner script could not be downloaded from CookieScript.'));
        }

        return $script;
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function get(string $endpoint, string $secret): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::API_TIMEOUT_SECONDS);
        $curl->addHeader('Accept', 'application/json');

        try {
            $curl->get(self::API_URL . '/' . $endpoint . '?' . http_build_query(['wp_id' => $secret]));
        } catch (\Exception $exception) {
            throw $this->unreachable($exception);
        }

        return $this->decode((string) $curl->getBody());
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function decode(string $body): array
    {
        try {
            $data = $this->json->unserialize($body);
        } catch (\InvalidArgumentException) {
            $data = null;
        }

        if (!is_array($data)) {
            $this->logger->error('CookieScript: the scan service did not answer with JSON.', ['body' => substr($body, 0, 500)]);
            throw new LocalizedException(__('The CookieScript scan service gave an unexpected answer. Please try again later.'));
        }

        return $data;
    }

    /**
     * Turns the script address from the API into a safe https URL, or throws.
     *
     * @throws LocalizedException
     */
    private function normalizeScriptUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        $hasPortOrCredentials = isset($parts['port']) || isset($parts['user']) || isset($parts['pass']);

        $isSafe = in_array($scheme, ['http', 'https'], true)
            && !$hasPortOrCredentials
            && ($host === self::SCRIPT_HOST || str_ends_with($host, '.' . self::SCRIPT_HOST));

        if (!$isSafe) {
            throw new LocalizedException(__('The CookieScript scan service returned a banner script address that is not allowed.'));
        }

        return preg_replace('#^http://#i', 'https://', $url);
    }

    private function unreachable(\Exception $exception): LocalizedException
    {
        $this->logger->error('CookieScript: request failed: ' . $exception->getMessage());

        return new LocalizedException(__('Could not reach the CookieScript scan service. Please try again later.'));
    }
}
