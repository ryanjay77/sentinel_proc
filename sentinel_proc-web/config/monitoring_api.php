<?php

return [
    'rate_limit_per_token' => env('AGENT_API_RATE_LIMIT_PER_TOKEN', 60),
    'rate_limit_per_ip' => env('AGENT_API_RATE_LIMIT_PER_IP', 120),
];