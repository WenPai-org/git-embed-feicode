<?php
declare(strict_types=1);

/**
 * Plugin Name: Git Embed for feiCode
 * Description: Embed Git repositories from GitHub with beautiful cards
 * Version: 1.0.0
 * Author: feiCode
 * Text Domain: git-embed-feicode
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitEmbedFeiCode {
    
    private const PLUGIN_VERSION = '1.0.0';
    private const BLOCK_NAME = 'git-embed-feicode/repository';
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_git_embed_fetch', [$this, 'ajax_fetch_repo']);
        add_action('wp_ajax_nopriv_git_embed_fetch', [$this, 'ajax_fetch_repo']);
        add_action('wp_ajax_git_embed_clear_cache', [$this, 'ajax_clear_cache']);
        register_deactivation_hook(__FILE__, [$this, 'clear_all_cache']);
    }
    
    public function init(): void {
        $this->register_block();
        $this->load_textdomain();
    }
    
    private function register_block(): void {
        register_block_type(self::BLOCK_NAME, [
            'editor_script' => 'git-embed-feicode-editor',
            'editor_style' => 'git-embed-feicode-style',  
            'style' => 'git-embed-feicode-style',
            'render_callback' => [$this, 'render_block'],
            'attributes' => [
                'platform' => [
                    'type' => 'string',
                    'default' => 'github'
                ],
                'customDomain' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'customSiteName' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'owner' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'repo' => [
                    'type' => 'string', 
                    'default' => ''
                ],
                'showDescription' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showStats' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showLanguage' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showActions' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showViewButton' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showCloneButton' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showDownloadButton' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'cardStyle' => [
                    'type' => 'string',
                    'default' => 'default'
                ],
                'buttonStyle' => [
                    'type' => 'string',
                    'default' => 'default'
                ],
                'buttonSize' => [
                    'type' => 'string',
                    'default' => 'medium'
                ],
                'showIssuesButton' => [
                    'type' => 'boolean',
                    'default' => false
                ],
                'showForksButton' => [
                    'type' => 'boolean',
                    'default' => false
                ],
                'showAvatar' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showSiteInfo' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'avatarSize' => [
                    'type' => 'string',
                    'default' => 'medium'
                ],
                'alignment' => [
                    'type' => 'string',
                    'default' => 'none'
                ]
            ]
        ]);
        
        wp_register_script(
            'git-embed-feicode-editor',
            plugin_dir_url(__FILE__) . 'block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            self::PLUGIN_VERSION
        );
        
        wp_register_style(
            'git-embed-feicode-style',
            plugin_dir_url(__FILE__) . 'style.css',
            [],
            self::PLUGIN_VERSION
        );
        
        wp_localize_script('git-embed-feicode-editor', 'gitEmbedAjax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('git_embed_nonce'),
            'cache_nonce' => wp_create_nonce('git_embed_cache_nonce')
        ]);
    }
    
    public function render_block(array $attributes): string {
        $owner = sanitize_text_field($attributes['owner'] ?? '');
        $repo = sanitize_text_field($attributes['repo'] ?? '');
        $platform = sanitize_text_field($attributes['platform'] ?? 'github');
        $custom_domain = sanitize_text_field($attributes['customDomain'] ?? '');
        $custom_site_name = sanitize_text_field($attributes['customSiteName'] ?? '');
        
        if (empty($owner) || empty($repo)) {
            return '<div class="git-embed-error">Repository information required</div>';
        }
        
        if (in_array($platform, ['gitea', 'forgejo', 'gitlab', 'custom']) && empty($custom_domain)) {
            return '<div class="git-embed-error">Custom domain required for ' . ucfirst($platform) . '</div>';
        }
        
        $repo_data = $this->fetch_repository_data($platform, $owner, $repo, $custom_domain, $custom_site_name);
        
        if (!$repo_data) {
            return '<div class="git-embed-error">Failed to fetch repository data</div>';
        }
        
        return $this->render_repository_card($repo_data, $attributes);
    }
    
    private function fetch_repository_data(string $platform, string $owner, string $repo, string $custom_domain = '', string $custom_site_name = ''): ?array {
        $cache_key = "git_embed_{$platform}_{$owner}_{$repo}" . ($custom_domain ? "_{$custom_domain}" : '') . ($custom_site_name ? "_{$custom_site_name}" : '');
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $api_config = $this->get_api_config($platform, $custom_domain, $custom_site_name);
        if (!$api_config) {
            return null;
        }
        
        $url = $api_config['api_url'] . "/repos/{$owner}/{$repo}";
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Git-Embed-FeiCode/1.0',
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$data) {
            return null;
        }
        
        $repo_data = $this->normalize_repository_data($data, $platform, $api_config);
        
        set_transient($cache_key, $repo_data, DAY_IN_SECONDS);
        
        if (!empty($repo_data['owner']['avatar_url'])) {
            $this->cache_avatar($repo_data['owner']['avatar_url']);
        }
        
        return $repo_data;
    }
    
    private function get_api_config(string $platform, string $custom_domain = '', string $custom_site_name = ''): ?array {
        switch ($platform) {
            case 'github':
                return [
                    'api_url' => 'https://api.github.com',
                    'base_url' => 'https://github.com',
                    'site_info' => [
                        'name' => 'GitHub',
                        'url' => 'https://github.com',
                        'favicon' => 'https://github.com/favicon.ico',
                        'color' => '#24292f'
                    ]
                ];
                
            case 'gitea':
                if (empty($custom_domain)) {
                    return null;
                }
                $domain = $this->normalize_domain($custom_domain);
                $site_name = $custom_site_name ?: $this->get_site_name($domain, 'Gitea');
                return [
                    'api_url' => "https://{$domain}/api/v1",
                    'base_url' => "https://{$domain}",
                    'site_info' => [
                        'name' => $site_name,
                        'url' => "https://{$domain}",
                        'favicon' => "https://{$domain}/assets/img/favicon.png",
                        'color' => '#609926'
                    ]
                ];
                
            case 'forgejo':
                if (empty($custom_domain)) {
                    return null;
                }
                $domain = $this->normalize_domain($custom_domain);
                $site_name = $custom_site_name ?: $this->get_site_name($domain, 'Forgejo');
                return [
                    'api_url' => "https://{$domain}/api/v1",
                    'base_url' => "https://{$domain}",
                    'site_info' => [
                        'name' => $site_name,
                        'url' => "https://{$domain}",
                        'favicon' => "https://{$domain}/assets/img/favicon.png",
                        'color' => '#fb923c'
                    ]
                ];
                
            case 'gitlab':
                if (empty($custom_domain)) {
                    return null;
                }
                $domain = $this->normalize_domain($custom_domain);
                $site_name = $custom_site_name ?: $this->get_site_name($domain, 'GitLab');
                return [
                    'api_url' => "https://{$domain}/api/v4",
                    'base_url' => "https://{$domain}",
                    'site_info' => [
                        'name' => $site_name,
                        'url' => "https://{$domain}",
                        'favicon' => "https://{$domain}/assets/favicon.ico",
                        'color' => '#fc6d26'
                    ]
                ];
                
            case 'custom':
                if (empty($custom_domain)) {
                    return null;
                }
                $domain = $this->normalize_domain($custom_domain);
                $site_name = $custom_site_name ?: $this->get_site_name($domain, 'Git Service');
                return [
                    'api_url' => "https://{$domain}/api/v1",
                    'base_url' => "https://{$domain}",
                    'site_info' => [
                        'name' => $site_name,
                        'url' => "https://{$domain}",
                        'favicon' => "https://{$domain}/favicon.ico",
                        'color' => '#6366f1'
                    ]
                ];
                
            default:
                return null;
        }
    }
    
    private function get_site_name(string $domain, string $fallback): string {
        $cache_key = 'git_embed_site_name_' . md5($domain);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $response = wp_remote_get("https://{$domain}", [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Git-Embed-FeiCode/1.0'
            ]
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $body, $matches)) {
                $title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
                if (!empty($title) && strlen($title) < 100) {
                    set_transient($cache_key, $title, DAY_IN_SECONDS);
                    return $title;
                }
            }
        }
        
        set_transient($cache_key, $fallback, HOUR_IN_SECONDS);
        return $fallback;
    }
    
    private function normalize_domain(string $domain): string {
        $domain = trim($domain);
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = rtrim($domain, '/');
        return $domain;
    }
    
    private function normalize_repository_data(array $data, string $platform, array $api_config): array {
        $base_url = $api_config['base_url'];
        
        if ($platform === 'gitlab') {
            return [
                'name' => $data['name'],
                'full_name' => $data['path_with_namespace'] ?? ($data['namespace']['name'] . '/' . $data['name']),
                'description' => $data['description'],
                'html_url' => $data['web_url'],
                'language' => $data['language'] ?? null,
                'stargazers_count' => $data['star_count'] ?? 0,
                'forks_count' => $data['forks_count'] ?? 0,
                'open_issues_count' => $data['open_issues_count'] ?? 0,
                'clone_url' => $data['http_url_to_repo'],
                'archive_url' => $this->get_archive_url($data, $platform, $base_url),
                'owner' => [
                    'login' => $data['namespace']['name'] ?? $data['owner']['username'],
                    'avatar_url' => $data['namespace']['avatar_url'] ?? $data['owner']['avatar_url'],
                    'html_url' => $base_url . '/' . ($data['namespace']['name'] ?? $data['owner']['username']),
                    'type' => $this->normalize_owner_type($data['namespace']['kind'] ?? 'user')
                ],
                'site_info' => $api_config['site_info'],
                'platform' => $platform
            ];
        }
        
        return [
            'name' => $data['name'],
            'full_name' => $data['full_name'] ?? ($data['owner']['login'] . '/' . $data['name']),
            'description' => $data['description'],
            'html_url' => $data['html_url'],
            'language' => $data['language'],
            'stargazers_count' => $data['stargazers_count'] ?? $data['stars_count'] ?? 0,
            'forks_count' => $data['forks_count'] ?? $data['forks'] ?? 0,
            'open_issues_count' => $data['open_issues_count'] ?? $data['open_issues'] ?? 0,
            'clone_url' => $data['clone_url'],
            'archive_url' => $this->get_archive_url($data, $platform, $base_url),
            'owner' => [
                'login' => $data['owner']['login'],
                'avatar_url' => $data['owner']['avatar_url'],
                'html_url' => $data['owner']['html_url'] ?? $base_url . '/' . $data['owner']['login'],
                'type' => $this->normalize_owner_type($data['owner']['type'] ?? 'User')
            ],
            'site_info' => $api_config['site_info'],
            'platform' => $platform
        ];
    }
    
    private function get_archive_url(array $data, string $platform, string $base_url): string {
        if (isset($data['archive_url'])) {
            return $data['archive_url'];
        }
        
        $owner = $data['owner']['login'] ?? $data['namespace']['name'] ?? '';
        $repo = $data['name'];
        
        switch ($platform) {
            case 'github':
                return "https://api.github.com/repos/{$owner}/{$repo}/zipball/{archive_format}{/ref}";
            case 'gitlab':
                $project_id = $data['id'] ?? '';
                return "{$base_url}/api/v4/projects/{$project_id}/repository/archive.zip";
            case 'gitea':
            case 'forgejo':
            case 'custom':
                return "{$base_url}/{$owner}/{$repo}/archive/main.zip";
            default:
                return '';
        }
    }
    
    private function normalize_owner_type(string $type): string {
        $type = strtolower($type);
        switch ($type) {
            case 'organization':
            case 'org':
                return 'Organization';
            case 'user':
            default:
                return 'User';
        }
    }
    
    private function cache_avatar(string $avatar_url): void {
        if (empty($avatar_url)) {
            return;
        }
        
        $cache_key = 'git_embed_avatar_' . md5($avatar_url);
        
        if (get_transient($cache_key) !== false) {
            return;
        }
        
        $response = wp_remote_get($avatar_url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Git-Embed-FeiCode/1.0'
            ]
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            set_transient($cache_key, true, WEEK_IN_SECONDS);
        }
    }
    
    private function render_repository_card(array $repo_data, array $attributes): string {
        $show_description = $attributes['showDescription'] ?? true;
        $show_stats = $attributes['showStats'] ?? true;
        $show_language = $attributes['showLanguage'] ?? true;
        $show_actions = $attributes['showActions'] ?? true;
        $show_view_button = $attributes['showViewButton'] ?? true;
        $show_clone_button = $attributes['showCloneButton'] ?? true;
        $show_download_button = $attributes['showDownloadButton'] ?? true;
        $show_issues_button = $attributes['showIssuesButton'] ?? false;
        $show_forks_button = $attributes['showForksButton'] ?? false;
        $show_avatar = $attributes['showAvatar'] ?? true;
        $show_site_info = $attributes['showSiteInfo'] ?? true;
        $avatar_size = $attributes['avatarSize'] ?? 'medium';
        $card_style = $attributes['cardStyle'] ?? 'default';
        $button_style = $attributes['buttonStyle'] ?? 'default';
        $button_size = $attributes['buttonSize'] ?? 'medium';
        $alignment = $attributes['alignment'] ?? 'none';
        
        $align_class = $alignment !== 'none' ? " align{$alignment}" : '';
        $card_class = $card_style !== 'default' ? " git-embed-card-{$card_style}" : '';
        $avatar_class = "git-embed-avatar-{$avatar_size}";
        $button_class = "git-embed-button-{$button_size}";
        
        $download_url = $this->get_download_url($repo_data);
        
        ob_start();
        ?>
        <div class="wp-block-git-embed-feicode-repository<?php echo esc_attr($align_class); ?>">
            <div class="git-embed-card<?php echo esc_attr($card_class); ?>">
                <?php if ($show_site_info): ?>
                    <div class="git-embed-site-info platform-<?php echo esc_attr($repo_data['platform'] ?? 'github'); ?>">
                        <img src="<?php echo esc_url($repo_data['site_info']['favicon']); ?>" 
                             alt="<?php echo esc_attr($repo_data['site_info']['name']); ?>" 
                             class="git-embed-site-favicon"
                             loading="lazy"
                             onerror="this.style.display='none'">
                        <span class="git-embed-site-name">
                            <a href="<?php echo esc_url($repo_data['site_info']['url']); ?>" 
                               target="_blank" rel="noopener">
                                <?php echo esc_html($repo_data['site_info']['name']); ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="git-embed-header">
                    <div class="git-embed-title-section">
                        <?php if ($show_avatar): ?>
                            <img src="<?php echo esc_url($repo_data['owner']['avatar_url']); ?>" 
                                 alt="<?php echo esc_attr($repo_data['owner']['login']); ?>" 
                                 class="git-embed-avatar <?php echo esc_attr($avatar_class); ?>"
                                 loading="lazy">
                        <?php endif; ?>
                        
                        <div class="git-embed-title-content">
                            <h3 class="git-embed-title">
                                <span class="dashicons dashicons-admin-links git-embed-repo-icon"></span>
                                <a href="<?php echo esc_url($repo_data['html_url']); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($repo_data['full_name']); ?>
                                </a>
                            </h3>
                            
                            <?php if ($show_avatar): ?>
                                <div class="git-embed-owner-info">
                                    <span class="git-embed-owner-type"><?php echo esc_html($repo_data['owner']['type']); ?></span>
                                    <a href="<?php echo esc_url($repo_data['owner']['html_url']); ?>" 
                                       target="_blank" rel="noopener" class="git-embed-owner-link">
                                        @<?php echo esc_html($repo_data['owner']['login']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="git-embed-meta-section">
                        <?php if ($show_language && $repo_data['language']): ?>
                            <span class="git-embed-language">
                                <span class="dashicons dashicons-editor-code"></span>
                                <?php echo esc_html($repo_data['language']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <span class="git-embed-platform-badge platform-<?php echo esc_attr($repo_data['platform'] ?? 'github'); ?>">
                            <?php echo esc_html(strtoupper($repo_data['platform'] ?? 'github')); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($show_description && $repo_data['description']): ?>
                    <p class="git-embed-description">
                        <span class="dashicons dashicons-text-page"></span>
                        <?php echo esc_html($repo_data['description']); ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($show_stats): ?>
                    <div class="git-embed-stats">
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="git-embed-stat-label">Stars:</span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data['stargazers_count']); ?></span>
                        </span>
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-networking"></span>
                            <span class="git-embed-stat-label">Forks:</span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data['forks_count']); ?></span>
                        </span>
                        <span class="git-embed-stat">
                            <span class="dashicons dashicons-editor-help"></span>
                            <span class="git-embed-stat-label">Issues:</span>
                            <span class="git-embed-stat-value"><?php echo number_format_i18n($repo_data['open_issues_count']); ?></span>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_actions && ($show_view_button || $show_clone_button || $show_download_button || $show_issues_button || $show_forks_button)): ?>
                    <div class="git-embed-actions">
                        <?php if ($show_view_button): ?>
                            <a href="<?php echo esc_url($repo_data['html_url']); ?>" 
                               class="git-embed-button git-embed-button-<?php echo esc_attr($button_style); ?> <?php echo esc_attr($button_class); ?>" 
                               target="_blank" rel="noopener">
                                <span class="dashicons dashicons-external"></span>
                                View Repository
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($show_clone_button): ?>
                            <button type="button" 
                                    class="git-embed-button git-embed-button-secondary <?php echo esc_attr($button_class); ?> git-embed-clone-btn" 
                                    data-clone-url="<?php echo esc_attr($repo_data['clone_url']); ?>"
                                    title="Click to copy clone URL">
                                <span class="dashicons dashicons-admin-page"></span>
                                Clone
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($show_download_button): ?>
                            <a href="<?php echo esc_url($download_url); ?>" 
                               class="git-embed-button git-embed-button-secondary <?php echo esc_attr($button_class); ?>"
                               download="<?php echo esc_attr($repo_data['name']); ?>.zip">
                                <span class="dashicons dashicons-download"></span>
                                Download ZIP
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($show_issues_button): ?>
                            <a href="<?php echo esc_url($repo_data['html_url'] . '/issues'); ?>" 
                               class="git-embed-button git-embed-button-outline <?php echo esc_attr($button_class); ?>"
                               target="_blank" rel="noopener">
                                <span class="dashicons dashicons-editor-help"></span>
                                Issues (<?php echo number_format_i18n($repo_data['open_issues_count']); ?>)
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($show_forks_button): ?>
                            <a href="<?php echo esc_url($repo_data['html_url'] . '/forks'); ?>" 
                               class="git-embed-button git-embed-button-outline <?php echo esc_attr($button_class); ?>"
                               target="_blank" rel="noopener">
                                <span class="dashicons dashicons-networking"></span>
                                Forks (<?php echo number_format_i18n($repo_data['forks_count']); ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($show_clone_button): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cloneButtons = document.querySelectorAll('.git-embed-clone-btn');
            cloneButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const cloneUrl = this.dataset.cloneUrl;
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(cloneUrl).then(() => {
                            const originalText = this.innerHTML;
                            this.innerHTML = '<span class="dashicons dashicons-yes"></span>Copied!';
                            setTimeout(() => {
                                this.innerHTML = originalText;
                            }, 2000);
                        });
                    } else {
                        const input = document.createElement('input');
                        input.value = cloneUrl;
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        document.body.removeChild(input);
                        
                        const originalText = this.innerHTML;
                        this.innerHTML = '<span class="dashicons dashicons-yes"></span>Copied!';
                        setTimeout(() => {
                            this.innerHTML = originalText;
                        }, 2000);
                    }
                });
            });
        });
        </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    
    private function get_download_url(array $repo_data): string {
        $platform = $repo_data['platform'] ?? 'github';
        $archive_url = $repo_data['archive_url'] ?? '';
        
        switch ($platform) {
            case 'github':
                $url = str_replace('{archive_format}', 'zipball', $archive_url);
                return str_replace('{/ref}', '/main', $url);
                
            case 'gitlab':
            case 'gitea':
            case 'forgejo':
            case 'custom':
                return $archive_url;
                
            default:
                return $archive_url;
        }
    }
    
    public function ajax_fetch_repo(): void {
        check_ajax_referer('git_embed_nonce', 'nonce');
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $custom_domain = sanitize_text_field($_POST['customDomain'] ?? '');
        $custom_site_name = sanitize_text_field($_POST['customSiteName'] ?? '');
        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $repo = sanitize_text_field($_POST['repo'] ?? '');
        
        if (empty($owner) || empty($repo)) {
            wp_send_json_error('Repository information required');
        }
        
        if (in_array($platform, ['gitea', 'forgejo', 'gitlab', 'custom']) && empty($custom_domain)) {
            wp_send_json_error('Custom domain required for ' . ucfirst($platform));
        }
        
        $repo_data = $this->fetch_repository_data($platform, $owner, $repo, $custom_domain, $custom_site_name);
        
        if (!$repo_data) {
            wp_send_json_error('Failed to fetch repository data');
        }
        
        wp_send_json_success($repo_data);
    }
    
    public function ajax_clear_cache(): void {
        check_ajax_referer('git_embed_cache_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $custom_domain = sanitize_text_field($_POST['customDomain'] ?? '');
        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $repo = sanitize_text_field($_POST['repo'] ?? '');
        
        if (empty($owner) || empty($repo)) {
            wp_send_json_error('Repository information required');
        }
        
        $this->clear_repository_cache($platform, $owner, $repo, $custom_domain);
        
        wp_send_json_success('Cache cleared successfully');
    }
    
    private function clear_repository_cache(string $platform, string $owner, string $repo, string $custom_domain = ''): void {
        $cache_key = "git_embed_{$platform}_{$owner}_{$repo}" . ($custom_domain ? "_{$custom_domain}" : '');
        delete_transient($cache_key);
        
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_git_embed_avatar_') . '%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_timeout_git_embed_avatar_') . '%'
            )
        );
    }
    
    public function clear_all_cache(): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_git_embed_') . '%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_timeout_git_embed_') . '%'
            )
        );
    }
    
    private function load_textdomain(): void {
        load_plugin_textdomain(
            'git-embed-feicode',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
}

new GitEmbedFeiCode();