<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Enums\Hooks\McpFilterHook;
use App\Models\User;
use App\Support\Facades\Hook;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\PersonalAccessToken;

class McpTokenService
{
    public const TOKEN_NAME = 'laradashboard-mcp';

    public function createToken(User $user): string
    {
        $abilities = $this->resolveAbilitiesForUser($user);

        return $user->createToken(self::TOKEN_NAME, $abilities)->plainTextToken;
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function listTokensForUser(User $user): Collection
    {
        return $user->tokens()
            ->where('name', self::TOKEN_NAME)
            ->orderByDesc('created_at')
            ->get();
    }

    public function revokeToken(User $user, int $tokenId): bool
    {
        return $user->tokens()
            ->where('name', self::TOKEN_NAME)
            ->whereKey($tokenId)
            ->delete() > 0;
    }

    /**
     * @return array<int, string>
     */
    public function resolveAbilitiesForUser(User $user): array
    {
        $abilities = ['mcp:access'];

        /** @var array<string, string> $map */
        $map = Hook::applyFilters(McpFilterHook::ABILITY_PERMISSION_MAP, [
            'mcp:posts.read' => 'post.view',
            'mcp:posts.write' => 'post.create',
            'mcp:posts.update' => 'post.edit',
            'mcp:posts.delete' => 'post.delete',
            'mcp:terms.read' => 'term.view',
            'mcp:media.read' => 'media.view',
            'mcp:email_templates.read' => 'email_template.view',
            'mcp:email.send' => 'email_template.view',
            'mcp:briefing.read' => 'dashboard.view',
            'mcp:ops.cache' => 'settings.edit',
            'mcp:ops.logs.read' => 'settings.edit',
            'mcp:ops.health.read' => 'dashboard.view',
            'mcp:modules.read' => 'module.view',
            'mcp:modules.activate' => 'module.activate',
            'mcp:modules.deactivate' => 'module.deactivate',
        ]);

        foreach ($map as $ability => $permission) {
            if ($user->can($permission)) {
                $abilities[] = $ability;
            }
        }

        /** @var array<int, string> $abilities */
        $abilities = Hook::applyFilters(McpFilterHook::TOKEN_ABILITIES, $abilities, $user);

        return array_values(array_unique($abilities));
    }

    public function isMcpToken(?HasAbilities $token): bool
    {
        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        return $token->name === self::TOKEN_NAME;
    }
}
