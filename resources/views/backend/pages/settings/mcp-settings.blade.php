@php
    use App\Enums\Hooks\SettingFilterHook;
    use App\Models\Setting;
    use App\Services\Mcp\McpSettingsService;
    use App\Services\Mcp\McpTokenService;

    $mcpSettings = app(McpSettingsService::class);
    $mcpTokenService = app(McpTokenService::class);
    $mcpEnabled = $mcpSettings->isEnabled();
    $mcpServerUrl = $mcpSettings->serverUrl();
    $mcpTokens = auth()->user() ? $mcpTokenService->listTokensForUser(auth()->user()) : collect();
    $availableTools = $mcpSettings->availableTools();
    $groupedTools = $mcpSettings->groupedAvailableTools();
    $placeholderConfig = $mcpSettings->configJson();
    $defaultClient = $mcpSettings->defaultAiClient();
    $csrfToken = csrf_token();
    $createTokenUrl = route('admin.settings.mcp.tokens.store');
    $revokeTokenUrlTemplate = route('admin.settings.mcp.tokens.destroy', ['tokenId' => '__TOKEN_ID__']);
@endphp

{!! Hook::applyFilters(SettingFilterHook::SETTINGS_MCP_TAB_BEFORE_SECTION_START, '') !!}

<x-card>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <iconify-icon icon="lucide:bot" width="20" height="20" class="text-primary"></iconify-icon>
            {{ __('Model Context Protocol (MCP)') }}
        </div>
    </x-slot>

    <div class="space-y-8" x-data="mcpSettingsPanel({
        createTokenUrl: @js($createTokenUrl),
        revokeTokenUrlTemplate: @js($revokeTokenUrlTemplate),
        csrfToken: @js($csrfToken),
        serverUrl: @js($mcpServerUrl),
        selectedClient: @js($defaultClient),
        groupedTools: @js($groupedTools),
        mcpEnabledSaved: @js($mcpEnabled),
        demoMode: @js(config('app.demo_mode', false)),
    })">
        <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700 bg-gradient-to-br from-indigo-50 to-blue-50/60 dark:from-gray-800/50 dark:to-gray-900/30">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">{{ __('Connect AI agents to LaraDashboard') }}</h4>
            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                {{ __('MCP lets tools like Cursor (Desktop or CLI), Claude Desktop, and Claude Code manage your LaraDashboard site — create blog posts, fetch content, and generate SEO metadata using natural language commands.') }}
            </p>
            <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                {{ __('MCP is disabled by default. Enable it only when you want external AI agents to access this installation.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <input type="hidden" name="mcp_enabled" value="0">
            <div class="shrink-0">
                <x-inputs.toggle
                    name="mcp_enabled"
                    :checked="$mcpEnabled"
                    :disabled="config('app.demo_mode', false)"
                />
            </div>
            <div class="min-w-0">
                <label for="mcp_enabled" class="font-medium text-gray-900 dark:text-white text-sm">
                    {{ __('Enable MCP server') }}
                </label>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('When disabled, the MCP endpoint returns 404 and no agent can connect.') }}
                </p>
                @if (config('app.demo_mode', false))
                    <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                        {{ __('MCP cannot be enabled in demo mode.') }}
                    </p>
                @endif
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label">{{ __('MCP server URL') }}</label>
                <div class="flex gap-2">
                    <input type="text" readonly value="{{ $mcpServerUrl }}" class="form-control font-mono text-sm" id="mcp-server-url">
                    <x-copy-button
                        :copy-value="$mcpServerUrl"
                        class="btn-outline-primary"
                    />
                </div>
            </div>
            <div>
                <label class="form-label">{{ __('Status') }}</label>
                <div class="flex items-center h-[42px]">
                    @if ($mcpEnabled)
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            {{ __('Enabled') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                            {{ __('Disabled') }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <template x-if="!canUseMcp()">
            <div class="p-4 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700" role="status">
                <p class="text-sm text-amber-900 dark:text-amber-200" x-show="demoMode">
                    {{ __('MCP agent tokens cannot be created in demo mode.') }}
                </p>
                <p class="text-sm text-amber-900 dark:text-amber-200" x-show="!demoMode && mcpPendingSave()" x-cloak>
                    {{ __('Save changes to apply MCP access before generating a token or connecting an AI client.') }}
                </p>
                <p class="text-sm text-amber-900 dark:text-amber-200" x-show="!demoMode && !mcpToggleOn" x-cloak>
                    {{ __('Enable MCP server above and save changes before generating tokens or connecting an AI client.') }}
                </p>
            </div>
        </template>

        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">{{ __('Agent tokens') }}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('Create a dedicated MCP token. Regular API login tokens will not work.') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="btn btn-primary"
                    @click="createToken()"
                    :disabled="creatingToken || !canUseMcp()"
                    :class="{ 'opacity-50 cursor-not-allowed': creatingToken || !canUseMcp() }"
                >
                    <span x-show="!creatingToken">{{ __('Generate MCP token') }}</span>
                    <span x-show="creatingToken">{{ __('Generating...') }}</span>
                </button>
            </div>

            <template x-if="newToken">
                <div class="mb-4 p-4 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                    <p class="text-sm font-medium text-amber-900 dark:text-amber-200 mb-2">
                        {{ __('Copy this token now. It will not be shown again.') }}
                    </p>
                    <div class="flex gap-2">
                        <input type="text" readonly :value="newToken" class="form-control font-mono text-xs">
                        <x-copy-button
                            class="btn-outline-primary"
                            x-bind:data-copy-value="newToken"
                        />
                    </div>
                </div>
            </template>

            <template x-if="tokenError">
                <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300 text-sm" x-text="tokenError"></div>
            </template>

            @if ($mcpTokens->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No MCP agent tokens yet.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 pr-4">{{ __('Created') }}</th>
                                <th class="py-2 pr-4">{{ __('Last used') }}</th>
                                <th class="py-2 pr-4">{{ __('Abilities') }}</th>
                                <th class="py-2">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mcpTokens as $token)
                                <tr class="border-b border-gray-100 dark:border-gray-800" id="mcp-token-row-{{ $token->id }}">
                                    <td class="py-3 pr-4">{{ $token->created_at?->format('M j, Y g:i A') }}</td>
                                    <td class="py-3 pr-4">{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                    <td class="py-3 pr-4">
                                        <code class="text-xs">{{ implode(', ', $token->abilities ?? []) }}</code>
                                    </td>
                                    <td class="py-3">
                                        <button type="button"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400 text-sm"
                                            @click="revokeToken({{ $token->id }})">
                                            {{ __('Revoke') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div :class="{ 'opacity-60': !canUseMcp() }">
            @include('backend.pages.settings.partials.mcp-connection-guide')
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-4">
                <div class="min-w-0 flex-1">
                    <h4 class="font-semibold text-gray-900 dark:text-white">{{ __('Available tools') }}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('Tools are registered automatically. Modules can add MCP tools via the mcp.tools hook.') }}
                    </p>
                </div>

                @if (count($availableTools) > 0)
                    <div class="w-full sm:w-72 shrink-0">
                        <label for="mcp-tool-search" class="sr-only">{{ __('Search MCP tools') }}</label>
                        <div class="relative">
                            <iconify-icon
                                icon="lucide:search"
                                width="16"
                                height="16"
                                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                                aria-hidden="true"
                            ></iconify-icon>
                            <input
                                id="mcp-tool-search"
                                type="search"
                                x-model="toolSearch"
                                placeholder="{{ __('Search tools...') }}"
                                class="form-control pl-9 text-sm"
                                autocomplete="off"
                            >
                        </div>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 text-right" aria-live="polite">
                            <span x-show="! toolSearch.trim()">{{ trans_choice(':count tool|:count tools', count($availableTools), ['count' => count($availableTools)]) }}</span>
                            <span x-show="toolSearch.trim()" x-cloak x-text="filteredToolCountLabel()"></span>
                        </p>
                    </div>
                @else
                    <span class="inline-flex items-center self-start px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 shrink-0">
                        {{ trans_choice(':count tool|:count tools', 0, ['count' => 0]) }}
                    </span>
                @endif
            </div>

            <template x-if="Object.keys(groupedTools).length === 0">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No MCP tools are registered yet.') }}</p>
            </template>

            <template x-if="Object.keys(groupedTools).length > 0 && visibleToolCount() === 0">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No tools match your search.') }}</p>
            </template>

            <div class="space-y-6">
                <template x-for="[group, tools] in Object.entries(groupedTools)" :key="group">
                    <div x-show="groupHasVisibleTools(group, tools)">
                        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="group"></h5>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <template x-for="tool in tools" :key="tool.name">
                                <div
                                    x-show="matchesToolSearch(tool, group)"
                                    class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <code class="text-sm font-semibold text-primary" x-text="tool.name"></code>
                                        <span class="shrink-0 px-2 py-0.5 text-[11px] font-medium rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300" x-text="tool.ability"></span>
                                    </div>
                                    <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300" x-text="tool.description"></p>
                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('Permission') }}:
                                        <span class="font-medium text-gray-700 dark:text-gray-300" x-text="tool.permission || @js(__('none'))"></span>
                                    </p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-card>

{!! Hook::applyFilters(SettingFilterHook::SETTINGS_MCP_TAB_AFTER_SECTION_END, '') !!}

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mcpSettingsPanel', ({ createTokenUrl, revokeTokenUrlTemplate, csrfToken, serverUrl, selectedClient, groupedTools, mcpEnabledSaved, demoMode }) => ({
        creatingToken: false,
        newToken: null,
        tokenError: null,
        selectedClient: selectedClient || @js($defaultClient),
        configSnippet: @js($placeholderConfig),
        serverUrl,
        groupedTools: groupedTools || {},
        toolSearch: '',
        mcpEnabledSaved: Boolean(mcpEnabledSaved),
        mcpToggleOn: Boolean(mcpEnabledSaved),
        demoMode: Boolean(demoMode),

        init() {
            const toggle = document.getElementById('mcp_enabled');

            if (toggle) {
                this.mcpToggleOn = toggle.checked;
                toggle.addEventListener('change', () => {
                    this.mcpToggleOn = toggle.checked;
                });
            }
        },

        canUseMcp() {
            return this.mcpEnabledSaved && !this.demoMode;
        },

        mcpPendingSave() {
            return this.mcpToggleOn && !this.mcpEnabledSaved;
        },

        matchesToolSearch(tool, group) {
            const query = this.toolSearch.trim().toLowerCase();

            if (! query) {
                return true;
            }

            const haystack = [
                group,
                tool.name,
                tool.description,
                tool.ability,
                tool.permission || '',
            ].join(' ').toLowerCase();

            return haystack.includes(query);
        },

        groupHasVisibleTools(group, tools) {
            return tools.some((tool) => this.matchesToolSearch(tool, group));
        },

        visibleToolCount() {
            return Object.entries(this.groupedTools).reduce((count, [group, tools]) => {
                return count + tools.filter((tool) => this.matchesToolSearch(tool, group)).length;
            }, 0);
        },

        filteredToolCountLabel() {
            const count = this.visibleToolCount();
            const template = count === 1 ? @js(__(':count tool')) : @js(__(':count tools'));

            return template.replace(':count', count);
        },

        updateConfigSnippet(token) {
            this.configSnippet = JSON.stringify({
                mcpServers: {
                    laradashboard: {
                        url: this.serverUrl,
                        headers: {
                            Authorization: 'Bearer ' + token,
                        },
                    },
                },
            }, null, 2);
        },

        async createToken() {
            if (!this.canUseMcp()) {
                if (this.demoMode) {
                    this.tokenError = @js(__('MCP agent tokens cannot be created in demo mode.'));
                } else if (this.mcpPendingSave()) {
                    this.tokenError = @js(__('Save changes to apply MCP access before generating a token.'));
                } else {
                    this.tokenError = @js(__('Enable MCP server and save changes before generating a token.'));
                }

                return;
            }

            this.creatingToken = true;
            this.tokenError = null;

            try {
                const response = await fetch(createTokenUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    this.tokenError = data.message || 'Failed to create token.';
                    return;
                }

                this.newToken = data.token;
                this.updateConfigSnippet(data.token);
            } catch (error) {
                this.tokenError = error.message;
            } finally {
                this.creatingToken = false;
            }
        },

        async revokeToken(tokenId) {
            if (!confirm(@js(__('Revoke this MCP token? Connected agents will stop working immediately.')))) {
                return;
            }

            const url = revokeTokenUrlTemplate.replace('__TOKEN_ID__', tokenId);

            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                document.getElementById('mcp-token-row-' + tokenId)?.remove();
            }
        },
    }));
});
</script>
@endpush
