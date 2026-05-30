<?php

namespace Keloola\Quota\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Keloola\Quota\Facades\Quota;
use Keloola\Quota\Exceptions\QuotaMetricNotFoundException;

class CheckQuota
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $metricCode
     * @param  int|string  $amount
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $metricCode, $amount = 1)
    {
        $metricName = $metricCode;
        $metric = \Keloola\Quota\Models\QuotaMetric::where('code', $metricCode)->first();
        if ($metric) {
            $metricName = $metric->name;
        }

        try {
            if (!Quota::canConsume($metricCode, (int) $amount)) {
                return response()->json([
                    'message' => __('keloola-quota::messages.exceeded_limit', ['metric' => $metricName]),
                ], 429); // 429 Too Many Requests
            }
        } catch (QuotaMetricNotFoundException $e) {
            return response()->json([
                'message' => __('keloola-quota::messages.metric_not_found', ['metric' => $metricName]),
            ], 400);
        } catch (\LogicException $e) {
            return response()->json([
                'message' => __('keloola-quota::messages.context_missing'),
            ], 403);
        }

        return $next($request);
    }
}
