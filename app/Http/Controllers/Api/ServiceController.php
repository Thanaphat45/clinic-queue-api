<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ServiceResource::collection(Service::orderBy('code')->get());
    }

    public function store(Request $request): ServiceResource
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:3', 'alpha', 'unique:services,code'],
            'name' => ['required', 'string', 'max:100'],
            'avg_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_open' => ['nullable', 'boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);

        return new ServiceResource(Service::create($data));
    }

    public function update(Request $request, Service $service): ServiceResource
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:3', 'alpha', Rule::unique('services', 'code')->ignore($service->id)],
            'name' => ['sometimes', 'string', 'max:100'],
            'avg_minutes' => ['sometimes', 'integer', 'min:1', 'max:240'],
            'is_open' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        $service->update($data);

        return new ServiceResource($service);
    }
}
