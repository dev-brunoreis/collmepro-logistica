<?php

namespace App\Services\RateLimiter;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;

class RateLimiter
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public static function for(string $provider): self
    {
        $providerConfig = config("rate_limits.providers.$provider") ?: config('rate_limits.defaults', []);
        return new self($providerConfig);
    }

    public function http(PendingRequest $request, string $provider): PendingRequest
    {
        $backoff = Arr::get($this->config, 'backoff', ['base' => 300, 'cap' => 4000, 'jitter' => true]);

        return $request
            ->beforeSending(function () use ($provider) {
                $this->acquire($provider, 1, 5000);
            })
            ->retry(
                5,
                function (int $attempt) use ($backoff) {
                    $base = (int) ($backoff['base'] ?? 300);
                    $cap = (int) ($backoff['cap'] ?? 4000);
                    $delay = min($cap, $base * (2 ** ($attempt - 1)));
                    if (!empty($backoff['jitter'])) {
                        $delay = random_int((int) ($delay * 0.5), (int) $delay);
                    }
                    return (int) $delay;
                },
                function ($exception, $req) {
                    return true; // retry on any exception/response handled internally by client
                }
            );
    }

    public function acquire(string $provider, int $tokens = 1, int $timeoutMs = 2000): bool
    {
        $key = "rl:$provider:bucket";
        $nowMs = (int) floor(microtime(true) * 1000);
        $rps = max(1, (int) ($this->config['rps'] ?? 1));
        $burst = max(1, (int) ($this->config['burst'] ?? 1));

        $script = <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local rps = tonumber(ARGV[2])
local burst = tonumber(ARGV[3])
local tokens = tonumber(ARGV[4])

local data = redis.call('HMGET', key, 'tokens', 'ts')
local available = tonumber(data[1]) or burst
local last = tonumber(data[2]) or now
local refill = math.floor(((now - last) / 1000) * rps)
available = math.min(burst, available + refill)

if available >= tokens then
  available = available - tokens
  redis.call('HMSET', key, 'tokens', available, 'ts', now)
  redis.call('PEXPIRE', key, 5000)
  return 1
else
  redis.call('HMSET', key, 'tokens', available, 'ts', now)
  redis.call('PEXPIRE', key, 5000)
  return 0
end
LUA;

        $deadline = $nowMs + max(0, $timeoutMs);
        do {
            $ok = Redis::eval($script, 1, $key, $nowMs, $rps, $burst, $tokens);
            if ($ok) {
                return true;
            }
            usleep(100000); // 100ms
            $nowMs = (int) floor(microtime(true) * 1000);
        } while ($nowMs < $deadline);

        return false;
    }
}


