<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class SystemSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = $this->currentSettings();

        return response()->json([
            'data' => $settings->only([
                'business_name',
                'support_email',
                'support_phone',
            ]),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): JsonResponse
    {
        $settings = $this->currentSettings();
        $settings->update($request->validated());

        return response()->json([
            'message' => 'System settings updated.',
            'data' => $settings->refresh()->only([
                'business_name',
                'support_email',
                'support_phone',
            ]),
        ]);
    }

    private function currentSettings(): SystemSetting
    {
        $settings = SystemSetting::query()->find(1);

        if ($settings instanceof SystemSetting) {
            return $settings;
        }

        $settings = new SystemSetting([
            'business_name' => (string) config('app.name', 'Inventory Hub'),
            'support_email' => null,
            'support_phone' => null,
        ]);
        $settings->setAttribute($settings->getKeyName(), 1);
        $settings->save();

        return $settings;
    }
}
