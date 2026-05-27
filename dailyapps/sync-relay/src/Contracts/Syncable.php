<?php

namespace Dailyapps\SyncRelay\Contracts;

interface Syncable
{
    public function syncResource(): string;
    public function syncPayload(): array;
    public function syncWith(): array;
}
