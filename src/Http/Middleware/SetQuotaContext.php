<?php

namespace Keloola\Quota\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Keloola\Quota\Facades\Quota;
use Keloola\Quota\Services\SsoClient;

class SetQuotaContext
{
    public function __construct(protected SsoClient $ssoClient)
    {
    }

    public function handle(Request $request, Closure $next)
    {

        $token = $request->cookie('token');
        if($request->bearerToken()) {
            $token = $request->bearerToken();
        }

        if(!$token)  return response()->json(['message' => 'Unauthorized'], 401);
        

        $data = $request?->sso_user ?? $this->ssoClient->getUserProfile($token);
        if (!$data || !isset($data['applications']) || !is_array($data['applications'])) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // If the app defines its own ID in config, try to match it.
        // Otherwise, fallback to the first application in the list.
        $appId = config('keloola-quota.provisioning.app_id');
        $appData = null;

        if ($appId) {
            foreach ($data['applications'] as $app) {
                $app = (array) $app;
                if (isset($app['id']) && $app['id'] == $appId) {
                    $appData = $app;
                    break;
                }
            }
        }
        
        if (!$appData && count($data['applications']) > 0) {
            $appData = $data['applications'][0];
        }

        if (!$appData) {
            return response()->json(['message' => 'Application access denied or not found.'], 403);
        }

        Quota::app($appData['id']);

            $orgId = $appData['default_organization'] ?? null;
            if ($orgId) {
                Quota::for($orgId);

                // Find the plan ID for this organization
                if (isset($appData['organizations']) && is_array($appData['organizations'])) {
                    foreach ($appData['organizations'] as $org) {
                        $org = (array) $org;
                        if (isset($org['id']) && $org['id'] == $orgId) {
                            if (!empty($org['subscriptions'])) {
                                foreach ($org['subscriptions'] as $sub) {
                                    $sub = (array) $sub;
                                    if (isset($sub['plan_id'])) {
                                        Quota::plan($sub['plan_id']);
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }

        return $next($request);
    }
}
