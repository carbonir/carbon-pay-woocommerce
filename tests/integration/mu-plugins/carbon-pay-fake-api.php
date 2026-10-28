<?php
/**
 * Integration tests only: replaces the Carbon API with queued responses stored in options.
 *   cpw_responses: list of [status, body] consumed in order
 *   cpw_requests:  log of [url, payload]
 */

add_filter('carbon_pay_http_client', static function () {
    return new class implements Carbon\Pay\Api\HttpClient {
        public function postJson(string $url, array $payload): Carbon\Pay\Api\Response
        {
            $log = get_option('cpw_requests', []);
            $log[] = ['url' => $url, 'payload' => $payload];
            update_option('cpw_requests', $log, false);

            $queue = get_option('cpw_responses', []);
            $next = array_shift($queue);
            update_option('cpw_responses', $queue, false);

            if (!$next) {
                return new Carbon\Pay\Api\Response(500, '{"type":"server_error","message":"no fake response queued"}');
            }

            return new Carbon\Pay\Api\Response((int) $next[0], (string) wp_json_encode($next[1]));
        }
    };
});
