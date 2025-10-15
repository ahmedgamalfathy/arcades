<?php
namespace App\Services\Timer;

use App\Models\Timer\SessionDevice\SessionDevice;

class SessionDeviceService
{
    public function createSessionDevice(array $data)
    {
        return SessionDevice::create($data);
    }

    public function editSessionDevice(int $id)
    {
        return SessionDevice::findOrFail($id);
    }

    public function updateSessionDevice(int $id, array $data)
    {
        $sessionDevice=SessionDevice::findOrFail($id);
        $sessionDevice->update($data);
        return $sessionDevice;
    }

    public function deleteSessionDevice(int $id): void
    {
        $sessionDevice=SessionDevice::findOrFail($id);
        $sessionDevice->delete();
    }
}


