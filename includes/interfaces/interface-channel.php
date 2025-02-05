<?php

namespace SEWN\WebSockets\Interfaces;

interface Channel {
    public function subscribe($client_id);
    public function unsubscribe($client_id);
    public function process($event);
    public function broadcast($data);
    public function getStats();
} 