<?php

namespace SEWN\WebSockets;

class Simulation_Mode {
    const TRAFFIC_PATTERNS = [
        'baseline' => [
            'connections_min' => 50,
            'messages_per_min' => 1200
        ],
        'peak' => [
            'connections_min' => 200, 
            'messages_per_min' => 5000
        ]
    ];
    
    public function generate_traffic($pattern) {
        // Implementation that interacts with Process_Manager
    }
} 