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
                    'default' => 'primary'
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
        
        if (empty($owner) || empty($repo)) {
            return '<div class="git-embed-error">Repository information required</div>';
        }
        
        $repo_data = $this->fetch_repository_data($platform, $owner, $repo);
        
        if (!$repo_data) {
            return '<div class="git-embed-error">Failed to fetch repository data</div>';
        }
        
        return $this->render_repository_card($repo_data, $attributes);
    }
    
    private function fetch_repository_data(string $platform, string $owner, string $repo): ?array {
        if ($platform !== 'github') {
            return null;
        }
        
        $cache_key = "git_embed_{$platform}_{$owner}_{$repo}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = "https://api.github.com/repos/{$owner}/{$repo}";
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Git-Embed-FeiCode/1.0',
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$data) {
            return null;
        }
        
        $repo_data = [
            'name' => $data['name'],
            'full_name' => $data['full_name'],
            'description' => $data['description'],
            'html_url' => $data['html_url'],
            'language' => $data['language'],
            'stargazers_count' => $data['stargazers_count'],
            'forks_count' => $data['forks_count'],
            'open_issues_count' => $data['open_issues_count'],
            'clone_url' => $data['clone_url'],
            'archive_url' => $data['archive_url'],
            'owner' => [
                'login' => $data['owner']['login'],
                'avatar_url' => $data['owner']['avatar_url'],
                'html_url' => $data['owner']['html_url'],
                'type' => $data['owner']['type']
            ],
            'site_info' => [
                'name' => 'GitHub',
                'url' => 'https://github.com',
                'favicon' => 'https://github.com/favicon.ico',
                'color' => '#24292f'
            ]
        ];
        
        set_transient($cache_key, $repo_data, DAY_IN_SECONDS);
        
        $this->cache_avatar($repo_data['owner']['avatar_url']);
        
        return $repo_data;
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
        $show_avatar = $attributes['showAvatar'] ?? true;
        $show_site_info = $attributes['showSiteInfo'] ?? true;
        $avatar_size = $attributes['avatarSize'] ?? 'medium';
        $card_style = $attributes['cardStyle'] ?? 'default';
        $button_style = $attributes['buttonStyle'] ?? 'primary';
        $alignment = $attributes['alignment'] ?? 'none';
        
        $align_class = $alignment !== 'none' ? " align{$alignment}" : '';
        $card_class = $card_style !== 'default' ? " git-embed-card-{$card_style}" : '';
        $avatar_class = "git-embed-avatar-{$avatar_size}";
        
        $download_url = str_replace('{archive_format}', 'zipball', $repo_data['archive_url']);
        $download_url = str_replace('{/ref}', '/main', $download_url);
        
        ob_start();
        ?>
        <div class="wp-block-git-embed-feicode-repository<?php echo esc_attr($align_class); ?>">
            <div class="git-embed-card<?php echo esc_attr($card_class); ?>">
                <?php if ($show_site_info): ?>
                    <div class="git-embed-site-info">
                        <img src="<?php echo esc_url($repo_data['site_info']['favicon']); ?>" 
                             alt="<?php echo esc_attr($repo_data['site_info']['name']); ?>" 
                             class="git-embed-site-favicon"
                             loading="lazy">
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
                    
                    <?php if ($show_language && $repo_data['language']): ?>
                        <span class="git-embed-language">
                            <span class="dashicons dashicons-editor-code"></span>
                            <?php echo esc_html($repo_data['language']); ?>
                        </span>
                    <?php endif; ?>
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
                
                <?php if ($show_actions && ($show_view_button || $show_clone_button || $show_download_button)): ?>
                    <div class="git-embed-actions">
                        <?php if ($show_view_button): ?>
                            <a href="<?php echo esc_url($repo_data['html_url']); ?>" 
                               class="git-embed-button git-embed-button-<?php echo esc_attr($button_style); ?>" 
                               target="_blank" rel="noopener">
                                <span class="dashicons dashicons-external"></span>
                                View Repository
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($show_clone_button): ?>
                            <button type="button" 
                                    class="git-embed-button git-embed-button-secondary git-embed-clone-btn" 
                                    data-clone-url="<?php echo esc_attr($repo_data['clone_url']); ?>"
                                    title="Click to copy clone URL">
                                <span class="dashicons dashicons-admin-page"></span>
                                Clone
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($show_download_button): ?>
                            <a href="<?php echo esc_url($download_url); ?>" 
                               class="git-embed-button git-embed-button-secondary"
                               download="<?php echo esc_attr($repo_data['name']); ?>.zip">
                                <span class="dashicons dashicons-download"></span>
                                Download ZIP
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
    
    public function ajax_fetch_repo(): void {
        check_ajax_referer('git_embed_nonce', 'nonce');
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $repo = sanitize_text_field($_POST['repo'] ?? '');
        
        if (empty($owner) || empty($repo)) {
            wp_send_json_error('Repository information required');
        }
        
        $repo_data = $this->fetch_repository_data($platform, $owner, $repo);
        
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
        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $repo = sanitize_text_field($_POST['repo'] ?? '');
        
        if (empty($owner) || empty($repo)) {
            wp_send_json_error('Repository information required');
        }
        
        $this->clear_repository_cache($platform, $owner, $repo);
        
        wp_send_json_success('Cache cleared successfully');
    }
    
    private function clear_repository_cache(string $platform, string $owner, string $repo): void {
        $cache_key = "git_embed_{$platform}_{$owner}_{$repo}";
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