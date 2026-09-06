<?php

use App\Http\Middleware\AuthenticateMcpAgent;
use App\Http\Middleware\EnsureMcpEnabled;
use App\Mcp\Servers\LaraDashboardServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| LaraDashboard MCP Routes
|--------------------------------------------------------------------------
|
| MCP HTTP endpoint for external AI agents (Cursor, Claude Desktop, etc.).
| Disabled by default via Settings > MCP. When disabled, EnsureMcpEnabled
| returns 404 so agents cannot connect.
|
*/

Mcp::web('/mcp', LaraDashboardServer::class)
    ->middleware([
        EnsureMcpEnabled::class,
        'auth:sanctum',
        AuthenticateMcpAgent::class,
    ]);
